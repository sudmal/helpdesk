<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Brigade;
use App\Models\Territory;

class MaterialReportController extends Controller
{
    private function parseRange(Request $request): array
    {
        $from = $request->get('from', now()->subMonth()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    private function previousRange(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    // filterDim/filterId (опционально) — фильтр по конкретной бригаде/территории применяется
    // ВНУТРИ каждой ветки union'а (до unionAll), а не поверх готового union'а — Laravel мержит
    // биндинги ->where() поверх DB::raw()-подзапроса не по текстовому порядку "?", а по типу клозы
    // (where/union/...), из-за чего плейсхолдеры съезжают. Фильтрация до union — безопасный способ.
    private function unionQuery(Carbon $from, Carbon $to, ?string $filterDim = null, $filterId = null)
    {
        $ticketSide = DB::table('ticket_materials as tm')
            ->join('tickets as t', 'tm.ticket_id', '=', 't.id')
            ->leftJoin('addresses as addr', 't.address_id', '=', 'addr.id')
            ->whereNull('t.deleted_at')
            ->whereBetween('tm.created_at', [$from, $to]);

        $crSide = DB::table('connection_request_materials as crm')
            ->join('connection_requests as cr', 'crm.connection_request_id', '=', 'cr.id')
            ->whereBetween('crm.created_at', [$from, $to]);

        if ($filterDim === 'brigade') {
            if ($filterId === 'connection_requests') {
                $ticketSide->whereRaw('1 = 0'); // у заявок на ремонт бригада есть всегда — этот бакет только из connection_requests
            } elseif ($filterId !== null) {
                $ticketSide->where('t.brigade_id', $filterId);
                $crSide->whereRaw('1 = 0'); // у заявок на подключение бригады не бывает вообще
            }
        } elseif ($filterDim === 'territory') {
            if ($filterId === 'unknown') {
                $ticketSide->whereNull('addr.territory_id');
                $crSide->whereNull('cr.territory_id');
            } elseif ($filterId !== null) {
                $ticketSide->where('addr.territory_id', $filterId);
                $crSide->where('cr.territory_id', $filterId);
            }
        }

        $ticketSide->selectRaw("tm.material_id, tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time, tm.created_at, t.brigade_id as brigade_id, addr.territory_id as territory_id, tm.ticket_id as source_id, 'ticket' as source");
        $crSide->selectRaw("crm.material_id, crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time, crm.created_at, NULL as brigade_id, cr.territory_id as territory_id, crm.connection_request_id as source_id, 'connection_request' as source");

        return $ticketSide->unionAll($crSide);
    }

    private function unionQueryAllTime()
    {
        $ticketSide = DB::table('ticket_materials as tm')
            ->join('tickets as t', 'tm.ticket_id', '=', 't.id')
            ->whereNull('t.deleted_at')
            ->selectRaw("tm.material_id, tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time, tm.created_at");

        $crSide = DB::table('connection_request_materials as crm')
            ->selectRaw("crm.material_id, crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time, crm.created_at");

        return $ticketSide->unionAll($crSide);
    }

    // ── Расход за период: общий (по материалам) / по бригадам / по территориям ──
    // entity_id (если задан и dimension != all) -> список материалов ИМЕННО этой бригады/территории
    public function consumption(Request $request)
    {
        $dimension = $request->get('dimension', 'all');
        $entityId  = $request->get('entity_id');
        [$from, $to] = $this->parseRange($request);

        $drillDown = $dimension !== 'all' && $entityId !== null && $entityId !== '';

        if ($drillDown) {
            $rows = $this->aggregateMaterials($from, $to, $dimension, $entityId);
        } else {
            $rows = $this->aggregateByDimension($from, $to, $dimension);
        }

        $result = [
            'dimension' => $dimension,
            'entity_id' => $drillDown ? $entityId : null,
            'rows'      => $rows,
            'totals'    => [
                'qty'    => array_sum(array_column($rows, 'qty')),
                'amount' => array_sum(array_column($rows, 'amount')),
            ],
        ];

        // % изменения к прошлому периоду считаем и для общего разреза, и для drill-down по материалам
        if ($dimension === 'all' || $drillDown) {
            [$prevFrom, $prevTo] = $this->previousRange($from, $to);
            $prevRows = $drillDown
                ? $this->aggregateMaterials($prevFrom, $prevTo, $dimension, $entityId)
                : $this->aggregateByDimension($prevFrom, $prevTo, 'all');
            $prevByKey = collect($prevRows)->keyBy('key');

            foreach ($result['rows'] as &$row) {
                $prev = $prevByKey->get($row['key']);
                $prevAmount = $prev['amount'] ?? 0;
                $row['prev_amount'] = $prevAmount;
                $row['change_pct'] = $prevAmount > 0
                    ? round((($row['amount'] - $prevAmount) / $prevAmount) * 100, 1)
                    : ($row['amount'] > 0 ? null : 0); // null = "новый" (не было в прошлом периоде)
            }
            unset($row);
        }

        return response()->json($result);
    }

    // Список материалов (как в dimension=all), опционально отфильтрованный по конкретной бригаде/территории
    private function aggregateMaterials(Carbon $from, Carbon $to, ?string $filterDim = null, $filterId = null): array
    {
        $union = $this->unionQuery($from, $to, $filterDim, $filterId);

        $raw = DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union)
            ->selectRaw('
                COALESCE(material_code, material_name) as key_id,
                MAX(material_name) as name,
                MAX(material_code) as code,
                MAX(material_unit) as unit,
                SUM(quantity) as qty,
                SUM(quantity * price_at_time) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'))
            ->orderByDesc('amount')
            ->get();

        return $raw->map(fn($r) => [
            'key'    => $r->key_id,
            'label'  => $r->name,
            'code'   => $r->code,
            'unit'   => $r->unit,
            'qty'    => (float)$r->qty,
            'amount' => (float)$r->amount,
        ])->toArray();
    }

    private function aggregateByDimension(Carbon $from, Carbon $to, string $dimension): array
    {
        $union = $this->unionQuery($from, $to);

        if ($dimension === 'brigade') {
            $raw = DB::table(DB::raw("({$union->toSql()}) as x"))
                ->mergeBindings($union)
                ->selectRaw('
                    brigade_id,
                    SUM(quantity) as qty,
                    SUM(quantity * price_at_time) as amount,
                    COUNT(DISTINCT CONCAT(source, "-", source_id)) as request_count
                ')
                ->groupBy('brigade_id')
                ->orderByDesc('amount')
                ->get();

            $brigadeNames = Brigade::whereIn('id', $raw->pluck('brigade_id')->filter()->all())->pluck('name', 'id');

            return $raw->map(function ($r) use ($brigadeNames) {
                $amount = (float)$r->amount;
                $requestCount = (int)$r->request_count;
                return [
                    'key'                  => $r->brigade_id ?? 'connection_requests',
                    'label'                => $r->brigade_id ? ($brigadeNames[$r->brigade_id] ?? '—') : 'Заявки на подключение',
                    'qty'                  => (float)$r->qty,
                    'amount'               => $amount,
                    'request_count'        => $requestCount,
                    'avg_amount_per_ticket'=> $requestCount > 0 ? round($amount / $requestCount, 2) : 0,
                ];
            })->toArray();
        }

        if ($dimension === 'territory') {
            $raw = DB::table(DB::raw("({$union->toSql()}) as x"))
                ->mergeBindings($union)
                ->selectRaw('
                    territory_id,
                    SUM(quantity) as qty,
                    SUM(quantity * price_at_time) as amount,
                    COUNT(DISTINCT CONCAT(source, "-", source_id)) as request_count
                ')
                ->groupBy('territory_id')
                ->orderByDesc('amount')
                ->get();

            $territoryNames = Territory::whereIn('id', $raw->pluck('territory_id')->filter()->all())->pluck('name', 'id');

            return $raw->map(function ($r) use ($territoryNames) {
                return [
                    'key'           => $r->territory_id ?? 'unknown',
                    'label'         => $r->territory_id ? ($territoryNames[$r->territory_id] ?? '—') : 'Без территории',
                    'qty'           => (float)$r->qty,
                    'amount'        => (float)$r->amount,
                    'request_count' => (int)$r->request_count,
                ];
            })->toArray();
        }

        // dimension === 'all' -> по материалам
        $raw = DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union)
            ->selectRaw('
                COALESCE(material_code, material_name) as key_id,
                MAX(material_name) as name,
                MAX(material_code) as code,
                MAX(material_unit) as unit,
                SUM(quantity) as qty,
                SUM(quantity * price_at_time) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'))
            ->orderByDesc('amount')
            ->get();

        return $raw->map(fn($r) => [
            'key'    => $r->key_id,
            'label'  => $r->name,
            'code'   => $r->code,
            'unit'   => $r->unit,
            'qty'    => (float)$r->qty,
            'amount' => (float)$r->amount,
        ])->toArray();
    }

    // ── Таблица материал × месяц ──
    public function monthlyMatrix(Request $request)
    {
        $months = $request->get('months', '12'); // '12' | '24' | 'all'

        $union = $this->unionQueryAllTime();

        $query = DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union);

        if ($months !== 'all') {
            $from = now()->subMonths((int)$months)->startOfMonth();
            $query->where('created_at', '>=', $from);
        }

        $raw = $query->selectRaw('
                COALESCE(material_code, material_name) as key_id,
                MAX(material_name) as name,
                MAX(material_code) as code,
                MAX(material_unit) as unit,
                DATE_FORMAT(created_at, "%Y-%m") as month,
                SUM(quantity) as qty,
                SUM(quantity * price_at_time) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'), DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $monthsSet = $raw->pluck('month')->unique()->sort()->values()->all();
        $materialsMap = [];
        foreach ($raw as $r) {
            $key = $r->key_id;
            if (!isset($materialsMap[$key])) {
                $materialsMap[$key] = [
                    'key'    => $key,
                    'name'   => $r->name,
                    'code'   => $r->code,
                    'unit'   => $r->unit,
                    'qty'    => [],
                    'amount' => [],
                    'total_qty'    => 0,
                    'total_amount' => 0,
                ];
            }
            $materialsMap[$key]['qty'][$r->month]    = (float)$r->qty;
            $materialsMap[$key]['amount'][$r->month] = (float)$r->amount;
            $materialsMap[$key]['total_qty']    += (float)$r->qty;
            $materialsMap[$key]['total_amount'] += (float)$r->amount;
        }

        $materials = array_values($materialsMap);
        usort($materials, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        // Развернуть в плоские массивы по месяцам (0, если не было расхода)
        foreach ($materials as &$m) {
            $qty = []; $amount = [];
            foreach ($monthsSet as $mo) {
                $qty[]    = $m['qty'][$mo]    ?? 0;
                $amount[] = $m['amount'][$mo] ?? 0;
            }
            $m['qty']    = $qty;
            $m['amount'] = $amount;
        }
        unset($m);

        return response()->json([
            'months'    => $monthsSet,
            'materials' => $materials,
        ]);
    }

    // ── Прогноз: линейный тренд (+ сезонность при достаточной истории) ──
    public function forecast(Request $request)
    {
        $topN = (int)$request->get('top', 5);

        $union = $this->unionQueryAllTime();
        $raw = DB::table(DB::raw("({$union->toSql()}) as x"))
            ->mergeBindings($union)
            ->selectRaw('
                COALESCE(material_code, material_name) as key_id,
                MAX(material_name) as name,
                MAX(material_code) as code,
                DATE_FORMAT(created_at, "%Y-%m") as month,
                SUM(quantity) as qty,
                SUM(quantity * price_at_time) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'), DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $byMaterial = [];
        foreach ($raw as $r) {
            $byMaterial[$r->key_id]['name'] ??= $r->name;
            $byMaterial[$r->key_id]['code'] ??= $r->code;
            $byMaterial[$r->key_id]['months'][$r->month] = (float)$r->amount;
        }

        // топ-N по сумме за последние 12 месяцев (актуальность)
        $recentCutoff = now()->subMonths(12)->format('Y-m');
        $recentTotals = [];
        foreach ($byMaterial as $key => $m) {
            $recentTotals[$key] = array_sum(array_filter(
                $m['months'],
                fn($month) => $month >= $recentCutoff,
                ARRAY_FILTER_USE_KEY
            ));
        }
        arsort($recentTotals);
        $topKeys = array_slice(array_keys($recentTotals), 0, $topN);

        $topMaterials = [];
        foreach ($topKeys as $key) {
            $topMaterials[] = $this->buildForecastSeries($key, $byMaterial[$key]['name'], $byMaterial[$key]['code'], $byMaterial[$key]['months']);
        }

        // агрегированный итог по всем материалам
        $aggregateMonths = [];
        foreach ($byMaterial as $m) {
            foreach ($m['months'] as $month => $amount) {
                $aggregateMonths[$month] = ($aggregateMonths[$month] ?? 0) + $amount;
            }
        }
        ksort($aggregateMonths);
        $aggregate = $this->buildForecastSeries('_total', 'Итого по всем материалам', null, $aggregateMonths);

        return response()->json([
            'top'       => $topMaterials,
            'aggregate' => $aggregate,
        ]);
    }

    private function buildForecastSeries(string $key, string $name, ?string $code, array $monthsData): array
    {
        ksort($monthsData);
        $months = array_keys($monthsData);
        $values = array_values($monthsData);
        $n = count($values);

        if ($n < 3) {
            return [
                'key' => $key, 'name' => $name, 'code' => $code,
                'months' => $months, 'values' => $values,
                'forecast_month' => null, 'forecast_value' => null,
                'method' => 'insufficient_data',
            ];
        }

        // МНК: y = a + b*x
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        foreach ($values as $x => $y) {
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumX2 += $x * $x;
        }
        $b = ($n * $sumXY - $sumX * $sumY) / (($n * $sumX2 - $sumX * $sumX) ?: 1);
        $a = ($sumY - $b * $sumX) / $n;

        $nextX = $n;
        $trendForecast = $a + $b * $nextX;

        $method = 'linear';
        $seasonalOffset = 0;

        if ($n >= 24) {
            // сезонность: средняя разница (факт - тренд) по каждому календарному месяцу
            $lastMonth = Carbon::createFromFormat('Y-m', end($months));
            $nextCalendarMonth = $lastMonth->copy()->addMonth()->format('m');

            $deviationsByCalMonth = [];
            foreach ($months as $x => $monthStr) {
                $calMonth = substr($monthStr, 5, 2);
                $trendAtX = $a + $b * $x;
                $deviationsByCalMonth[$calMonth][] = $values[$x] - $trendAtX;
            }
            if (!empty($deviationsByCalMonth[$nextCalendarMonth])) {
                $devs = $deviationsByCalMonth[$nextCalendarMonth];
                $seasonalOffset = array_sum($devs) / count($devs);
                $method = 'linear+seasonal';
            }
        }

        $forecastValue = max(0, round($trendForecast + $seasonalOffset, 2));

        $lastMonth = Carbon::createFromFormat('Y-m', end($months));
        $forecastMonth = $lastMonth->copy()->addMonth()->format('Y-m');

        return [
            'key' => $key, 'name' => $name, 'code' => $code,
            'months' => $months, 'values' => $values,
            'forecast_month' => $forecastMonth,
            'forecast_value' => $forecastValue,
            'method' => $method,
        ];
    }

    // ── Экспорт CSV текущей таблицы "Расход за период" (учитывает drill-down по бригаде/территории) ──
    public function exportCsv(Request $request)
    {
        $dimension = $request->get('dimension', 'all');
        $entityId  = $request->get('entity_id');
        [$from, $to] = $this->parseRange($request);

        $drillDown = $dimension !== 'all' && $entityId !== null && $entityId !== '';
        $rows = $drillDown
            ? $this->aggregateMaterials($from, $to, $dimension, $entityId)
            : $this->aggregateByDimension($from, $to, $dimension);
        $byMaterial = $dimension === 'all' || $drillDown;

        $filename = 'materials_' . $dimension . ($drillDown ? '_' . $entityId : '') . '_' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows, $dimension, $byMaterial) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM для корректной кодировки в Excel

            if ($byMaterial) {
                fputcsv($out, ['Код', 'Материал', 'Ед. изм.', 'Кол-во', 'Сумма, руб']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['code'], $r['label'], $r['unit'], $r['qty'], $r['amount']]);
                }
            } elseif ($dimension === 'brigade') {
                fputcsv($out, ['Бригада', 'Кол-во', 'Сумма, руб', 'Заявок', 'Сумма на заявку, руб']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['label'], $r['qty'], $r['amount'], $r['request_count'], $r['avg_amount_per_ticket']]);
                }
            } else {
                fputcsv($out, ['Территория', 'Кол-во', 'Сумма, руб', 'Заявок']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r['label'], $r['qty'], $r['amount'], $r['request_count']]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
