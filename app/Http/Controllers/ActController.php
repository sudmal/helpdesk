<?php

namespace App\Http\Controllers;

use App\Models\{Act, Brigade, Territory};
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ActController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Act::class);
        $user = auth()->user();

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            $userTerritories = $brigadeIds->isNotEmpty()
                ? Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
                : $user->territories()->pluck('territories.id');
        } elseif ($user->isPeo() || $user->isLogistics() || $user->isSubscriberDept()) {
            $userTerritories = $user->territories()->pluck('territories.id');
        } else {
            $userTerritories = collect(); // admin/head_support/operator — без ограничений
        }

        $acts = Act::with([
                'ticket:id,number,address_id,type_id,service_type_id',
                'ticket.address:id,city,street,building,apartment,territory_id',
                'materials',
                'creator:id,name',
                'foremanReviewer:id,name',
                'peoProcessor:id,name',
                'logisticsProcessor:id,name',
                'subscriberDeptCompleter:id,name',
            ])
            ->when($userTerritories->isNotEmpty(), fn($q) =>
                $q->whereHas('ticket.address', fn($a) => $a->whereIn('territory_id', $userTerritories))
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Acts/Index', [
            'acts'    => $acts,
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function show(Act $act): Response
    {
        $this->authorize('view', $act);

        $act->load([
            'ticket.address', 'ticket.type', 'ticket.serviceType',
            'materials.material',
            'history.user',
            'creator', 'foremanReviewer', 'peoProcessor', 'logisticsProcessor', 'subscriberDeptCompleter',
        ]);

        return Inertia::render('Acts/Show', ['act' => $act]);
    }

    public function approve(Act $act): RedirectResponse
    {
        $this->authorize('foremanReview', $act);
        $user = auth()->user();

        $act->update([
            'status'              => 'approved',
            'foreman_reviewed_by' => $user->id,
            'foreman_reviewed_at' => now(),
        ]);
        $this->logHistory($act, $user->id, 'approved');

        return back()->with('success', 'Акт утверждён');
    }

    public function returnAct(Request $request, Act $act): RedirectResponse
    {
        $this->authorize('foremanReview', $act);
        $request->validate(['comment' => 'required|string|max:2000']);
        $user = auth()->user();

        $act->update([
            'status'                 => 'returned',
            'foreman_reviewed_by'    => $user->id,
            'foreman_reviewed_at'    => now(),
            'foreman_return_comment' => $request->comment,
        ]);
        $this->logHistory($act, $user->id, 'returned', 'foreman_return_comment', null, $request->comment);

        return back()->with('success', 'Акт возвращён на доработку');
    }

    public function processPeo(Act $act): RedirectResponse
    {
        $this->authorize('processPeo', $act);
        $user = auth()->user();

        $act->peo_processed_by = $user->id;
        $act->peo_processed_at = now();
        $this->recomputeAfterProcessing($act);
        $act->save();

        $this->logHistory($act, $user->id, 'peo_processed');

        return back()->with('success', 'Отмечено как обработано ПЭО');
    }

    public function processLogistics(Act $act): RedirectResponse
    {
        $this->authorize('processLogistics', $act);
        $user = auth()->user();

        $act->logistics_processed_by = $user->id;
        $act->logistics_processed_at = now();
        $this->recomputeAfterProcessing($act);
        $act->save();

        $this->logHistory($act, $user->id, 'logistics_processed');

        return back()->with('success', 'Отмечено как обработано Логистикой');
    }

    public function complete(Act $act): RedirectResponse
    {
        $this->authorize('complete', $act);
        $user = auth()->user();

        $act->update([
            'status'                       => 'completed',
            'subscriber_dept_completed_by' => $user->id,
            'subscriber_dept_completed_at' => now(),
        ]);
        $this->logHistory($act, $user->id, 'completed');

        return back()->with('success', 'Акт завершён и отправлен в архив');
    }

    /**
     * Требуемые для гейта Абонотдела стороны зависят от типа акта:
     * regular — ПЭО + Логистика, repair — только Логистика (см. память project-acts-feature).
     * Статус становится pending_subscriber_dept, как только обработаны ВСЕ требуемые
     * стороны — для repair это происходит сразу после Логистики, минуя processing.
     */
    private function recomputeAfterProcessing(Act $act): void
    {
        $required = $act->type === 'regular' ? ['peo', 'logistics'] : ['logistics'];

        $done = collect($required)->every(function (string $side) use ($act) {
            $field = $side === 'peo' ? 'peo_processed_at' : 'logistics_processed_at';
            return $act->$field !== null;
        });

        $act->status = $done ? 'pending_subscriber_dept' : 'processing';
    }

    private function logHistory(Act $act, int $userId, string $action, ?string $field = null, ?string $old = null, ?string $new = null): void
    {
        $act->history()->create([
            'user_id'   => $userId,
            'action'    => $action,
            'field'     => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
