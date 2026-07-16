<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\ActMaterial;
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
