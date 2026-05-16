<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $brigadeId = $user->brigades()->first()?->id;

        $base = fn(): Builder => Ticket::with([
                'address', 'type', 'status', 'brigade', 'assignee',
                'comments.author',
            ])
            ->when($brigadeId && !$user->hasPermission('*'), fn($q) => $q->where('brigade_id', $brigadeId));

        $overdue  = $base()->whereDate('scheduled_at', '<', $today)
                           ->whereHas('status', fn($s) => $s->where('is_final', false))
                           ->orderBy('scheduled_at')->get();

        $today_list = $base()->whereDate('scheduled_at', $today)
                             ->orderBy('scheduled_at')->get();

        $new_today  = $base()->whereDate('scheduled_at', $today)
                             ->whereDate('created_at', $today)
                             ->orderByDesc('created_at')->get();

        $tomorrow_list = $base()->whereDate('scheduled_at', $tomorrow)
                                ->orderBy('scheduled_at')->get();

        return response()->json([
            'overdue'   => $this->format($overdue),
            'today'     => $this->format($today_list),
            'new_today' => $this->format($new_today),
            'tomorrow'  => $this->format($tomorrow_list),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->load(['address', 'type', 'status', 'brigade', 'assignee', 'comments.author']);

        return response()->json($this->formatOne($ticket));
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $comment = $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->body,
            'is_internal' => false,
        ]);

        $comment->load('author');

        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'author'     => $comment->author?->name,
            'created_at' => $comment->created_at->toIso8601String(),
        ], 201);
    }

    private function formatOne(Ticket $t): array
    {
        return [
            'id'           => $t->id,
            'number'       => $t->number,
            'scheduled_at' => $t->scheduled_at?->toIso8601String(),
            'description'  => $t->description,
            'phone'        => $t->phone,
            'apartment'    => $t->apartment,
            'close_notes'  => $t->close_notes,
            'address'      => $t->address ? [
                'full'     => $t->address->full_address,
                'street'   => $t->address->street,
                'building' => $t->address->building,
            ] : null,
            'type'    => $t->type?->name,
            'status'  => [
                'name'     => $t->status?->name,
                'is_final' => (bool) $t->status?->is_final,
                'color'    => $t->status?->color,
            ],
            'brigade'  => $t->brigade?->name,
            'assignee' => $t->assignee?->name,
            'comments' => $t->comments->map(fn($c) => [
                'id'         => $c->id,
                'body'       => $c->body,
                'author'     => $c->author?->name,
                'created_at' => $c->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function format($tickets): array
    {
        return $tickets->map(fn(Ticket $t) => $this->formatOne($t))->values()->all();
    }
}