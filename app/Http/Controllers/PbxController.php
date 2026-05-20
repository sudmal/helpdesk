<?php

namespace App\Http\Controllers;

use App\Models\{Call, Address, Ticket};
use Illuminate\Http\{Request, JsonResponse};

class PbxController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if ($token !== config('services.pbx.token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $phone         = $this->normalizePhone($request->input('phone', ''));
        $addressString = trim($request->input('address', ''));

        if (!$phone) {
            return response()->json(['status' => 'skipped']);
        }

        [$addressId, $apartment] = $addressString
            ? $this->matchAddress($addressString)
            : [null, null];

        Call::create([
            'phone'          => $phone,
            'address_string' => $addressString ?: null,
            'apartment'      => $apartment,
            'address_id'     => $addressId,
            'called_at'      => now(),
            'event'          => $request->input('event', 'incoming'),
            'payload'        => $request->except('token'),
        ]);

        return response()->json(['status' => 'ok', 'address_id' => $addressId]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $phone = $this->normalizePhone($request->input('phone', ''));

        if (strlen($phone) < 7) {
            return response()->json(['last_call' => null, 'tickets' => []]);
        }

        $suffix = substr($phone, -7);

        $lastCall = Call::where('phone', 'like', "%{$suffix}")
            ->with('address')
            ->latest('called_at')
            ->first();

        $tickets = Ticket::with(['address', 'status', 'type'])
            ->where('phone', 'like', "%{$suffix}")
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'number'     => $t->number,
                'address'    => $t->address?->full_address,
                'address_id' => $t->address_id,
                'apartment'  => $t->apartment,
                'type'       => $t->type?->name,
                'status'     => $t->status?->name,
                'date'       => $t->scheduled_at?->format('d.m.Y'),
            ]);

        return response()->json([
            'last_call' => $lastCall ? [
                'called_at'      => $lastCall->called_at->format('d.m.Y H:i'),
                'address_string' => $lastCall->address_string,
                'address_id'     => $lastCall->address_id,
                'apartment'      => $lastCall->apartment,
                'address_full'   => $lastCall->address?->full_address,
            ] : null,
            'tickets' => $tickets,
        ]);
    }

    // Парсинг строки биллинга: "кв-л Железнодорожный дом 15 кв 63"
    // Возвращает [address_id|null, apartment|null]
    private function matchAddress(string $raw): array
    {
        $apartment = null;
        if (preg_match('/\bкв[\.\s]+(\S+)/iu', $raw, $a)) {
            $apartment = $a[1];
        }

        if (!preg_match('/^(.+?)\s+дом\s+(\S+)/iu', $raw, $m)) {
            return [null, $apartment];
        }

        $street   = trim($m[1]);
        $building = trim($m[2]);

        $address = Address::where('building', $building)
            ->where('street', 'like', '%' . mb_substr($street, -5) . '%')
            ->first();

        return [$address?->id, $apartment];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        return $digits;
    }
}