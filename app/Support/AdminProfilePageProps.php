<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** FR-PROFILE-20261025: read-only allowlist; credentials and old input never become page props. */
final class AdminProfilePageProps
{
    public static function show(Request $request, Admin $admin): array
    {
        $initials = collect(preg_split('/\s+/u', trim($admin->name), -1, PREG_SPLIT_NO_EMPTY))
            ->take(2)->map(fn (string $part) => mb_substr($part, 0, 1))->implode('');
        $totpEnabled = $admin->requiresTotp();
        $status = $request->session()->get('success');

        return [
            'locale' => app()->getLocale(),
            'copy' => __('profile_ui'),
            'identity' => [
                'name' => $admin->name,
                'email' => $admin->email,
                'initials' => mb_strtoupper($initials),
                'role' => $admin->isAdmin() ? __('profile_ui.role_admin') : __('profile_ui.role_staff'),
            ],
            'security' => ['totpEnabled' => $totpEnabled],
            'urls' => [
                'password' => route('admin.password.update'),
                'totpEnrol' => $admin->getRawOriginal('totp_secret') === null ? route('admin.totp.enrol') : null,
                'authEvents' => Gate::forUser($admin)->allows('view-audit-log') ? route('admin.auth-events.index') : null,
            ],
            'status' => is_string($status) ? $status : null,
        ];
    }
}
