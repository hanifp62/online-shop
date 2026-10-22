<?php

namespace App\Support;

use Illuminate\Http\Request;

final class AuthSecurityPageProps
{
    public static function adminLogin(Request $request): array
    {
        return self::page($request, 'admin_ui.login', [
            'title', 'visual_label', 'image_alt', 'home_label', 'eyebrow', 'quote_heading', 'quote_body', 'heading', 'submit',
        ], 'admin_ui.common', ['back_maison', 'admin_access', 'email', 'password'], [
            'home' => route('home'), 'submit' => route('admin.login.process'),
        ], true);
    }

    public static function adminTotp(Request $request): array
    {
        return self::page($request, 'admin_ui.totp', [
            'title', 'heading', 'intro', 'code', 'submit', 'help', 'recovery_code', 'recovery_submit',
        ], 'admin_ui.common', ['admin_access', 'logout'], [
            'submit' => route('admin.totp.verify'), 'recovery' => route('admin.totp.recovery'), 'logout' => route('admin.logout'),
        ]);
    }

    public static function customerTotp(Request $request): array
    {
        return self::page($request, 'auth_ui.totp', [
            'title', 'eyebrow', 'heading', 'intro', 'code', 'submit', 'recovery_help', 'recovery_code', 'recovery_submit', 'back',
        ], 'auth_ui.common', [], [
            'submit' => route('login.totp.verify'), 'recovery' => route('login.totp.verify'), 'login' => route('login'),
        ]);
    }

    public static function reset(Request $request): array
    {
        $props = self::page($request, 'auth_ui.reset', [
            'title', 'quote_heading', 'quote_body', 'heading', 'intro', 'new_password', 'confirm_password', 'submit',
        ], 'auth_ui.common', ['visual_label', 'image_alt', 'email'], [
            'home' => route('home'), 'submit' => route('password.update'),
        ], true);
        $email = $request->session()->getOldInput('email', $request->query('email'));
        $props['old']['email'] = is_string($email) ? $email : '';

        return $props;
    }

    private static function page(Request $request, string $catalog, array $pageKeys, string $commonCatalog, array $commonKeys, array $urls, bool $email = false): array
    {
        $translate = static function (string $prefix, array $keys): array {
            $copy = [];
            foreach ($keys as $key) {
                $copy[$key] = __("$prefix.$key");
            }

            return $copy;
        };
        $oldEmail = $request->session()->getOldInput('email');
        $status = $request->session()->get('success');

        return [
            'copy' => ['page' => $translate($catalog, $pageKeys), 'common' => $translate($commonCatalog, $commonKeys)],
            'assets' => ['hero' => asset('prototype/assets/hero_couture.jpg')],
            'urls' => $urls,
            'old' => $email ? ['email' => is_string($oldEmail) ? $oldEmail : ''] : [],
            'status' => $catalog === 'admin_ui.login' && is_string($status) ? $status : null,
        ];
    }
}
