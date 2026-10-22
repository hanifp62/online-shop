<?php

namespace App\Providers;

use App\Http\Middleware\AuthenticateGuardSession;
use App\Models\Admin;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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

        Event::listen(Login::class, function (Login $event): void {
            $request = request();
            if (in_array($event->guard, ['web', 'admin'], true) && $request->hasSession()) {
                AuthenticateGuardSession::clear($request, $event->guard);
                AuthenticateGuardSession::remember($request, $event->guard, $event->user);
            }
        });

        // Clone the generator: sensitive mail links never inherit Host, while dev navigation stays request-local.
        $canonical = static function () {
            $origin = rtrim((string) config('app.url'), '/');
            $parts = parse_url($origin);
            if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['query']) || isset($parts['fragment'])
                || (app()->isProduction() && $parts['scheme'] !== 'https')) {
                throw new \LogicException('A trusted canonical application URL is required.');
            }
            $url = clone URL::getFacadeRoot();
            // Discard the shallow-cloned RouteUrlGenerator still bound to the original generator.
            $url->setRequest($url->getRequest());
            $url->forceRootUrl($origin);
            $url->forceScheme($parts['scheme']);

            return $url;
        };
        ResetPassword::createUrlUsing(fn ($user, string $token) => $canonical()->route('password.reset', [
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ]));
        VerifyEmail::createUrlUsing(fn ($user) => $canonical()->temporarySignedRoute('verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)), [
                'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
            ]));

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
