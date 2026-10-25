<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17263f">
    <x-theme-preference />
    <title>@yield('title') · ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600,700,900&family=manrope:300,400,500,600,700&display=swap" rel="stylesheet">
    <script type="application/json" id="zafira-admin-table-copy">@json(__('admin_ui.table_js'))</script>
    <script type="application/json" id="zafira-app-copy">@json(__('shop_ui.app_js'))</script>
    @if (isset($page))
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/css/admin.css', 'resources/js/inertia.jsx'])
    @else
        @vite(['resources/css/app.css', 'resources/css/admin.css', 'resources/js/app.js'])
    @endif
    @yield('styles')
</head>
<body class="admin-console">
<x-private-page-lifecycle />
@php
    $operator = auth('admin')->user();
    $adminGate = \Illuminate\Support\Facades\Gate::forUser($operator);
    $panelBase = $activePage === 'dashboard' ? '' : route('admin.dashboard');
    $operatorName = trim($operator->name ?? '') ?: $operator->email;
@endphp
<a class="skip-link" href="#main-content">{{ __('admin_ui.common.skip') }}</a>
<div class="admin-workspace">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}" aria-label="ZAFIRA · {{ __('admin_ui.common.dashboard_maison') }}">
            <span class="admin-brand-name">ZAFIRA</span>
            <span class="admin-brand-caption">La Maison de Lumière</span>
        </a>
        <details class="admin-sidebar-navigation" data-admin-navigation open>
            <summary>{{ __('admin_ui.common.console_navigation') }}</summary>
            <nav class="admin-work-nav" aria-label="{{ __('admin_ui.common.console_navigation') }}" data-admin-active="{{ $activePage }}">
                <p class="admin-nav-label">{{ __('admin_ui.common.administration') }}</p>
                @if ($adminGate->allows('view-dashboard'))
                <a href="{{ route('admin.dashboard') }}"@if ($activePage === 'dashboard') aria-current="page"@endif>{{ __('admin_ui.common.dashboard') }}</a>
                @endif
                @if ($adminGate->allows('manage-orders'))
                <a href="{{ $panelBase }}#panel-pesanan">{{ __('admin_ui.dashboard.orders_reports') }}</a>
                @endif
                @if ($adminGate->allows('manage-catalog'))
                <a href="{{ $panelBase }}#panel-produk">{{ __('admin_ui.common.product') }}</a>
                @endif
                @if ($adminGate->allows('manage-categories'))
                <a href="{{ $panelBase }}#panel-kategori">{{ __('admin_ui.common.category') }}</a>
                @endif
                @if ($adminGate->allows('manage-concierge'))
                <a href="{{ $panelBase }}#panel-concierge">{{ __('admin_ui.common.concierge') }}</a>
                @endif
                @if ($adminGate->allows('view-dashboard'))
                <a href="{{ $panelBase }}#panel-user">{{ __('admin_ui.common.user') }}</a>
                @endif
                @if ($adminGate->allows('manage-subscribers'))
                <a href="{{ $panelBase }}#panel-subscriber">{{ __('admin_ui.common.subscriber') }}</a>
                @endif
                @if ($adminGate->allows('view-audit-log'))
                <p class="admin-nav-label">{{ __('admin_ui.common.security') }}</p>
                <a href="{{ route('admin.auth-events.index') }}"@if ($activePage === 'auth-events') aria-current="page"@endif>{{ __('admin_ui.common.auth_log') }}</a>
                <a href="{{ $panelBase }}#panel-audit">{{ __('admin_ui.common.audit') }}</a>
                @endif
                @if ($adminGate->allows('view-reports') && app(\App\Support\FeatureFlags::class)->enabled('admin_ops_summary', (string) $operator->id))
                <a href="{{ route('admin.reports.operations-summary') }}"@if ($activePage === 'operations-summary') aria-current="page"@endif lang="id">Ringkasan operasional</a>
                @endif
                <a class="admin-store-link" href="{{ route('home') }}">{{ __('admin_ui.common.view_store') }}</a>
            </nav>
        </details>
    </aside>
    <div class="admin-work-area">
        <header class="admin-work-header">
            <div class="admin-work-topbar">
                <nav class="admin-work-breadcrumb" aria-label="{{ __('admin_ui.common.breadcrumb') }}">
                    @if ($activePage === 'dashboard')
                        <span aria-current="page">{{ __('admin_ui.common.dashboard_maison') }}</span>
                    @else
                        <a href="{{ route('admin.dashboard') }}">{{ __('admin_ui.common.dashboard_maison') }}</a>
                        <span aria-hidden="true"> / </span><span aria-current="page">{{ $pageTitle }}</span>
                    @endif
                </nav>
                <div class="admin-topbar-actions">
                    <div class="admin-theme-control">
                        <label for="admin-theme">{{ __('profile_ui.theme') }}</label>
                        <select id="admin-theme" data-theme-select>
                            <option value="light">{{ __('profile_ui.theme_light') }}</option>
                            <option value="dark">{{ __('profile_ui.theme_dark') }}</option>
                            <option value="system" selected>{{ __('profile_ui.theme_system') }}</option>
                        </select>
                    </div>
                    <a class="admin-profile-link" href="{{ route('admin.profile.show') }}"@if ($activePage === 'profile') aria-current="page"@endif>
                        <span class="admin-profile-initial" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($operatorName, 0, 1)) }}</span>
                        <span class="admin-profile-identity"><strong>{{ $operatorName }}</strong><span>{{ __('profile_ui.title') }}</span></span>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="admin-logout" type="submit">{{ __('admin_ui.common.logout') }}</button></form>
                </div>
            </div>
            <div class="admin-page-heading">
                <h1>{{ $pageTitle }}</h1>
                <p class="admin-work-operator">{{ __('admin_ui.common.signed_in', ['email' => $operator->email]) }}</p>
            </div>
        </header>
        <main id="main-content" class="admin-work-main" tabindex="-1">
            @if (isset($page))
                <x-react-root :page="$page">@yield('content')</x-react-root>
            @else
                @yield('content')
            @endif
        </main>
    </div>
</div>
<script data-admin-shell>
(() => {
    const navigation = document.querySelector('[data-admin-navigation]');
    if (!navigation || typeof window.matchMedia !== 'function') return;
    const mobile = window.matchMedia('(max-width: 960px)');
    const summary = navigation.querySelector('summary');
    let isMobile = mobile.matches;
    // The disclosure is in normal flow: no overlay, focus trap or page scroll lock.
    const resize = event => {
        isMobile = event.matches;
        const active = document.activeElement;
        const focusedInside = navigation.contains(active);
        navigation.open = !isMobile;
        if (isMobile && focusedInside) summary.focus();
        else if (!isMobile && active === summary) navigation.querySelector('a')?.focus();
    };
    resize(mobile);
    mobile.addEventListener('change', resize);
    navigation.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !isMobile || !navigation.open) return;
        event.preventDefault();
        navigation.open = false;
        summary.focus();
    });
})();
</script>
@yield('after-content')
</body>
</html>
