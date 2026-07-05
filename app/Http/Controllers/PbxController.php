<?php

namespace App\Http\Controllers;

use App\Models\{Call, Address, Ticket, QueueStat, IvrLog, DndLog};
use App\Services\TelegramService;
use App\Services\MaxService;
use AppServicesMaxService;
use Illuminate\Http\{Request, JsonResponse};

class PbxController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $phone         = $this->normalizePhone($request->input('phone', ''));
        $addressString = trim($request->input('address', ''));

        if (!$phone) {
            return response()->json(['status' => 'skipped']);
        }

        if ($addressString) {
            [$addressId, $apartment] = $this->matchAddress($addressString);
        } else {
            [$addressId, $addressString, $apartment] = $this->fallbackAddress($phone);
        }

        Call::create([
            'phone'          => $phone,
            'address_string' => $addressString ?: null,
            'apartment'      => $apartment,
            'address_id'     => $addressId,
            'called_at'      => now(),
            'event'          => $request->input('event', 'incoming'),
            'payload'        => $request->except('token'),
        ]);

        return response()->json(['status' => 'ok', 'address_id' => $addressId]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->input('phone', ''));

        if (strlen($phone) < 7) {
            return response()->json(['last_call' => null, 'tickets' => []]);
        }

        $suffix = substr($phone, -7);

        $lastCall = Call::where('phone', 'like', "%{$suffix}")
            ->with('address')
            ->latest('called_at')
            ->first();

        $tickets = Ticket::with(['address', 'status', 'type'])
            ->where('phone', 'like', "%{$suffix}")
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'number'     => $t->number,
                'address'    => $t->address?->full_address,
                'address_id' => $t->address_id,
                'apartment'  => $t->apartment,
                'type'       => $t->type?->name,
                'status'     => $t->status?->name,
                'date'       => $t->scheduled_at?->format('d.m.Y'),
            ]);

        $addr = $lastCall?->address;

        return response()->json([
            'last_call' => $lastCall ? [
                'called_at'      => $lastCall->called_at->format('d.m.Y H:i'),
                'address_string' => $lastCall->address_string,
                'apartment'      => $lastCall->apartment,
                'address'        => $addr ? [
                    'id'           => $addr->id,
                    'territory_id' => $addr->territory_id,
                    'label'        => $addr->full_address,
                    'full_address' => $addr->full_address,
                ] : null,
            ] : null,
            'tickets' => $tickets,
        ]);
    }

    // Парсинг строки биллинга: "кв-л Железнодорожный дом 15 кв 63"
    // Возвращает [address_id|null, apartment|null]
    private function matchAddress(string $raw): array
    {
        $apartment = null;
        if (preg_match('/\bкв[\.\s]+(\S+)/iu', $raw, $a)) {
            $apartment = $a[1];
        }

        if (!preg_match('/^(.+?)\s+дом\s+(\S+)/iu', $raw, $m)) {
            return [null, $apartment];
        }

        $street   = trim($m[1]);
        $building = trim($m[2]);

        // Strip common prefixes (ул, кв-л, пр-т, etc.) before matching
        $streetClean = preg_replace(
            '/^(ул\.?\s*|кв-л\.?\s*|кв\.?л\.?\s*|квартал\s*|пр-т\.?\s*|проспект\s*|пер\.?\s*|пл\.?\s*|б-р\.?\s*|бульвар\s*|ш\.?\s*)/iu',
            '', $street
        );
        $streetClean = trim($streetClean);

        $address = Address::where('building', $building)
            ->where('street', 'like', '%' . $streetClean . '%')
            ->first();

        return [$address?->id, $apartment];
    }

    // Fallback: find address from previous calls or tickets by phone suffix
    private function fallbackAddress(string $phone): array
    {
        $suffix = substr($phone, -7);

        $prevCall = Call::where('phone', 'like', "%{$suffix}")
            ->whereNotNull('address_id')
            ->whereNotNull('address_string')
            ->latest('called_at')
            ->first();

        if ($prevCall) {
            return [$prevCall->address_id, $prevCall->address_string, $prevCall->apartment];
        }

        $ticket = Ticket::where('phone', 'like', "%{$suffix}")
            ->whereNotNull('address_id')
            ->with('address')
            ->latest()
            ->first();

        if ($ticket?->address) {
            return [$ticket->address_id, $ticket->address->full_address, $ticket->apartment];
        }

        return [null, null, null];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        return $digits;
    }

    // ── Состояние очереди АТС ───────────────────────────────────────────

    public function queueStatus(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'queue'          => 'required|string|max:100',
            'waiting'        => 'required|integer|min:0',
            'talking'        => 'required|integer|min:0',
            'active_members' => 'required|integer|min:0',
            'total_members'  => 'required|integer|min:0',
            'raw'            => 'nullable|string',
            'channels_raw'   => 'nullable|string',
            'phones'         => 'nullable|array',
            'trunk'          => 'nullable|array',
        ]);

        QueueStat::create([
            'queue_name'     => $data['queue'],
            'waiting'        => $data['waiting'],
            'talking'        => $data['talking'],
            'active_members' => $data['active_members'],
            'total_members'  => $data['total_members'],
            'recorded_at'    => now(),
        ]);

        QueueStat::where('recorded_at', '<', now()->subHours(24))->delete();

        if (!empty($data['raw'])) {
            $channelsRaw = !empty($data['channels_raw']) ? base64_decode($data['channels_raw']) : '';
            $detail = $this->parseQueueOutput(base64_decode($data['raw']), $channelsRaw);
            $detail['phones'] = $data['phones'] ?? [];
            $detail['trunk']  = $data['trunk']  ?? null;
            \Cache::put('queue:detail:' . $data['queue'], $detail, 300);
            $this->trackCallEvents($data['queue'], $detail);
        }

        $cmd = \Cache::get('queue:pending_cmd');
        if ($cmd) {
            \Cache::forget('queue:pending_cmd');
        }

        return response()->json(array_filter(['status' => 'ok', 'cmd' => $cmd]));
    }

    public function triggerCmd(Request $request): JsonResponse
    {
        $cmd     = $request->input('cmd');
        $allowed = ['pjsip_reload', 'queue_reload', 'qualify_all', 'fix_dialing'];
        if (!in_array($cmd, $allowed, true)) {
            return response()->json(['error' => 'Invalid cmd'], 422);
        }
        \Cache::put('queue:pending_cmd', $cmd, 120);
        return response()->json(['status' => 'ok']);
    }

    public function queueHistory(Request $request): JsonResponse
    {
        $queue = $request->input('queue');
        $hours = min((int) $request->input('hours', 3), 24);

        $query = QueueStat::where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at');

        if ($queue) {
            $query->where('queue_name', $queue);
        }

        $rows = $query->get(['recorded_at', 'waiting', 'talking', 'active_members', 'total_members']);
        $this->attachDndCounts($rows, $hours);

        $latest = QueueStat::orderByDesc('recorded_at')->first();

        $queueName = $queue ?: QueueStat::orderByDesc('recorded_at')->value('queue_name');
        $detail = $queueName ? \Cache::get('queue:detail:' . $queueName) : null;
        $detail = $detail ?? ['members' => [], 'callers' => []];
        $detail['members'] = $this->attachDndStatus($detail['members'] ?? []);

        // Последняя точка графика "В DND" должна совпадать с live-статусом
        // операторов (тем же, что видно в бейджах) -- иначе, например,
        // оператор, который был в DND-стрике и потом ушёл в офлайн, ещё
        // долго висит "в DND" на графике, хотя бейдж уже корректно погас.
        if ($rows->isNotEmpty()) {
            $liveDndExts = collect($detail['members'])
                ->filter(fn($m) => ($m['dnd'] ?? false) || !empty($m['dnd_missed_since']))
                ->pluck('ext')
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $lastRow = $rows->last();
            $lastRow->dnd_active     = $liveDndExts->count();
            $lastRow->dnd_extensions = $liveDndExts->all();

            // А для операторов, которые сейчас офлайн, но были в DND-стрике
            // (dnd_suppressed_since) -- убираем их из ВСЕХ точек графика с
            // этого момента и до конца окна, а не только из последней. Иначе
            // весь хвост графика продолжает честно (но вводяще в заблуждение)
            // показывать "в DND" для того, кто уже давно просто отключился.
            $suppressedSince = collect($detail['members'])
                ->filter(fn($m) => !empty($m['dnd_suppressed_since']))
                ->pluck('dnd_suppressed_since', 'ext');

            if ($suppressedSince->isNotEmpty()) {
                foreach ($rows as $row) {
                    if (empty($row->dnd_extensions)) continue;
                    $changed = false;
                    $kept = [];
                    foreach ($row->dnd_extensions as $ext) {
                        $since = $suppressedSince->get($ext);
                        if ($since && $row->recorded_at >= $since) {
                            $changed = true;
                            continue;
                        }
                        $kept[] = $ext;
                    }
                    if ($changed) {
                        $row->dnd_extensions = $kept;
                        $row->dnd_active     = count($kept);
                    }
                }
            }
        }

        $missedCalls = \App\Models\Call::where('queue_status', 'missed')
            ->where('called_at', '>=', now()->subHours($hours))
            ->orderBy('called_at')
            ->pluck('called_at');

        $missedDnd = DndLog::where('state', 'missed_dnd')
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get(['extension', 'created_at']);

        return response()->json([
            'latest'       => $latest,
            'history'      => $rows,
            'detail'       => $detail,
            'missed_calls' => $missedCalls,
            'missed_dnd'   => $missedDnd,
        ]);
    }

    /**
     * Проставляет каждой точке истории количество операторов,
     * находившихся в DND на этот момент (dnd_active), для графика.
     */
    private function attachDndCounts($rows, int $hours): void
    {
        if ($rows->isEmpty()) return;
        $since = now()->subHours($hours);

        // Честный DND (*78/*79)
        $honestState = [];
        $initialHonest = DndLog::select('extension', \DB::raw('MAX(id) as max_id'))
            ->where('created_at', '<', $since)
            ->whereIn('state', ['on', 'off'])
            ->groupBy('extension')
            ->pluck('max_id');
        foreach (DndLog::whereIn('id', $initialHonest)->get() as $log) {
            if ($log->state === 'on') $honestState[$log->extension] = true;
        }

        // DND по звонку: серия missed_dnd, ещё не прерванная ответившим звонком
        // этого же добавочного -- считаем активной на начало окна, если
        // последний missed_dnd до $since новее последнего ответа этого добавочного.
        $streakState = [];
        $lastMissedBefore = DndLog::where('created_at', '<', $since)
            ->where('state', 'missed_dnd')
            ->select('extension', \DB::raw('MAX(created_at) as last_at'))
            ->groupBy('extension')
            ->get()
            ->keyBy('extension');
        foreach ($lastMissedBefore as $ext => $row0) {
            $answeredAfter = Call::where('operator_ext', $ext)
                ->where('queue_status', 'answered')
                ->where('called_at', '>', $row0->last_at)
                ->where('called_at', '<', $since)
                ->exists();
            $ringingAfter = DndLog::where('extension', $ext)
                ->where('state', 'ringing')
                ->where('created_at', '>', $row0->last_at)
                ->where('created_at', '<', $since)
                ->exists();
            $unavailableAfter = DndLog::where('extension', $ext)
                ->where('state', 'unavailable')
                ->where('created_at', '>', $row0->last_at)
                ->where('created_at', '<', $since)
                ->exists();
            if (!$answeredAfter && !$ringingAfter && !$unavailableAfter) $streakState[$ext] = true;
        }

        $dndEvents = DndLog::where('created_at', '>=', $since)
            ->whereIn('state', ['on', 'off', 'missed_dnd', 'ringing', 'unavailable'])
            ->orderBy('created_at')
            ->get(['extension', 'state', 'created_at']);

        $answeredEvents = Call::where('called_at', '>=', $since)
            ->where('queue_status', 'answered')
            ->whereNotNull('operator_ext')
            ->orderBy('called_at')
            ->get(['operator_ext', 'called_at']);

        $events = [];
        foreach ($dndEvents as $e) {
            $events[] = ['at' => $e->created_at, 'type' => $e->state, 'ext' => $e->extension];
        }
        foreach ($answeredEvents as $e) {
            $events[] = ['at' => $e->called_at, 'type' => 'answered', 'ext' => $e->operator_ext];
        }
        usort($events, fn($a, $b) => $a['at'] <=> $b['at']);

        $i = 0;
        $eventsCount = count($events);
        foreach ($rows as $row) {
            while ($i < $eventsCount && $events[$i]['at'] <= $row->recorded_at) {
                $e = $events[$i];
                if ($e['type'] === 'on') { $honestState[$e['ext']] = true; }
                elseif ($e['type'] === 'off') { unset($honestState[$e['ext']]); }
                elseif ($e['type'] === 'missed_dnd') { $streakState[$e['ext']] = true; }
                elseif ($e['type'] === 'answered') { unset($streakState[$e['ext']]); }
                elseif ($e['type'] === 'ringing') { unset($streakState[$e['ext']]); }
                elseif ($e['type'] === 'unavailable') { unset($streakState[$e['ext']]); }
                $i++;
            }
            $extensions = array_values(array_unique(array_merge(array_keys($honestState), array_keys($streakState))));
            sort($extensions);
            $row->dnd_active     = count($extensions);
            $row->dnd_extensions = $extensions;
        }
    }

    /**
     * Добавляет каждому оператору в списке текущий статус DND
     * (dnd: bool, dnd_since: время последнего включения, если сейчас в DND).
     */
    private function attachDndStatus(array $members): array
    {
        if (empty($members)) return $members;

        $exts = array_column($members, 'ext');
        $latestIds = DndLog::whereIn('extension', $exts)
            ->whereIn('state', ['on', 'off'])
            ->select('extension', \DB::raw('MAX(id) as max_id'))
            ->groupBy('extension')
            ->pluck('max_id');
        $latestByExt = DndLog::whereIn('id', $latestIds)->get()->keyBy('extension');

        // DND по звонку: непрерывная серия missed_dnd для добавочного, начиная
        // с первого события ПОСЛЕ его последнего реально отвеченного звонка --
        // это и есть текущий "стрик", который тянется, пока номер не ответит.
        $lastAnsweredByExt = Call::whereIn('operator_ext', $exts)
            ->where('queue_status', 'answered')
            ->select('operator_ext', \DB::raw('MAX(called_at) as last_answered_at'))
            ->groupBy('operator_ext')
            ->pluck('last_answered_at', 'operator_ext');

        $allMissed = DndLog::whereIn('extension', $exts)
            ->where('state', 'missed_dnd')
            ->orderBy('created_at')
            ->get(['extension', 'created_at']);

        $streaks = [];
        foreach ($allMissed->groupBy('extension') as $ext => $evList) {
            $lastAnswered = $lastAnsweredByExt->get($ext);
            $start = null;
            $last  = null;
            foreach ($evList as $ev) {
                if ($lastAnswered && $ev->created_at <= $lastAnswered) continue;
                if ($start === null) $start = $ev->created_at;
                $last = $ev->created_at;
            }
            if ($start !== null) $streaks[$ext] = ['start' => $start, 'last' => $last];
        }

        foreach ($members as &$m) {
            $log = $latestByExt->get($m['ext'] ?? null);
            $m['dnd']       = $log && $log->state === 'on';
            $m['dnd_since'] = $m['dnd'] ? $log->created_at : null;

            $streak = $streaks[$m['ext'] ?? null] ?? null;

            // Подстраховка: если по свежему "queue show" (secs) видно, что
            // реальный звонок уже прошёл позже последнего missed_dnd в серии,
            // а запись в calls ещё не долетела -- гасим тоже.
            if ($streak && isset($m['secs'])) {
                $lastActivityAt = now()->subSeconds($m['secs']);
                if ($lastActivityAt->gt($streak['last'])) {
                    $streak = null;
                }
            }

            // Если оператор сейчас реально offline (Unavailable) -- DND
            // тут ни при чём, это уже не "человек сам выключил дозвон",
            // а потерянная регистрация. Показывать одновременно "Недоступен"
            // и "DND (по звонку)" вводит в заблуждение -- гасим бейдж.
            // Запоминаем начало подавленного стрика отдельно -- пригодится,
            // чтобы поправить график "В DND" за весь этот период, не
            // только последнюю точку (см. queueHistory()).
            $m['dnd_suppressed_since'] = null;
            if ($streak && ($m['status'] ?? null) === 'unavailable') {
                $m['dnd_suppressed_since'] = $streak['start'];
                $streak = null;
            }

            // Сам факт нормального звонка (Ringing) -- уже доказательство,
            // что СЕЙЧАС это не DND: честный DND отбивает мгновенным 486,
            // до состояния "звонит" дело просто не доходит. Раз дозвон
            // идёт нормально, гасим бейдж, не дожидаясь именно ответа --
            // иначе бейдж виснет часами, если звонок дошёл, но не был
            // принят по другой причине (не наша забота).
            if ($streak && ($m['status'] ?? null) === 'ringing') {
                $m['dnd_suppressed_since'] = $streak['start'];
                $streak = null;
            }

            $m['dnd_missed_since'] = $streak['start'] ?? null;
            $m['dnd_missed_at']    = $streak['last'] ?? null;
        }

        return $members;
    }

    private function trackCallEvents(string $queueName, array $detail): void
    {
        $cacheKey  = 'queue:callers_state:' . $queueName;
        $prevState = \Cache::get($cacheKey, []);

        // Приводим номер к единому формату (без "+", как в normalizePhone()) --
        // иначе звонки, попавшие в calls через этот запасной путь, не
        // подтягивают адрес/IVR из-за расхождения формата с основным вебхуком.
        $currentPhones = [];
        foreach ($detail['callers'] as $caller) {
            if ($caller['phone'] ?? null) {
                $currentPhones[$this->normalizePhone($caller['phone'])] = $caller['wait'];
            }
        }

        $inCallPhones = [];
        foreach ($detail['members'] as $member) {
            if (($member['status'] ?? '') === 'in_call' && ($member['caller_phone'] ?? null)) {
                $inCallPhones[$this->normalizePhone($member['caller_phone'])] = $member['ext'];
            }
        }

        foreach ($prevState as $phone => $state) {
            if (isset($currentPhones[$phone])) continue;

            $status      = isset($inCallPhones[$phone]) ? 'answered' : 'missed';
            $operatorExt = $inCallPhones[$phone] ?? null;

            $parts   = array_map('intval', explode(':', $state['last_wait'] ?? '0:00'));
            $waitSec = count($parts) === 3
                ? $parts[0] * 3600 + $parts[1] * 60 + $parts[2]
                : ($parts[0] * 60 + ($parts[1] ?? 0));

            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            }
            $suffix = substr($digits, -7);

            $call = Call::where('phone', 'like', '%' . $suffix)
                ->where('called_at', '>=', now()->subHours(2))
                ->whereNull('queue_status')
                ->orderByDesc('called_at')
                ->first();

            if ($call) {
                $call->update([
                    'queue_status' => $status,
                    'operator_ext' => $operatorExt,
                    'wait_seconds' => $waitSec,
                ]);
            } else {
                Call::create([
                    'phone'        => $phone,
                    'called_at'    => $state['entered_at'],
                    'event'        => 'queue',
                    'queue_status' => $status,
                    'operator_ext' => $operatorExt,
                    'wait_seconds' => $waitSec,
                ]);
            }
        }

        $newState = [];
        foreach ($detail['callers'] as $caller) {
            $phone = $caller['phone'] ?? null;
            if (!$phone) continue;
            $phone = $this->normalizePhone($phone);
            $newState[$phone] = [
                'phone'      => $phone,
                'entered_at' => $prevState[$phone]['entered_at'] ?? now()->toIso8601String(),
                'last_wait'  => $caller['wait'],
            ];
        }

        // Звонки, принятые мгновенно (не попали в очередь)
        $prevInCallKey    = 'queue:incall_state:' . $queueName;
        $prevInCallPhones = \Cache::get($prevInCallKey, []);

        foreach ($inCallPhones as $phone => $ext) {
            // пропускаем: был в очереди (уже обработан выше) или уже был в разговоре
            if (isset($prevState[$phone]) || isset($prevInCallPhones[$phone])) continue;

            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) === 11 && $digits[0] === '8') {
                $digits = '7' . substr($digits, 1);
            }
            $suffix = substr($digits, -7);

            $call = Call::where('phone', 'like', '%' . $suffix)
                ->where('called_at', '>=', now()->subHours(2))
                ->whereNull('queue_status')
                ->orderByDesc('called_at')
                ->first();

            if ($call) {
                $call->update([
                    'queue_status' => 'answered',
                    'operator_ext' => $ext,
                    'wait_seconds' => 0,
                ]);
            }
        }

        \Cache::put($prevInCallKey, $inCallPhones, 600);
        \Cache::put($cacheKey, $newState, 600);

        // Момент перехода добавочного в "Ringing" -- фиксируем как отдельное
        // событие, чтобы график "В DND" мог закрывать missed_dnd-стрик уже
        // здесь, а не ждать полного завершения разговора (queue_status=
        // answered пишется только когда звонок УЖЕ закончился -- из-за этого
        // график часами держал "в DND" тех, кто на самом деле уже разговаривал,
        // просто разговор ещё не завершился). Та же логика, что уже работает
        // в живом бейдже attachDndStatus() -- сам факт нормального дозвона
        // доказывает, что это не честный DND (тот отбивает мгновенным 486).
        $prevStatusKey  = 'queue:member_status:' . $queueName;
        $prevStatuses   = \Cache::get($prevStatusKey, []);
        $currentStatuses = [];
        foreach ($detail['members'] as $member) {
            $ext = $member['ext'] ?? null;
            if (!$ext) continue;
            $status = $member['status'] ?? null;
            $currentStatuses[$ext] = $status;
            if ($status === 'ringing' && ($prevStatuses[$ext] ?? null) !== 'ringing') {
                DndLog::create(['extension' => $ext, 'state' => 'ringing']);
            }
            // Аналогично ringing -- если добавочный ушёл в Unavailable (потерял
            // регистрацию/офлайн), это уже не "выбор оператора", а потеря связи.
            // Закрываем стрик и здесь же, как это уже делает живой бейдж
            // attachDndStatus() -- иначе график держит "В DND" человека,
            // который просто выключил телефон и никогда больше не звонил
            // (реальный случай: 220 разлогинился 2026-07-04, стрик от
            // старого missed_dnd висел бы в графике бесконечно).
            if ($status === 'unavailable' && ($prevStatuses[$ext] ?? null) !== 'unavailable') {
                DndLog::create(['extension' => $ext, 'state' => 'unavailable']);
            }
        }
        \Cache::put($prevStatusKey, $currentStatuses, 300);
    }

    private function parseQueueOutput(string $raw, string $channelsRaw = ''): array
    {
        $members           = [];
        $callers           = [];
        $inCallers         = false;
        $channelPhones     = [];
        $memberCallerPhone = [];

        if ($channelsRaw) {
            $channelInfo = [];
            foreach (explode("\n", $channelsRaw) as $line) {
                $line = rtrim($line);
                if (!$line || !preg_match('/^(\S+)\s/', $line, $cm)) continue;
                $channel = $cm[1];

                $callerId = null;
                if (preg_match('/(\S+)\s+\d{2}:\d{2}:\d{2}/', $line, $pm)) {
                    $callerId = $pm[1];
                }

                $bridgeId = null;
                if (preg_match('/\s+([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{1,})\s*$/i', $line, $bm)) {
                    $bridgeId = $bm[1];
                }

                $channelInfo[$channel] = ['callerID' => $callerId, 'bridgeID' => $bridgeId];

                // Для звонящих в очереди: канал из queue show → телефон
                if (str_contains($line, 'Queue') && preg_match('/(\+?7\d{10})\s+\d{2}:\d{2}:\d{2}/', $line, $pm)) {
                    $channelPhones[$channel] = $pm[1];
                }
            }

            // Группируем каналы по BridgeID
            $bridgeGroups = [];
            foreach ($channelInfo as $ch => $info) {
                if ($info['bridgeID']) {
                    $bridgeGroups[$info['bridgeID']][] = $ch;
                }
            }

            // Для каждого Local/EXT@internal-...;1 ищем собеседника в том же бридже
            foreach ($channelInfo as $ch => $info) {
                if (!preg_match('/^Local\/(\d+)@internal-[^;]+;1$/', $ch, $em)) continue;
                $ext      = $em[1];
                $bridgeId = $info['bridgeID'];
                if (!$bridgeId || !isset($bridgeGroups[$bridgeId])) continue;

                foreach ($bridgeGroups[$bridgeId] as $bridgeCh) {
                    $cid = $channelInfo[$bridgeCh]['callerID'] ?? null;
                    if ($cid && preg_match('/^\+?7\d{10}$/', $cid)) {
                        $memberCallerPhone[$ext] = $cid;
                        break;
                    }
                }
            }
        }

        foreach (explode("\n", $raw) as $line) {
            if (str_contains($line, 'Callers:'))  { $inCallers = true;  continue; }
            if (str_contains($line, 'Members:'))  { $inCallers = false; continue; }
            if (trim($line) === 'No Callers')     { continue; }

            if (!$inCallers && preg_match('/^\s+(\d+)\s+\(/', $line) && str_contains($line, 'has taken')) {
                preg_match('/^\s+(\d+)\s+/', $line, $m);
                $ext = $m[1];
                if (str_contains($line, '(in call)') || str_contains($line, '(Busy)')) {
                    $status = 'in_call';
                } elseif (str_contains($line, '(Ringing)')) {
                    $status = 'ringing';
                } elseif (str_contains($line, '(Unavailable)')) {
                    $status = 'unavailable';
                } else {
                    $status = 'idle';
                }
                $secs = null;
                if (preg_match('/last was (\d+) secs/', $line, $m)) {
                    $secs = (int) $m[1];
                }
                $members[] = compact('ext', 'status', 'secs');
            }

            if ($inCallers && preg_match('/^\s+(\d+)\.\s+(\S+)\s+\(wait:\s*([\d:]+)/', $line, $m)) {
                $phone = $channelPhones[$m[2]] ?? null;
                $callers[] = ['pos' => (int)$m[1], 'wait' => $m[3], 'phone' => $phone];
            }
        }

        // Собираем все номера для поиска адресов (очередь + операторы в разговоре)
        $allPhones = array_unique(array_filter(array_merge(
            array_column($callers, 'phone'),
            array_values($memberCallerPhone)
        )));

        $addressByPhone = [];
        foreach ($allPhones as $phone) {
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
            $suffix = substr($digits, -7);
            $call = Call::where('phone', 'like', "%{$suffix}")
                ->where(fn($q) => $q->whereNotNull('address_string')->orWhereNotNull('address_id'))
                ->with('address')
                ->latest('called_at')
                ->first();
            if ($call) {
                // Если есть привязанный Address -- город+улица+дом оттуда (в
                // сыром address_string города никогда нет), квартиру берём
                // СВОЮ у звонка (Address.apartment общий на дом, может
                // относиться к другой заявке/типу услуги -- та же ловушка,
                // что была в address.full мобильного API).
                if ($call->address) {
                    $parts = array_filter([
                        $call->address->city,
                        $call->address->street,
                        $call->address->building,
                        $call->apartment ? 'кв. ' . $call->apartment : null,
                    ]);
                    $addressByPhone[$phone] = implode(', ', $parts);
                } else {
                    $addressByPhone[$phone] = $call->address_string;
                }
            }
        }

        $callers = array_map(fn($c) => array_merge($c, [
            'address' => $addressByPhone[$c['phone'] ?? ''] ?? null,
        ]), $callers);

        $members = array_map(function ($m) use ($memberCallerPhone, $addressByPhone) {
            if ($m['status'] !== 'in_call') return $m;
            $phone = $memberCallerPhone[$m['ext']] ?? null;
            return array_merge($m, [
                'caller_phone'   => $phone,
                'caller_address' => $phone ? ($addressByPhone[$phone] ?? null) : null,
            ]);
        }, $members);

        return ['members' => $members, 'callers' => $callers];
    }

    public function dndStatus(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $exts = array_filter(explode(',', (string) $request->input('extensions', '')));
        if (empty($exts)) {
            return response()->json(['status' => 'skipped']);
        }

        // Для watchdog очереди -- не путать реальный "затык" с честным
        // DND, за которым решает сам оператор (не наша забота лечить).
        // ВАЖНО: раньше тут была узкая эвристика "missed_dnd за последние
        // 3 минуты" -- она пропускала стрики, начавшиеся раньше 3 минут
        // назад, но всё ещё не закрытые реальным ответом (как в бейдже).
        // Нашли на практике 2026-07-04: у 221 DND-стрик был старше 3 минут,
        // watchdog посчитал его "не в DND" и словил false positive. Теперь
        // используем ТУ ЖЕ логику стрика, что и в attachDndStatus() для
        // бейджа -- без ограничения по времени.
        $lastAnsweredByExt = Call::whereIn('operator_ext', $exts)
            ->where('queue_status', 'answered')
            ->select('operator_ext', \DB::raw('MAX(called_at) as last_answered_at'))
            ->groupBy('operator_ext')
            ->pluck('last_answered_at', 'operator_ext');

        $allMissed = DndLog::whereIn('extension', $exts)
            ->where('state', 'missed_dnd')
            ->orderBy('created_at')
            ->get(['extension', 'created_at']);

        // PHP приводит числовые строковые ключи массива к int (groupBy
        // отдаёт $ext как int для "221" и т.п.) -- без явного (string)
        // json_encode отдаёт [221] БЕЗ кавычек, а watchdog (bash grep по
        // "[0-9]+") такое не парсит и молча получает пустой список.
        // Нашли на практике 2026-07-04: с момента этого коммита watchdog
        // ни разу не увидел честный DND, что дало ложный alert.
        $recent = collect();
        foreach ($allMissed->groupBy('extension') as $ext => $evList) {
            $lastAnswered = $lastAnsweredByExt->get($ext);
            $inStreak = $evList->contains(fn($ev) => !$lastAnswered || $ev->created_at > $lastAnswered);
            if ($inStreak) {
                $recent->push((string) $ext);
            }
        }
        $recent = $recent->unique()->values();

        return response()->json(['dnd_extensions' => $recent]);
    }

    public function alert(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $text = trim((string) $request->input('text', ''));
        if (!$text) {
            return response()->json(['status' => 'skipped']);
        }

        // Watchdog очереди АТС не может достучаться напрямую до
        // api.telegram.org (файрвол MikoPBX режет исходящий HTTPS кроме
        // vega8.ru) -- поэтому шлёт сюда, а HelpDesk уже ретранслирует
        // в Telegram (он и так умеет).
        (new TelegramService())->send('674796905', $text);
        (new MaxService())->send('161346780', $text);

        return response()->json(['status' => 'ok']);
    }

    public function ivrLog(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $action = $request->input('action', '');
        if (!$action) {
            return response()->json(['status' => 'skipped']);
        }

        IvrLog::create([
            'call_id'         => $request->input('call_id', ''),
            'phone'           => $request->input('phone', ''),
            'subscriber_name' => $request->input('name', null),
            'agreement_num'   => $request->input('agrmnum', null),
            'address'         => $request->input('address', null),
            'balance'         => is_numeric($request->input('balance')) ? (float) $request->input('balance') : null,
            'blocked'         => (int) $request->input('blocked', 0),
            'action'          => $action,
            'details'         => $request->input('details', null),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function dndLog(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $extension = $request->input('extension', '');
        $state     = $request->input('state', '');
        if (!$extension || !in_array($state, ['on', 'off', 'missed_dnd'], true)) {
            return response()->json(['status' => 'skipped']);
        }

        // missed_dnd может прилетать пачкой (несколько ожидающих в очереди
        // одновременно ловят DND у одного и того же добавочного) -- не пишем
        // журнал плотнее одной записи на 2 минуты на добавочный.
        if ($state === 'missed_dnd') {
            $recent = \App\Models\DndLog::where('extension', $extension)
                ->where('state', 'missed_dnd')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();
            if ($recent) {
                return response()->json(['status' => 'deduped']);
            }
        }

        \App\Models\DndLog::create([
            'extension'  => $extension,
            'state'      => $state,
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
