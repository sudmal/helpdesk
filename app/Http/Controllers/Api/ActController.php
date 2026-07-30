<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\ActMaterial;
use App\Models\Brigade;
use App\Services\ActService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мобильная (полевая) часть workflow "Акты": утверждение бригадиром,
 * правка состава материалов бригадиром, подтверждение монтажником.
 * Звенья ПЭО/Логистика/Абонотдел — только в веб-версии (offline-роли,
 * работают в офисе), см. память project-acts-feature.
 */
class ActController extends Controller
{
    public function __construct(private ActService $actService) {}

    /**
     * Очередь актов текущего пользователя — раньше акт был виден только
     * через ссылку из конкретной заявки/подключения, до которых нужно было
     * дойти по одной; здесь отдельный список для экрана-меню "Акты".
     * Скоуп — по бригаде (как и в веб-версии для бригадира/монтажника),
     * ПЭО/Логистика/Абонотдел сюда не заходят вообще (см. класс-докблок).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Act::class);
        $user = auth()->user();

        $brigadeScopeIds = collect();
        $territoryIds    = collect();
        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeScopeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            if ($brigadeScopeIds->isEmpty()) {
                $territoryIds = $user->territories()->pluck('territories.id');
            }
        }

        $acts = Act::query()
            ->where('acts.status', '!=', 'completed')
            ->with(['ticket.address', 'connectionRequest', 'creator'])
            ->when($brigadeScopeIds->isNotEmpty(), fn($q) => $q->where(function ($qq) use ($brigadeScopeIds) {
                $qq->whereHas('ticket', fn($t) => $t->whereIn('brigade_id', $brigadeScopeIds))
                   ->orWhereHas('connectionRequest', fn($c) => $c->whereIn('brigade_id', $brigadeScopeIds));
            }))
            ->when($territoryIds->isNotEmpty(), fn($q) => $q->where(function ($qq) use ($territoryIds) {
                $qq->whereHas('ticket.address', fn($a) => $a->whereIn('territory_id', $territoryIds))
                   ->orWhereHas('connectionRequest', fn($c) => $c->whereIn('territory_id', $territoryIds));
            }))
            ->orderByRaw("CASE WHEN acts.status = 'pending_foreman' THEN 0 WHEN acts.materials_changed_at IS NOT NULL THEN 1 ELSE 2 END")
            ->orderByDesc('acts.created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data'      => $acts->map(fn(Act $act) => $this->formatSummary($act, $user))->values(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, Act $act): JsonResponse
    {
        $this->authorize('view', $act);

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()));
    }

    public function approve(Request $request, Act $act): JsonResponse
    {
        $this->authorize('foremanReview', $act);
        $this->actService->approve($act, $request->user());

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()));
    }

    public function addMaterial(Request $request, Act $act): JsonResponse
    {
        $this->authorize('editMaterials', $act);
        $request->validate([
            'material_id' => 'required|integer|exists:materials,id',
            'quantity'    => 'required|numeric|min:0.001',
        ]);

        $this->actService->addMaterial($act, $request->user(), (int) $request->material_id, (float) $request->quantity);

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()), 201);
    }

    public function updateMaterial(Request $request, Act $act, ActMaterial $material): JsonResponse
    {
        $this->authorize('editMaterials', $act);
        abort_unless($material->act_id === $act->id, 404);
        $request->validate(['quantity' => 'required|numeric|min:0.001']);

        $this->actService->updateMaterial($act, $material, $request->user(), (float) $request->quantity);

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()));
    }

    public function removeMaterial(Request $request, Act $act, ActMaterial $material): JsonResponse
    {
        $this->authorize('editMaterials', $act);
        abort_unless($material->act_id === $act->id, 404);

        $this->actService->removeMaterial($act, $material, $request->user());

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()));
    }

    public function acknowledge(Request $request, Act $act): JsonResponse
    {
        $this->authorize('acknowledge', $act);
        $this->actService->acknowledge($act, $request->user());

        $act->load(['materials.material', 'history.user', 'creator', 'foremanReviewer']);

        return response()->json($this->formatOne($act, $request->user()));
    }

    /** Облегчённая версия Act для списка — см. раздел "Акты" в API_MOBILE.md */
    private function formatSummary(Act $act, $user): array
    {
        return [
            'id'                      => $act->id,
            'number'                  => $act->number,
            'type'                    => $act->type,
            'status'                  => $act->status,
            'ticket_id'               => $act->ticket_id,
            'ticket_number'           => $act->ticket?->number,
            'connection_request_id'   => $act->connection_request_id,
            'connection_request_name' => $act->connectionRequest?->name,
            'address'                 => $act->ticket
                ? ($act->ticket->address ? collect([
                    $act->ticket->address->city,
                    $act->ticket->address->street,
                    $act->ticket->address->building,
                  ])->filter()->implode(', ') : null)
                : $act->connectionRequest?->address_string,
            'creator'                 => $act->creator?->name,
            'created_at'              => $act->created_at->toIso8601String(),
            'foreman_reviewed_at'     => $act->foreman_reviewed_at?->toIso8601String(),
            'materials_changed_at'    => $act->materials_changed_at?->toIso8601String(),
            'can' => [
                'foreman_review' => $user->can('foremanReview', $act),
                'edit_materials' => $user->can('editMaterials', $act),
                'acknowledge'    => $user->can('acknowledge', $act),
            ],
        ];
    }

    private function formatOne(Act $act, $user): array
    {
        return [
            'id'                    => $act->id,
            'number'                => $act->number,
            'type'                  => $act->type,
            'status'                => $act->status,
            'ticket_id'             => $act->ticket_id,
            'connection_request_id' => $act->connection_request_id,
            'created_at'            => $act->created_at->toIso8601String(),
            'creator'               => $act->creator?->name,
            'foreman_reviewed_at'   => $act->foreman_reviewed_at?->toIso8601String(),
            'foreman_reviewed_by'   => $act->foremanReviewer?->name,
            'materials_changed_at'  => $act->materials_changed_at?->toIso8601String(),
            // Акция — только у актов заявок на подключение (connection_request_id
            // не null), у тикетных всегда null. См. память project-acts-feature,
            // "Акции по подключениям".
            'promotion_name'        => $act->promotion_name,
            'promotion_price'       => $act->promotion_price !== null ? (float) $act->promotion_price : null,
            'materials' => $act->materials->map(fn(ActMaterial $m) => [
                'id'            => $m->id,
                'material_id'   => $m->material_id,
                'name'          => $m->material_name,
                'code'          => $m->material_code,
                'unit'          => $m->material_unit,
                'price_at_time' => (float) $m->price_at_time,
                'quantity'      => (float) $m->quantity,
                'total'         => $m->total,
            ])->values()->all(),
            'history' => $act->history->map(fn($h) => [
                'id'               => $h->id,
                'user'             => $h->user?->name,
                'action'           => $h->action,
                'field'            => $h->field,
                'old_value'        => $h->old_value,
                'new_value'        => $h->new_value,
                'acknowledged_at'  => $h->acknowledged_at?->toIso8601String(),
                'created_at'       => $h->created_at->toIso8601String(),
            ])->values()->all(),
            'can' => [
                'foreman_review'  => $user->can('foremanReview', $act),
                'edit_materials'  => $user->can('editMaterials', $act),
                'acknowledge'     => $user->can('acknowledge', $act),
            ],
        ];
    }
}
