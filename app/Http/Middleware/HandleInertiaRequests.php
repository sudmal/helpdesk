<?php

namespace App\Http\Middleware;

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
        ]);
    }
}

