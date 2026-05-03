<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, Brigade};
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Calendar/Index', [
            'brigades' => Brigade::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * JSON-эндпоинт для FullCalendar
     * GET /calendar/events?start=...&end=...&brigade_id=...
     */
    public function events(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start'      => 'required|date',
            'end'        => 'required|date',
            'brigade_id' => 'nullable|exists:brigades,id',
        ]);

        $user = auth()->user();

        $tickets = Ticket::with(['address', 'type', 'status', 'brigade'])
            ->whereBetween('scheduled_at', [$request->start, $request->end])
            ->when($request->brigade_id, fn($q) => $q->where('brigade_id', $request->brigade_id))
            ->when(
                $user->isTechnician(),
                fn($q) => $q->where(function ($sub) use ($user) {
                    $sub->where('assigned_to', $user->id)
                        ->orWhereHas('brigade.members', fn($m) => $m->where('user_id', $user->id));
                })
            )
            ->get();

        // Форматируем для FullCalendar
        $events = $tickets->map(fn($ticket) => [
            'id'              => $ticket->id,
            'title'           => $this->eventTitle($ticket),
            'start'           => $ticket->scheduled_at->toIso8601String(),
            'backgroundColor' => $ticket->status->color,
            'borderColor'     => $ticket->type->color,
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'ticketNumber' => $ticket->number,
                'address'      => $ticket->address?->full_address,
                'type'         => $ticket->type->name,
                'status'       => $ticket->status->name,
                'statusSlug'   => $ticket->status->slug,
                'brigade'      => $ticket->brigade?->name,
                'priority'     => $ticket->priority,
                'url'          => route('tickets.show', $ticket->id),
            ],
        ]);

        return response()->json($events);
    }

    private function eventTitle(Ticket $ticket): string
    {
        $time    = $ticket->scheduled_at->format('H:i');
        $address = $ticket->address?->street . ' ' . $ticket->address?->building;
        return "{$time} {$address}";
    }
}
