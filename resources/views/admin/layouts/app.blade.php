<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17263f">
    <title>@yield('title') — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600,700,900&family=manrope:300,400,500,600,700&display=swap" rel="stylesheet">
    <script type="application/json" id="zafira-admin-table-copy">@json(__('admin_ui.table_js'))</script>
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @vite(['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="admin-console">
<a class="skip-link" href="#main-content">{{ __('admin_ui.common.skip') }}</a>
<header class="admin-work-header">
    <div class="admin-work-top">
        <div>
            <span class="eyebrow">{{ __('admin_ui.common.administration') }} · ZAFIRA Maison</span>
            <nav class="admin-work-breadcrumb" aria-label="{{ __('admin_ui.common.breadcrumb') }}">
                @if ($activePage === 'dashboard')
                    <span aria-current="page">{{ __('admin_ui.common.dashboard_maison') }}</span>
                @else
                    <a href="{{ route('admin.dashboard') }}">{{ __('admin_ui.common.dashboard_maison') }}</a>
                    <span aria-hidden="true"> / </span><span aria-current="page">{{ $pageTitle }}</span>
                @endif
            </nav>
            <h1>{{ $pageTitle }}</h1>
            <p class="admin-work-operator">{{ __('admin_ui.common.signed_in', ['email' => auth('admin')->user()->email]) }}</p>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button button-dark" type="submit">{{ __('admin_ui.common.logout') }}</button></form>
    </div>
    <nav class="admin-work-nav" aria-label="{{ __('admin_ui.common.console_navigation') }}" data-admin-active="{{ $activePage }}">
        <a href="{{ route('admin.dashboard') }}"@if ($activePage === 'dashboard') aria-current="page"@endif>{{ __('admin_ui.common.dashboard') }}</a>
        @if (auth('admin')->user()->isAdmin())
        <a href="{{ route('admin.auth-events.index') }}"@if ($activePage === 'auth-events') aria-current="page"@endif>{{ __('admin_ui.common.auth_log') }}</a>
        @endif
        <a href="{{ route('home') }}">{{ __('admin_ui.common.view_store') }}</a>
    </nav>
</header>
<main id="main-content" class="admin-work-main" tabindex="-1">
    @yield('content')
</main>
@yield('after-content')
</body>
</html>
