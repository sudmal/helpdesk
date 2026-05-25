<?php

namespace App\Http\Controllers;

use App\Models\Brigade;
use App\Models\ConnectionRequest;
use App\Models\Material;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConnectionRequestController extends Controller
{
    public function index(Request $request)
    {
        $user            = $request->user();
        $userTerritories = $this->getUserTerritories($user);

        $territory = $request->get('territory');
        if (!$territory && $userTerritories->isNotEmpty()) {
            $territory = $userTerritories->first()->id;
        }

        $query = ConnectionRequest::with(['assignee', 'creator', 'materials', 'territory'])
            ->when($territory, fn($q) => $q->where('territory_id', $territory))
            ->latest();

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

        return Inertia::render('ConnectionRequests/Index', [
            'requests'          => $query->paginate(50)->withQueryString(),
            'filters'           => $request->only(['status', 'search', 'territory']),
            'territories'       => $userTerritories->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'selectedTerritory' => $territory ? (int)$territory : null,
            'users'             => User::orderBy('name')->get(['id', 'name']),
            'materialsCatalog'  => Material::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'unit', 'price']),
        ]);
    }

    public function store(Request $request)
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

        ConnectionRequest::create($data);

        return back()->with('success', 'Заявка на подключение создана');
    }

    public function update(Request $request, ConnectionRequest $connectionRequest)
    {
        $data = $request->validate([
            'status'       => 'required|in:pending,scheduled,rejected,closed',
            'scheduled_at' => 'nullable|date',
            'notes'        => 'nullable|string|max:2000',
            'assigned_to'  => 'nullable|exists:users,id',
        ]);

        $connectionRequest->update($data);

        return back()->with('success', 'Заявка обновлена');
    }

    public function close(Request $request, ConnectionRequest $connectionRequest)
    {
        $request->validate([
            'notes'                   => 'nullable|string|max:2000',
            'act_number'              => 'nullable|string|max:50',
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

        return back()->with('success', 'Подключение выполнено');
    }

    public function destroy(ConnectionRequest $connectionRequest)
    {
        $connectionRequest->delete();

        return back()->with('success', 'Заявка удалена');
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
