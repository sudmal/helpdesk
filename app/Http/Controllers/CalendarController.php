<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, Brigade, Territory, ServiceType};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = \App\Models\Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            $territoriesQuery = $brigadeIds->isNotEmpty()
                ? Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))
                : Territory::whereIn('id', $user->territories()->pluck('territories.id'));
        } else {
            $territoriesQuery = Territory::orderBy('sort_order')->orderBy('name');
        }

        return Inertia::render('Calendar/Index', [
            'brigades'     => Brigade::orderBy('name')->get(['id', 'name']),
            'territories'  => $territoriesQuery->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'serviceTypes' => ServiceType::active()->orderBy('sort_order')->get(['id', 'name', 'color']),
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

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = \App\Models\Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            $userTerritories = $brigadeIds->isNotEmpty()
                ? \App\Models\Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
                : $user->territories()->pluck('territories.id');
        } else {
            $userTerritories = collect();
        }

        $tickets = Ticket::with(['address', 'type', 'status', 'brigade'])
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->when($userTerritories->isNotEmpty(), fn($q) =>
                $q->whereHas('address', fn($a) => $a->whereIn('territory_id', $userTerritories)))
            ->when($request->filled('brigade_id'),
                fn($q) => $q->where('brigade_id', $request->brigade_id))
            ->when($request->filled('territory_id'),
                fn($q) => $q->whereHas('address',
                    fn($a) => $a->where('territory_id', $request->territory_id)))
            ->when($request->filled('service_type_id'),
                fn($q) => $q->where('service_type_id', $request->service_type_id))
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
                ],
            ];
        });

        return response()->json($events);
    }

    private function eventTitle(Ticket $ticket): string
    {
        $apt      = $ticket->apartment ?? $ticket->address?->apartment;
        $street   = $ticket->address?->street ?? '';
        $building = $ticket->address?->building ? ' ' . $ticket->address->building : '';
        $aptStr   = $apt ? ' кв.' . $apt : '';
        return $street . $building . $aptStr ?: $ticket->number;
    }
}
