<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Policies\TicketPolicy;
use App\Observers\TicketObserver;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends AuthServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Регистрируем Observer
        Ticket::observe(TicketObserver::class);

        // Gate для проверки настроек
        Gate::define('manage-settings', function ($user) {
            return $user->canManageSettings();
        });
    }
}
