<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Act;
use App\Models\Brigade;
use App\Models\ConnectionRequest;
use App\Models\Material;
use App\Models\Territory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectionRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user         = $request->user();
        $territories  = $this->getUserTerritories($user);
        $territoryIds = $territories->pluck('id');

        $query = ConnectionRequest::with(['territory', 'serviceType', 'creator', 'assignee', 'act'])
            ->whereIn('territory_id', $territoryIds)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'scheduled', 'rejected'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'closed')
                         ->where('updated_at', '>=', now()->subDays(2));
                  });
            })
            ->latest();

        if ($request->filled('territory_id')) {
            $query->where('territory_id', $request->territory_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('phone', 'like', $s)
                  ->orWhere('address_string', 'like', $s);
            });
        }

        $perPage = min((int)($request->per_page ?? 50), 100);
        $page    = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data'         => $page->map(fn($r) => $this->formatOne($r))->values(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'territories'  => $territories->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'synced_at'    => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'materials', 'act']);
        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
            'address_string'  => 'required|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'territory_id'    => 'required|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        $cr = ConnectionRequest::create($data);
        $cr->load(['territory', 'serviceType', 'creator', 'assignee']);

        return response()->json($this->formatOne($cr), 201);
    }

    public function update(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:100',
            'phone'           => 'sometimes|string|max:30',
            'address_string'  => 'sometimes|string|max:255',
            'description'     => 'nullable|string|max:2000',
            'territory_id'    => 'sometimes|exists:territories,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'status'          => 'sometimes|in:pending,scheduled,rejected',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string|max:2000',
        ]);

        if (isset($data['status'])) {
            $data['needs_callback'] = in_array($data['status'], ['scheduled', 'rejected']);
        }

        $connectionRequest->update($data);
        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'materials']);

        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    /**
     * Закрытие с материалами создаёт полноценный Акт (Act/ActMaterial/ActHistory),
     * как и веб-версия (см. ConnectionRequestController::close, память
     * project-acts-feature) — раньше здесь писался только плоский текстовый
     * act_number, из-за чего заявки на подключение, закрытые с телефона,
     * не попадали в workflow согласования Бригадир→ПЭО/Логистика→Абонотдел.
     */
    public function close(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $request->validate([
            'notes'                    => 'nullable|string|max:2000',
            'materials'                => 'nullable|array',
            'materials.*.material_id'  => 'required_with:materials.*|integer|exists:materials,id',
            'materials.*.quantity'     => 'required_with:materials.*|numeric|min:0.01',
        ]);

        if (!empty($request->materials) && !$connectionRequest->service_type_id) {
            return response()->json([
                'message' => 'У заявки не указан участок (тип услуги) — укажите его перед закрытием с материалами.',
                'errors'  => ['service_type_id' => ['У заявки не указан участок (тип услуги).']],
            ], 422);
        }

        $actNumber = null;

        DB::transaction(function () use ($connectionRequest, $request, &$actNumber) {
            $connectionRequest->update([
                'status'         => 'closed',
                'notes'          => $request->notes,
                'needs_callback' => false,
            ]);

            if (!empty($request->materials)) {
                $actNumber = Act::generateNumberForConnectionRequest($connectionRequest);
                $act = Act::create([
                    'connection_request_id' => $connectionRequest->id,
                    'number'                => $actNumber,
                    'type'                  => 'regular',
                    'status'                => 'pending_foreman',
                    'created_by'            => $request->user()->id,
                ]);

                foreach ($request->materials as $item) {
                    $material = Material::find($item['material_id']);
                    if (!$material) continue;
                    $act->materials()->create([
                        'material_id'   => $material->id,
                        'material_name' => $material->name,
                        'material_code' => $material->code,
                        'material_unit' => $material->unit,
                        'price_at_time' => $material->price,
                        'quantity'      => $item['quantity'],
                        'created_by'    => $request->user()->id,
                    ]);
                }

                $act->history()->create([
                    'user_id' => $request->user()->id,
                    'action'  => 'created',
                ]);
            }
        });

        $connectionRequest->load(['territory', 'serviceType', 'creator', 'assignee', 'act']);

        return response()->json($this->formatOne($connectionRequest));
    }

    public function markCalled(ConnectionRequest $connectionRequest): JsonResponse
    {
        $connectionRequest->update(['needs_callback' => false]);
        return response()->json(['message' => 'Отмечено: прозвонили']);
    }

    public function destroy(ConnectionRequest $connectionRequest): JsonResponse
    {
        $connectionRequest->delete();
        return response()->json(['message' => 'Заявка удалена']);
    }

    private function formatOne(ConnectionRequest $r, bool $withMaterials = false): array
    {
        $data = [
            'id'             => $r->id,
            'name'           => $r->name,
            'phone'          => $r->phone,
            'address_string' => $r->address_string,
            'description'    => $r->description,
            'status'         => $r->status,
            'scheduled_at'   => $r->scheduled_at?->toIso8601String(),
            'notes'          => $r->notes,
            // act_number оставлен для обратной совместимости со старыми сборками
            // приложения — заполняется из старой плоской колонки, если она есть
            // (легаси-заявки), иначе из номера полноценного Акта (см. `act` ниже).
            'act_number'     => $r->act_number ?: $r->act?->number,
            'act'            => $r->relationLoaded('act') && $r->act ? [
                'id'                   => $r->act->id,
                'number'               => $r->act->number,
                'status'               => $r->act->status,
                'materials_changed_at' => $r->act->materials_changed_at?->toIso8601String(),
            ] : null,
            'needs_callback' => (bool) $r->needs_callback,
            'territory'      => $r->territory ? ['id' => $r->territory->id, 'name' => $r->territory->name] : null,
            'service_type'   => $r->serviceType ? [
                'id'    => $r->serviceType->id,
                'name'  => $r->serviceType->name,
                'color' => $r->serviceType->color,
            ] : null,
            'creator'        => $r->creator?->name,
            'assigned_to'    => $r->assigned_to,
            'assignee'       => $r->assignee ? ['id' => $r->assignee->id, 'name' => $r->assignee->name] : null,
            'created_at'     => $r->created_at->toIso8601String(),
            'updated_at'     => $r->updated_at->toIso8601String(),
        ];

        if ($withMaterials) {
            $data['materials'] = ($r->relationLoaded('materials') ? $r->materials : collect())
                ->map(fn($m) => [
                    'id'            => $m->id,
                    'material_id'   => $m->material_id,
                    'name'          => $m->material_name,
                    'code'          => $m->material_code,
                    'unit'          => $m->material_unit,
                    'price_at_time' => (float) $m->price_at_time,
                    'quantity'      => (float) $m->quantity,
                    'total'         => round((float)$m->price_at_time * (float)$m->quantity, 2),
                ])->values()->all();
        }

        return $data;
    }

    private function getUserTerritories($user)
    {
        if ($user->hasPermission('*') || $user->hasPermission('settings.*')) {
            return Territory::orderBy('sort_order')->orderBy('name')->get();
        }
        $ids = collect();
        $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
        if ($brigadeIds->isNotEmpty()) {
            $ids = $ids->merge(
                Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
            );
        }
        $ids = $ids->merge($user->territories()->pluck('territories.id'))->unique();
        if ($ids->isNotEmpty()) {
            return Territory::whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->get();
        }
        return Territory::orderBy('sort_order')->orderBy('name')->get();
    }
}
