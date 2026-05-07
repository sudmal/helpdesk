<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:service_types,name',
            'color'      => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        ServiceType::create($data);
        return back()->with('success', 'Участок добавлен');
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $this->authorize('manage-settings');
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:service_types,name,' . $serviceType->id,
            'color'      => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $serviceType->update($data);
        return back()->with('success', 'Участок обновлён');
    }

    public function destroy(ServiceType $serviceType)
    {
        $this->authorize('manage-settings');
        if ($serviceType->tickets()->exists()) {
            return back()->withErrors(['service' => 'Нельзя удалить — есть заявки']);
        }
        $serviceType->delete();
        return back()->with('success', 'Участок удалён');
    }
}
