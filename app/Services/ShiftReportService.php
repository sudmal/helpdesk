<?php

namespace App\Services;

use App\Models\Call;
use App\Models\OperatorStatusLog;
use App\Models\ShiftDefinition;
use App\Models\ShiftReport;
use App\Models\ShiftReportExtension;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Считает (и для завершённых смен -- сохраняет) итог по смене: сколько
 * звонков приняла/потеряла ТП целиком, и по каждому добавочному отдельно --
 * сколько времени в DND/офлайне/ожидании/разговоре (из того же таймлайна
 * operator_status_logs, что рисует график "Очередь АТС"), сколько звонков
 * принял, длительность разговоров, уникальные номера.
 *
 * Длительность разговора считается ПРИБЛИЖЁННО -- как длина отрезков
 * "in_call" в operator_status_logs, а не по данным конкретного звонка (в
 * calls такого поля нет вообще). Если у оператора два звонка идут вплотную
 * без секунды простоя между ними -- склеятся в один "разговор". Точный
 * per-call трекинг потребовал бы новой передачи данных с MikoPBX.
 *
 * current() считает то же самое "на лету" для ещё идущей смены (окно
 * [начало, сейчас]) и ничего не сохраняет -- вызывается прямо из запроса
 * фронта, каждый раз заново.
 *
 * ВАЖНО (2026-07-08): цифры по DND теперь влияют на штрафы операторам --
 * значит любой ложный DND-сегмент от гонки статусов (см. коммент у
 * SHORT_DND_NOISE_SECONDS) это не "косметика графика", а прямой риск
 * несправедливого штрафа. Короткие DND-миганья сглаживаются перед подсчётом
 * (см. smoothShortDndBlips()), а auditSegments() отдаёт СЫРЫЕ (несглаженные)
 * сегменты с пометкой, что реально засчиталось -- для разбора спорных случаев.
 */
class ShiftReportService
{
    /** Сколько дней назад ещё пытаться досчитать пропущенные отчёты (даунтайм и т.п.) */
    private const CATCH_UP_DAYS = 4;

    public function generateDue(): int
    {
        $generated = 0;
        $definitions = ShiftDefinition::where('is_active', true)->get();
        $today = Carbon::today();

        foreach ($definitions as $def) {
            for ($daysAgo = self::CATCH_UP_DAYS; $daysAgo >= 0; $daysAgo--) {
                $date = $today->copy()->subDays($daysAgo)->toDateString();
                [$start, $end] = $def->boundsFor($date);

                if ($end->isFuture()) continue; // смена ещё не закончилась

                $exists = ShiftReport::where('shift_date', $date)
                    ->where('shift_definition_id', $def->id)
                    ->exists();
                if ($exists) continue;

                $this->generate($def, $date, $start, $end);
                $generated++;
            }
        }

        return $generated;
    }

    public function regenerate(ShiftDefinition $def, string $date): ShiftReport
    {
        [$start, $end] = $def->boundsFor($date);
        ShiftReport::where('shift_date', $date)->where('shift_definition_id', $def->id)->delete();
        return $this->generate($def, $date, $start, $end);
    }

    /**
     * Снимок ещё идущей смены "на сейчас" -- не пишется в БД, считается
     * заново на каждый вызов. Возвращает null, если прямо сейчас ни одна
     * активная смена не идёт (например, смены настроены с разрывом).
     */
    public function current(): ?array
    {
        $now = now();
        foreach (ShiftDefinition::where('is_active', true)->orderBy('sort_order')->get() as $def) {
            foreach ([$now->toDateString(), $now->copy()->subDay()->toDateString()] as $date) {
                [$start, $scheduledEnd] = $def->boundsFor($date);
                if ($now->lt($start) || $now->gte($scheduledEnd)) continue;

                return array_merge(
                    [
                        'shift_definition_id' => $def->id,
                        'shift_date'          => $date,
                        'shift_name'          => $def->name,
                        'shift_start_at'      => $start->toIso8601String(),
                        'shift_end_at'        => $scheduledEnd->toIso8601String(),
                        'as_of'               => $now->toIso8601String(),
                        'is_current'          => true,
                    ],
                    $this->computeTotals($start, $now),
                    ['extensions' => $this->computeExtensionRows($start, $now)]
                );
            }
        }
        return null;
    }

