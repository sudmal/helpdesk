<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $user        = $request->user();
        $territories = $this->getUserTerritories($user);
        $territoryIds = $territories->pluck('id');

        $query = ConnectionRequest::with(['territory', 'creator'])
            ->whereIn('territory_id', $territoryIds)
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
        $connectionRequest->load(['territory', 'creator', 'materials']);
        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'phone'          => 'required|string|max:30',
            'address_string' => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'territory_id'   => 'required|exists:territories,id',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        $cr = ConnectionRequest::create($data);
        $cr->load(['territory', 'creator']);

        return response()->json($this->formatOne($cr), 201);
    }

    public function update(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'phone'          => 'sometimes|string|max:30',
            'address_string' => 'sometimes|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'territory_id'   => 'sometimes|exists:territories,id',
            'status'         => 'sometimes|in:pending,scheduled,rejected',
            'scheduled_at'   => 'nullable|date',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $connectionRequest->update($data);
        $connectionRequest->load(['territory', 'creator', 'materials']);

        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
    }

    public function close(Request $request, ConnectionRequest $connectionRequest): JsonResponse
    {
        $request->validate([
            'notes'                   => 'nullable|string|max:2000',
            'act_number'              => [
                'nullable', 'string', 'max:50',
                function ($attribute, $value, $fail) use ($request) {
                    if (!empty($request->input('materials')) && (empty($value) || mb_strlen(trim($value)) < 5)) {
                        $fail('При использовании материалов обязателен номер акта (минимум 5 символов).');
                    }
                },
            ],
            'materials'               => 'nullable|array',
            'materials.*.material_id' => 'required_with:materials.*|integer|exists:materials,id',
            'materials.*.quantity'    => 'required_with:materials.*|numeric|min:0.01',
        ]);

        $actNumber = filled($request->act_number) ? $request->act_number : 'б/а';

        DB::transaction(function () use ($connectionRequest, $actNumber, $request) {
            $connectionRequest->update([
                'status'     => 'closed',
                'act_number' => $actNumber,
                'notes'      => $request->notes,
            ]);

            if (!empty($request->materials)) {
                $connectionRequest->materials()->delete();
                foreach ($request->materials as $item) {
                    $material = Material::find($item['material_id']);
                    if (!$material) continue;
                    $connectionRequest->materials()->create([
                        'material_id'   => $material->id,
                        'material_name' => $material->name,
                        'material_code' => $material->code,
                        'material_unit' => $material->unit,
                        'price_at_time' => $material->price,
                        'quantity'      => $item['quantity'],
                        'created_by'    => $request->user()->id,
                    ]);
                }
            }
        });

        $connectionRequest->load(['territory', 'creator', 'materials']);

        return response()->json($this->formatOne($connectionRequest, withMaterials: true));
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
            'act_number'     => $r->act_number,
            'territory'      => $r->territory ? ['id' => $r->territory->id, 'name' => $r->territory->name] : null,
            'creator'        => $r->creator?->name,
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
