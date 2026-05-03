<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\AddressImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::with('territory')
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->territory_id, fn($q) => $q->where('territory_id', $request->territory_id))
            ->orderBy('street')->orderBy('building')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Addresses/Index', [
            'addresses'   => $addresses,
            'filters'     => $request->only(['search', 'territory_id']),
            'territories' => \App\Models\Territory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'territory_id'    => 'nullable|exists:territories,id',
            'city'            => 'nullable|string|max:100',
            'street'          => 'required|string|max:200',
            'building'        => 'required|string|max:20',
            'apartment'       => 'nullable|string|max:20',
            'entrance'        => 'nullable|string|max:10',
            'floor'           => 'nullable|string|max:10',
            'subscriber_name' => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:20',
            'contract_no'     => 'nullable|string|max:50',
            'notes'           => 'nullable|string',
            // Генерация диапазона квартир
            'apt_from'        => 'nullable|integer|min:1',
            'apt_to'          => 'nullable|integer|min:1',
        ]);

        // Автогенерация записей по диапазону квартир
        if (!empty($data['apt_from']) && !empty($data['apt_to'])) {
            $created = 0;
            for ($apt = $data['apt_from']; $apt <= $data['apt_to']; $apt++) {
                Address::firstOrCreate(
                    ['street' => $data['street'], 'building' => $data['building'], 'apartment' => (string)$apt],
                    array_merge($data, ['apartment' => (string)$apt])
                );
                $created++;
            }
            return back()->with('success', "Создано {$created} записей");
        }

        Address::create($data);
        return back()->with('success', 'Адрес добавлен');
    }

    public function update(Request $request, Address $address)
    {
        $address->update($request->validate([
            'territory_id'    => 'nullable|exists:territories,id',
            'city'            => 'nullable|string|max:100',
            'street'          => 'required|string|max:200',
            'building'        => 'required|string|max:20',
            'apartment'       => 'nullable|string|max:20',
            'subscriber_name' => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:20',
            'contract_no'     => 'nullable|string|max:50',
            'notes'           => 'nullable|string',
        ]));
        return back()->with('success', 'Адрес обновлён');
    }

    public function destroy(Address $address)
    {
        $address->delete();
        return back()->with('success', 'Адрес удалён');
    }

    public function import(Request $request, AddressImportService $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $result = $importer->import($request->file('file'));

        return back()->with('import_result', $result);
    }

    /** AJAX: поиск адресов для автодополнения при создании заявки */
    public function search(Request $request)
    {
        $addresses = Address::with('territory')
            ->search($request->q ?? '')
            ->limit(15)
            ->get(['id', 'city', 'street', 'building', 'apartment',
                   'subscriber_name', 'phone', 'contract_no', 'territory_id']);

        return response()->json($addresses->map(fn($a) => [
            'id'    => $a->id,
            'label' => $a->full_address,
            'subscriber_name' => $a->subscriber_name,
            'phone'           => $a->phone,
            'contract_no'     => $a->contract_no,
            'territory'       => $a->territory?->name,
        ]));
    }
}
