<?php
namespace App\Http\Controllers;

use App\Models\{Material, TicketMaterial, Ticket};
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaterialController extends Controller
{
    public function index()
    {
        return Inertia::render('Materials/Index', [
            'materials' => Material::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'unit'       => 'required|string|max:20',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);
        Material::create($data);
        return back()->with('success', 'Материал добавлен');
    }

    public function update(Request $request, Material $material)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'unit'       => 'required|string|max:20',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ]);
        $material->update($data);
        return back()->with('success', 'Материал обновлён');
    }

    public function destroy(Material $material)
    {
        $material->update(['is_active' => false]);
        return back()->with('success', 'Материал деактивирован');
    }

    // Сохранение расходников по заявке
    public function storeForTicket(Request $request, Ticket $ticket)
    {
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.material_id'    => 'required|exists:materials,id',
            'items.*.quantity'       => 'required|numeric|min:0.001',
        ]);

        // Удаляем старые записи (перезапись)
        $ticket->materials()->delete();

        foreach ($request->items as $item) {
            $material = Material::findOrFail($item['material_id']);
            $ticket->materials()->create([
                'material_id'    => $material->id,
                'material_name'  => $material->name,
                'material_unit'  => $material->unit,
                'price_at_time'  => $material->price,
                'quantity'       => $item['quantity'],
                'created_by'     => auth()->id(),
            ]);
        }

        return back()->with('success', 'Расходники сохранены');
    }
}
