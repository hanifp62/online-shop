<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <x-theme-preference />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('account_ui.recovery.title') }}</title>
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
            <span class="eyebrow">Mon Compte</span>
            <h1>{{ __('account_ui.recovery.heading') }}</h1>
            <p>{{ __('account_ui.recovery.intro') }}</p>
            <ul style="list-style:none;margin:16px 0;padding:0;display:grid;gap:8px;font-family:ui-monospace,monospace">
                @foreach ($codes as $code)
                    <li style="border:1px solid var(--line);padding:10px 14px;background:var(--white)">{{ $code }}</li>
                @endforeach
            </ul>
            <a class="button button-sapphire" href="{{ route('account.show') }}">{{ __('account_ui.recovery.submit') }}</a>
        </div>
    </section>
</main>
</x-react-root>
<x-private-page-lifecycle />
</body>
</html>
