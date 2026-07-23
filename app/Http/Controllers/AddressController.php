<?php

namespace App\Http\Controllers;

use App\Models\{Address, Territory};
use Illuminate\Support\Facades\DB;
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
            // Уровень 1 — улицы города. Сортируем по названию БЕЗ префикса
            // (ул./пер./пр./бул. и т.п.) — иначе "ул. Х" и "Y" вперемешку
            // скачут по алфавиту вместо логичной сортировки по имени улицы,
            // по которому реально ищут (см. память проекта, normalizeStreet).
            $streetList = Address::selectRaw('street, COUNT(*) as count')
                ->where('city', $city)
                ->whereNotNull('street')
                ->groupBy('street')
                ->get()
                ->map(fn($r) => ['name' => $r->street, 'count' => $r->count])
                ->sortBy(fn($r) => Address::normalizeStreet($r['name']))
                ->values()
                ->toArray();

        } elseif (!$building) {
            // Уровень 2 — дома на улице
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

            // Запрос 4: явное переопределение типа is_private (NULL = авто-определение)
            $privateOverrides = Address::selectRaw('building, MAX(is_private) as override')
                ->where('city', $city)->where('street', $street)
                ->whereNotNull('building')
                ->groupBy('building')
                ->pluck('override', 'building');

            $buildingList = $buildingsRaw->map(function ($r) use ($addrAptCounts, $ticketAptCounts, $privateOverrides) {
                $b             = $r->building;
                $aptsFromAddr  = (int)($addrAptCounts[$b]  ?? 0);
                $aptsFromTicks = (int)($ticketAptCounts[$b] ?? 0);
                $aptCount      = max($aptsFromAddr, $aptsFromTicks);
                $override      = $privateOverrides[$b] ?? null;

                if ($override !== null) {
                    // is_private=1 → ЧС; is_private=0 → МКД
                    $hasApts = !(bool)(int)$override;
                } else {
                    $hasApts = $aptsFromAddr > 0 || $aptsFromTicks > 0;
                }

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

            // Проверяем явное переопределение типа
            $isPrivateOverride = $allInBuilding->filter(fn($a) => $a->is_private !== null)->first()?->is_private;

            $addressIds = $allInBuilding->pluck('id');
            $ticketApartments = \App\Models\Ticket::whereIn('address_id', $addressIds)
                ->whereNotNull('apartment')
                ->where('apartment', '!=', '')
                ->whereNull('deleted_at')
                ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                ->distinct()
                ->pluck('apartment');

            if ($isPrivateOverride !== null) {
                $isMkd = !(bool)(int)$isPrivateOverride;
            } else {
                $isMkd = $isMkdFromAddresses || $ticketApartments->isNotEmpty();
            }

            // Единый запрос: количество заявок на квартиру (учитывает оба способа хранения).
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

    public function bulkSetType(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:addresses,id',
            'type'  => 'required|in:private,mkd',
        ]);

        $reps = Address::whereIn('id', $data['ids'])
            ->get(['city', 'street', 'building'])
            ->unique(fn($a) => $a->city . '|' . $a->street . '|' . $a->building);

        DB::transaction(function () use ($reps, $data) {
            foreach ($reps as $rep) {
                if ($data['type'] === 'private') {
                    Address::where('city', $rep->city)
                        ->where('street', $rep->street)
                        ->where('building', $rep->building)
                        ->update(['is_private' => 1]);
                    // Удаляем записи с квартирами (тип МКД → ЧС)
                    Address::where('city', $rep->city)
                        ->where('street', $rep->street)
                        ->where('building', $rep->building)
                        ->whereNotNull('apartment')
                        ->where('apartment', '!=', '')
                        ->delete();
                } else {
                    Address::where('city', $rep->city)
                        ->where('street', $rep->street)
                        ->where('building', $rep->building)
                        ->update(['is_private' => 0]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city'            => 'required|string|max:100',
            'territory_id'    => 'required|exists:territories,id',
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
            'confirm_duplicate' => 'nullable|boolean',
        ]);

        $buildingFrom = $request->input('building_from');
        $buildingTo   = $request->input('building_to');
        $aptFrom      = $request->input('apt_from');
        $aptTo        = $request->input('apt_to');
        $buildStep    = max(1, (int) $request->input('building_step', 1));

        if ($buildingFrom && $buildingTo) {
            $created = 0;
            DB::transaction(function () use ($data, $buildingFrom, $buildingTo, $buildStep, $aptFrom, $aptTo, &$created) {
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
            });
            return back()->with('success', "Создано {$created} записей");
        }

        // Компания образована слиянием ~10 провайдеров — один и тот же дом мог
        // уже попасть в addresses под другим написанием улицы (см. память проекта
        // и Address::normalizeStreet). Перед созданием одиночного адреса ищем
        // похожий в том же городе+доме+квартире и просим оператора подтвердить,
        // а не молча плодим дубли (или молча блокируем — вдруг это правда другой
        // дом с тем же номером на другой улице с похожим названием).
        if (!$request->boolean('confirm_duplicate')) {
            $streetNorm   = Address::normalizeStreet($data['street']);
            $buildingNorm = mb_strtolower(trim((string) ($data['building'] ?? '')));
            $aptNorm      = mb_strtolower(trim((string) ($data['apartment'] ?? '')));

            $dup = Address::where('city', $data['city'])
                ->where('building', trim((string) ($data['building'] ?? '')))
                ->get(['id', 'street', 'apartment', 'subscriber_name'])
                ->first(fn($a) => Address::normalizeStreet($a->street) === $streetNorm
                    && mb_strtolower(trim((string) $a->apartment)) === $aptNorm);

            if ($dup) {
                return back()->withErrors([
                    'duplicate' => "Похоже на дубль: уже есть адрес «{$dup->full_address}»"
                        . ($dup->subscriber_name ? " ({$dup->subscriber_name})" : '')
                        . ". Если это другой адрес — нажмите «Всё равно создать».",
                ])->withInput();
            }
        }

        Address::create($data);
        return back()->with('success', 'Адрес добавлен');
    }

    public function update(Request $request, Address $address)
    {
        $data = $request->validate([
            'city'            => 'required|string|max:100',
            'territory_id'    => 'required|exists:territories,id',
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
        // FK tickets.address_id -- nullOnDelete(): без этой проверки заявки
        // на этот адрес молча остаются с address_id=NULL ("Адрес не указан"),
        // теряя территорию и ссылку на адрес, оставаясь на вид никак не
        // связанными с этим адресом при разборе, откуда они взялись.
        $ticketsCount = \App\Models\Ticket::where('address_id', $address->id)->count();
        if ($ticketsCount > 0) {
            return back()->with('error',
                "Нельзя удалить адрес: на него ссылается {$ticketsCount} " .
                ($ticketsCount === 1 ? 'заявка' : 'заявок') . ". Сначала перенесите или закройте эти заявки."
            );
        }

        $address->delete();
        return back()->with('success', 'Адрес удалён');
    }

    // territory_ids для фильтра по бригаде (опционально, через ?brigade_id=)
    // null = без ограничения (бригада не передана/не найдена/не покрывает территорий)
    private function brigadeTerritoryIds(Request $request): ?array
    {
        $brigadeId = $request->get('brigade_id');
        if (!$brigadeId) return null;
        $brigade = \App\Models\Brigade::find($brigadeId);
        if (!$brigade) return null;
        $ids = $brigade->territories()->pluck('territories.id')->all();
        return empty($ids) ? null : $ids;
    }

    public function hierarchy(Request $request): \Illuminate\Http\JsonResponse
    {
        $city     = $request->get('city');
        $street   = $request->get('street');
        $building = $request->get('building');
        $withId   = $request->boolean('with_id');
        $territoryIds = $this->brigadeTerritoryIds($request);

        if (!$city) {
            return response()->json(
                Address::selectRaw('DISTINCT city')
                    ->whereNotNull('city')->where('city', '!=', '')
                    ->when($territoryIds, fn($q) => $q->whereIn('territory_id', $territoryIds))
                    ->orderBy('city')->pluck('city')
            );
        }
        if (!$street) {
            // Сортируем по названию без префикса (ул./пер./пр./бул. и т.п.) —
            // иначе в выпадающем списке улицы вперемешку скачут по алфавиту
            // вместо сортировки по тому, по чему реально ищут (см. такой же
            // фикс в index() и Address::normalizeStreet).
            $streets = Address::selectRaw('DISTINCT street')
                ->where('city', $city)->whereNotNull('street')
                ->when($territoryIds, fn($q) => $q->whereIn('territory_id', $territoryIds))
                ->pluck('street');
            return response()->json(
                $streets->sortBy(fn($s) => Address::normalizeStreet($s))->values()
            );
        }
        if (!$building) {
            return response()->json(
                Address::selectRaw('DISTINCT building')
                    ->where('city', $city)->where('street', $street)
                    ->whereNotNull('building')
                    ->when($territoryIds, fn($q) => $q->whereIn('territory_id', $territoryIds))
                    ->orderByRaw('CAST(building AS UNSIGNED), building')->pluck('building')
            );
        }

        // Для редактирования адреса заявки нужна привязка к конкретной записи Address
        // (id), а не просто номер квартиры — берём строго из справочника, без
        // подмешивания ticket.apartment (в отличие от ветки ниже для Create.vue).
        if ($withId) {
            return response()->json(
                Address::where('city', $city)->where('street', $street)->where('building', $building)
                    ->whereNotNull('apartment')->where('apartment', '!=', '')
                    ->when($territoryIds, fn($q) => $q->whereIn('territory_id', $territoryIds))
                    ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')
                    ->get(['id', 'apartment', 'subscriber_name'])
            );
        }

        $fromAddrs = Address::where('city', $city)->where('street', $street)->where('building', $building)
            ->whereNotNull('apartment')->where('apartment', '!=', '')
            ->distinct()->orderByRaw('CAST(apartment AS UNSIGNED), apartment')->pluck('apartment');
        $fromTickets = \App\Models\Ticket::whereHas('address', fn($q) =>
                $q->where('city', $city)->where('street', $street)->where('building', $building))
            ->whereNotNull('apartment')->where('apartment', '!=', '')
            ->whereNull('deleted_at')->distinct()
            ->orderByRaw('CAST(apartment AS UNSIGNED), apartment')->pluck('apartment');
        return response()->json($fromAddrs->merge($fromTickets)->unique()->values());
    }

    // Кириллица/латиница-омоглифы в номере дома ("23А" кириллица vs "23A"
    // латиница) визуально неотличимы, но это разные символы -- если оператор
    // печатает вручную (обычно кириллицей, т.к. раскладка русская), а адрес
    // в базе импортирован с латинской буквой (или наоборот), точное
    // сравнение building никогда не совпадёт, и адрес нельзя выбрать из
    // списка. Возвращаем обе версии буквы для поиска.
    private const HOMOGLYPHS = [
        'A' => 'А', 'А' => 'A', 'В' => 'B', 'B' => 'В', 'С' => 'C', 'C' => 'С',
        'Е' => 'E', 'E' => 'Е', 'Н' => 'H', 'H' => 'Н', 'К' => 'K', 'K' => 'К',
        'М' => 'M', 'M' => 'М', 'О' => 'O', 'O' => 'О', 'Р' => 'P', 'P' => 'Р',
        'Т' => 'T', 'T' => 'Т', 'Х' => 'X', 'X' => 'Х', 'У' => 'Y', 'Y' => 'У',
    ];

    private function buildingVariants(string $building): array
    {
        $variants = [$building];
        $lastChar = mb_substr($building, -1);
        $upper    = mb_strtoupper($lastChar);
        if (isset(self::HOMOGLYPHS[$upper])) {
            $alt = self::HOMOGLYPHS[$upper];
            if ($lastChar !== $upper) $alt = mb_strtolower($alt); // сохраняем регистр
            $variants[] = mb_substr($building, 0, -1) . $alt;
        }
        return array_unique($variants);
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

        // Точный резолв из модалки "Выбор адреса" -- city/street/building
        // там УЖЕ известны из иерархии (dropdown'ы), поэтому вместо
        // реконструкции текстовой строки и прогона через хрупкий разбор
        // ниже (ломался на нечисловых домах вроде "Гаражи" и на служебных
        // плейсхолдерах квартиры вроде "-", взятых из старых заявок) --
        // фильтруем БД напрямую по точным значениям.
        $exactCity      = trim((string) $request->get('city', ''));
        $exactStreet    = trim((string) $request->get('street', ''));
        $exactBuilding  = trim((string) $request->get('building', ''));
        $exactApartment = trim((string) $request->get('apartment', ''));

        $buildingHint  = null;
        $apartmentHint = null;

        if ($exactCity !== '' && $exactStreet !== '' && $exactBuilding !== '') {
            $buildingHint  = $exactBuilding;
            $apartmentHint = ($exactApartment !== '' && $exactApartment !== '-') ? $exactApartment : null;

            $addresses = Address::with('territory')
                ->when($userTerritories->isNotEmpty(), fn($q) => $q->whereIn('territory_id', $userTerritories))
                ->where('city', $exactCity)->where('street', $exactStreet)
                ->whereIn('building', $this->buildingVariants($exactBuilding))
                ->orderByRaw('CAST(building AS UNSIGNED)')
                ->limit(15)
                ->get(['id', 'city', 'street', 'building', 'apartment',
                       'subscriber_name', 'phone', 'contract_no', 'territory_id']);
        } else {
            $words     = array_values(array_filter(explode(' ', $q)));
            $textWords = $words;

            // Дом/квартира -- это ХВОСТОВЫЕ числовые токены, а не любое число в
            // строке. Улицы вида "20 партсъезда" начинают название с числа,
            // которое не имеет отношения к номеру дома -- раньше первое же
            // числовое слово (даже в начале, часть названия улицы) считалось
            // buildingHint, из-за чего запросы вроде "20 парт" или "20 партсъезда
            // 23A" не находили вообще ничего (дом "20" не существует, реальный
            // дом "23A" утекал в apartmentHint или вообще терялся).
            $tailNums = [];
            while (!empty($textWords) && count($tailNums) < 2 && preg_match('/^\d+[а-яёa-z]?$/iu', end($textWords))) {
                $tailNums[] = array_pop($textWords);
            }
            $tailNums = array_reverse($tailNums);
            if (count($tailNums) === 2) {
                [$buildingHint, $apartmentHint] = $tailNums;
            } elseif (count($tailNums) === 1) {
                $buildingHint = $tailNums[0];
            }

            $searchQuery = implode(' ', $textWords);

            $addresses = Address::with('territory')
                ->when($userTerritories->isNotEmpty(), fn($q) => $q->whereIn('territory_id', $userTerritories))
                ->when($searchQuery, fn($q) => $q->search($searchQuery))
                ->when($buildingHint, fn($q) => $q->whereIn('building', $this->buildingVariants($buildingHint)))
                ->orderByRaw('CAST(building AS UNSIGNED)')
                ->limit(15)
                ->get(['id', 'city', 'street', 'building', 'apartment',
                       'subscriber_name', 'phone', 'contract_no', 'territory_id']);
        }

        $results     = [];
        $seenBuildings = [];

        foreach ($addresses as $a) {
            $baseLabel = collect([
                $a->city,
                $a->street,
                $a->building ? 'д.'.$a->building : null,
            ])->filter()->join(', ');

            if ($apartmentHint) {
                $bKey = $a->city . '|' . $a->street . '|' . $a->building;
                if (isset($seenBuildings[$bKey])) continue;
                $seenBuildings[$bKey] = true;

                // Раньше тут всегда брался $a -- ПЕРВЫЙ попавшийся адрес с
                // этим домом (при большом кол-ве квартир-строк это, по сути,
                // случайная запись, а НЕ реально запрошенная квартира), а
                // номер квартиры просто дописывался в label. В итоге
                // address_id указывал на чужую квартиру того же дома.
                // Теперь ищем реальную запись по квартире, если она есть.
                $exactApt = Address::where('city', $a->city)->where('street', $a->street)
                    ->where('building', $a->building)->where('apartment', $apartmentHint)
                    ->first(['id', 'subscriber_name', 'phone', 'contract_no']);
                $match = $exactApt ?? $a;

                $results[] = [
                    'id'              => $match->id,
                    'label'           => $baseLabel . ', кв.' . $apartmentHint,
                    'apartment'       => $apartmentHint,
                    'has_apartments'  => true,
                    'subscriber_name' => $match->subscriber_name,
                    'phone'           => $match->phone,
                    'contract_no'     => $match->contract_no,
                    'territory'       => $a->territory?->name,
                    'territory_id'    => $a->territory_id,
                    'building'        => $a->building,
                ];
            } elseif ($buildingHint) {
                $bKey = $a->city . '|' . $a->street . '|' . $a->building;
                if (isset($seenBuildings[$bKey])) continue;
                $seenBuildings[$bKey] = true;

                $ticketApts = \App\Models\Ticket::where('address_id', $a->id)
                    ->whereNotNull('apartment')->where('apartment', '!=', '')
                    ->distinct()->pluck('apartment');

                // Дом может быть смоделирован ОДНОЙ записью Address (без
                // квартир) или ОТДЕЛЬНОЙ записью на каждую квартиру -- во
                // втором случае у каждой квартиры СВОЙ address_id, и раньше
                // весь список квартир подставлял id ПЕРВОЙ попавшейся записи
                // ($a->id) на любую выбранную квартиру -- заявка привязывалась
                // к чужой квартире того же дома. Тянем реальные записи разом.
                $addrRows = Address::where('city', $a->city)->where('street', $a->street)
                    ->where('building', $a->building)->whereNotNull('apartment')->where('apartment', '!=', '')
                    ->get(['id', 'apartment', 'subscriber_name', 'phone', 'contract_no'])
                    ->keyBy('apartment');

                $allApts = $ticketApts->merge($addrRows->keys())->unique()
                    ->sortBy(fn($v) => (int) $v)->values();
                $hasApts = $allApts->isNotEmpty();

                if (!$hasApts) {
                    $results[] = [
                        'id'              => $a->id,
                        'label'           => $baseLabel,
                        'apartment'       => null,
                        'has_apartments'  => false,
                        'subscriber_name' => $a->subscriber_name,
                        'phone'           => $a->phone,
                        'contract_no'     => $a->contract_no,
                        'territory'       => $a->territory?->name,
                        'territory_id'    => $a->territory_id,
                    'building'        => $a->building,
                    ];
                }

                foreach ($allApts as $apt) {
                    $row = $addrRows->get($apt);
                    $results[] = [
                        'id'              => $row?->id ?? $a->id,
                        'label'           => $baseLabel . ', кв.' . $apt,
                        'apartment'       => $apt,
                        'has_apartments'  => true,
                        'subscriber_name' => $row?->subscriber_name ?? $a->subscriber_name,
                        'phone'           => $row?->phone ?? $a->phone,
                        'contract_no'     => $row?->contract_no ?? $a->contract_no,
                        'territory'       => $a->territory?->name,
                        'territory_id'    => $a->territory_id,
                    'building'        => $a->building,
                    ];
                }
            } else {
                $apt = $a->apartment;
                $results[] = [
                    'id'              => $a->id,
                    'label'           => $baseLabel . ($apt ? ', кв.'.$apt : ''),
                    'apartment'       => $apt,
                    'has_apartments'  => !empty($apt),
                    'subscriber_name' => $a->subscriber_name,
                    'phone'           => $a->phone,
                    'contract_no'     => $a->contract_no,
                    'territory'       => $a->territory?->name,
                    'territory_id'    => $a->territory_id,
                    'building'        => $a->building,
                ];
            }
        }

        // Раньше жёстко резалось до 15 результатов ВСЕГО -- для дома на 200
        // квартир это отрезало почти весь список (см. openAddrModal/take(12)
        // фикс выше). Ограничение по количеству ДОМОВ уже есть в исходном
        // запросе ($addresses->limit(15)), этого достаточно.
        return response()->json(array_values($results));
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
    public function storeGeocode(Request $request, Address $address): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);
        $address->update(['lat' => $data['lat'], 'lng' => $data['lng']]);
        return response()->json(['ok' => true]);
    }

}