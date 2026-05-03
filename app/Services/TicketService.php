<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, TicketAttachment, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function create(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            $newStatus = TicketStatus::where('slug', 'new')->firstOrFail();

            $ticket = Ticket::create([
                'number'     => Ticket::generateNumber(),
                'status_id'  => $newStatus->id,
                'created_by' => $creator->id,
                ...$data,
            ]);

            // history пишет Observer через created()
            return $ticket->load(['address', 'type', 'status', 'brigade', 'creator']);
        });
    }

    public function updateStatus(Ticket $ticket, string $slug, User $user, ?string $comment = null): Ticket
    {
        $newStatus = TicketStatus::where('slug', $slug)->firstOrFail();

        // Авторизуем пользователя чтобы Observer знал кто менял
        auth()->setUser($user);

        $updates = ['status_id' => $newStatus->id];

        match ($slug) {
            'in_progress'         => $updates['started_at'] = now(),
            'paused'              => $updates['paused_at']  = now(),
            'closed', 'cancelled' => $updates['closed_at']  = now(),
            default               => null,
        };

        if ($comment) {
            $updates['close_notes'] = $comment;
        }

        // Observer автоматически залогирует изменение status_id
        $ticket->update($updates);

        return $ticket->fresh(['status']);
    }

    public function assign(Ticket $ticket, ?int $brigadeId, ?int $userId, User $by): Ticket
    {
        auth()->setUser($by);

        // Запоминаем старое имя ДО обновления
        $oldName = $ticket->brigade?->name;

        // Observer залогирует изменение brigade_id
        $ticket->update([
            'brigade_id'  => $brigadeId,
            'assigned_to' => $userId,
        ]);

        // Загружаем новую бригаду ПОСЛЕ обновления
        $ticket->load('brigade');
        $newName = $ticket->brigade?->name;

        // Дополнительно пишем понятное имя (Observer пишет ID, мы — имя)
        $ticket->history()->create([
            'user_id'   => $by->id,
            'action'    => 'assigned',
            'field'     => 'brigade',
            'old_value' => $oldName,
            'new_value' => $newName,
        ]);

        return $ticket->fresh(['brigade', 'assignee']);
    }

    public function storeAttachment(Ticket $ticket, UploadedFile $file, User $user, string $context = 'attachment', ?int $commentId = null): TicketAttachment
    {
        $path = $file->store("tickets/{$ticket->id}/attachments", 'public');

        return $ticket->attachments()->create([
            'comment_id'    => $commentId,
            'user_id'       => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'context'       => $context,
        ]);
    }
}
