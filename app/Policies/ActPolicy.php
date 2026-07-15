<?php

namespace App\Policies;

use App\Models\{Act, User};

class ActPolicy
{
    /** Список актов */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('acts.view')
            || $user->isForeman() || $user->isTechnician()
            || $user->isPeo() || $user->isLogistics() || $user->isSubscriberDept();
    }

    /** Просмотр одного акта — с учётом территории заявки, к которой привязан акт */
    public function view(User $user, Act $act): bool
    {
        if (!$this->viewAny($user)) return false;

        return $this->inScope($user, $act);
    }

    /** Бригадир: approve/return из pending_foreman */
    public function foremanReview(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_foreman') return false;
        if (!$user->hasPermission('acts.foreman_review')) return false;

        return $this->inScope($user, $act);
    }

    /** ПЭО: только акты type=regular, ждущие ПЭО */
    public function processPeo(User $user, Act $act): bool
    {
        if ($act->type !== 'regular') return false;
        if (!in_array($act->status, ['approved', 'processing'])) return false;
        if ($act->peo_processed_at !== null) return false;
        if (!$user->hasPermission('acts.process_peo')) return false;

        return $this->inScope($user, $act);
    }

    /** Логистика: любой тип, независимо от ПЭО */
    public function processLogistics(User $user, Act $act): bool
    {
        if (!in_array($act->status, ['approved', 'processing'])) return false;
        if ($act->logistics_processed_at !== null) return false;
        if (!$user->hasPermission('acts.process_logistics')) return false;

        return $this->inScope($user, $act);
    }

    /** Абонотдел: только когда все требуемые для типа стороны провели */
    public function complete(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_subscriber_dept') return false;
        if (!$user->hasPermission('acts.complete')) return false;

        return $this->inScope($user, $act);
    }

    /**
     * Территориальный скоуп: бригадир/монтажник — по территориям своей
     * бригады (как в TicketPolicy), ПЭО/Логистика/Абонотдел — по territories()
     * пользователя напрямую (у них нет бригад). Остальные роли — без ограничений.
     */
    private function inScope(User $user, Act $act): bool
    {
        if ($user->isAdmin() || $user->isHeadSupport() || $user->isOperator()) return true;

        $territoryId = $act->ticket?->address?->territory_id;
        if (!$territoryId) return false;

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = $user->brigades->pluck('id');
            if ($brigadeIds->isNotEmpty()) {
                return \App\Models\Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))
                    ->pluck('id')->contains($territoryId);
            }
            return $user->territories->pluck('id')->contains($territoryId);
        }

        if ($user->isPeo() || $user->isLogistics() || $user->isSubscriberDept()) {
            return $user->territories->pluck('id')->contains($territoryId);
        }

        return false;
    }
}
