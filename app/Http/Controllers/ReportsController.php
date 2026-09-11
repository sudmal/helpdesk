<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index', [
            'territories' => DB::table('territories')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function parseRange(Request $request): array
    {
        $from = $request->get('from', now()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    public function brigadeEfficiencyData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->brigadeEfficiency($from, $to));
    }

    public function territoryFrequencyData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->territoryFrequency($from, $to));
    }

    /**
     * Перенесено во вкладку "Отчёты" раздела Акты (2026-07-15) — доступ шире,
     * чем у общих Отчётов (manage-settings): ПЭО/Логистика/Абонотдел видят по
     * своему reports.view, без него сюда не попадают. Роут остался прежним
     * (reports.material-dynamics), middleware can:manage-settings снят с него
     * в пользу этой явной проверки.
     */
    public function materialDynamicsData(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isHeadSupport() || $user->hasPermission('reports.view'), 403);

        [$from, $to] = $this->parseRange($request);
        return response()->json($this->materialDynamics($from, $to));
    }

    public function distributionData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        $territoryId = $request->filled('territory_id') ? (int)$request->get('territory_id') : null;
        return response()->json($this->distribution($from, $to, $territoryId));
    }

    public function callStatsData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->callStats($from, $to));
    }

    public function operatorLoadData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->operatorLoad($from, $to));
    }

    /**
     * Сводный отчёт по бригадам (2026-09-11, заменил разрозненные "Нагрузка
     * бригад" + "Соблюдение сроков"): закрытые заявки, сроки, расход
     * материалов и нагрузка нормализованная на человеко-дни из графика
     * бригад (brigade_schedules — реальная явка, а не штатная численность,
     * см. память project-brigade-schedule-rework про плавающие выходные).
     *
     * Reopen-rate сюда сознательно НЕ включён — по словам пользователя,
     * бригадиры чаще переоткрывают заявку из-за ошибки при заполнении формы
     * закрытия, а не из-за реальной недоделанной работы, так что общий
     * счётчик переоткрытий вводил бы в заблуждение, а не отражал качество.
     */
    private function brigadeEfficiency(Carbon $from, Carbon $to): array
    {
        $closed = DB::table('tickets as t')
            ->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
            ->whereNotNull('t.brigade_id')
            ->where('ts.is_final', 1)
            ->whereBetween('t.closed_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('
                t.brigade_id,
                COUNT(*) as closed,
                SUM(t.scheduled_at IS NOT NULL) as with_schedule,
                SUM(t.scheduled_at IS NOT NULL AND t.closed_at <= t.scheduled_at) as on_time,
                SUM(t.scheduled_at IS NOT NULL AND t.closed_at > t.scheduled_at) as overdue,
                AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)) as avg_hours
            ')
            ->groupBy('t.brigade_id')
            ->get()
            ->keyBy('brigade_id');

        $materials = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereNotNull('t.brigade_id')
            ->whereNull('t.deleted_at')
            ->whereBetween('tm.created_at', [$from, $to])
            ->selectRaw('t.brigade_id, SUM(tm.quantity * tm.price_at_time) as amount')
            ->groupBy('t.brigade_id')
            ->pluck('amount', 'brigade_id');

        // Человеко-дни — реальная явка по графику (work), не штатная численность,
        // поэтому корректно учитывает отпуска/больничные/неполный состав.
        $manDays = DB::table('brigade_schedules')
            ->where('status', 'work')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('brigade_id, COUNT(*) as days')
            ->groupBy('brigade_id')
            ->pluck('days', 'brigade_id');

        $brigades = DB::table('brigades')->orderBy('name')->get(['id', 'name']);

        $rows = $brigades->map(function ($b) use ($closed, $materials, $manDays) {
            $c = $closed->get($b->id);
            $closedCount  = (int)($c->closed ?? 0);
            $withSchedule = (int)($c->with_schedule ?? 0);
            $onTime       = (int)($c->on_time ?? 0);
            $overdue      = (int)($c->overdue ?? 0);
            $days         = (int)($manDays->get($b->id) ?? 0);
            $amount       = (float)($materials->get($b->id) ?? 0);

            return [
                'brigade_id'      => $b->id,
                'brigade'         => $b->name,
                'closed'          => $closedCount,
                'on_time'         => $onTime,
                'overdue'         => $overdue,
                'pct_on_time'     => $withSchedule > 0 ? round(100 * $onTime / $withSchedule, 1) : null,
                'man_days'        => $days,
                'per_man_day'     => $days > 0 ? round($closedCount / $days, 2) : null,
                'material_cost'   => round($amount, 2),
                'cost_per_ticket' => $closedCount > 0 ? round($amount / $closedCount, 2) : null,
                'avg_hours'       => $c && $c->avg_hours !== null ? round((float)$c->avg_hours, 1) : null,
            ];
        })->sortByDesc('closed')->values();

        $totalClosed   = $rows->sum('closed');
        $totalOnTime   = $rows->sum('on_time');
        $totalOverdue  = $rows->sum('overdue');
        $totalManDays  = $rows->sum('man_days');
        $totalMaterial = $rows->sum('material_cost');
        $withSchedule  = $totalOnTime + $totalOverdue;

        return [
            'rows'    => $rows->toArray(),
            'summary' => [
                'closed'        => (int)$totalClosed,
                'pct_on_time'   => $withSchedule > 0 ? round(100 * $totalOnTime / $withSchedule, 1) : null,
                'material_cost' => round($totalMaterial, 2),
                'per_man_day'   => $totalManDays > 0 ? round($totalClosed / $totalManDays, 2) : null,
            ],
        ];
    }

    /**
     * Территории (2026-09-11): к сырому числу обращений добавлена
     * нормализация на количество адресов территории — иначе крупная
     * территория всегда выглядит "самой проблемной" просто за счёт размера.
     * Число адресов не зависит от периода (текущий размер территории), в
     * отличие от заявок.
     */
    private function territoryFrequency(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('tickets as t')
            ->join('addresses as a', 't.address_id', '=', 'a.id')
            ->join('territories as ter', 'a.territory_id', '=', 'ter.id')
            ->whereBetween('t.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('ter.id as territory_id, ter.name as territory, COUNT(*) as total')
            ->groupBy('ter.id', 'ter.name')
            ->orderByDesc('total')
            ->get();

        $addressCounts = DB::table('addresses')
            ->whereNotNull('territory_id')
            ->selectRaw('territory_id, COUNT(*) as cnt')
            ->groupBy('territory_id')
            ->pluck('cnt', 'territory_id');

        $labels = [];
        $values = [];
        $addresses = [];
        $per100 = [];

        foreach ($rows as $r) {
            $addrCnt = (int)($addressCounts->get($r->territory_id) ?? 0);
            $labels[]    = $r->territory;
            $values[]    = (int)$r->total;
            $addresses[] = $addrCnt;
            $per100[]    = $addrCnt > 0 ? round($r->total / $addrCnt * 100, 2) : null;
        }

        return [
            'labels'    => $labels,
            'values'    => $values,
            'addresses' => $addresses,
            'per100'    => $per100,
        ];
    }

    private function materialDynamics(Carbon $from, Carbon $to): array
    {
        $union = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('tm.created_at, tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time')
            ->unionAll(
                DB::table('connection_request_materials as crm')
                    ->join('connection_requests as cr', 'crm.connection_request_id', '=', 'cr.id')
                    ->whereBetween('crm.created_at', [$from, $to])
                    ->selectRaw('crm.created_at, crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time')
            );

        $weekly = DB::table(DB::raw("({$union->toSql()}) as all_materials"))
            ->mergeBindings($union)
            ->selectRaw('
                YEARWEEK(created_at, 1) as week_key,
                DATE_FORMAT(MIN(created_at), "%d.%m") as week_label,
                ROUND(SUM(quantity), 2) as qty,
                ROUND(SUM(quantity * price_at_time), 2) as amount
            ')
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $union2 = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time')
            ->unionAll(
                DB::table('connection_request_materials as crm')
                    ->join('connection_requests as cr', 'crm.connection_request_id', '=', 'cr.id')
                    ->whereBetween('crm.created_at', [$from, $to])
                    ->selectRaw('crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time')
            );

        $top = DB::table(DB::raw("({$union2->toSql()}) as all_materials"))
            ->mergeBindings($union2)
            ->selectRaw('
                material_name as name,
                material_code as code,
                material_unit as unit,
                ROUND(SUM(quantity), 2) as qty,
                ROUND(SUM(quantity * price_at_time), 2) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'), 'material_name', 'material_code', 'material_unit')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return [
            'weekly' => [
                'labels' => $weekly->pluck('week_label')->toArray(),
                'qty'    => $weekly->pluck('qty')->map(fn($v) => (float)$v)->toArray(),
                'amount' => $weekly->pluck('amount')->map(fn($v) => (float)$v)->toArray(),
            ],
            'top' => $top->toArray(),
        ];
    }

    /**
     * Распределение заявок по типу обращения (2026-09-11: добавлен выбор
     * периода — раньше вкладка была жёстко на текущий месяц, из-за чего
     * "По дням месяца" внутри одного месяца ничего фактически не
     * агрегировало, каждое число месяца встречалось ровно один раз; теперь
     * при периоде шире месяца это честная агрегация по числам месяца. Плюс
     * необязательный фильтр по территории.
     */
    private function distribution(Carbon $from, Carbon $to, ?int $territoryId = null): array
    {
        $serviceTypes = DB::table('service_types')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        $applyFilters = function ($query) use ($from, $to, $territoryId) {
            $query->whereBetween('t.created_at', [$from, $to])
                ->whereNull('t.deleted_at')
                ->whereNotNull('t.service_type_id');
            if ($territoryId) {
                $query->join('addresses as addr', 't.address_id', '=', 'addr.id')
                    ->where('addr.territory_id', $territoryId);
            }
            return $query;
        };

        $byDayRaw = $applyFilters(DB::table('tickets as t'))
            ->selectRaw('t.service_type_id, DAY(t.created_at) as day, COUNT(*) as cnt')
            ->groupBy('t.service_type_id', DB::raw('DAY(t.created_at)'))
            ->get()
            ->groupBy('service_type_id');

        $byDowRaw = $applyFilters(DB::table('tickets as t'))
            ->selectRaw('t.service_type_id, DAYOFWEEK(t.created_at) as dow, COUNT(*) as cnt')
            ->groupBy('t.service_type_id', DB::raw('DAYOFWEEK(t.created_at)'))
            ->get()
            ->groupBy('service_type_id');

        $dayLabels = range(1, 31);
        $dowOrder  = [2, 3, 4, 5, 6, 7, 1]; // MySQL: 1=Вс,2=Пн..7=Сб
        $dowLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $fallback  = ['#3b82f6','#ef4444','#22c55e','#f59e0b','#8b5cf6','#06b6d4','#f97316','#ec4899'];

        $byDay = [];
        $byDow = [];

        foreach ($serviceTypes as $i => $st) {
            $color = $st->color ?: $fallback[$i % count($fallback)];

            $dayData = $byDayRaw->get($st->id, collect())->keyBy('day');
            $byDay[] = [
                'name'  => $st->name,
                'color' => $color,
                'data'  => array_map(fn($d) => (int)($dayData[$d]->cnt ?? 0), $dayLabels),
            ];

            $dowData = $byDowRaw->get($st->id, collect())->keyBy('dow');
            $byDow[] = [
                'name'  => $st->name,
                'color' => $color,
                'data'  => array_map(fn($d) => (int)($dowData[$d]->cnt ?? 0), $dowOrder),
            ];
        }

        return [
            'byDay'     => ['labels' => $dayLabels, 'datasets' => $byDay],
            'byWeekday' => ['labels' => $dowLabels,  'datasets' => $byDow],
        ];
    }

    private function callStats(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('call_daily_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                hour,
                SUM(total_calls)                  as total_calls,
                SUM(answered)                     as answered,
                SUM(missed)                       as missed,
                ROUND(AVG(avg_wait_sec), 1)       as avg_wait_sec,
                MAX(max_wait_sec)                 as max_wait_sec,
                MAX(max_queue_depth)              as max_queue_depth,
                ROUND(AVG(avg_queue_depth), 1)    as avg_queue_depth,
                MAX(avg_operators)                as avg_operators
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $r      = $rows->get($h);
            $total  = (int)($r?->total_calls ?? 0);
            $ans    = (int)($r?->answered    ?? 0);
            $missed = (int)($r?->missed      ?? 0);
            $hours[] = [
                'hour'         => $h,
                'total'        => $total,
                'answered'     => $ans,
                'missed'       => $missed,
                'miss_rate'    => $total > 0 ? round(100 * $missed / $total, 1) : 0,
                'avg_wait'     => $r?->avg_wait_sec    !== null ? (float)$r->avg_wait_sec    : null,
                'max_wait'     => $r?->max_wait_sec    !== null ? (int)$r->max_wait_sec      : null,
                'max_queue'    => $r?->max_queue_depth !== null ? (int)$r->max_queue_depth   : null,
                'avg_queue'    => $r?->avg_queue_depth !== null ? round((float)$r->avg_queue_depth, 1) : null,
                'avg_operators'=> $r?->avg_operators   !== null ? (int)$r->avg_operators     : null,
            ];
        }

        $totalAll    = array_sum(array_column($hours, 'total'));
        $totalAns    = array_sum(array_column($hours, 'answered'));
        $totalMissed = array_sum(array_column($hours, 'missed'));
        $peakHour    = collect($hours)->sortByDesc('total')->first();
        $worstHour   = collect($hours)->where('total', '>', 2)->sortByDesc('miss_rate')->first();

        return [
            'hours'   => $hours,
            'summary' => [
                'total'       => $totalAll,
                'answered'    => $totalAns,
                'missed'      => $totalMissed,
                'answer_rate' => $totalAll > 0 ? round(100 * $totalAns / $totalAll, 1) : 0,
                'peak_hour'   => $peakHour  ? $peakHour['hour']  : null,
                'worst_hour'  => $worstHour ? $worstHour['hour'] : null,
            ],
        ];
    }

    /**
     * Оптимизация расписания операторов (2026-09-12) — для планирования смен
     * ТП нужна не картина одного дня, а типичная нагрузка на оператора по
     * часам суток за длительный период. Намеренно отдельный от callStats()
     * источник (его трогать нельзя — см. память project-reports-redesign):
     * здесь усреднение по дням честное (AVG), а не MAX как в почасовой
     * таблице выше, потому что там MAX оправдан для "что видели хотя бы
     * раз", а тут важна типичная, а не пиковая картина.
     *
     * Вердикт по часу — эвристика, не точная модель: "не хватает", если
     * очередь превышала число операторов на линии минимум в четверти дней
     * этого часа; "возможен избыток", если нагрузка на оператора (звонков/
     * оператора) заметно ниже среднечасовой по периоду и перегрузок не
     * было вовсе. Финальное решение по расписанию — за человеком.
     */
    private function operatorLoad(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('call_daily_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                hour,
                COUNT(*) as days,
                ROUND(AVG(avg_operators), 2) as avg_operators,
                ROUND(AVG(total_calls), 2) as avg_calls,
                SUM(total_calls) as calls_sum,
                SUM(missed) as missed_sum,
                SUM(CASE WHEN avg_operators > 0 AND max_queue_depth > avg_operators THEN 1 ELSE 0 END) as overload_days
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // Среднечасовая нагрузка на оператора по всем часам с операторами —
        // точка отсчёта, относительно которой час считается "тихим".
        $ratios = [];
        foreach ($rows as $r) {
            if ((float)$r->avg_operators > 0) {
                $ratios[] = (float)$r->avg_calls / (float)$r->avg_operators;
            }
        }
        $baseline = count($ratios) > 0 ? array_sum($ratios) / count($ratios) : null;

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $r = $rows->get($h);
            $days = $r ? (int)$r->days : 0;

            if ($days === 0 || (float)$r->avg_operators <= 0) {
                $hours[] = [
                    'hour' => $h, 'days' => $days, 'avg_operators' => null, 'avg_calls' => null,
                    'calls_per_operator' => null, 'miss_rate' => null, 'overload_pct' => null,
                    'verdict' => 'no_data',
                ];
                continue;
            }

            $avgOperators     = (float)$r->avg_operators;
            $avgCalls         = (float)$r->avg_calls;
            $callsPerOperator = round($avgCalls / $avgOperators, 2);
            $missRate         = $r->calls_sum > 0 ? round(100 * $r->missed_sum / $r->calls_sum, 1) : 0;
            $overloadPct      = round(100 * $r->overload_days / $days, 1);

            $verdict = 'balanced';
            if ($overloadPct >= 25) {
                $verdict = 'understaffed';
            } elseif ($baseline !== null && $callsPerOperator <= 0.4 * $baseline && $overloadPct == 0) {
                $verdict = 'overstaffed';
            }

            $hours[] = [
                'hour'               => $h,
                'days'               => $days,
                'avg_operators'      => round($avgOperators, 1),
                'avg_calls'          => round($avgCalls, 1),
                'calls_per_operator' => $callsPerOperator,
                'miss_rate'          => $missRate,
                'overload_pct'       => $overloadPct,
                'verdict'            => $verdict,
            ];
        }

        return [
            'hours'        => $hours,
            'baseline'     => $baseline !== null ? round($baseline, 2) : null,
            'understaffed' => array_values(array_filter($hours, fn($h) => $h['verdict'] === 'understaffed')),
            'overstaffed'  => array_values(array_filter($hours, fn($h) => $h['verdict'] === 'overstaffed')),
        ];
    }

}
