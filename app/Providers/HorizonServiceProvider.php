<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request): bool {
            if (Gate::check('viewHorizon', [$request->user()])) {
                return true;
            }

            return (bool) config('horizon.allow_local_bypass', false)
                && app()->environment('local');
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon when the local bypass is off
     * or when the user must be an admin even in local.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => (bool) optional($user)?->isAdmin());
    }
}
