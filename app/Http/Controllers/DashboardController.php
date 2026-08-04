<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketStatus, Territory, ServiceType, Material, ConnectionRequest, ServiceRequest};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user        = auth()->user();
        $date        = $request->get('date', today()->toDateString());
        $territory   = $request->get('territory');
        $serviceType = $request->get('service_type');
        $sort        = in_array($request->get('sort'), ['scheduled_at','created_at','number','status_id','priority'])
                       ? $request->get('sort') : 'scheduled_at';
        $sortDir     = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        $userTerritories = $this->getUserTerritories($user);
        $serviceTypes    = ServiceType::active()->orderBy('sort_order')->get(['id', 'name', 'color']);

        if (!$request->has('service_type') && $serviceTypes->isNotEmpty()) {
            $serviceType = $serviceTypes->first()->id;
        }
        // ?territory= из URL проверяется на принадлежность зоне видимости —
        // раньше принимался как есть (2026-08-04).
        if ($territory && !$userTerritories->pluck('id')->contains((int) $territory)) {
            $territory = null;
        }
        if (!$territory && $userTerritories->isNotEmpty()) {
            $territory = $userTerritories->first()->id;
        }

        // Если $territory не выбран (userTerritories пуст — у пользователя
        // нет ни одной территории), раньше when() просто пропускал фильтр —
        // показывались заявки со всех территорий подряд. Теперь в этом
        // случае явно фильтруем по (пустому) userTerritories -> 0 заявок
        // (тот же принцип, что и везде: 2026-08-04).
        $scoped = Ticket::query()
            ->when($territory,  fn($q) => $q->whereHas('address', fn($a) => $a->where('territory_id', $territory)))
            ->when(!$territory, fn($q) => $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $userTerritories->pluck('id'))))
            ->when($serviceType, fn($q) => $q->where('service_type_id', $serviceType));

        $openIds          = TicketStatus::where('is_final', false)->pluck('id');
        $overdueThreshold = Carbon::today();

        $onlyOpen = $request->boolean('only_open', false);

        $todayTickets = (clone $scoped)
            ->with(['address', 'type', 'serviceType', 'status', 'brigade', 'act.materials'])
            ->withCount('attachments')
            ->whereDate('scheduled_at', $date)
            ->when($onlyOpen, fn($q) => $q->whereIn('status_id', $openIds))
            ->orderBy($sort, $sortDir)
            ->get()
            ->each->append('days_overdue');

        $overdue = (clone $scoped)
            ->with(['address', 'type', 'serviceType', 'status'])
            ->withCount('attachments')
            ->whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $overdueThreshold)
            ->orderBy('scheduled_at')
            ->get()
            ->each->append('days_overdue');

        // Счётчики для вкладок территорий
        $territoryStats = \DB::table('tickets')
            ->join('addresses', 'tickets.address_id', '=', 'addresses.id')
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->whereIn('addresses.territory_id', $userTerritories->pluck('id'))
            ->whereDate('tickets.scheduled_at', $date)
            ->when($serviceType, fn($q) => $q->where('tickets.service_type_id', $serviceType))
            ->whereNull('tickets.deleted_at')
            ->selectRaw('addresses.territory_id,
                SUM(ticket_statuses.is_final = 0) as open_count,
                SUM(ticket_statuses.is_final = 1) as closed_count')
            ->groupBy('addresses.territory_id')
            ->get()
            ->keyBy('territory_id');

        $serviceTypeHasOpen = Ticket::query()
            ->when($territory, fn($q) => $q->whereHas('address', fn($a) => $a->where('territory_id', $territory)))
            ->whereHas('address', fn($q) => $q->whereIn('territory_id', $userTerritories->pluck('id')))
            ->whereDate('scheduled_at', $date)
            ->whereIn('status_id', $openIds)
            ->selectRaw('service_type_id, COUNT(*) as cnt')
            ->groupBy('service_type_id')
            ->pluck('cnt', 'service_type_id');

        $overdueByTerritory = \DB::table('tickets')
            ->join('addresses', 'tickets.address_id', '=', 'addresses.id')
            ->whereIn('addresses.territory_id', $userTerritories->pluck('id'))
            ->when($serviceType, fn($q) => $q->where('tickets.service_type_id', $serviceType))
            ->whereIn('tickets.status_id', $openIds)
            ->whereNotNull('tickets.scheduled_at')
            ->where('tickets.scheduled_at', '<', $overdueThreshold)
            ->whereNull('tickets.deleted_at')
            ->selectRaw('addresses.territory_id, COUNT(*) as cnt')
            ->groupBy('addresses.territory_id')
            ->get()
            ->keyBy('territory_id');

        $territoriesWithCounts = $userTerritories->map(fn($t) => [
            'id'            => $t->id,
            'name'          => $t->name,
            'open_count'    => (int)($territoryStats[$t->id]->open_count   ?? 0),
            'closed_count'  => (int)($territoryStats[$t->id]->closed_count ?? 0),
            'overdue_count' => (int)($overdueByTerritory[$t->id]->cnt      ?? 0),
        ]);

        $serviceTypesWithCounts = $serviceTypes->map(fn($s) => [
            'id'       => $s->id,
            'name'     => $s->name,
            'color'    => $s->color,
            'has_open' => ($serviceTypeHasOpen[$s->id] ?? 0) > 0,
        ]);

        // Подключения, назначенные на выбранную дату -- отдельный блок "Подключения
        // на сегодня" (не смешиваем с тикетной таблицей выше: у Ticket статус --
        // FK на ticket_statuses с is_final/счётчиками по территориям, у
        // ConnectionRequest статус -- простая строка, другая модель, см. память
        // проекта, project-connection-feasibility).
        $scheduledConnections = ConnectionRequest::with(['territory', 'serviceType'])
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', $date)
            ->when($territory,  fn($q) => $q->where('territory_id', $territory))
            ->when(!$territory, fn($q) => $q->whereIn('territory_id', $userTerritories->pluck('id')))
            ->orderBy('scheduled_at')
            ->get(['id', 'name', 'phone', 'address_string', 'scheduled_at', 'territory_id', 'service_type_id']);

        return Inertia::render('Dashboard/Index', [
            'todayTickets'      => $todayTickets,
            'overdue'           => $overdue,
            'scheduledConnections' => $scheduledConnections,
            'territories'       => $territoriesWithCounts,
            'serviceTypes'      => $serviceTypesWithCounts,
            'materialsCatalog'  => Material::active()->orderBy('sort_order')->orderBy('name')->get(['id','code','name','unit','price']),
            'selectedDate'      => $date,
            'selectedTerritory' => $territory ? (int)$territory : null,
            'serviceType'       => $serviceType ? (int)$serviceType : null,
            'sort'              => $sort,
            'sortDir'           => $sortDir,
            'onlyOpen'          => $onlyOpen,
            // Бейдж тоже скоупится по территории (2026-08-04) — раньше показывал
            // общее число заявок на подключение по всей компании, не по своим
            // территориям, расходясь с уже отфильтрованным списком подключений.
            'pendingConnectionsCount'        => ConnectionRequest::where('status', 'pending')
                ->when(!$user->isAdmin(), fn($q) => $q->whereIn('territory_id', $userTerritories->pluck('id')))
                ->count(),
            'pendingServiceRequestsCount' => ServiceRequest::where('status', 'pending')->count(),
        ]);
    }

    public function newTicketsSince(Request $request): \Illuminate\Http\JsonResponse
    {
        $user        = auth()->user();
        $since       = $request->integer('since', 0);
        $territory   = $request->get('territory');
        $serviceType = $request->get('service_type');

        $userTerritories = $this->getUserTerritories($user);

        $tickets = Ticket::with(['address', 'type'])
            ->when(!$user->isAdmin(), fn($q) =>
                $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $userTerritories->pluck('id'))))
            ->when($territory,   fn($q) => $q->whereHas('address', fn($a) => $a->where('territory_id', $territory)))
            ->when($serviceType, fn($q) => $q->where('service_type_id', $serviceType))
            ->where('created_at', '>', Carbon::createFromTimestamp(max($since, 0)))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json($tickets->map(fn($t) => [
            'id'      => $t->id,
            'number'  => $t->number,
            'address' => collect([
                $t->address?->street,
                $t->address?->building ? 'д.' . $t->address->building : null,
            ])->filter()->join(' '),
        ]));
    }

    // Единая формула видимости по территориям (2026-08-04) — territoryScopeIds()
    // для всех, кроме admin. Раньше при пустом итоговом списке территорий
    // код молча откатывался на "показать вообще все территории" (та же
    // дыра, что уже находили и чинили в Заявках/Актах/Адресах 2026-08-04) —
    // теперь пустой список так и остаётся пустым (0 территорий = 0 данных).
    private function getUserTerritories($user)
    {
        if ($user->isAdmin()) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $ids = $user->territoryScopeIds();
        return Territory::whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->get();
    }
}
