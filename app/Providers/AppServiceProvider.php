<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\Act;
use App\Policies\TicketPolicy;
use App\Policies\ActPolicy;
use App\Observers\TicketObserver;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends AuthServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        Act::class => ActPolicy::class,
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
