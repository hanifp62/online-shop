<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <x-theme-preference />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('account_ui.show.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @isset($page)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    @else
        <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endisset
</head>
<body>
<x-private-page-lifecycle />
<x-react-root :page="$page ?? null">
<main class="auth-page">
    <section class="auth-visual" aria-label="{{ __('account_ui.common.brand_world') }}">
        <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="{{ __('account_ui.common.editorial') }}" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('account_ui.common.home') }}"><x-brand-mark /></a>
        <div class="auth-quote">
            <span class="eyebrow light">Le Salon Privé</span>
            <h2>{{ __('account_ui.show.salon') }}</h2>
            <p>{{ __('account_ui.show.intro') }}</p>
        </div>
    </section>
    <section class="auth-content profile-content">
        <a class="auth-back text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>{{ __('account_ui.show.back') }}</span>
        </a>
        <div class="auth-card profile-page profile-customer" style="max-width: 580px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;">
                <span class="eyebrow" style="margin-bottom:0;">{{ __('account_ui.show.account_label') }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-link" style="border:0;background:none;cursor:pointer;">{{ __('account_ui.show.logout') }}</button></form>
            </div>
            
            <header class="profile-identity">
                <span class="profile-initials" aria-hidden="true">{{ mb_strtoupper(collect(preg_split('/\s+/u', trim($user->name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) }}</span>
                <div><h1>{{ __('account_ui.show.greeting', ['name' => $user->first_name ?? $user->name]) }}</h1><p>{{ $user->email }}</p></div>
            </header>
            <nav class="profile-section-nav" aria-label="{{ __('account_ui.show.navigation') }}">
                <a href="#account-details">{{ __('account_ui.show.details') }}</a>
                <a href="#account-security">{{ __('account_ui.show.security') }}</a>
                <a href="#account-privacy">{{ __('account_ui.privacy.heading') }}</a>
                <a href="#account-history">{{ __('account_ui.history.heading') }}</a>
            </nav>
            <div class="field profile-theme-field">
                <label for="account-theme">{{ __('account_ui.show.theme') }}</label>
                <select id="account-theme" data-theme-select>
                    @foreach (['system', 'light', 'dark'] as $mode)
                        <option value="{{ $mode }}">{{ __('account_ui.show.theme_'.$mode) }}</option>
                    @endforeach
                </select>
            </div>

            @if (session('success'))
                <div class="auth-errors" role="status" style="border-color: green"><ul><li>{{ session('success') }}</li></ul></div>
            @endif
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @unless ($user->hasVerifiedEmail())
                <div class="auth-errors" role="status" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <span>{{ __('account_ui.show.unverified', ['email' => $user->email]) }}</span>
                    <form method="POST" action="{{ route('verification.send') }}">@csrf<button type="submit" class="text-link" style="border:0;background:none;cursor:pointer;min-height:44px">{{ __('account_ui.show.resend') }}</button></form>
                </div>
            @endunless

            <section class="profile-section" id="account-details" aria-labelledby="account-details-heading">
            <h2 id="account-details-heading">{{ __('account_ui.show.details') }}</h2>
            <p class="profile-note">{{ __('account_ui.show.contact_intro') }}</p>
            <form method="POST" action="{{ route('account.update') }}" data-auth-form novalidate>
                @csrf
                @method('PATCH')
                <div class="field"><label for="name">{{ __('account_ui.profile.name') }}</label><input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<small id="name-error">{{ $message }}</small>@enderror</div>
                <div class="form-row">
                    <div class="field"><label for="first_name">{{ __('account_ui.profile.first_name') }}</label><input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first-name-error" @enderror>@error('first_name')<small id="first-name-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="last_name">{{ __('account_ui.profile.last_name') }}</label><input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last-name-error" @enderror>@error('last_name')<small id="last-name-error">{{ $message }}</small>@enderror</div>
                </div>
                <div class="field"><label for="mobile">{{ __('account_ui.profile.mobile') }}</label><input id="mobile" name="mobile" type="tel" value="{{ old('mobile', $user->mobile) }}" autocomplete="tel" inputmode="tel" placeholder="{{ __('account_ui.profile.mobile_placeholder') }}" @error('mobile') aria-invalid="true" aria-describedby="mobile-error" @enderror>@error('mobile')<small id="mobile-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="address1">{{ __('account_ui.profile.address1') }}</label><input id="address1" name="address1" type="text" value="{{ old('address1', $user->address1) }}" autocomplete="street-address" @error('address1') aria-invalid="true" aria-describedby="address1-error" @enderror>@error('address1')<small id="address1-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="address2">{{ __('account_ui.profile.address2') }}</label><input id="address2" name="address2" type="text" value="{{ old('address2', $user->address2) }}" autocomplete="address-level2" @error('address2') aria-invalid="true" aria-describedby="address2-error" @enderror>@error('address2')<small id="address2-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="email">{{ __('account_ui.profile.email') }}</label><input id="email" type="email" value="{{ $user->email }}" disabled></div>
                <div class="field"><input type="hidden" name="cart_reminder_opt_in" value="0"><label for="cart_reminder_opt_in"><input id="cart_reminder_opt_in" name="cart_reminder_opt_in" type="checkbox" value="1" @checked(old('cart_reminder_opt_in', $user->cart_reminder_opt_in))> {{ __('account_ui.profile.reminders') }}</label></div>
                <button class="button button-sapphire" type="submit">{{ __('account_ui.profile.save') }}</button>
            </form>

            </section>
            <section class="profile-section" id="account-security" aria-labelledby="account-security-heading">
            <h2 id="account-security-heading">{{ __('account_ui.show.security') }}</h2>
            @php($linkedProviders = $user->socialAccounts()->pluck('provider')->all())
            @if (\App\Http\Controllers\SocialAuthController::enabled('google') || $linkedProviders)
                <div style="margin-top:32px">
                    <h3>{{ __('account_ui.social.heading') }}</h3>
                    <ul style="list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px">
                        @foreach (['google' => 'Google', 'apple' => 'Apple'] as $provider => $label)
                            @if (in_array($provider, $linkedProviders, true))
                                <li style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                                    <span>{{ __('account_ui.social.linked', ['provider' => $label]) }}</span>
                                    <form method="POST" action="{{ route('social.unlink', $provider) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('account_ui.social.confirm', ['provider' => $label])) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-link" style="border:0;background:none;cursor:pointer;min-height:44px">{{ __('account_ui.social.unlink') }}</button>
                                    </form>
                                </li>
                            @elseif ($provider === 'google' && \App\Http\Controllers\SocialAuthController::enabled('google'))
                                <li><a class="button button-dark" href="{{ route('social.link', 'google') }}">{{ __('account_ui.social.link') }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-top:32px">
                <h3>{{ __('account_ui.totp.heading') }}</h3>
                @if ($user->hasTotp())
                    <p style="color:var(--muted);font-size:12px;margin:6px 0 12px">{{ __('account_ui.totp.active', ['date' => $user->totp_enabled_at->format('d M Y'), 'count' => $user->totpRecoveryCodes()->count()]) }}</p>
                    <details @if ($errors->has('disable_code')) open @endif>
                        <summary style="cursor:pointer;min-height:44px">{{ __('account_ui.totp.disable_heading') }}</summary>
                        <form method="POST" action="{{ route('account.totp.destroy') }}" style="display:grid;gap:12px;margin-top:12px" novalidate>
                            @csrf
                            @method('DELETE')
                            <div class="field"><label for="disable_code">{{ __('account_ui.totp.code') }}</label><input id="disable_code" name="disable_code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required @error('disable_code') aria-invalid="true" aria-describedby="disable_code-error" @enderror>@error('disable_code')<small id="disable_code-error" role="alert">{{ $message }}</small>@enderror</div>
                            <button class="button button-dark" type="submit">{{ __('account_ui.totp.disable') }}</button>
                        </form>
                    </details>
                @else
                    <p style="color:var(--muted);font-size:12px;margin:6px 0 12px">{{ __('account_ui.totp.intro') }}</p>
                    <a class="button button-light" href="{{ route('account.totp.enrol') }}">{{ __('account_ui.totp.enable') }}</a>
                @endif
            </div>

            </section>
            <section class="profile-section" id="account-privacy" aria-labelledby="account-privacy-heading">
                <h2 id="account-privacy-heading">{{ __('account_ui.privacy.heading') }}</h2>
                <p style="color:var(--muted);font-size:12px;margin:6px 0 12px">{{ __('account_ui.privacy.intro') }}</p>
                <a class="button button-light" href="{{ route('account.export') }}">{{ __('account_ui.privacy.export') }}</a>
                <details style="margin-top:16px" @if ($errors->hasAny(['password', 'confirmation', 'delete'])) open @endif>
                    <summary style="cursor:pointer;min-height:44px">{{ __('account_ui.privacy.delete') }}</summary>
                    <form method="POST" action="{{ route('account.destroy') }}" style="display:grid;gap:12px;margin-top:12px" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('account_ui.privacy.confirm')) }})">
                        @csrf
                        @method('DELETE')
                        @error('delete')<p role="alert" style="color:var(--danger)">{{ $message }}</p>@enderror
                        @if ($user->has_password)
                            <div class="field"><label for="delete_password">{{ __('account_ui.privacy.password') }}</label><input id="delete_password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="delete-password-error" @enderror>@error('password')<small id="delete-password-error" role="alert">{{ $message }}</small>@enderror</div>
                        @else
                            <div class="field"><label for="delete_confirmation">{{ __('account_ui.privacy.phrase', ['phrase' => 'HAPUS AKUN SAYA']) }}</label><input id="delete_confirmation" name="confirmation" type="text" autocomplete="off" required @error('confirmation') aria-invalid="true" aria-describedby="delete-confirmation-error" @enderror>@error('confirmation')<small id="delete-confirmation-error" role="alert">{{ $message }}</small>@enderror</div>
                        @endif
                        <button class="button button-dark" type="submit">{{ __('account_ui.privacy.submit') }}</button>
                    </form>
                </details>
            </section>

            <section class="profile-section" id="account-history" aria-labelledby="account-history-heading">
                <h2 id="account-history-heading">{{ __('account_ui.history.heading') }}</h2>
                @if (($history ?? collect())->isEmpty())
                    <p style="color:var(--muted); font-size:12px; margin-top:6px;">{{ __('account_ui.history.empty') }}</p>
                @else
                    <ul style="list-style:none;margin:12px 0 0;padding:0;display:grid;gap:16px">
                        @foreach ($history as $item)
                            <li class="profile-history-item">
                                <div class="profile-history-heading">
                                    <strong style="font-size:15px; color:var(--ink);">{{ $item->service }}</strong>
                                    <span class="badge-status badge-{{ $item->status }}">{{ \Illuminate\Support\Facades\Lang::has('account_ui.status.'.$item->status) ? __('account_ui.status.'.$item->status) : $item->status }}</span>
                                </div>
                                <div style="color:var(--muted);font-size:12px">
                                    {{ $item->preferred_date?->format('d M Y') ?? __('account_ui.history.unscheduled') }} {{ $item->preferred_time ?? '' }} · {{ __('account_ui.history.submitted', ['date' => $item->created_at?->format('d M Y H:i')]) }}
                                </div>

                                @if ($item->notes)
                                    <div class="profile-history-notes" style="white-space:pre-wrap">
                                        {{ $item->notes }}
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

        </div>
    </section>
</main>


<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</x-react-root>
</body>
</html>
