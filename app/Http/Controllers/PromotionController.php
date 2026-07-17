<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:promotions,name',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        Promotion::create($data);
        return back()->with('success', 'Акция добавлена');
    }

    public function update(Request $request, Promotion $promotion)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:promotions,name,' . $promotion->id,
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $promotion->update($data);
        return back()->with('success', 'Акция обновлена');
    }

    public function destroy(Promotion $promotion)
    {
        $this->authorize('manage-settings');
        if ($promotion->acts()->exists()) {
            return back()->withErrors(['promotion' => 'Нельзя удалить — есть акты по этой акции. Отключите её вместо удаления.']);
        }
        $promotion->delete();
        return back()->with('success', 'Акция удалена');
    }
}
