<?php

namespace App\Policies;

use App\Models\{Ticket, User};

class TicketPolicy
{
    /** Список заявок */
    public function viewAny(User $user): bool
    {
        // Монтажник и бригадир всегда видят список (фильтрация по территории — в контроллере)
        if ($user->isTechnician() || $user->isForeman()) return true;

        return $user->hasPermission('tickets.view');
    }

    /** Просмотр одной заявки */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.*') || $user->isAdmin()) return true;

        // Монтажник и бригадир: видят заявки своей бригады или назначенные лично
        if ($user->isTechnician() || $user->isForeman()) {
            if ($ticket->assigned_to === $user->id) return true;
            if ($ticket->brigade_id && $user->brigades->contains('id', $ticket->brigade_id)) return true;
            // Также видят заявки по своим территориям
            $brigadeIds = $user->brigades->pluck('id');
            if ($brigadeIds->isNotEmpty()) {
                $territoryIds = \App\Models\Territory::whereHas('brigades',
                    fn($q) => $q->whereIn('brigades.id', $brigadeIds)
                )->pluck('id');
                return $ticket->address && $territoryIds->contains($ticket->address->territory_id);
            }
            return false;
        }

        return $user->hasPermission('tickets.view');
    }

    /** Создание */
    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.*') || $user->hasPermission('tickets.create');
    }

    /** Редактирование полей */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin() || $user->isHeadSupport() || $user->isOperator()) return true;
        if ($user->isForeman()) return true;
        return false;
    }

    /** Удаление — только Админ */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /** Назначение бригады */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $user->isHeadSupport()
            || $user->isOperator()
            || $user->isForeman();
    }

    /** Взять в работу (монтажник/бригадир) */
    public function start(User $user, Ticket $ticket): bool
    {
        if ($ticket->status->slug !== 'new' && $ticket->status->slug !== 'paused') return false;

        return $user->isForeman()
            || $user->isTechnician()
            || $user->isAdmin();
    }

    /** Приостановить */
    public function pause(User $user, Ticket $ticket): bool
    {
        if ($ticket->status->slug !== 'in_progress') return false;

        return $user->isForeman()
            || $user->isTechnician()
            || $user->isAdmin();
    }

    /** Закрыть */
    public function close(User $user, Ticket $ticket): bool
    {
        if ($ticket->status?->is_final) return false;

        return $user->hasPermission('tickets.close') || $user->isAdmin();
    }

    /** Комментировать */
    public function comment(User $user, Ticket $ticket): bool
    {
        // Все авторизованные пользователи могут оставлять комментарии
        return true;
    }
}
