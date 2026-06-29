<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\IvrLog;
use Carbon\Carbon;
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
            $parts     = preg_split('/\s+/', trim($request->address));
            $textParts = [];
            $numParts  = [];
            foreach ($parts as $part) {
                if ($part === '') continue;
                if (preg_match('/^\d+$/', $part)) {
                    $numParts[] = $part;
                } else {
                    $textParts[] = $part;
                }
            }
            $q->where(function ($query) use ($textParts, $numParts) {
                foreach ($textParts as $text) {
                    $query->where('address_string', 'like', '%' . $text . '%');
                }
                if (isset($numParts[0])) {
                    $query->where('address_string', 'like', '%дом ' . $numParts[0] . '%');
                }
                if (isset($numParts[1])) {
                    $query->where('address_string', 'like', '%кв ' . $numParts[1] . '%');
                }
            });
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
        if ($request->filled('ivr_action')) {
            $ivrAction = $request->ivr_action;
            $q->whereExists(function ($sub) use ($ivrAction) {
                $sub->from('ivr_logs')
                    ->whereColumn('ivr_logs.phone', 'calls.phone')
                    ->where('ivr_logs.action', $ivrAction)
                    ->whereRaw('ivr_logs.created_at BETWEEN DATE_SUB(calls.called_at, INTERVAL 30 MINUTE) AND DATE_ADD(calls.called_at, INTERVAL 2 MINUTE)');
            });
        }

        $qStats = clone $q;
        $stats = [
            'total'     => (clone $qStats)->count(),
            'answered'  => (clone $qStats)->where('queue_status', 'answered')->count(),
            'missed'    => (clone $qStats)->where('queue_status', 'missed')->count(),
            'no_status' => (clone $qStats)->whereNull('queue_status')->count(),
        ];

        $calls = $q->paginate(50)->withQueryString();

        // Обогащаем IVR-данными: для каждого звонка — последняя запись ivr_logs в окне [-30..+2] мин
        $callsData = $calls->getCollection();
        if ($callsData->isNotEmpty()) {
            $phones   = $callsData->pluck('phone')->unique()->values()->all();
            $minDate  = Carbon::parse($callsData->min('called_at'))->subMinutes(30);
            $maxDate  = Carbon::parse($callsData->max('called_at'))->addMinutes(2);

            $ivrMap = IvrLog::whereIn('phone', $phones)
                ->whereBetween('created_at', [$minDate, $maxDate])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('phone');

            $callsData->transform(function ($call) use ($ivrMap) {
                $calledAt = Carbon::parse($call->called_at);
                $ivr = ($ivrMap->get($call->phone) ?? collect())->first(function ($log) use ($calledAt) {
                    $t = Carbon::parse($log->created_at);
                    return $t->between($calledAt->copy()->subMinutes(30), $calledAt->copy()->addMinutes(2));
                });
                $call->ivr_subscriber_name = $ivr?->subscriber_name;
                $call->ivr_agreement_num   = $ivr?->agreement_num;
                $call->ivr_address         = $ivr?->address;
                $call->ivr_balance         = $ivr?->balance;
                $call->ivr_blocked         = $ivr?->blocked;
                $call->ivr_action          = $ivr?->action;
                return $call;
            });
        }

        return Inertia::render('Calls/Index', [
            'calls'   => $calls,
            'stats'   => $stats,
            'filters' => array_merge(
                $request->only(['phone', 'address', 'matched', 'queue_status', 'ivr_action']),
                ['date_from' => $dateFrom, 'date_to' => $dateTo]
            ),
            'actionLabels' => IvrLog::$actionLabels,
        ]);
    }
}