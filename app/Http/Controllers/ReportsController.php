<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate   = Carbon::parse($to)->endOfDay();

        return Inertia::render('Reports/Index', [
            'from'               => $from,
            'to'                 => $to,
            'brigadeLoad'        => $this->brigadeLoad($fromDate, $toDate),
            'territoryFrequency' => $this->territoryFrequency($fromDate, $toDate),
            'materialDynamics'   => $this->materialDynamics($fromDate, $toDate),
            'deadlineCompliance' => $this->deadlineCompliance($fromDate, $toDate),
        ]);
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
        $weekly = DB::table('ticket_materials as tm')
            ->join('tickets as t', 'tm.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('
                YEARWEEK(tm.created_at, 1) as week_key,
                DATE_FORMAT(MIN(tm.created_at), "%d.%m") as week_label,
                ROUND(SUM(tm.quantity), 2) as qty,
                ROUND(SUM(tm.quantity * tm.price_at_time), 2) as amount
            ')
            ->groupBy('week_key')
            ->orderBy('week_key')
            ->get();

        $top = DB::table('ticket_materials as tm')
            ->join('tickets as t', 'tm.ticket_id', '=', 't.id')
            ->whereBetween('tm.created_at', [$from, $to])
            ->whereNull('t.deleted_at')
            ->selectRaw('
                tm.material_name as name,
                tm.material_code as code,
                tm.material_unit as unit,
                ROUND(SUM(tm.quantity), 2) as qty,
                ROUND(SUM(tm.quantity * tm.price_at_time), 2) as amount
            ')
            ->groupBy(DB::raw('COALESCE(tm.material_code, tm.material_name)'), 'tm.material_name', 'tm.material_code', 'tm.material_unit')
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

        $totals = DB::table('tickets as t')
            ->join('ticket_statuses as ts', 't.status_id', '=', 'ts.id')
            ->where('ts.is_final', 1)
            ->whereBetween('t.closed_at', [$from, $to])
            ->whereNotNull('t.scheduled_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COUNT(*) as total, SUM(t.closed_at <= t.scheduled_at) as on_time')
            ->first();

        return [
            'labels'  => $rows->pluck('brigade')->toArray(),
            'on_time' => $rows->pluck('on_time')->map(fn($v) => (int)$v)->toArray(),
            'overdue' => $rows->pluck('overdue')->map(fn($v) => (int)$v)->toArray(),
            'summary' => [
                'total'   => (int)($totals->total ?? 0),
                'on_time' => (int)($totals->on_time ?? 0),
                'pct'     => $totals && $totals->total > 0
                    ? round(100 * $totals->on_time / $totals->total, 1)
                    : 0,
            ],
        ];
    }
}
