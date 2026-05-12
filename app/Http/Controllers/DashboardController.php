<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketStatus, Territory, Brigade, ServiceType, Material};
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
        if (!$territory && $userTerritories->isNotEmpty()) {
            $territory = $userTerritories->first()->id;
        }

        $scoped = Ticket::query()
            ->when($territory,   fn($q) => $q->whereHas('address', fn($a) => $a->where('territory_id', $territory)))
            ->when($serviceType, fn($q) => $q->where('service_type_id', $serviceType));

        $openIds          = TicketStatus::where('is_final', false)->pluck('id');
        $overdueThreshold = Carbon::today();

        $todayTickets = (clone $scoped)
            ->with(['address', 'type', 'serviceType', 'status', 'brigade'])
            ->whereDate('scheduled_at', $date)
            ->orderBy($sort, $sortDir)
            ->get();

        $overdue = (clone $scoped)
            ->with(['address', 'type', 'serviceType', 'status'])
            ->whereIn('status_id', $openIds)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $overdueThreshold)
            ->orderBy('scheduled_at')
            ->get();

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

        $territoriesWithCounts = $userTerritories->map(fn($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'open_count'   => (int)($territoryStats[$t->id]->open_count   ?? 0),
            'closed_count' => (int)($territoryStats[$t->id]->closed_count ?? 0),
        ]);

        $serviceTypesWithCounts = $serviceTypes->map(fn($s) => [
            'id'       => $s->id,
            'name'     => $s->name,
            'color'    => $s->color,
            'has_open' => ($serviceTypeHasOpen[$s->id] ?? 0) > 0,
        ]);

        return Inertia::render('Dashboard/Index', [
            'todayTickets'      => $todayTickets,
            'overdue'           => $overdue,
            'territories'       => $territoriesWithCounts,
            'serviceTypes'      => $serviceTypesWithCounts,
            'materialsCatalog'  => Material::active()->orderBy('sort_order')->orderBy('name')->get(['id','code','name','unit','price']),
            'selectedDate'      => $date,
            'selectedTerritory' => $territory ? (int)$territory : null,
            'serviceType'       => $serviceType ? (int)$serviceType : null,
            'sort'              => $sort,
            'sortDir'           => $sortDir,
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
            ->when($userTerritories->isNotEmpty(), fn($q) =>
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

    private function getUserTerritories($user)
    {
        if ($user->hasPermission('*') || $user->hasPermission('settings.*')) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
        if ($brigadeIds->isNotEmpty()) {
            return Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->orderBy('name')->get();
        }
        $ids = $user->territories()->pluck('territories.id');
        if ($ids->isNotEmpty()) {
            return Territory::whereIn('id', $ids)->orderBy('name')->get();
        }
        return Territory::orderBy('sort_order')->orderBy('name')->get();
    }
}