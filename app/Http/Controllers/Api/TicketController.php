<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $today    = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $brigadeId = $user->brigades()->first()?->id;

        $base = fn(): Builder => Ticket::with([
                'address', 'type', 'serviceType', 'status', 'brigade', 'assignee',
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
        $ticket->load(['address', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'comments.author']);

        return response()->json($this->formatOne($ticket));
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'body'          => 'nullable|string|max:2000',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        if (!$request->filled('body') && !$request->hasFile('attachments')) {
            return response()->json(['error' => 'body or attachments required'], 422);
        }

        $comment = $ticket->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->input('body', ''),
            'is_internal' => false,
        ]);

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $att = $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'comment', $comment->id);
                $attachments[] = [
                    'id'            => $att->id,
                    'original_name' => $att->original_name,
                    'url'           => Storage::url($att->stored_path),
                    'mime_type'     => $att->mime_type,
                    'size'          => $att->size,
                ];
            }
        }

        $comment->load('author');

        return response()->json([
            'id'          => $comment->id,
            'body'        => $comment->body,
            'author'      => $comment->author?->name,
            'created_at'  => $comment->created_at->toIso8601String(),
            'attachments' => $attachments,
        ], 201);
    }

    public function addAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $request->validate([
            'attachments'   => 'required|array|min:1|max:10',
            'attachments.*' => 'required|file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        $stored = [];
        foreach ($request->file('attachments') as $file) {
            $att = $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'attachment');
            $stored[] = [
                'id'            => $att->id,
                'original_name' => $att->original_name,
                'url'           => Storage::url($att->stored_path),
                'mime_type'     => $att->mime_type,
                'size'          => $att->size,
            ];
        }

        return response()->json(['attachments' => $stored], 201);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('close', $ticket);

        $request->validate([
            'close_notes'   => 'nullable|string|max:2000',
            'act_number'    => 'nullable|string|max:50',
            'materials'     => 'nullable|array',
            'materials.*.material_id' => 'required|integer|exists:materials,id',
            'materials.*.quantity'    => 'required|numeric|min:0.01',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf|max:20480',
        ]);

        $actNumber = filled($request->act_number) ? $request->act_number : 'б/а';

        DB::transaction(function () use ($ticket, $actNumber, $request) {
            $ticket->update(['act_number' => $actNumber]);
            $this->ticketService->updateStatus($ticket, 'closed', $request->user(), $request->close_notes);

            if (!empty($request->materials)) {
                $ticket->materials()->delete();
                foreach ($request->materials as $item) {
                    $material = Material::find($item['material_id']);
                    if (!$material) continue;
                    $ticket->materials()->create([
                        'material_id'   => $material->id,
                        'material_name' => $material->name,
                        'material_code' => $material->code,
                        'material_unit' => $material->unit,
                        'price_at_time' => $material->price,
                        'quantity'      => $item['quantity'],
                        'created_by'    => $request->user()->id,
                    ]);
                }
            }
        });

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->ticketService->storeAttachment($ticket, $file, $request->user(), 'attachment');
            }
        }

        $ticket->load(['address', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'comments.author', 'attachments']);

        return response()->json($this->formatOne($ticket));
    }

    public function reschedule(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('postpone', $ticket);

        $request->validate([
            'scheduled_at' => 'required|date|after:today',
            'comment'      => 'nullable|string|max:2000',
        ]);

        $ticket->update(['scheduled_at' => $request->scheduled_at]);
        $this->ticketService->updateStatus($ticket, 'postponed', $request->user(), $request->comment);

        $ticket->load(['address', 'type', 'serviceType', 'status', 'brigade', 'assignee', 'comments.author']);

        return response()->json($this->formatOne($ticket));
    }

    private function formatOne(Ticket $t): array
    {
        return [
            'id'           => $t->id,
            'number'       => $t->number,
            'scheduled_at' => $t->scheduled_at?->toIso8601String(),
            'closed_at'    => $t->closed_at?->toIso8601String(),
            'description'  => $t->description,
            'phone'        => $t->phone,
            'apartment'    => $t->apartment,
            'close_notes'  => $t->close_notes,
            'act_number'   => $t->act_number,
            'address'      => $t->address ? [
                'full'     => $t->address->full_address,
                'street'   => $t->address->street,
                'building' => $t->address->building,
            ] : null,
            'type'    => $t->type?->name,
            'service_type' => $t->serviceType ? [
                'id'    => $t->serviceType->id,
                'name'  => $t->serviceType->name,
                'color' => $t->serviceType->color,
            ] : null,
            'status'  => [
                'name'     => $t->status?->name,
                'is_final' => (bool) $t->status?->is_final,
                'color'    => $t->status?->color,
                'slug'     => $t->status?->slug,
            ],
            'brigade'  => $t->brigade?->name,
            'assignee' => $t->assignee?->name,
            'comments' => $t->comments->map(fn($c) => [
                'id'         => $c->id,
                'body'       => $c->body,
                'author'     => $c->author?->name,
                'created_at' => $c->created_at->toIso8601String(),
            ])->values()->all(),
            'attachments' => ($t->relationLoaded('attachments') ? $t->attachments : collect())->map(fn($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => Storage::url($a->stored_path),
                'mime_type'     => $a->mime_type,
                'size'          => $a->size,
            ])->values()->all(),
        ];
    }

    private function format($tickets): array
    {
        return $tickets->map(fn(Ticket $t) => $this->formatOne($t))->values()->all();
    }
}