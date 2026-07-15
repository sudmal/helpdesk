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

    /** Бригадир: approve из pending_foreman */
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
     * Редактирование состава акта (добавить/удалить/изменить материал) —
     * ТОЛЬКО бригадир (буквально роль foreman, без обхода для admin/head_support —
     * так решил пользователь), и только пока акт ещё не утверждён им же.
     */
    public function editMaterials(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_foreman') return false;
        if (!$user->isForeman()) return false;

        return $this->inScope($user, $act);
    }

    /** Монтажник подтверждает, что увидел правки бригадира в составе акта */
    public function acknowledge(User $user, Act $act): bool
    {
        if ($act->materials_changed_at === null) return false;

        return $act->created_by === $user->id;
    }

    /**
     * Территориальный скоуп: бригадир/монтажник — строго по бригаде заявки
     * (ticket.brigade_id), к которой привязан акт (2026-07-15: раньше было по
     * пересечению территорий бригад, сузили по прямому требованию пользователя
     * — "бригадир видит акты только своей бригады"). ПЭО/Логистика/Абонотдел —
     * по territories() пользователя напрямую (у них нет бригад). Остальные
     * роли — без ограничений.
     */
    private function inScope(User $user, Act $act): bool
    {
        if ($user->isAdmin() || $user->isHeadSupport() || $user->isOperator()) return true;

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = $user->brigades->pluck('id');
            if ($brigadeIds->isNotEmpty()) {
                return $brigadeIds->contains($act->ticket?->brigade_id);
            }
            $territoryId = $act->ticket?->address?->territory_id;
            return $territoryId && $user->territories->pluck('id')->contains($territoryId);
        }

        if ($user->isPeo() || $user->isLogistics() || $user->isSubscriberDept()) {
            $territoryId = $act->ticket?->address?->territory_id;
            return $territoryId && $user->territories->pluck('id')->contains($territoryId);
        }

        return false;
    }
}
