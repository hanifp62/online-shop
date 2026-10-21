<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        User::observe(UserObserver::class);

        // Peran tidak dikenal (role dirusak/typo) otomatis ditolak: hanya 'admin' dan 'staff' yang punya kemampuan.
        foreach (['view-dashboard', 'manage-orders', 'manage-catalog', 'manage-concierge'] as $ability) {
            Gate::define($ability, fn (Admin $a) => in_array($a->role, Admin::ROLES, true));
        }
        foreach (['manage-refunds', 'view-audit-log', 'view-reports', 'manage-subscribers', 'manage-categories'] as $ability) {
            Gate::define($ability, fn (Admin $a) => $a->isAdmin());
        }

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', AppleProvider::class);
        });
    }
}
