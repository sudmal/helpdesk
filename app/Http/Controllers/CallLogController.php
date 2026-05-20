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
        if ($request->filled('date_from')) {
            $q->whereDate('called_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('called_at', '<=', $request->date_to);
        }
        if ($request->matched === 'yes') {
            $q->whereNotNull('address_id');
        } elseif ($request->matched === 'no') {
            $q->whereNull('address_id');
        }

        $calls = $q->paginate(50)->withQueryString();

        return Inertia::render('Calls/Index', [
            'calls'   => $calls,
            'filters' => $request->only(['phone', 'address', 'date_from', 'date_to', 'matched']),
        ]);
    }
}