<?php

namespace App\Support;

use App\Http\Controllers\SocialAuthController;
use Illuminate\Http\Request;

final class AuthPageProps
{
    public static function login(Request $request): array
    {
        $intended = IntendedRedirect::sanitize($request->query('intended'));
        $params = $intended !== null ? ['intended' => $intended] : [];

        return self::page($request, 'login', ['email'], [
            'submit' => route('login.process', $params),
            'forgotPassword' => route('password.request'),
            'register' => route('register', $params),
        ], self::providers($intended));
    }

    public static function register(Request $request): array
    {
        $intended = IntendedRedirect::sanitize($request->query('intended'));
        $params = $intended !== null ? ['intended' => $intended] : [];

        return self::page($request, 'register', ['name', 'first_name', 'last_name', 'email', 'mobile', 'address1'], [
            'submit' => route('register.store', $params),
            'login' => route('login', $params),
        ], self::providers($intended));
    }

    public static function forgotPassword(Request $request): array
    {
        return self::page($request, 'forgotten', ['email'], [
            'submit' => route('password.email'),
            'login' => route('login'),
        ]);
    }

    private static function page(Request $request, string $catalog, array $fields, array $urls, array $providers = []): array
    {
        $old = [];
        foreach ($fields as $field) {
            $value = $request->session()->getOldInput($field);
            $old[$field] = is_string($value) ? $value : '';
        }
        $status = $request->session()->get('success');

        // Errors come from Inertia's shared middleware; CSRF stays in the document, not page/history props.
        return [
            'copy' => [
                'common' => __('auth_ui.common'),
                'page' => __("auth_ui.$catalog"),
                'social' => __('auth_ui.social'),
            ],
            'assets' => [
                'hero' => asset('prototype/assets/hero_couture.jpg'),
                'heroSrcSet' => asset('prototype/assets/hero_couture-600.webp').' 600w, '.asset('prototype/assets/hero_couture.webp').' 896w',
            ],
            'urls' => ['home' => route('home'), ...$urls],
            'old' => $old,
            'providers' => $providers,
            'status' => is_string($status) ? $status : null,
        ];
    }

    private static function providers(?string $intended = null): array
    {
        $providers = [];
        foreach (['google' => 'Google', 'apple' => 'Apple'] as $provider => $label) {
            if (SocialAuthController::visible($provider)) {
                $routeParams = $intended !== null ? ['provider' => $provider, 'intended' => $intended] : $provider;
                $providers[] = [
                    'id' => $provider,
                    'url' => route('social.redirect', $routeParams),
                    'label' => __('auth_ui.social.continue', ['provider' => $label]),
                ];
            }
        }

        return $providers;
    }
}
