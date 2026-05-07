<?php

namespace App\Services;

use App\Models\{Ticket, TicketStatus, TicketAttachment, TicketHistory, User, ServiceType, SystemSetting};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketService
{
    /**
     * Проверка занятости временного слота
     * Возвращает null если свободно, строку с описанием конфликта если занято
     */
    public function checkSlotConflict(array $data): ?string
    {
        if (empty($data['scheduled_at']) || empty($data['brigade_id'])) {
            return null;
        }

        $step      = (int) SystemSetting::get('schedule_step_minutes', 30);
        $slotStart = Carbon::parse($data['scheduled_at']);
        $slotEnd   = $slotStart->copy()->addMinutes($step);

        $conflict = Ticket::with('address')
            ->where('brigade_id', $data['brigade_id'])
            ->whereNotNull('scheduled_at')
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->whereBetween('scheduled_at', [
                $slotStart->copy()->subMinutes($step - 1),
                $slotEnd->copy()->subMinute(),
            ])
            ->when(
                !empty($data['service_type_id']),
                fn($q) => $q->where('service_type_id', $data['service_type_id'])
            )
            ->when(
                !empty($data['id']),
                fn($q) => $q->where('id', '!=', $data['id'])
            )
            ->first();

        if ($conflict) {
            $time = Carbon::parse($conflict->scheduled_at)->format('H:i');
            $addr = $conflict->address
                ? $conflict->address->street . ' ' . $conflict->address->building
                : '—';
            return "Слот {$slotStart->format('H:i')} занят: заявка {$conflict->number} ({$addr} в {$time})";
        }

        return null;
    }

    public function create(array $data, User $creator): Ticket
    {
        return DB::transaction(function () use ($data, $creator) {
            $newStatus = TicketStatus::where('slug', 'new')->firstOrFail();

            // Определяем префикс номера по участку
            $serviceTypeName = !empty($data['service_type_id'])
                ? ServiceType::find($data['service_type_id'])?->name
                : null;

            $ticket = Ticket::create([
                'number'     => Ticket::generateNumber($serviceTypeName),
                'status_id'  => $newStatus->id,
                'created_by' => $creator->id,
                ...$data,
            ]);

            return $ticket->load(['address', 'type', 'status', 'brigade', 'creator']);
        });
    }

    public function updateStatus(Ticket $ticket, string $slug, User $user, ?string $comment = null): Ticket
    {
        $newStatus = TicketStatus::where('slug', $slug)->firstOrFail();

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

        $ticket->update($updates);

        return $ticket->fresh(['status']);
    }

    public function assign(Ticket $ticket, ?int $brigadeId, ?int $userId, User $by): Ticket
    {
        auth()->setUser($by);

        $oldName = $ticket->brigade?->name;

        $ticket->update([
            'brigade_id'  => $brigadeId,
            'assigned_to' => $userId,
        ]);

        $ticket->load('brigade');
        $newName = $ticket->brigade?->name;

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
