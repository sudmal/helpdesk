<?php

namespace App\Http\Controllers;

use App\Models\Territory;
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
        Territory::create($data);
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
