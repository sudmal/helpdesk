<?php
namespace App\Http\Controllers;
use App\Models\{Ticket, Address, TicketType, TicketStatus, Brigade, User, SystemSetting};
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    // Маппинг sector_id -> territory_id (IDs совпадают в новой системе)
    private const SECTOR_MAP = [1 => 1, 14 => 14, 15 => 15, 16 => 16, 17 => 17, 18 => 18, 19 => 19];
    // Сектор -> город
    private const CITY_MAP   = [1 => 'Макеевка', 14 => 'Макеевка', 15 => 'Ждановка', 16 => 'Донецк', 17 => 'Макеевка', 18 => 'Ждановка', 19 => 'Макеевка'];
    // Нелайвовые территории (живые — 16 Донецк, 17 Гвардейка — пропускаем)
    private const LIVE_IDS   = [16, 17];
    // Маппинг сеть -> service_type_id
    private const NETWORK_MAP = ['Интернет' => 1, 'КТВ' => 2, 'ВОЛС' => 3];

    public function __construct(private TicketService $ticketService) {}

    // Существующий метод для живых территорий (artisan sync:ticket)
    public function store(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== config('services.sync.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $oldNumber = 'old-' . $request->input('old_id');
        if (Ticket::where('number', $oldNumber)->exists()) {
            return response()->json(['status' => 'already_synced']);
        }
        $brigade = Brigade::where('name', 'ЧГДН')->first();
        if (!$brigade) {
            return response()->json(['error' => 'Brigade ЧГДН not found'], 422);
        }
        $date        = $request->input('execution_date');
        $scheduledAt = $this->findNextSlot($brigade->id, $date);
        $creator     = User::find($request->input('creator_id', 1)) ?? User::first();
        $ticket      = $this->ticketService->create([
            'number'          => $oldNumber,
            'address_id'      => $request->input('address_id'),
            'apartment'       => $request->input('apartment') ?: null,
            'type_id'         => $request->input('type_id'),
            'service_type_id' => $request->input('service_type_id'),
            'brigade_id'      => $brigade->id,
            'phone'           => $request->input('phone'),
            'description'     => $request->input('description') ?? '',
            'scheduled_at'    => $scheduledAt,
            'priority'        => 'normal',
        ], $creator);
        return response()->json([
            'status' => 'created', 'id' => $ticket->id,
            'number' => $ticket->number, 'scheduled_at' => $scheduledAt,
        ], 201);
    }

    // Синхронизация нелайвовых территорий из старой системы
    public function storeLegacy(Request $request): JsonResponse
    {
        // Проверка IP
        $allowedIps = array_filter(array_map('trim', explode(',', config('services.sync.allowed_ips', ''))));
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Проверка токена
        if ($request->bearerToken() !== config('services.sync.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sectorId    = (int) $request->input('sector_id');
        $territoryId = self::SECTOR_MAP[$sectorId] ?? null;

        if (!$territoryId || in_array($territoryId, self::LIVE_IDS)) {
            return response()->json(['status' => 'skipped', 'reason' => 'live or unknown sector']);
        }

        // Адрес: найти или создать
        $street   = trim($request->input('quarter_title', ''));
        $building = trim($request->input('house_num', ''));
        $address  = Address::where('street', $street)->where('building', $building)->first();
        if (!$address) {
            $address = Address::create([
                'territory_id' => $territoryId,
                'city'         => self::CITY_MAP[$sectorId] ?? 'Донецк',
                'street'       => $street,
                'building'     => $building,
            ]);
        }

        // Статус
        if ($request->boolean('is_canceled')) {
            $statusSlug = 'cancelled';
        } elseif ($request->input('act_number') || $request->input('executor_comment')) {
            $statusSlug = 'closed';
        } elseif ($request->boolean('is_postponed')) {
            $statusSlug = 'postponed';
        } else {
            $statusSlug = 'new';
        }
        $statusId = TicketStatus::where('slug', $statusSlug)->value('id')
                 ?? TicketStatus::where('slug', 'new')->value('id');

        $oldNumber = 'old-' . $request->input('old_id');
        $closedAt  = in_array($statusSlug, ['closed', 'cancelled'])
                   ? $request->input('updated_at') : null;

        // Обновление существующей заявки
        $existing = Ticket::where('number', $oldNumber)->first();
        if ($existing) {
            $existing->update([
                'status_id'   => $statusId,
                'act_number'  => $request->input('act_number') ?: null,
                'close_notes' => $request->input('executor_comment') ?: null,
                'closed_at'   => $closedAt,
            ]);
            return response()->json(['status' => 'updated']);
        }

        // Тип заявки
        $typeName = $request->input('type_name', '');
        $typeId   = TicketType::where('name', $typeName)->value('id');

        // Тип услуги
        $serviceTypeId = self::NETWORK_MAP[$request->input('network', 'Интернет')] ?? 1;

        // Исполнитель/создатель
        $creatorLogin = $request->input('creator_login', '');
        $creator      = User::where('login', $creatorLogin)->first() ?? User::find(1);

        // Плановое время
        $execDate   = $request->input('execution_date');
        $timePeriod = $request->input('time_period');
        $execTime   = $request->input('execution_time');
        $timeStr    = $execTime ?: ($timePeriod === 'AM' ? '09:00' : '13:00');
        $scheduledAt = $execDate ? $execDate . ' ' . $timeStr . ':00' : null;

        Ticket::insert([
            'number'          => $oldNumber,
            'address_id'      => $address->id,
            'apartment'       => $request->input('apartment') ?: null,
            'type_id'         => $typeId,
            'service_type_id' => $serviceTypeId,
            'status_id'       => $statusId,
            'phone'           => $request->input('phone') ?: null,
            'description'     => $request->input('description') ?? '',
            'act_number'      => $request->input('act_number') ?: null,
            'close_notes'     => $request->input('executor_comment') ?: null,
            'scheduled_at'    => $scheduledAt,
            'created_by'      => $creator?->id ?? 1,
            'closed_at'       => $closedAt,
            'created_at'      => $request->input('created_at'),
            'updated_at'      => $request->input('updated_at'),
        ]);

        return response()->json(['status' => 'created'], 201);
    }

    private function findNextSlot(int $brigadeId, string $date): string
    {
        $step      = (int) SystemSetting::get('schedule_step_minutes', 30);
        $workStart = SystemSetting::get('work_hours_start', '09:00');
        $workEnd   = SystemSetting::get('work_hours_end', '17:00');
        [$sh, $sm] = array_map('intval', explode(':', $workStart));
        [$eh, $em] = array_map('intval', explode(':', $workEnd));
        $startMins = $sh * 60 + $sm;
        $endMins   = $eh * 60 + $em;
        $occupied  = Ticket::whereDate('scheduled_at', $date)
            ->where('brigade_id', $brigadeId)->whereNotNull('scheduled_at')
            ->pluck('scheduled_at')
            ->mapWithKeys(fn($dt) => [Carbon::parse($dt)->format('H:i') => true]);
        for ($m = $startMins; $m <= $endMins; $m += $step) {
            $slot = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            if (!$occupied->has($slot)) {
                return $date . ' ' . $slot . ':00';
            }
        }
        $overflow = $endMins + $step;
        return $date . ' ' . sprintf('%02d:%02d:00', intdiv($overflow, 60), $overflow % 60);
    }
}