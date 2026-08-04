<?php

namespace App\Http\Controllers;

use App\Models\{Territory, User};
use Illuminate\Http\Request;
use Inertia\Inertia;

class TerritoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Territories/Index', [
            'territories' => Territory::withCount('brigades')
                ->with('brigades:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:territories,name',
            'description' => 'nullable|string|max:500',
        ]);
        $territory = Territory::create($data);

        // Роли, которые по бизнес-правилам всегда видят все территории
        // (Оператор ТП/Начальник ТП/ПЭО/Логистика — не admin, у него
        // отдельный хардкод) реализуют это через данные (user_territory),
        // а не через код видимости — см. User::territoryScopeIds() и
        // бэкфилл 2026-08-04. Значит новую территорию нужно сразу
        // прикрепить им всем, иначе они молча перестанут видеть её данные
        // до тех пор, пока кто-то не отметит галочку вручную.
        User::whereHas('role', fn($q) => $q->whereIn('slug', ['operator', 'head_support', 'peo', 'logistics']))
            ->where('is_active', true)
            ->get()
            ->each(fn($u) => $u->territories()->syncWithoutDetaching([$territory->id]));

        return back()->with('success', 'Территория добавлена');
    }

    public function update(Request $request, Territory $territory)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:territories,name,' . $territory->id,
            'description' => 'nullable|string|max:500',
        ]);
        $territory->update($data);
        return back()->with('success', 'Территория обновлена');
    }

    public function destroy(Territory $territory)
    {
        if ($territory->addresses()->exists()) {
            return back()->withErrors(['territory' => 'Нельзя удалить — есть адреса в этой территории']);
        }
        $territory->delete();
        return back()->with('success', 'Территория удалена');
    }
}
