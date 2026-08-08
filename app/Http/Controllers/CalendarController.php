<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, Brigade, Territory, ServiceType, SystemSetting, TicketStatus, ConnectionRequest};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $user   = auth()->user();
        $tIds   = $this->getUserTerritoryIds($user);
        $tQuery = $tIds === null
            ? Territory::query()
            : Territory::whereIn('id', $tIds);

        return Inertia::render('Calendar/Index', [
            'brigades'     => Brigade::orderBy('name')->get(['id', 'name']),
            'territories'  => $tQuery->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'serviceTypes' => ServiceType::active()->orderBy('sort_order')->get(['id', 'name', 'color']),
            'workSettings' => [
                'start' => SystemSetting::get('work_hours_start', '09:00'),
                'end'   => SystemSetting::get('work_hours_end', '17:00'),
                'step'  => (int) SystemSetting::get('schedule_step_minutes', 30),
            ],
        ]);
    }

    public function events(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start'           => 'required|date',
            'end'             => 'required|date',
            'brigade_id'      => 'nullable|exists:brigades,id',
            'territory_id'    => 'nullable|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
        ]);

        $user = auth()->user();

        $userTerritories = $this->getUserTerritoryIds($user);

        $tickets = Ticket::with(['address', 'type', 'serviceType', 'status', 'brigade'])
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->when($userTerritories !== null, fn($q) =>
                $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $userTerritories)))
            ->when($request->filled('brigade_id'),
                fn($q) => $q->where('brigade_id', $request->brigade_id))
            ->when($request->filled('territory_id'),
                fn($q) => $q->whereHas('address',
                    fn($a) => $a->where('territory_id', $request->territory_id)))
            ->when($request->filled('service_type_id'),
                fn($q) => $q->where('service_type_id', $request->service_type_id))
            ->when($request->boolean('overdue'), function ($q) {
                $final = TicketStatus::where('is_final', true)->pluck('id');
                $q->whereNotIn('status_id', $final);
            })
            ->get();

        $events = $tickets->map(function ($ticket) {
            $apt = $ticket->apartment ?? $ticket->address?->apartment;
            $fullAddress = collect([
                $ticket->address?->city,
                $ticket->address?->street,
                $ticket->address?->building ? 'д.' . $ticket->address->building : null,
                $apt ? 'кв.' . $apt : null,
            ])->filter()->join(', ');

            return [
                'id'              => $ticket->id,
                'title'           => $this->eventTitle($ticket),
                'start'           => $ticket->scheduled_at->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $ticket->status->color . '30',
                'borderColor'     => $ticket->status->color,
                'textColor'       => '#1f2937',
                'extendedProps'   => [
                    'ticketNumber' => $ticket->number,
                    'address'      => $fullAddress,
                    'type'         => $ticket->type->name,
                    'typeColor'    => $ticket->type->color,
                    'status'       => $ticket->status->name,
                    'statusColor'  => $ticket->status->color,
                    'brigade'      => $ticket->brigade?->name,
                    'scheduled'    => $ticket->scheduled_at->format('d.m.Y H:i'),
                    'description'  => $ticket->description,
                    'phone'        => $ticket->phone,
                    'url'          => route('tickets.show', $ticket->id),
                    'isFinal'      => (bool) $ticket->status->is_final,
                    'isCancelled'  => $ticket->status->slug === 'cancelled',
                    'daysOverdue'  => $ticket->days_overdue,
                ],
            ];
        });

        // Заявки на подключение с назначенной датой -- показываем в общем
        // календаре вместе с обычными заявками (в нужном тайм-слоте), но не
        // заводим под них Ticket. Визуально отличаются постоянным синим
        // цветом (у обычных заявок цвет из статуса) + иконкой 🔌, ссылка ведёт
        // в карточку подключения (?open=), а не в Ticket (см. Dashboard —
        // тот же принцип и тот же цвет, запрос пользователя 2026-08-08).
        // territory_id у ConnectionRequest лежит прямо на записи (не через
        // address, как у Ticket), поэтому фильтр территории проще.
        $connections = ConnectionRequest::with(['brigade', 'serviceType'])
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->when($userTerritories !== null, fn($q) => $q->whereIn('territory_id', $userTerritories))
            ->when($request->filled('brigade_id'), fn($q) => $q->where('brigade_id', $request->brigade_id))
            ->when($request->filled('territory_id'), fn($q) => $q->where('territory_id', $request->territory_id))
            ->when($request->filled('service_type_id'), fn($q) => $q->where('service_type_id', $request->service_type_id))
            ->get();

        $connectionEvents = $connections->map(function ($cr) {
            $type = $cr->serviceType?->name ?? 'Подключение';
            return [
                'id'              => 'cr-' . $cr->id,
                'title'           => '🔌 ' . $type . ' · ' . $cr->address_string,
                'start'           => $cr->scheduled_at->format('Y-m-d\TH:i:s'),
                'backgroundColor' => '#3b82f630',
                'borderColor'     => '#3b82f6',
                'textColor'       => '#1f2937',
                'extendedProps'   => [
                    'ticketNumber' => null,
                    'address'      => $cr->address_string,
                    'type'         => $type,
                    'typeColor'    => $cr->serviceType?->color ?? '#3b82f6',
                    'status'       => 'Подключение',
                    'statusColor'  => '#3b82f6',
                    'brigade'      => $cr->brigade?->name,
                    'scheduled'    => $cr->scheduled_at->format('d.m.Y H:i'),
                    'description'  => $cr->name,
                    'phone'        => $cr->phone,
                    'url'          => route('connection-requests.index', ['open' => $cr->id]),
                    'isFinal'      => false,
                    'isCancelled'  => false,
                    'daysOverdue'  => null,
                    'isConnection' => true,
                ],
            ];
        });

        return response()->json($events->concat($connectionEvents)->values());
    }

    private function eventTitle(Ticket $ticket): string
    {
        $icon     = $this->serviceIcon($ticket->serviceType?->name);
        $type     = $ticket->type?->name ? $ticket->type->name . ' · ' : '';
        $apt      = $ticket->apartment ?? $ticket->address?->apartment;
        $street   = $ticket->address?->street ?? '';
        $building = $ticket->address?->building ? ' ' . $ticket->address->building : '';
        $aptStr   = $apt ? ' кв.' . $apt : '';
        return $icon . $type . ($street . $building . $aptStr ?: $ticket->number);
    }

    // Единая формула видимости по территориям (2026-08-04) — territoryScopeIds()
    // для всех, кроме admin (null = без фильтра вообще). Раньше сюда же
    // пускали любого с hasPermission('settings.*') (сейчас это только
    // head_support) — теперь head_support "видит всё" через данные (все
    // территории проставлены бэкфиллом), как и остальные подобные роли.
    private function getUserTerritoryIds($user): ?array
    {
        if ($user->isAdmin()) return null;
        return $user->territoryScopeIds()->values()->all();
    }
    private function serviceIcon(?string $name): string
    {
        if (!$name) return '';
        $lower = mb_strtolower($name);
        if (str_contains($lower, 'интернет') || str_contains($lower, 'inet')) return '🌐 ';
        if (str_contains($lower, 'ктв') || str_contains($lower, 'ctv'))       return '📺 ';
        if (str_contains($lower, 'волс'))                                      return '🔆 ';
        if (str_contains($lower, 'подключ'))                                  return '🟢 ';
        return '';
    }
}
