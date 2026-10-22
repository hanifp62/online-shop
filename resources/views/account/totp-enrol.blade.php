<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('account_ui.enrol.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">Mon Compte</span>
            <h1>{{ __('account_ui.enrol.heading') }}</h1>
            <p>{{ __('account_ui.enrol.intro') }}</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <img src="{{ $qr }}" alt="{{ __('account_ui.enrol.qr_alt') }}" width="220" height="220" style="display:block;margin:16px 0">
            <p style="font-size:12px;color:var(--muted)">{{ __('account_ui.enrol.manual') }} <code style="word-break:break-all">{{ $secret }}</code></p>
            <form method="POST" action="{{ route('account.totp.store') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="code">{{ __('account_ui.enrol.code') }}</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>@error('code')<p id="code-error" class="form-error">{{ $message }}</p>@enderror</div>
                <button class="button button-sapphire" type="submit">{{ __('account_ui.enrol.submit') }}</button>
            </form>
            <p class="auth-switch"><a href="{{ route('account.show') }}">{{ __('account_ui.enrol.cancel') }}</a></p>
        </div>
    </section>
</main>
</body>
</html>
