<?php
namespace App\Observers;

use App\Models\Ticket;
use App\Notifications\NewTicketNotification;

class TicketObserver
{
    public function updated(Ticket $ticket): void
    {
        $user = auth()->user();
        if (!$user) return;

        $watched = ['status_id', 'brigade_id', 'assigned_to', 'type_id', 'scheduled_at', 'priority'];
        $changed = array_intersect(array_keys($ticket->getChanges()), $watched);
        if (empty($changed)) return;

        static $written = [];
        $key = $ticket->id . '_' . implode('_', $changed);
        if (isset($written[$key])) return;
        $written[$key] = true;

        $watchedFields = [
            'status_id'    => 'Статус',
            'brigade_id'   => 'Бригада',
            'assigned_to'  => 'Монтажник',
            'type_id'      => 'Тип',
            'priority'     => 'Приоритет',
            'scheduled_at' => 'Время выезда',
        ];

        foreach ($watchedFields as $field => $label) {
            if (!$ticket->wasChanged($field)) continue;
            $old      = $ticket->getOriginal($field);
            $new      = $ticket->getAttribute($field);
            $oldLabel = $this->resolveLabel($ticket, $field, $old);
            $newLabel = $this->resolveLabel($ticket, $field, $new);

            $ticket->history()->create([
                'user_id'   => $user->id,
                'action'    => 'field_changed',
                'field'     => $label,
                'old_value' => $oldLabel,
                'new_value' => $newLabel,
            ]);
        }
        // Уведомление об отмене заявки на сегодня
        if ($ticket->wasChanged('status_id')) {
            $newStatus = \App\Models\TicketStatus::find($ticket->status_id);
            $isToday   = $ticket->scheduled_at &&
                         \Carbon\Carbon::parse($ticket->scheduled_at)->isToday();

            if ($newStatus?->slug === 'cancelled' && $isToday) {
                dispatch(function () use ($ticket) {
                    $fresh    = $ticket->fresh(['address', 'serviceType']);
                    $telegram = app(\App\Services\TelegramService::class);
                    $telegram->broadcast(
                        $telegram->formatCancelledTicket($fresh, $ticket->close_notes),
                        $fresh->address?->territory_id
                    );
                })->afterResponse();
            }
        }
    }

    public function created(Ticket $ticket): void
    {
        $user = auth()->user();
        if (!$user) return;

        $ticket->history()->create([
            'user_id'   => $user->id,
            'action'    => 'created',
            'field'     => null,
            'old_value' => null,
            'new_value' => null,
        ]);

        // Уведомления (асинхронно чтобы не тормозить создание)
        dispatch(function () use ($ticket) {
            NewTicketNotification::dispatch($ticket->fresh(['address', 'type', 'serviceType']));
        })->afterResponse();
    }

    public function deleted(Ticket $ticket): void
    {
        $user = auth()->user();
        if (!$user) return;
        $ticket->history()->create([
            'user_id' => $user->id,
            'action'  => 'deleted',
        ]);
    }

    private function resolveLabel(Ticket $ticket, string $field, mixed $value): ?string
    {
        if ($value === null) return null;
        return match ($field) {
            'status_id'   => \App\Models\TicketStatus::find($value)?->name ?? $value,
            'brigade_id'  => \App\Models\Brigade::find($value)?->name ?? $value,
            'assigned_to' => \App\Models\User::find($value)?->name ?? $value,
            'type_id'     => \App\Models\TicketType::find($value)?->name ?? $value,
            'priority'    => match ($value) {
                'low'    => 'Низкий',
                'normal' => 'Обычный',
                'high'   => 'Высокий',
                'urgent' => 'Срочный',
                default  => $value,
            },
            default => (string) $value,
        };
    }
}

