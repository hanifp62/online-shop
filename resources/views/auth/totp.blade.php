<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <x-theme-preference />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth_ui.totp.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @isset($page)
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    @else
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endisset
</head>
<body>
<x-react-root :page="$page ?? null">
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">{{ __('auth_ui.totp.eyebrow') }}</span>
            <h1>{{ __('auth_ui.totp.heading') }}</h1>
            <p>{{ __('auth_ui.totp.intro') }}</p>
            @if ($errors->any())
                <div id="auth-error-summary" class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('login.totp.verify') }}" data-auth-form data-error-summary="auth-error-summary" novalidate>
                @csrf
                <div class="field"><label for="code">{{ __('auth_ui.totp.code') }}</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>@error('code')<p id="code-error" class="form-error">{{ $message }}</p>@enderror</div>
                <button class="button button-sapphire" type="submit">{{ __('auth_ui.totp.submit') }}</button>
            </form>
            <details style="margin-top:16px">
                <summary style="cursor:pointer;min-height:44px">{{ __('auth_ui.totp.recovery_help') }}</summary>
                <form method="POST" action="{{ route('login.totp.verify') }}" data-auth-form data-error-summary="auth-error-summary" novalidate style="margin-top:12px">
                    @csrf
                    <div class="field"><label for="recovery_code">{{ __('auth_ui.totp.recovery_code') }}</label><input id="recovery_code" name="recovery_code" type="text" autocomplete="off" spellcheck="false" required @error('recovery_code') aria-invalid="true" aria-describedby="recovery_code-error" @enderror>@error('recovery_code')<p id="recovery_code-error" class="form-error">{{ $message }}</p>@enderror</div>
                    <button class="button button-light" type="submit">{{ __('auth_ui.totp.recovery_submit') }}</button>
                </form>
            </details>
            <p class="auth-switch"><a href="{{ route('login') }}">{{ __('auth_ui.totp.back') }}</a></p>
        </div>
    </section>
</main>
</x-react-root>
<x-private-page-lifecycle />
</body>
</html>
