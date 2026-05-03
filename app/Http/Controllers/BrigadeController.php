<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, Territory, User};
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrigadeController extends Controller
{
    public function index()
    {
        return Inertia::render('Brigades/Index', [
            'brigades'    => Brigade::with(['foreman', 'territories', 'members'])
                                ->withCount('members')
                                ->orderBy('name')
                                ->get(),
            'territories' => Territory::orderBy('name')->get(['id', 'name']),
            'technicians' => User::whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman']))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'name', 'role_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:brigades,name',
            'foreman_id'    => 'nullable|exists:users,id',
            'territory_ids' => 'array',
            'territory_ids.*' => 'exists:territories,id',
            'member_ids'    => 'array',
            'member_ids.*'  => 'exists:users,id',
        ]);

        $brigade = Brigade::create([
            'name'       => $data['name'],
            'foreman_id' => $data['foreman_id'] ?? null,
        ]);

        if (!empty($data['territory_ids'])) {
            $brigade->territories()->sync($data['territory_ids']);
        }
        if (!empty($data['member_ids'])) {
            $brigade->members()->sync($data['member_ids']);
        }

        return back()->with('success', 'Бригада создана');
    }

    public function update(Request $request, Brigade $brigade)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100|unique:brigades,name,' . $brigade->id,
            'foreman_id'      => 'nullable|exists:users,id',
            'territory_ids'   => 'array',
            'territory_ids.*' => 'exists:territories,id',
            'member_ids'      => 'array',
            'member_ids.*'    => 'exists:users,id',
        ]);

        $brigade->update([
            'name'       => $data['name'],
            'foreman_id' => $data['foreman_id'] ?? null,
        ]);

        $brigade->territories()->sync($data['territory_ids'] ?? []);
        $brigade->members()->sync($data['member_ids'] ?? []);

        return back()->with('success', 'Бригада обновлена');
    }

    public function destroy(Brigade $brigade)
    {
        if ($brigade->tickets()->whereHas('status', fn($q) => $q->where('is_final', false))->exists()) {
            return back()->withErrors(['brigade' => 'Нельзя удалить — есть открытые заявки']);
        }
        $brigade->territories()->detach();
        $brigade->members()->detach();
        $brigade->delete();
        return back()->with('success', 'Бригада удалена');
    }
}
