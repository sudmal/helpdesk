<?php

namespace App\Http\Controllers;

use App\Models\Call;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $q = Call::with('address')->latest('called_at');

        if ($request->filled('phone')) {
            $digits = preg_replace('/\D/', '', $request->phone);
            $suffix = substr($digits, -7);
            $q->where('phone', 'like', "%{$suffix}");
        }
        if ($request->filled('address')) {
            $q->where('address_string', 'like', '%' . $request->address . '%');
        }
        // default to today if no date filter provided (initial load)
        $dateFrom = $request->has('date_from') ? $request->date_from : now()->toDateString();
        $dateTo   = $request->has('date_to')   ? $request->date_to   : now()->toDateString();
        if ($dateFrom) { $q->whereDate('called_at', '>=', $dateFrom); }
        if ($dateTo)   { $q->whereDate('called_at', '<=', $dateTo);   }
        if ($request->matched === 'yes') {
            $q->whereNotNull('address_id');
        } elseif ($request->matched === 'no') {
            $q->whereNull('address_id');
        }
        if ($request->filled('queue_status')) {
            $q->where('queue_status', $request->queue_status);
        }

        $qStats = clone $q;
        $stats = [
            'total'     => (clone $qStats)->count(),
            'answered'  => (clone $qStats)->where('queue_status', 'answered')->count(),
            'missed'    => (clone $qStats)->where('queue_status', 'missed')->count(),
            'no_status' => (clone $qStats)->whereNull('queue_status')->count(),
        ];

        $calls = $q->paginate(50)->withQueryString();

        return Inertia::render('Calls/Index', [
            'calls'   => $calls,
            'stats'   => $stats,
            'filters' => array_merge(
                $request->only(['phone', 'address', 'matched', 'queue_status']),
                ['date_from' => $dateFrom, 'date_to' => $dateTo]
            ),
        ]);
    }
}