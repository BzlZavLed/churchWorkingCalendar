<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Invitation;
use App\Models\Objective;
use App\Policies\EventPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\ObjectivePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(Objective::class, ObjectivePolicy::class);
    }
}
