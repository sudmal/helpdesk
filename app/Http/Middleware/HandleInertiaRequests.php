<?php

namespace App\Http\Middleware;

use App\Models\Act;
use App\Models\Brigade;
use App\Models\ConnectionRequest;
use App\Models\ServiceRequest;
use App\Models\Territory;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user'               => $request->user()?->load('role'),
                'foreman_brigade_id' => $request->user()?->isForeman()
                    ? \App\Models\Brigade::where('foreman_id', $request->user()->id)->value('id')
                    : null,
            ],
            'flash' => [
                'success'       => session('success'),
                'error'         => session('error'),
                'import_result' => session('import_result'),
            ],
            'closeReasons'      => config('tickets.close_reasons', []),
            'serviceRequestAlerts' => function () use ($request) {
                $user = $request->user();
                if (!$user) return ['pending' => 0];
                return [
                    'pending' => ServiceRequest::where('status', 'pending')->count(),
                ];
            },
            'connectionAlerts'  => function () use ($request) {
                $user = $request->user();
                if (!$user) return ['pending' => 0, 'needs_callback' => 0];

                $base = ConnectionRequest::query();

                if (!$user->hasPermission('*') && !$user->hasPermission('settings.*')) {
                    $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
                    $ids = collect();
                    if ($brigadeIds->isNotEmpty()) {
                        $ids = $ids->merge(
                            Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
                        );
                    }
                    $ids = $ids->merge($user->territories()->pluck('territories.id'))->unique();
                    if ($ids->isNotEmpty()) {
                        $base->whereIn('territory_id', $ids);
                    }
                }

                return [
                    'pending'        => (clone $base)->where('status', 'pending')->count(),
                    'needs_callback' => (clone $base)->where('needs_callback', true)->count(),
                ];
            },
            'actsAlerts' => function () use ($request) {
                $user = $request->user();
                if (!$user) return ['pending' => 0];

                $isActor    = $user->isForeman() || $user->isPeo() || $user->isLogistics() || $user->isSubscriberDept();
                $isOverseer = $user->isAdmin() || $user->isHeadSupport();
                if (!$isActor && !$isOverseer) return ['pending' => 0];

                $base = Act::query();

                if (!$isOverseer) {
                    $brigadeIds = Brigade::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id');
                    $territoryIds = collect();
                    if ($brigadeIds->isNotEmpty()) {
                        $territoryIds = $territoryIds->merge(
                            Territory::whereHas('brigades', fn($q) => $q->whereIn('brigades.id', $brigadeIds))->pluck('id')
                        );
                    }
                    $territoryIds = $territoryIds->merge($user->territories()->pluck('territories.id'))->unique();
                    if ($territoryIds->isNotEmpty()) {
                        $base->whereHas('ticket.address', fn($q) => $q->whereIn('territory_id', $territoryIds));
                    }
                }

                // Для бригадира/ПЭО/Логистики/Абонотдела — только их этап (см. память project-acts-feature).
                // Для admin/head_support — оверсайт-счётчик по всей цепочке, без territory-скоупа.
                $pending = match (true) {
                    $user->isForeman()       => (clone $base)->where('status', 'pending_foreman')->count(),
                    $user->isPeo()           => (clone $base)->whereIn('status', ['approved', 'processing'])->where('type', 'regular')->whereNull('peo_processed_at')->count(),
                    $user->isLogistics()     => (clone $base)->whereIn('status', ['approved', 'processing'])->whereNull('logistics_processed_at')->count(),
                    $user->isSubscriberDept() => (clone $base)->where('status', 'pending_subscriber_dept')->count(),
                    default => (clone $base)->where(function ($q) {
                        $q->where('status', 'pending_foreman')
                          ->orWhere('status', 'pending_subscriber_dept')
                          ->orWhere(function ($q2) { $q2->where('type', 'regular')->whereIn('status', ['approved', 'processing'])->whereNull('peo_processed_at'); })
                          ->orWhere(function ($q2) { $q2->whereIn('status', ['approved', 'processing'])->whereNull('logistics_processed_at'); });
                    })->count(),
                };

                return ['pending' => $pending];
            },
        ]);
    }
}

