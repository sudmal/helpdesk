<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketStatus};
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Базовый запрос — монтажник видит только свои
        $base = Ticket::query()->when(
            $user->isTechnician(),
            fn($q) => $q->where(function ($sub) use ($user) {
                $sub->where('assigned_to', $user->id)
                    ->orWhereHas('brigade.members', fn($m) => $m->where('user_id', $user->id));
            })
        );

        $openStatus   = TicketStatus::where('is_final', false)->pluck('id');
        $closedToday  = TicketStatus::where('is_final', true)->pluck('id');

        $stats = [
            'open'          => (clone $base)->whereIn('status_id', $openStatus)->count(),
            'scheduled_today' => (clone $base)->whereDate('scheduled_at', today())->count(),
            'closed_today'  => (clone $base)->whereIn('status_id', $closedToday)
                                             ->whereDate('closed_at', today())->count(),
            'urgent'        => (clone $base)->whereIn('priority', ['high', 'urgent'])
                                             ->whereIn('status_id', $openStatus)->count(),
        ];

        $recent = (clone $base)
            ->with(['address', 'type', 'status', 'brigade'])
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats'  => $stats,
            'recent' => $recent,
        ]);
    }
}