    /**
     * Сырые (НЕсглаженные) сегменты статуса добавочного за окно -- для
     * разбора спорных случаев: показывает, что реально произошло по логу,
     * и какие DND-сегменты не были засчитаны в отчёт как шум (counted=false).
     */
    public function auditSegments(string $ext, Carbon $start, Carbon $end): array
    {
        $minDnd = $this->minDndSeconds();
        return array_map(function ($seg) use ($minDnd) {
            $secs = (int) round($seg['start']->diffInSeconds($seg['end']));
            return [
                'status'  => $seg['status'],
                'start'   => $seg['start']->toIso8601String(),
                'end'     => $seg['end']->toIso8601String(),
                'seconds' => $secs,
                'counted' => !($seg['status'] === 'dnd' && $secs < $minDnd),
            ];
        }, $this->statusSegments($ext, $start, $end));
    }

    private function generate(ShiftDefinition $def, string $date, Carbon $start, Carbon $end): ShiftReport
    {
        $totals = $this->computeTotals($start, $end);

        $report = ShiftReport::create(array_merge($totals, [
            'shift_definition_id' => $def->id,
            'shift_date'          => $date,
            'shift_name'          => $def->name,
            'shift_start_at'      => $start,
            'shift_end_at'        => $end,
            'generated_at'        => now(),
        ]));

        foreach ($this->computeExtensionRows($start, $end) as $row) {
            ShiftReportExtension::create(array_merge($row, ['shift_report_id' => $report->id]));
        }

        return $report;
    }

    /** Звонки/SLA/ожидание по очереди целиком за окно */
    private function computeTotals(Carbon $start, Carbon $end): array
    {
        $callsQuery = fn() => Call::whereBetween('called_at', [$start, $end])->whereNotNull('queue_status');

        $total    = $callsQuery()->count();
        $answered = $callsQuery()->where('queue_status', 'answered')->count();
        $missed   = $callsQuery()->where('queue_status', 'missed')->count();
        $missedPercent = $total > 0 ? round($missed / $total * 100, 1) : null;

        $avgWait = $callsQuery()->whereNotNull('wait_seconds')->avg('wait_seconds');
        $maxWait = $callsQuery()->whereNotNull('wait_seconds')->max('wait_seconds');

        $slaThreshold = (int) SystemSetting::get('shift_report_sla_threshold_sec', 30);
        $slaCount = $callsQuery()->where('queue_status', 'answered')
            ->where('wait_seconds', '<=', $slaThreshold)->count();
        $slaPercent = $total > 0 ? round($slaCount / $total * 100, 1) : null;

        return [
            'total_calls'       => $total,
            'answered_calls'    => $answered,
            'missed_calls'      => $missed,
            'missed_percent'    => $missedPercent,
            'avg_wait_sec'      => $avgWait !== null ? round($avgWait, 1) : null,
            'max_wait_sec'      => $maxWait,
            'sla_threshold_sec' => $slaThreshold,
            'sla_percent'       => $slaPercent,
            'unique_numbers'    => $callsQuery()->distinct('phone')->count('phone'),
        ];
    }

    /** Разбивка по добавочным (DND/офлайн/ожидание/разговор + звонки) за окно */
    private function computeExtensionRows(Carbon $start, Carbon $end): array
    {
        $rows = [];
        foreach ($this->extensionsInWindow($start, $end) as $ext) {
            $segments = $this->smoothShortDndBlips($this->statusSegments($ext, $start, $end));

            $seconds = ['offline' => 0, 'idle' => 0, 'in_call' => 0, 'dnd' => 0];
            $callDurations = [];
            foreach ($segments as $seg) {
                $dur = (int) round($seg['start']->diffInSeconds($seg['end']));
                $seconds[$seg['status']] = ($seconds[$seg['status']] ?? 0) + $dur;
                if ($seg['status'] === 'in_call') {
                    $callDurations[] = $dur;
                }
            }

            $answeredCalls = Call::where('operator_ext', $ext)
                ->where('queue_status', 'answered')
                ->whereBetween('called_at', [$start, $end]);

            $rows[] = [
                'extension'             => $ext,
                'seconds_dnd'           => $seconds['dnd'],
                'seconds_offline'       => $seconds['offline'],
                'seconds_idle'          => $seconds['idle'],
                'seconds_in_call'       => $seconds['in_call'],
                'calls_answered'        => (clone $answeredCalls)->count(),
                'call_duration_min_sec' => $callDurations ? min($callDurations) : null,
                'call_duration_avg_sec' => $callDurations ? round(array_sum($callDurations) / count($callDurations), 1) : null,
                'call_duration_max_sec' => $callDurations ? max($callDurations) : null,
                'unique_numbers'        => (clone $answeredCalls)->distinct('phone')->count('phone'),
            ];
        }
        return $rows;
    }

