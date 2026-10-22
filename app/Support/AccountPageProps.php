<?php

namespace App\Support;

use App\Http\Controllers\SocialAuthController;
use App\Models\ConciergeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

final class AccountPageProps
{
    public static function show(Request $request, User $user, Collection $history): array
    {
        $profile = [];
        foreach (['name', 'first_name', 'last_name', 'mobile', 'address1', 'address2'] as $field) {
            $value = $request->session()->getOldInput($field, $user->{$field});
            $profile[$field] = is_string($value) ? $value : '';
        }
        $reminders = $request->session()->getOldInput('cart_reminder_opt_in', $user->cart_reminder_opt_in);
        $profile['cart_reminder_opt_in'] = in_array($reminders, [true, 1, '1'], true);
        $profile['email'] = $user->email;
        $linked = $user->socialAccounts()->pluck('provider')->all();
        $providers = [];
        foreach (['google' => 'Google', 'apple' => 'Apple'] as $id => $label) {
            if (in_array($id, $linked, true)) {
                $providers[] = ['id' => $id, 'linked' => true,
                    'label' => __('account_ui.social.linked', ['provider' => $label]),
                    'confirm' => __('account_ui.social.confirm', ['provider' => $label]),
                    'url' => route('social.unlink', $id)];
            } elseif ($id === 'google' && SocialAuthController::enabled($id)) {
                $providers[] = ['id' => $id, 'linked' => false,
                    'label' => __('account_ui.social.link'), 'url' => route('social.link', $id)];
            }
        }
        $hasTotp = $user->hasTotp();
        $verified = $user->hasVerifiedEmail();
        $show = __('account_ui.show');
        if ($verified) {
            unset($show['unverified']);
        }
        $status = $request->session()->get('success');

        return [
            'copy' => [
                'common' => __('account_ui.common'), 'show' => $show,
                'profile' => __('account_ui.profile'), 'social' => __('account_ui.social'),
                'totp' => __('account_ui.totp'), 'privacy' => __('account_ui.privacy'),
                'history' => __('account_ui.history'),
                'greeting' => __('account_ui.show.greeting', ['name' => $user->first_name ?? $user->name]),
                'unverified' => $verified ? null : __('account_ui.show.unverified', ['email' => $user->email]),
                'deletePhrase' => __('account_ui.privacy.phrase', ['phrase' => 'HAPUS AKUN SAYA']),
            ],
            'assets' => ['hero' => asset('prototype/assets/hero_couture.jpg')],
            'urls' => [
                'home' => route('home'), 'logout' => route('logout'), 'update' => route('account.update'),
                'resend' => route('verification.send'), 'export' => route('account.export'),
                'destroy' => route('account.destroy'), 'totpEnrol' => route('account.totp.enrol'),
                'totpDestroy' => route('account.totp.destroy'),
            ],
            'profile' => $profile,
            'verified' => $verified, 'hasPassword' => (bool) $user->has_password,
            'providers' => $providers,
            'totp' => ['enabled' => $hasTotp, 'active' => $hasTotp
                ? __('account_ui.totp.active', ['date' => $user->totp_enabled_at->format('d M Y'), 'count' => $user->totpRecoveryCodes()->count()]) : null],
            'status' => is_string($status) ? $status : null,
            // Defense in depth: never serialize another owner's row, even if a caller changes its query.
            'history' => $history->filter(fn (ConciergeRequest $item) => (string) $item->user_id === (string) $user->id)
                ->take(10)->map(fn (ConciergeRequest $item) => [
                    'service' => $item->service, 'status' => $item->status,
                    'statusLabel' => Lang::has('account_ui.status.'.$item->status) ? __('account_ui.status.'.$item->status) : $item->status,
                    'schedule' => ($item->preferred_date?->format('d M Y') ?? __('account_ui.history.unscheduled')).' '.($item->preferred_time ?? ''),
                    'submitted' => __('account_ui.history.submitted', ['date' => $item->created_at?->format('d M Y H:i')]),
                    'notes' => $item->notes,
                ])->values()->all(),
        ];
    }
}
