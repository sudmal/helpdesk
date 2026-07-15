<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    private function parseRange(Request $request): array
    {
        $from = $request->get('from', now()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }

    public function brigadeLoadData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->brigadeLoad($from, $to));
    }

    public function territoryFrequencyData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->territoryFrequency($from, $to));
    }

    public function materialDynamicsData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->materialDynamics($from, $to));
    }

    public function deadlineComplianceData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->deadlineCompliance($from, $to));
    }

    public function distributionData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->distribution($from, $to));
    }

    public function callStatsData(Request $request)
    {
        [$from, $to] = $this->parseRange($request);
        return response()->json($this->callStats($from, $to));
    }

    private function brigadeLoad(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('tickets as t')
            ->join('brigades as b', 't.brigade_id', '=', 'b.id')
            ->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
            ->whereBetween('t.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('b.name as brigade, COUNT(*) as total, SUM(ts.is_final) as closed')
            ->groupBy('b.id', 'b.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('brigade')->toArray(),
            'total'  => $rows->pluck('total')->map(fn($v) => (int)$v)->toArray(),
            'closed' => $rows->pluck('closed')->map(fn($v) => (int)$v)->toArray(),
        ];
    }

    private function territoryFrequency(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('tickets as t')
            ->join('addresses as a', 't.address_id', '=', 'a.id')
            ->join('territories as ter', 'a.territory_id', '=', 'ter.id')
            ->whereBetween('t.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('ter.name as territory, COUNT(*) as total')
            ->groupBy('ter.id', 'ter.name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('territory')->toArray(),
            'values' => $rows->pluck('total')->map(fn($v) => (int)$v)->toArray(),
        ];
    }

    private function materialDynamics(Carbon $from, Carbon $to): array
    {
        $union = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('tm.created_at, tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time')
            ->unionAll(
                DB::table('connection_request_materials as crm')
                    ->join('connection_requests as cr', 'crm.connection_request_id', '=', 'cr.id')
                    ->whereBetween('crm.created_at', [$from, $to])
                    ->selectRaw('crm.created_at, crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time')
            );

        $weekly = DB::table(DB::raw("({$union->toSql()}) as all_materials"))
            ->mergeBindings($union)
            ->selectRaw('
                YEARWEEK(created_at, 1) as week_key,
                DATE_FORMAT(MIN(created_at), "%d.%m") as week_label,
                ROUND(SUM(quantity), 2) as qty,
                ROUND(SUM(quantity * price_at_time), 2) as amount
            ')
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $union2 = DB::table('act_materials as tm')
            ->join('acts as a', 'tm.act_id', '=', 'a.id')
            ->join('tickets as t', 'a.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('tm.material_name, tm.material_code, tm.material_unit, tm.quantity, tm.price_at_time')
            ->unionAll(
                DB::table('connection_request_materials as crm')
                    ->join('connection_requests as cr', 'crm.connection_request_id', '=', 'cr.id')
                    ->whereBetween('crm.created_at', [$from, $to])
                    ->selectRaw('crm.material_name, crm.material_code, crm.material_unit, crm.quantity, crm.price_at_time')
            );

        $top = DB::table(DB::raw("({$union2->toSql()}) as all_materials"))
            ->mergeBindings($union2)
            ->selectRaw('
                material_name as name,
                material_code as code,
                material_unit as unit,
                ROUND(SUM(quantity), 2) as qty,
                ROUND(SUM(quantity * price_at_time), 2) as amount
            ')
            ->groupBy(DB::raw('COALESCE(material_code, material_name)'), 'material_name', 'material_code', 'material_unit')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return [
            'weekly' => [
                'labels' => $weekly->pluck('week_label')->toArray(),
                'qty'    => $weekly->pluck('qty')->map(fn($v) => (float)$v)->toArray(),
                'amount' => $weekly->pluck('amount')->map(fn($v) => (float)$v)->toArray(),
            ],
            'top' => $top->toArray(),
        ];
    }

    private function deadlineCompliance(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('tickets as t')
            ->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
            ->join('brigades as b', 't.brigade_id', '=', 'b.id')
            ->where('ts.is_final', 1)
            ->whereBetween('t.closed_at', [$from, $to])
            ->whereNotNull('t.scheduled_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('
                b.name as brigade,
                COUNT(*) as total,
                SUM(t.closed_at <= t.scheduled_at) as on_time,
                SUM(t.closed_at > t.scheduled_at) as overdue
            ')
            ->groupBy('b.id', 'b.name')
            ->orderByDesc('total')
            ->get();

        $totalOnTime = $rows->sum('on_time');
        $totalAll    = $rows->sum('total');

        return [
            'labels'  => $rows->pluck('brigade')->toArray(),
            'on_time' => $rows->pluck('on_time')->map(fn($v) => (int)$v)->toArray(),
            'overdue' => $rows->pluck('overdue')->map(fn($v) => (int)$v)->toArray(),
            'summary' => [
                'total'   => (int)$totalAll,
                'on_time' => (int)$totalOnTime,
                'pct'     => $totalAll > 0 ? round(100 * $totalOnTime / $totalAll, 1) : 0,
            ],
        ];
    }

    private function distribution(Carbon $from, Carbon $to): array
    {
        $serviceTypes = DB::table('service_types')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        $byDayRaw = DB::table('tickets as t')
            ->whereBetween('t.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.service_type_id')
            ->selectRaw('t.service_type_id, DAY(t.created_at) as day, COUNT(*) as cnt')
            ->groupBy('t.service_type_id', DB::raw('DAY(t.created_at)'))
            ->get()
            ->groupBy('service_type_id');

        $byDowRaw = DB::table('tickets as t')
            ->whereBetween('t.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.service_type_id')
            ->selectRaw('t.service_type_id, DAYOFWEEK(t.created_at) as dow, COUNT(*) as cnt')
            ->groupBy('t.service_type_id', DB::raw('DAYOFWEEK(t.created_at)'))
            ->get()
            ->groupBy('service_type_id');

        $dayLabels = range(1, 31);
        $dowOrder  = [2, 3, 4, 5, 6, 7, 1]; // MySQL: 1=Вс,2=Пн..7=Сб
        $dowLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        $fallback  = ['#3b82f6','#ef4444','#22c55e','#f59e0b','#8b5cf6','#06b6d4','#f97316','#ec4899'];

        $byDay = [];
        $byDow = [];

        foreach ($serviceTypes as $i => $st) {
            $color = $st->color ?: $fallback[$i % count($fallback)];

            $dayData = $byDayRaw->get($st->id, collect())->keyBy('day');
            $byDay[] = [
                'name'  => $st->name,
                'color' => $color,
                'data'  => array_map(fn($d) => (int)($dayData[$d]->cnt ?? 0), $dayLabels),
            ];

            $dowData = $byDowRaw->get($st->id, collect())->keyBy('dow');
            $byDow[] = [
                'name'  => $st->name,
                'color' => $color,
                'data'  => array_map(fn($d) => (int)($dowData[$d]->cnt ?? 0), $dowOrder),
            ];
        }

        return [
            'byDay'     => ['labels' => $dayLabels, 'datasets' => $byDay],
            'byWeekday' => ['labels' => $dowLabels,  'datasets' => $byDow],
        ];
    }

    private function callStats(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('call_daily_stats')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('
                hour,
                SUM(total_calls)                  as total_calls,
                SUM(answered)                     as answered,
                SUM(missed)                       as missed,
                ROUND(AVG(avg_wait_sec), 1)       as avg_wait_sec,
                MAX(max_wait_sec)                 as max_wait_sec,
                MAX(max_queue_depth)              as max_queue_depth,
                ROUND(AVG(avg_queue_depth), 1)    as avg_queue_depth,
                MAX(avg_operators)                as avg_operators
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $r      = $rows->get($h);
            $total  = (int)($r?->total_calls ?? 0);
            $ans    = (int)($r?->answered    ?? 0);
            $missed = (int)($r?->missed      ?? 0);
            $hours[] = [
                'hour'         => $h,
                'total'        => $total,
                'answered'     => $ans,
                'missed'       => $missed,
                'miss_rate'    => $total > 0 ? round(100 * $missed / $total, 1) : 0,
                'avg_wait'     => $r?->avg_wait_sec    !== null ? (float)$r->avg_wait_sec    : null,
                'max_wait'     => $r?->max_wait_sec    !== null ? (int)$r->max_wait_sec      : null,
                'max_queue'    => $r?->max_queue_depth !== null ? (int)$r->max_queue_depth   : null,
                'avg_queue'    => $r?->avg_queue_depth !== null ? round((float)$r->avg_queue_depth, 1) : null,
                'avg_operators'=> $r?->avg_operators   !== null ? (int)$r->avg_operators     : null,
            ];
        }

        $totalAll    = array_sum(array_column($hours, 'total'));
        $totalAns    = array_sum(array_column($hours, 'answered'));
        $totalMissed = array_sum(array_column($hours, 'missed'));
        $peakHour    = collect($hours)->sortByDesc('total')->first();
        $worstHour   = collect($hours)->where('total', '>', 2)->sortByDesc('miss_rate')->first();

        return [
            'hours'   => $hours,
            'summary' => [
                'total'       => $totalAll,
                'answered'    => $totalAns,
                'missed'      => $totalMissed,
                'answer_rate' => $totalAll > 0 ? round(100 * $totalAns / $totalAll, 1) : 0,
                'peak_hour'   => $peakHour  ? $peakHour['hour']  : null,
                'worst_hour'  => $worstHour ? $worstHour['hour'] : null,
            ],
        ];
    }

}
