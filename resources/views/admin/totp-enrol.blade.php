<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <x-theme-preference />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('admin_ui.enrol.title') }}</title>
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
            <span class="eyebrow">{{ __('admin_ui.common.admin_access') }}</span>
            <h1>{{ __('admin_ui.enrol.heading') }}</h1>
            <p>{{ __('admin_ui.enrol.intro') }}</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <img src="{{ $qr }}" width="220" height="220" alt="{{ __('admin_ui.enrol.qr_alt') }}" style="display:block;margin:0 auto 1rem;background:#fff">
            <div class="field">
                <label for="setup_key">{{ __('admin_ui.enrol.setup_key') }}</label>
                <input id="setup_key" type="text" value="{{ $secret }}" readonly spellcheck="false" autocomplete="off">
            </div>
            <div class="field">
                <label for="setup_uri">{{ __('admin_ui.enrol.uri') }}</label>
                <input id="setup_uri" type="text" value="{{ $uri }}" readonly spellcheck="false" autocomplete="off">
            </div>
            <form method="POST" action="{{ route('admin.totp.enrol.store') }}" novalidate>
                @csrf
                <div class="field"><label for="code">{{ __('admin_ui.totp.code') }}</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
                <button class="button button-sapphire" type="submit">{{ __('admin_ui.enrol.submit') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="text-link" type="submit">{{ __('admin_ui.common.logout') }}</button>
            </form>
        </div>
    </section>
</main>
</x-react-root>
<x-private-page-lifecycle />
</body>
</html>
