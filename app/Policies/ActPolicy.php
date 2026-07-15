<?php

namespace App\Policies;

use App\Models\{Act, User};

class ActPolicy
{
    /** Список актов — видит любой участник цепочки + admin/head_support/operator (оверсайт) */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isHeadSupport() || $user->isOperator()
            || $user->hasPermission('acts.view')
            || $user->isForeman() || $user->isTechnician()
            || $user->isPeo() || $user->isLogistics() || $user->isSubscriberDept();
    }

    /**
     * Просмотр одного акта — admin/head_support/operator видят всё без
     * territory/brigade-скоупа (оверсайт), остальные — по своему участку цепочки.
     */
    public function view(User $user, Act $act): bool
    {
        if ($user->isAdmin() || $user->isHeadSupport() || $user->isOperator()) return true;
        if (!$this->viewAny($user)) return false;

        return $this->scopeMatch($user, $act);
    }

    /**
     * Бригадир: approve из pending_foreman — ТОЛЬКО бригадир своей бригады
     * (несёт материальную ответственность) или главный админ (подстраховка на
     * случай отсутствия бригадира — отпуск/болезнь). head_support/operator
     * права на утверждение НЕ имеют (2026-07-15, уточнение пользователя —
     * раньше был общий admin/head_support/operator-байпас на все действия).
     */
    public function foremanReview(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_foreman') return false;
        if ($user->isAdmin()) return true;
        if (!$user->isForeman()) return false;
        if (!$user->hasPermission('acts.foreman_review')) return false;

        return $this->scopeMatch($user, $act);
    }

    /**
     * ПЭО: только сотрудники ПЭО (роль peo), без исключений для admin/
     * head_support — так же строго, как и остальные звенья цепочки после
     * бригадира (2026-07-15, уточнение пользователя).
     */
    public function processPeo(User $user, Act $act): bool
    {
        if ($act->type !== 'regular') return false;
        if (!in_array($act->status, ['approved', 'processing'])) return false;
        if ($act->peo_processed_at !== null) return false;
        if (!$user->isPeo()) return false;
        if (!$user->hasPermission('acts.process_peo')) return false;

        return $this->scopeMatch($user, $act);
    }

    /** Логистика: только сотрудники Логистики (роль logistics), любой тип акта */
    public function processLogistics(User $user, Act $act): bool
    {
        if (!in_array($act->status, ['approved', 'processing'])) return false;
        if ($act->logistics_processed_at !== null) return false;
        if (!$user->isLogistics()) return false;
        if (!$user->hasPermission('acts.process_logistics')) return false;

        return $this->scopeMatch($user, $act);
    }

    /** Абонотдел: только работники Абонотдела (роль subscriber_dept) */
    public function complete(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_subscriber_dept') return false;
        if (!$user->isSubscriberDept()) return false;
        if (!$user->hasPermission('acts.complete')) return false;

        return $this->scopeMatch($user, $act);
    }

    /**
     * Редактирование состава акта (добавить/удалить/изменить материал) —
     * ТОЛЬКО бригадир (буквально роль foreman, без обхода для admin/head_support —
     * так решил пользователь), и только пока акт ещё не утверждён им же.
     * Для акта, который бригадир создал сам (сам закрыл заявку), редактирование
     * недоступно вовсе — "для своих заявок бригадир ничего не должен, только
     * утвердить" (2026-07-15, см. память project-acts-feature).
     */
    public function editMaterials(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_foreman') return false;
        if (!$user->isForeman()) return false;
        if ($act->created_by === $user->id) return false;

        return $this->scopeMatch($user, $act);
    }

    /** Монтажник подтверждает, что увидел правки бригадира в составе акта */
    public function acknowledge(User $user, Act $act): bool
    {
        if ($act->materials_changed_at === null) return false;

        return $act->created_by === $user->id;
    }

    /**
     * Территориальный скоуп участников цепочки (без admin/head_support/operator —
     * они проверяются отдельно в каждом методе выше, у кого есть оверсайт/обход).
     * Бригадир/монтажник — строго по бригаде (ticket.brigade_id ИЛИ
     * connection_request.brigade_id — акт теперь может относиться к любому из
     * двух, см. память project-acts-feature, "Заявки на подключение").
     * ПЭО/Логистика/Абонотдел — по territories() пользователя напрямую.
     */
    private function scopeMatch(User $user, Act $act): bool
    {
        $brigadeId   = $act->ticket?->brigade_id ?? $act->connectionRequest?->brigade_id;
        $territoryId = $act->ticket?->address?->territory_id ?? $act->connectionRequest?->territory_id;

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = $user->brigades->pluck('id');
            if ($brigadeIds->isNotEmpty()) {
                return $brigadeIds->contains($brigadeId);
            }
            return $territoryId && $user->territories->pluck('id')->contains($territoryId);
        }

        if ($user->isPeo() || $user->isLogistics() || $user->isSubscriberDept()) {
            return $territoryId && $user->territories->pluck('id')->contains($territoryId);
        }

        return false;
    }
}
