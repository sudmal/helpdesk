<?php

namespace App\Http\Controllers;

use App\Models\IvrLog;
use Illuminate\Http\{Request, JsonResponse};
use Inertia\Inertia;

class IvrLogController extends Controller
{
    public function index(Request $request)
    {
        $q = IvrLog::latest();
        if ($request->filled('phone')) {
            $digits = preg_replace('/\D/', '', $request->phone);
            $suffix = substr($digits, -7);
            $q->where('phone', 'like', "%{$suffix}");
        }
        if ($request->filled('action')) { $q->where('action', $request->action); }
        $dateFrom = $request->has('date_from') ? $request->date_from : now()->toDateString();
        $dateTo   = $request->has('date_to')   ? $request->date_to   : now()->toDateString();
        if ($dateFrom) { $q->whereDate('created_at', '>=', $dateFrom); }
        if ($dateTo)   { $q->whereDate('created_at', '<=', $dateTo); }
        return Inertia::render('IvrLog/Index', [
            'logs'         => $q->paginate(50)->withQueryString(),
            'actionLabels' => IvrLog::$actionLabels,
            'filters'      => array_merge($request->only(['phone','action']),['date_from'=>$dateFrom,'date_to'=>$dateTo]),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $q = IvrLog::whereNotIn('action', ['not_found', 'api_error'])->latest();
        if ($request->filled('phone')) {
            $digits = preg_replace('/\D/', '', $request->phone);
            $suffix = substr($digits, -7);
            $q->where('phone', 'like', "%{$suffix}");
        }
        if ($request->filled('action')) { $q->where('action', $request->action); }
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->toDateString();
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : now()->toDateString();
        if ($dateFrom) { $q->whereDate('created_at', '>=', $dateFrom); }
        if ($dateTo)   { $q->whereDate('created_at', '<=', $dateTo); }
        $perPage = 200;
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $q->count();
        $logs    = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();
        return response()->json([
            'logs'         => $logs,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'last_page'    => (int) ceil($total / $perPage),
            'actionLabels' => IvrLog::$actionLabels,
        ]);
    }
}