    /**
     * DND-сегменты короче порога -- почти наверняка гонка опроса очереди
     * (шаг ~15с), а не реальный осознанный DND, и раз по этим цифрам теперь
     * штрафуют операторов -- их не засчитываем как отдельный статус, а
     * приклеиваем к предыдущему сегменту (тому, что реально шёл до мигания).
     * Первый сегмент окна короткий DND не трогаем -- приклеивать некуда,
     * да и это редкий крайний случай (обычно там offline-заглушка).
     */
    private function smoothShortDndBlips(array $segments): array
    {
        $minDnd = $this->minDndSeconds();
        $result = [];
        foreach ($segments as $seg) {
            $secs = $seg['start']->diffInSeconds($seg['end']);
            if ($seg['status'] === 'dnd' && $secs < $minDnd && !empty($result)) {
                $result[count($result) - 1]['end'] = $seg['end'];
                continue;
            }
            $result[] = $seg;
        }
        return $result;
    }

    private function minDndSeconds(): int
    {
        return (int) SystemSetting::get('shift_report_min_dnd_seconds', 15);
    }

    /** Все добавочные, реально присутствовавшие в очереди за окно */
    private function extensionsInWindow(Carbon $start, Carbon $end): array
    {
        $fromLogs = OperatorStatusLog::whereBetween('created_at', [$start, $end])
            ->distinct()->pluck('extension');
        $initialLogs = OperatorStatusLog::where('created_at', '<', $start)
            ->select('extension', DB::raw('MAX(id) as max_id'))
            ->groupBy('extension')->pluck('max_id');
        $fromInitial = OperatorStatusLog::whereIn('id', $initialLogs)->pluck('extension');
        $fromCalls = Call::whereBetween('called_at', [$start, $end])
            ->where('queue_status', 'answered')
            ->whereNotNull('operator_ext')->where('operator_ext', '!=', '')
            ->distinct()->pluck('operator_ext');

        return $fromLogs->merge($fromInitial)->merge($fromCalls)
            ->filter()->unique()->sort(SORT_NATURAL)->values()->all();
    }

    /**
     * Реконструкция отрезков статуса за окно -- тот же принцип, что
     * PbxController::buildOperatorTimeline() для живого графика, но
     * независимая копия: тут нужна СУММА по статусам за окно смены (полное
     * или "пока что" для текущей), а не набор сегментов для отрисовки
     * графика, и трогать рабочий код графика ради этого не хотелось.
     */
    private function statusSegments(string $ext, Carbon $since, Carbon $until): array
    {
        $initial = OperatorStatusLog::where('extension', $ext)
            ->where('created_at', '<', $since)
            ->orderByDesc('id')->first();

        $events = OperatorStatusLog::where('extension', $ext)
            ->whereBetween('created_at', [$since, $until])
            ->orderBy('created_at')->get(['status', 'created_at']);

        $segments = [];
        $cursorStatus = $initial?->status;
        $cursorStart  = $since;

        foreach ($events as $ev) {
            if ($cursorStatus !== null) {
                $segments[] = ['status' => $cursorStatus, 'start' => $cursorStart, 'end' => $ev->created_at];
            }
            $cursorStatus = $ev->status;
            $cursorStart  = $ev->created_at;
        }
        if ($cursorStatus !== null) {
            $segments[] = ['status' => $cursorStatus, 'start' => $cursorStart, 'end' => $until];
        }

        return $segments;
    }
}
