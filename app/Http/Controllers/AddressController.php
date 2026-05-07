<?php

namespace App\Http\Controllers;

use App\Models\{Address, Territory};
use App\Services\AddressImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    /**
     * Иерархический проводник адресов.
     * level 0 (нет параметров) — только фильтр, без списка
     * level 1 (?city=X)        — улицы города
     * level 2 (?city=X&street=Y) — дома на улице
     * level 3 (?city=X&street=Y&building=Z) — квартиры или ЧС
     */
    public function index(Request $request): Response
    {
        $city     = $request->get('city');
        $street   = $request->get('street');
        $building = $request->get('building');

        $cityList    = [];
        $streetList  = [];
        $buildingList= [];
        $aptList     = [];

        if (!$city) {
            // Уровень 0 — список городов с количеством адресов
            $cityList = Address::selectRaw('city, COUNT(*) as count')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->groupBy('city')
                ->orderBy('city')
                ->get()
                ->map(fn($r) => ['name' => $r->city, 'count' => $r->count])
                ->toArray();

        } elseif (!$street) {
            // Уровень 1 — улицы выбранного города
            $streetList = Address::selectRaw('street, COUNT(*) as count')
                ->where('city', $city)
                ->whereNotNull('street')
                ->groupBy('street')
                ->orderBy('street')
                ->get()
                ->map(fn($r) => ['name' => $r->street, 'count' => $r->count])
                ->toArray();

        } elseif (!$building) {
            // Уровень 2 — дома на улице
            $buildingList = Address::selectRaw('building, COUNT(*) as cnt, MAX(id) as id')
                ->where('city', $city)
                ->where('street', $street)
                ->whereNotNull('building')
                ->groupBy('building')
                ->orderByRaw('CAST(building AS UNSIGNED), building')
                ->get()
                ->map(function ($r) use ($city, $street) {
                    // Определяем МКД или ЧС — проверяем и addresses.apartment и tickets.apartment
                    $addressIds = Address::where('city', $city)
                        ->where('street', $street)
                        ->where('building', $r->building)
                        ->pluck('id');

                    $hasApts = Address::where('city', $city)
                        ->where('street', $street)
                        ->where('building', $r->building)
                        ->whereNotNull('apartment')
                        ->where('apartment', '!=', '')
                        ->exists()
                        || \App\Models\Ticket::whereIn('address_id', $addressIds)
                            ->whereNotNull('apartment')
                            ->where('apartment', '!=', '')
                            ->exists();

                    $aptCount = $hasApts
                        ? \App\Models\Ticket::whereIn('address_id', $addressIds)
                            ->whereNotNull('apartment')
                            ->where('apartment', '!=', '')
                            ->distinct('apartment')
                            ->count('apartment')
                        : 0;

                    return [
                        'building'        => $r->building,
                        'count'           => $aptCount ?: $r->cnt,
                        'has_apartments'  => $hasApts,
                        'id'              => $r->id,
                    ];
                })
                ->toArray();

        } else {
            // Уровень 3 — квартиры или данные дома
$allInBuilding = Address::with('territory')
                ->withCount('tickets')
                ->where('city', $city)
                ->where('street', $street)
                ->where('building', $building)
                ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                ->get();

            // Есть ли квартиры в addresses?
            $isMkdFromAddresses = $allInBuilding->filter(
                fn($a) => !empty($a->apartment)
            )->isNotEmpty();

            // Проверяем также квартиры из tickets
            $addressIds = $allInBuilding->pluck('id');
            $ticketApartments = \App\Models\Ticket::whereIn('address_id', $addressIds)
                ->whereNotNull('apartment')
                ->where('apartment', '!=', '')
                ->distinct()
                ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                ->pluck('apartment', 'address_id');

            $isMkd = $isMkdFromAddresses || $ticketApartments->isNotEmpty();

            if (!$isMkdFromAddresses && $ticketApartments->isNotEmpty()) {
                // Квартиры только в tickets — строим aptList из них
                $baseAddress = $allInBuilding->first();
                $aptList = $ticketApartments->unique()->values()->map(fn($apt) => array_merge(
                    $baseAddress ? $baseAddress->toArray() : [],
                    ['apartment' => $apt, 'id' => $baseAddress?->id]
                ))->toArray();
            } else {
                $aptList = $allInBuilding->toArray();
            }

            // Для ЧС — данные первой записи
            $buildingInfo = !$isMkd ? $allInBuilding->first()?->toArray() : null;
        }

        return Inertia::render('Addresses/Index', [
            'cityList'        => $cityList,
            'streetList'      => $streetList,
            'buildingList'    => $buildingList,
            'aptList'         => $aptList ?? [],
            'isMkd'           => $isMkd ?? null,
            'buildingInfo'    => $buildingInfo ?? null,
            'currentCity'     => $city,
            'currentStreet'   => $street,
            'currentBuilding' => $building,
            'territories'     => Territory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city'            => 'required|string|max:100',
            'territory_id'    => 'nullable|exists:territories,id',
            'street'          => 'required|string|max:200',
            'building'        => 'nullable|string|max:20',
            'apartment'       => 'nullable|string|max:20',
            'entrance'        => 'nullable|string|max:10',
            'floor'           => 'nullable|string|max:10',
            'subscriber_name' => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:30',
            'contract_no'     => 'nullable|string|max:50',
            'apt_from'        => 'nullable|integer|min:1',
            'apt_to'          => 'nullable|integer|min:1',
            'building_from'   => 'nullable|integer|min:1',
            'building_to'     => 'nullable|integer|min:1',
            'building_step'   => 'nullable|integer|min:1',
        ]);

        // Генерация диапазона домов
        $buildingFrom = $request->input('building_from');
        $buildingTo   = $request->input('building_to');
        $aptFrom      = $request->input('apt_from');
        $aptTo        = $request->input('apt_to');
        $buildStep    = max(1, (int) $request->input('building_step', 1));

        if ($buildingFrom && $buildingTo) {
            $created = 0;
            for ($b = $buildingFrom; $b <= $buildingTo; $b += $buildStep) {
                if ($aptFrom && $aptTo) {
                    for ($apt = $aptFrom; $apt <= $aptTo; $apt++) {
                        Address::firstOrCreate(
                            ['city' => $data['city'], 'street' => $data['street'],
                             'building' => (string)$b, 'apartment' => (string)$apt],
                            array_merge($data, ['building' => (string)$b, 'apartment' => (string)$apt])
                        );
                        $created++;
                    }
                } else {
                    Address::firstOrCreate(
                        ['city' => $data['city'], 'street' => $data['street'],
                         'building' => (string)$b, 'apartment' => null],
                        array_merge($data, ['building' => (string)$b, 'apartment' => null])
                    );
                    $created++;
                }
            }
            return back()->with('success', "Создано {$created} записей");
        }

        Address::create($data);
        return back()->with('success', 'Адрес добавлен');
    }

    public function update(Request $request, Address $address)
    {
        $data = $request->validate([
            'city'            => 'required|string|max:100',
            'territory_id'    => 'nullable|exists:territories,id',
            'street'          => 'required|string|max:200',
            'building'        => 'nullable|string|max:20',
            'apartment'       => 'nullable|string|max:20',
            'entrance'        => 'nullable|string|max:10',
            'subscriber_name' => 'nullable|string|max:200',
            'phone'           => 'nullable|string|max:30',
            'contract_no'     => 'nullable|string|max:50',
        ]);
        $address->update($data);
        return back()->with('success', 'Адрес обновлён');
    }

    public function destroy(Address $address)
    {
        $address->delete();
        return back()->with('success', 'Адрес удалён');
    }

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));

        // Определяем: если последнее слово — число, считаем его квартирой
        $words  = array_values(array_filter(explode(' ', $q)));

        // Парсим запрос: выделяем текстовую часть, дом и квартиру
        // Формат: [слова улицы] [дом] [квартира]
        // Числа в конце — дом и/или квартира
        $textWords     = [];
        $buildingHint  = null;
        $apartmentHint = null;

        foreach ($words as $word) {
            if (preg_match('/^\d+[а-яёa-z]?$/iu', $word)) {
                if ($buildingHint === null) {
                    $buildingHint = $word;  // первое число = дом
                } else {
                    $apartmentHint = $word; // второе число = квартира
                }
            } else {
                $textWords[] = $word;
            }
        }

        $searchQuery = implode(' ', $textWords);

        $addresses = Address::with('territory')
            ->when($searchQuery, fn($q) => $q->search($searchQuery))
            ->when($buildingHint, fn($q) => $q->where('building', $buildingHint))
            ->orderByRaw('CAST(building AS UNSIGNED)')
            ->limit(15)
            ->get(['id', 'city', 'street', 'building', 'apartment',
                   'subscriber_name', 'phone', 'contract_no', 'territory_id']);

        $results = [];

        foreach ($addresses as $a) {
            $baseLabel = collect([
                $a->city,
                $a->street,
                $a->building ? 'д.'.$a->building : null,
            ])->filter()->join(', ');

            if ($apartmentHint) {
                // Введена квартира — показываем один вариант с ней
                $results[] = [
                    'id'              => $a->id,
                    'label'           => $baseLabel . ', кв.' . $apartmentHint,
                    'apartment'       => $apartmentHint,
                    'subscriber_name' => $a->subscriber_name,
                    'phone'           => $a->phone,
                    'contract_no'     => $a->contract_no,
                    'territory'       => $a->territory?->name,
                    'territory_id'    => $a->territory_id,
                ];
            } elseif ($buildingHint) {
                // Найден конкретный дом — разворачиваем квартиры из истории заявок
                $apartments = \App\Models\Ticket::where('address_id', $a->id)
                    ->whereNotNull('apartment')
                    ->where('apartment', '!=', '')
                    ->distinct()
                    ->orderBy('apartment')
                    ->pluck('apartment');

                // Сначала вариант без квартиры
                $results[] = [
                    'id'              => $a->id,
                    'label'           => $baseLabel,
                    'apartment'       => null,
                    'subscriber_name' => $a->subscriber_name,
                    'phone'           => $a->phone,
                    'contract_no'     => $a->contract_no,
                    'territory'       => $a->territory?->name,
                    'territory_id'    => $a->territory_id,
                ];

                // Потом варианты с квартирами
                foreach ($apartments->take(12) as $apt) {
                    $results[] = [
                        'id'              => $a->id,
                        'label'           => $baseLabel . ', кв.' . $apt,
                        'apartment'       => $apt,
                        'subscriber_name' => $a->subscriber_name,
                        'phone'           => $a->phone,
                        'contract_no'     => $a->contract_no,
                        'territory'       => $a->territory?->name,
                        'territory_id'    => $a->territory_id,
                    ];
                }
            } else {
                $apt = $a->apartment;
                $results[] = [
                    'id'              => $a->id,
                    'label'           => $baseLabel . ($apt ? ', кв.'.$apt : ''),
                    'apartment'       => $apt,
                    'subscriber_name' => $a->subscriber_name,
                    'phone'           => $a->phone,
                    'contract_no'     => $a->contract_no,
                    'territory'       => $a->territory?->name,
                    'territory_id'    => $a->territory_id,
                ];
            }
        }

        return response()->json(array_slice($results, 0, 15));
    }

    public function import(Request $request, AddressImportService $importer)
    {
        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                        $fail('Файл должен быть в формате CSV, XLS или XLSX.');
                    }
                },
            ],
        ]);

        $result = $importer->import($request->file('file'));
        return response()->json(['import_result' => $result]);
    }
}
