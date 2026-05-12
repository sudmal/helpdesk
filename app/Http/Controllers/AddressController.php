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
            // Уровень 0 — список городов
            $cityList = Address::selectRaw('city, COUNT(*) as count')
                ->whereNotNull('city')->where('city', '!=', '')
                ->groupBy('city')->orderBy('city')
                ->get()
                ->map(fn($r) => ['name' => $r->city, 'count' => $r->count])
                ->toArray();

        } elseif (!$street) {
            // Уровень 1 — улицы города
            $streetList = Address::selectRaw('street, COUNT(*) as count')
                ->where('city', $city)
                ->whereNotNull('street')
                ->groupBy('street')->orderBy('street')
                ->get()
                ->map(fn($r) => ['name' => $r->street, 'count' => $r->count])
                ->toArray();

        } elseif (!$building) {
            // Уровень 2 — дома на улице
            // Было: 4 запроса на каждый дом (N+1). Теперь: 3 запроса суммарно.

            // Запрос 1: список домов с количеством адресных записей
            $buildingsRaw = Address::selectRaw('building, COUNT(*) as cnt, MAX(id) as id')
                ->where('city', $city)->where('street', $street)->whereNotNull('building')
                ->groupBy('building')
                ->orderByRaw('CAST(building AS UNSIGNED), building')
                ->get();

            // Запрос 2: количество квартир по домам из таблицы addresses
            $addrAptCounts = Address::selectRaw('building, COUNT(DISTINCT apartment) as apt_count')
                ->where('city', $city)->where('street', $street)
                ->whereNotNull('building')->whereNotNull('apartment')->where('apartment', '!=', '')
                ->groupBy('building')
                ->pluck('apt_count', 'building');

            // Запрос 3: количество квартир по домам из таблицы tickets
            $ticketAptCounts = \DB::table('tickets')
                ->join('addresses', 'tickets.address_id', '=', 'addresses.id')
                ->where('addresses.city', $city)->where('addresses.street', $street)
                ->whereNotNull('addresses.building')
                ->whereNotNull('tickets.apartment')->where('tickets.apartment', '!=', '')
                ->whereNull('tickets.deleted_at')
                ->selectRaw('addresses.building, COUNT(DISTINCT tickets.apartment) as apt_count')
                ->groupBy('addresses.building')
                ->pluck('apt_count', 'building');

            $buildingList = $buildingsRaw->map(function ($r) use ($addrAptCounts, $ticketAptCounts) {
                $b             = $r->building;
                $aptsFromAddr  = (int)($addrAptCounts[$b]  ?? 0);
                $aptsFromTicks = (int)($ticketAptCounts[$b] ?? 0);
                $hasApts       = $aptsFromAddr > 0 || $aptsFromTicks > 0;
                $aptCount      = max($aptsFromAddr, $aptsFromTicks);
                return [
                    'building'       => $b,
                    'count'          => $aptCount ?: $r->cnt,
                    'has_apartments' => $hasApts,
                    'id'             => $r->id,
                ];
            })->toArray();

        } else {
            // Уровень 3 — квартиры или данные дома
            $allInBuilding = Address::with('territory')
                ->where('city', $city)
                ->where('street', $street)
                ->where('building', $building)
                ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                ->get();

            $isMkdFromAddresses = $allInBuilding->filter(fn($a) => !empty($a->apartment))->isNotEmpty();

            $addressIds = $allInBuilding->pluck('id');
            $ticketApartments = \App\Models\Ticket::whereIn('address_id', $addressIds)
                ->whereNotNull('apartment')
                ->where('apartment', '!=', '')
                ->whereNull('deleted_at')
                ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                ->distinct()
                ->pluck('apartment');

            $isMkd = $isMkdFromAddresses || $ticketApartments->isNotEmpty();

            // Единый запрос: количество заявок на квартиру (учитывает оба способа хранения).
            // GROUP BY apt_val (алиас) обходит ONLY_FULL_GROUP_BY в MySQL.
            $rows = \DB::select(
                "SELECT COALESCE(NULLIF(t.apartment,''),NULLIF(a.apartment,''),'') AS apt_val, COUNT(*) AS cnt
                 FROM tickets t
                 JOIN addresses a ON t.address_id = a.id
                 WHERE a.city = ? AND a.street = ? AND a.building = ? AND t.deleted_at IS NULL
                 GROUP BY apt_val",
                [$city, $street, $building]
            );
            $ticketCountsByApt = collect($rows)->pluck('cnt', 'apt_val');

            if (!$isMkdFromAddresses && $ticketApartments->isNotEmpty()) {
                $baseAddress = $allInBuilding->first();
                $aptList = $ticketApartments->map(fn($apt) => array_merge(
                    $baseAddress ? $baseAddress->toArray() : [],
                    ['apartment' => $apt, 'id' => $baseAddress?->id, 'tickets_count' => (int)($ticketCountsByApt[$apt] ?? 0)]
                ))->toArray();
            } else {
                $aptList = $allInBuilding->map(fn($a) => array_merge(
                    $a->toArray(),
                    ['tickets_count' => (int)($ticketCountsByApt[$a->apartment ?? ''] ?? 0)]
                ))->toArray();
            }

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
        $q    = trim($request->get('q', ''));
        $user = auth()->user();

        if ($user->isTechnician() || $user->isForeman()) {
            $brigadeIds = \App\Models\Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
            $userTerritories = $brigadeIds->isNotEmpty()
                ? Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
                : $user->territories()->pluck('territories.id');
        } else {
            $userTerritories = collect();
        }

        $words         = array_values(array_filter(explode(' ', $q)));
        $textWords     = [];
        $buildingHint  = null;
        $apartmentHint = null;

        foreach ($words as $word) {
            if (preg_match('/^\d+[а-яёa-z]?$/iu', $word)) {
                if ($buildingHint === null) {
                    $buildingHint = $word;
                } else {
                    $apartmentHint = $word;
                }
            } else {
                $textWords[] = $word;
            }
        }

        $searchQuery = implode(' ', $textWords);

        $addresses = Address::with('territory')
            ->when($userTerritories->isNotEmpty(), fn($q) => $q->whereIn('territory_id', $userTerritories))
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
                $apartments = \App\Models\Ticket::where('address_id', $a->id)
                    ->whereNotNull('apartment')->where('apartment', '!=', '')
                    ->distinct()->orderBy('apartment')->pluck('apartment');

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