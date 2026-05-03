<?php

namespace App\Http\Controllers;

use App\Services\LanBillingService;
use Illuminate\Http\Request;

class LanBillingController extends Controller
{
    public function __construct(private LanBillingService $billing) {}

    /**
     * AJAX: поиск абонента по телефону или договору
     * GET /lanbilling/lookup?phone=79491234567
     * GET /lanbilling/lookup?contract=12345
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'phone'    => 'nullable|string',
            'contract' => 'nullable|string',
        ]);

        $raw = null;

        if ($request->filled('phone')) {
            $raw = $this->billing->findByPhone($request->phone);
        } elseif ($request->filled('contract')) {
            $raw = $this->billing->findByContract($request->contract);
        } else {
            return response()->json(['error' => 'Укажите phone или contract'], 422);
        }

        if (!$raw) {
            return response()->json(['error' => 'Абонент не найден'], 404);
        }

        return response()->json($this->billing->mapToAddress($raw));
    }
}
