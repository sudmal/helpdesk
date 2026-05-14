<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, Territory, User};
use Illuminate\Support\Facades\DB;
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
            'technicians' => (function () {
                    $map = DB::table('brigade_user')->pluck('brigade_id', 'user_id')->toArray();
                    return User::whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman']))
                        ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'in_brigade_id' => $map[$u->id] ?? null]);
                })(),
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

        if (!empty($data['member_ids'])) {
            $taken = \DB::table('brigade_user')
                ->join('brigades', 'brigade_user.brigade_id', '=', 'brigades.id')
                ->join('users', 'brigade_user.user_id', '=', 'users.id')
                ->whereIn('brigade_user.user_id', $data['member_ids'])
                ->select('users.name', 'brigades.name as brigade_name')
                ->get();
            if ($taken->isNotEmpty()) {
                $brigade->delete();
                $msg = $taken->map(fn($r) => "{$r->name} ({$r->brigade_name})")->join(', ');
                return back()->withErrors(['member_ids' => "Уже в другой бригаде: {$msg}"]);
            }
        }

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

        // Нельзя убрать бригадира без назначения нового
        if ($brigade->foreman_id && empty($data['foreman_id'])) {
            return back()->withErrors(['foreman_id' => 'Нельзя убрать бригадира — сначала назначьте нового']);
        }

        // Участники не могут быть в другой бригаде
        if (!empty($data['member_ids'])) {
            $taken = \DB::table('brigade_user')
                ->join('brigades', 'brigade_user.brigade_id', '=', 'brigades.id')
                ->join('users', 'brigade_user.user_id', '=', 'users.id')
                ->whereIn('brigade_user.user_id', $data['member_ids'])
                ->where('brigade_user.brigade_id', '!=', $brigade->id)
                ->select('users.name', 'brigades.name as brigade_name')
                ->get();
            if ($taken->isNotEmpty()) {
                $msg = $taken->map(fn($r) => "{$r->name} ({$r->brigade_name})")->join(', ');
                return back()->withErrors(['member_ids' => "Уже в другой бригаде: {$msg}"]);
            }
        }

        $brigade->update([
            'name'       => $data['name'],
            'foreman_id' => $data['foreman_id'] ?? null,
        ]);

        $brigade->territories()->sync($data['territory_ids'] ?? []);
        $brigade->members()->sync($data['member_ids'] ?? []);

        return back()->with('success', 'Бригада обновлена');
    }

    public function show(Brigade $brigade)
    {
        $user = auth()->user();
        if (!$user->canManageSettings() && $brigade->foreman_id !== $user->id) {
            abort(403);
        }
        $brigade->load(['foreman', 'territories', 'members.role']);
        $technicians = User::whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman']))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role_id'])
            ->map(function ($u) use ($brigade) {
                $inOtherBrigade = \DB::table('brigade_user')
                    ->where('user_id', $u->id)
                    ->where('brigade_id', '!=', $brigade->id)
                    ->exists();
                return ['id' => $u->id, 'name' => $u->name, 'in_other_brigade' => $inOtherBrigade];
            });
        return \Inertia\Inertia::render('Brigades/Show', [
            'brigade'     => $brigade,
            'canManage'   => $user->canManageSettings(),
            'technicians' => $technicians,
        ]);
    }

    public function updateMembers(Request $request, Brigade $brigade)
    {
        $user = auth()->user();
        if (!$user->canManageSettings() && $brigade->foreman_id !== $user->id) {
            abort(403);
        }
        $data = $request->validate([
            'member_ids'   => 'array',
            'member_ids.*' => 'exists:users,id',
        ]);
        if (!empty($data['member_ids'])) {
            $taken = \DB::table('brigade_user')
                ->join('brigades', 'brigade_user.brigade_id', '=', 'brigades.id')
                ->join('users', 'brigade_user.user_id', '=', 'users.id')
                ->whereIn('brigade_user.user_id', $data['member_ids'])
                ->where('brigade_user.brigade_id', '!=', $brigade->id)
                ->select('users.name', 'brigades.name as brigade_name')
                ->get();
            if ($taken->isNotEmpty()) {
                $msg = $taken->map(fn($r) => "{$r->name} ({$r->brigade_name})")->join(', ');
                return back()->withErrors(['member_ids' => "Уже в другой бригаде: {$msg}"]);
            }
        }
        // Бригадир всегда остаётся в составе
        $ids = $data['member_ids'] ?? [];
        if ($brigade->foreman_id && !in_array($brigade->foreman_id, $ids)) {
            $ids[] = $brigade->foreman_id;
        }
        $brigade->members()->sync($ids);
        return back()->with('success', 'Состав бригады обновлён');
    }

    public function updateMinWorkers(Request $request, Brigade $brigade)
    {
        $user = auth()->user();
        if (!$user->canManageSettings() && $brigade->foreman_id !== $user->id) {
            abort(403);
        }
        $request->validate(['min_workers' => 'required|integer|min:1|max:50']);
        $brigade->update(['min_workers' => $request->min_workers]);
        return response()->json(['ok' => true]);
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
