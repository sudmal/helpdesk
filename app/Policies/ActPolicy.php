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
     * Автор акта видит его всегда (2026-08-12) — иначе не может даже открыть
     * акт, который сам создал вне своей бригады, чтобы дойти до утверждения
     * (см. foremanReview ниже).
     */
    public function view(User $user, Act $act): bool
    {
        if ($user->isAdmin() || $user->isHeadSupport() || $user->isOperator()) return true;
        if (!$this->viewAny($user)) return false;
        if ($act->created_by === $user->id) return true;

        return $this->scopeMatch($user, $act);
    }

    /**
     * Бригадир: approve из pending_foreman — ТОЛЬКО бригадир своей бригады
     * (несёт материальную ответственность) или главный админ (подстраховка на
     * случай отсутствия бригадира — отпуск/болезнь). head_support/operator
     * права на утверждение НЕ имеют (2026-07-15, уточнение пользователя —
     * раньше был общий admin/head_support/operator-байпас на все действия).
     *
     * Автор акта, если он сам бригадир, может утвердить акт без привязки к
     * бригаде заявки (2026-08-12) — кейс: бригадир закрыл заявку чужой/
     * бесхозной бригады (например, подменял отсутствующего монтажника), и
     * scopeMatch по бригаде заявки не проходит, хотя утверждать явно есть кому.
     */
    public function foremanReview(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_foreman') return false;
        if ($user->isAdmin()) return true;
        if (!$user->isForeman()) return false;
        if (!$user->hasPermission('acts.foreman_review')) return false;

        if ($act->created_by === $user->id) return true;

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

    /**
     * Абонотдел: собственная "виза", независимая от ПЭО/Логистики и от
     * финальной отправки в архив (complete ниже) — ставится в любом порядке
     * относительно них, как и processPeo/processLogistics.
     */
    public function processSubscriberDept(User $user, Act $act): bool
    {
        if (!in_array($act->status, ['approved', 'processing'])) return false;
        if ($act->subscriber_dept_processed_at !== null) return false;
        if (!$user->isSubscriberDept()) return false;
        if (!$user->hasPermission('acts.process_subscriber_dept')) return false;

        return $this->scopeMatch($user, $act);
    }

    /**
     * Абонотдел: отправка в архив — только когда проведены все требуемые для
     * этого типа стороны, включая собственную визу Абонотдела выше
     * (status уходит в pending_subscriber_dept только после этого,
     * см. ActController::recomputeAfterProcessing()).
     */
    public function complete(User $user, Act $act): bool
    {
        if ($act->status !== 'pending_subscriber_dept') return false;
        if (!$user->isSubscriberDept()) return false;
        if (!$user->hasPermission('acts.complete')) return false;

        return $this->scopeMatch($user, $act);
    }

    /**
     * Редактирование состава акта (добавить/удалить/изменить материал).
     *
     * Окно правки расширено 2026-08-04 (живой кейс: бригадир забыл добавить
     * RJ45 и уже утвердил акт):
     * - **admin** — в любом статусе, КРОМЕ `completed` (акт уже в архиве —
     *   единственное железное ограничение, дальше меняться не должно).
     *   Прямая просьба пользователя — только admin имеет такое широкое окно.
     * - **бригадир своей бригады** — от `pending_foreman` ДО первого
     *   подтверждения любой из трёх последующих сторон (ПЭО/Логистика/
     *   Абонотдел). Как только `status` уходит из `approved` в `processing`
     *   (а `processing` по определению означает, что кто-то уже обработал —
     *   см. `recomputeAfterProcessing()`), бригадир теряет доступ. До
     *   2026-08-04 бригадир мог редактировать только в `pending_foreman`;
     *   расширение — прямая просьба пользователя ("иногда нужно править в
     *   акте даже после утверждения").
     *
     * До 2026-08-03 бригадир не мог редактировать акт, который создал сам
     * (закрыл заявку лично, например подменяя недоступного монтажника) —
     * так решил пользователь 2026-07-15 ("для своих заявок бригадир ничего
     * не должен, только утвердить", см. память project-acts-feature).
     * Ограничение снято 2026-08-03 по ТЗ от Android-агента (через
     * API_MOBILE.md): на практике мешало бригадиру исправить собственную
     * ошибку в составе материалов — оставалось только утвердить как есть.
     *
     * Автор акта (2026-08-12) — та же логика, что и в view()/foremanReview():
     * если бригадир закрыл заявку вне своей бригады (бесхозная/чужая
     * бригада), scopeMatch не проходит, но он всё равно должен мочь
     * поправить состав материалов в акте, который сам создал.
     */
    public function editMaterials(User $user, Act $act): bool
    {
        if ($act->status === 'completed') return false;

        if ($user->isAdmin()) return true;

        if (!$user->isForeman()) return false;
        if (!in_array($act->status, ['pending_foreman', 'approved'], true)) return false;

        if ($act->created_by === $user->id) return true;

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
