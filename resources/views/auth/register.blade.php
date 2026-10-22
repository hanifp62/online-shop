<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth_ui.register.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="{{ __('auth_ui.common.visual_label') }}">
        <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" srcset="{{ asset('prototype/assets/hero_couture-600.webp') }} 600w, {{ asset('prototype/assets/hero_couture.webp') }} 896w" sizes="(max-width: 900px) 100vw, 50vw" decoding="async" alt="{{ __('auth_ui.common.image_alt') }}" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('auth_ui.common.home_label') }}"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">{{ __('auth_ui.register.quote_eyebrow') }}</span><h2>{{ __('auth_ui.register.quote_heading') }}</h2><p>{{ __('auth_ui.register.quote_body') }}</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>{{ __('auth_ui.common.back_home') }}</span>
        </a>
        <div class="auth-card">
            <span class="eyebrow">{{ __('auth_ui.register.eyebrow') }}</span>
            <h1>{{ __('auth_ui.register.heading') }}</h1>
            <p>{{ __('auth_ui.register.intro') }}</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('register.store') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="name">{{ __('auth_ui.register.full_name') }}</label><input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<small id="name-error">{{ $message }}</small>@enderror</div>
                <div class="form-row">
                    <div class="field"><label for="first_name">{{ __('auth_ui.register.first_name') }} <span>{{ __('auth_ui.common.optional') }}</span></label><input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" @error('first_name') aria-invalid="true" aria-describedby="first-name-error" @enderror>@error('first_name')<small id="first-name-error">{{ $message }}</small>@enderror</div>
                    <div class="field"><label for="last_name">{{ __('auth_ui.register.last_name') }} <span>{{ __('auth_ui.common.optional') }}</span></label><input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" @error('last_name') aria-invalid="true" aria-describedby="last-name-error" @enderror>@error('last_name')<small id="last-name-error">{{ $message }}</small>@enderror</div>
                </div>
                <div class="field"><label for="email">{{ __('auth_ui.common.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<small id="email-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="mobile">{{ __('auth_ui.register.mobile') }} <span>{{ __('auth_ui.common.optional') }}</span></label><input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}" autocomplete="tel" inputmode="tel" placeholder="{{ __('auth_ui.register.mobile_placeholder') }}" @error('mobile') aria-invalid="true" aria-describedby="mobile-error" @enderror>@error('mobile')<small id="mobile-error">{{ $message }}</small>@enderror</div>
                <div class="field"><label for="address1">{{ __('auth_ui.register.address') }} <span>{{ __('auth_ui.common.optional') }}</span></label><input id="address1" name="address1" type="text" value="{{ old('address1') }}" autocomplete="street-address" @error('address1') aria-invalid="true" aria-describedby="address1-error" @enderror>@error('address1')<small id="address1-error">{{ $message }}</small>@enderror</div>
                <div class="field">
                    <label for="password">{{ __('auth_ui.common.password') }}</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button class="password-toggle" type="button" data-password-toggle data-i18n-show="{{ __('auth_ui.common.show') }}" data-i18n-hide="{{ __('auth_ui.common.hide') }}" data-i18n-aria-show="{{ __('auth_ui.common.show_password') }}" data-i18n-aria-hide="{{ __('auth_ui.common.hide_password') }}" aria-label="{{ __('auth_ui.common.show_password') }}" aria-pressed="false">{{ __('auth_ui.common.show') }}</button>
                    </div>
                    @error('password')<small id="password-error">{{ $message }}</small>@enderror
                    <p style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4">{{ __('auth_ui.register.password_hint') }}</p>
                </div>
                <div class="field"><label for="password_confirmation">{{ __('auth_ui.register.confirm_password') }}</label><div class="password-wrap"><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" @error('password_confirmation') aria-invalid="true" aria-describedby="password-confirmation-error" @enderror><button class="password-toggle" type="button" data-password-toggle data-i18n-show="{{ __('auth_ui.common.show') }}" data-i18n-hide="{{ __('auth_ui.common.hide') }}" data-i18n-aria-show="{{ __('auth_ui.common.show_password_confirmation') }}" data-i18n-aria-hide="{{ __('auth_ui.common.hide_password_confirmation') }}" aria-label="{{ __('auth_ui.common.show_password_confirmation') }}" aria-pressed="false">{{ __('auth_ui.common.show') }}</button></div>@error('password_confirmation')<small id="password-confirmation-error">{{ $message }}</small>@enderror</div>
                <button class="button button-sapphire" type="submit">{{ __('auth_ui.register.submit') }}</button>
            </form>
            @include('auth._social')
            <p class="auth-switch">{{ __('auth_ui.register.existing') }} <a href="{{ route('login') }}">{{ __('auth_ui.register.login') }}</a></p>
        </div>
    </section>
</main>
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</body>
</html>
