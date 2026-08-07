<?php

namespace App\Http\Controllers;

use App\Models\{Brigade, ConnectionRequest, Territory, User};
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
                                ->orderByDesc('is_active')
                                ->orderBy('name')
                                ->get(),
            'territories' => Territory::orderBy('name')->get(['id', 'name']),
            'technicians' => (function () {
                    $bmap = DB::table('brigade_user')
                        ->join('brigades', 'brigade_user.brigade_id', '=', 'brigades.id')
                        ->select('brigade_user.user_id', 'brigades.id as brigade_id', 'brigades.name as brigade_name')
                        ->get()->keyBy('user_id');
                    return User::whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman', 'admin']))
                        ->where('is_active', true)->with('role:id,slug')->orderBy('name')->get(['id', 'name', 'role_id'])
                        ->map(fn($u) => [
                            'id' => $u->id, 'name' => $u->name, 'role' => $u->role?->slug,
                            'in_brigade_id'   => $bmap[$u->id]->brigade_id ?? null,
                            'in_brigade_name' => $bmap[$u->id]->brigade_name ?? null,
                        ]);
                })(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100|unique:brigades,name',
            'foreman_id'    => ['nullable', 'exists:users,id', $this->foremanRoleRule($request)],
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
            'foreman_id'      => ['nullable', 'exists:users,id', $this->foremanRoleRule($request)],
            'territory_ids'   => 'array',
            'territory_ids.*' => 'exists:territories,id',
            'member_ids'      => 'array',
            'member_ids.*'    => 'exists:users,id',
        ]);

        // Нельзя убрать бригадира без назначения нового — только для активной
        // бригады. Неактивную (расформированную, см. toggleActive) можно
        // полностью опустошить: старые заявки хранят brigade_id напрямую и
        // не зависят от текущего состава, а расформировать бригаду без этого
        // послабления было невозможно (запрос пользователя 2026-08-07 —
        // перевод сотрудников распущенной бригады "Спутник - ХБ" в другую).
        if ($brigade->is_active && $brigade->foreman_id && empty($data['foreman_id'])) {
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
        $otherBrigadeNames = DB::table('brigade_user')
            ->join('brigades', 'brigade_user.brigade_id', '=', 'brigades.id')
            ->where('brigade_user.brigade_id', '!=', $brigade->id)
            ->pluck('brigades.name', 'brigade_user.user_id')->toArray();
        $technicians = User::whereHas('role', fn($q) => $q->whereIn('slug', ['technician', 'foreman', 'admin']))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'in_brigade_name' => $otherBrigadeNames[$u->id] ?? null]);
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
        // Бригадир всегда остаётся в составе — но только пока бригада активна,
        // иначе распустить неактивную бригаду до конца было бы невозможно.
        $ids = $data['member_ids'] ?? [];
        if ($brigade->is_active && $brigade->foreman_id && !in_array($brigade->foreman_id, $ids)) {
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

    // Деактивация — основной способ расформировать бригаду, не теряя историю:
    // у tickets.brigade_id стоит nullOnDelete(), т.е. физическое удаление
    // бригады молча обнулило бы brigade_id у ВСЕХ старых заявок (включая уже
    // закрытые). Деактивированная бригада остаётся строкой в БД и продолжает
    // корректно отображаться в истории, но пропадает из списков назначения
    // новых заявок (см. TicketController::create/edit/show,
    // ConnectionRequestController::index) и из формы Расписания. У неактивной
    // бригады также снимается ограничение "нельзя убрать бригадира без
    // замены" (см. update()/updateMembers()) — так её можно полностью
    // опустошить и перевести сотрудников в другую бригаду.
    public function toggleActive(Brigade $brigade)
    {
        $brigade->update(['is_active' => !$brigade->is_active]);
        return back()->with('success', $brigade->is_active ? 'Бригада активирована' : 'Бригада деактивирована');
    }

    public function destroy(Brigade $brigade)
    {
        // Полное удаление осмысленно только для бригады без какой-либо
        // истории — у tickets/connection_requests brigade_id стоит
        // nullOnDelete(), т.е. удаление бригады с историей молча обнулило бы
        // связь у всех её старых заявок. Для расформирования бригады с
        // историей — toggleActive(), не destroy().
        $hasHistory = $brigade->tickets()->exists()
            || ConnectionRequest::where('brigade_id', $brigade->id)->exists();
        if ($hasHistory) {
            return back()->with('error', 'Нельзя удалить бригаду — на неё ссылаются заявки. Деактивируйте бригаду вместо удаления, чтобы сохранить историю.');
        }
        $brigade->territories()->detach();
        $brigade->members()->detach();
        $brigade->delete();
        return back()->with('success', 'Бригада удалена');
    }

    // Бригадиром можно назначить пользователя с ролью «бригадир», либо
    // admin — но ТОЛЬКО если он уже входит в состав этой бригады (2026-08-04,
    // прямая просьба пользователя: "админ тоже может быть в списке выбора
    // бригадира, но только если он входит в бригаду"). Раньше в select можно
    // было выбрать любого участника бригады, включая монтажников — это и
    // было первично найдено и исправлено; сейчас же admin легитимен, но
    // именно как «участник, а не посторонний». Фронтенд уже фильтрует
    // список так же (foremanCandidates: role ∈ {foreman, admin} AND уже
    // отмечен в member_ids) — это защита от прямого POST в обход формы.
    private function foremanRoleRule(Request $request): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($request) {
            if (!$value) return;
            $role = User::find($value)?->role?->slug;
            if (!in_array($role, ['foreman', 'admin'], true)) {
                $fail('Бригадиром может быть только пользователь с ролью «Бригадир» (или admin — участник бригады).');
                return;
            }
            $memberIds = $request->input('member_ids', []);
            if (!in_array((int) $value, array_map('intval', $memberIds), true)) {
                $fail('Бригадир должен быть в составе бригады.');
            }
        };
    }
}
