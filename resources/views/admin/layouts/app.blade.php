<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17263f">
    <title>@yield('title') — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600,700,900&family=manrope:300,400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .admin-work-header { max-width: 1100px; margin: 0 auto; padding: 28px 24px 0; }
        .admin-work-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
        .admin-work-top > div { min-width: 0; }
        .admin-work-top h1 { font-family: var(--serif); font-size: clamp(28px, 4vw, 44px); font-weight: 400; line-height: 1.2; overflow-wrap: anywhere; }
        .admin-work-operator { color: var(--muted); overflow-wrap: anywhere; }
        .admin-work-breadcrumb { margin: 16px 0 10px; color: var(--muted); }
        .admin-work-breadcrumb a { text-decoration: underline; text-underline-offset: 3px; }
        .admin-work-nav { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; }
        .admin-work-nav a { padding: 10px 14px; border: 1px solid var(--line); background: var(--white); }
        .admin-work-nav a[aria-current="page"] { border-color: var(--sapphire); font-weight: 700; }
        .admin-work-header a:focus-visible, .admin-work-header button:focus-visible { outline: 3px solid var(--sapphire); outline-offset: 3px; }
        .admin-work-main { max-width: 1100px; margin: 0 auto; padding: 24px 24px 60px; }
        @media (max-width: 640px) { .admin-work-top { flex-direction: column; } .admin-work-nav a { flex: 1 1 auto; text-align: center; } }
    </style>
    @yield('styles')
</head>
<body class="storefront">
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<header class="admin-work-header">
    <div class="admin-work-top">
        <div>
            <span class="eyebrow">Administration · ZAFIRA Maison</span>
            <nav class="admin-work-breadcrumb" aria-label="Jejak navigasi">
                @if ($activePage === 'dashboard')
                    <span aria-current="page">Dashboard Maison</span>
                @else
                    <a href="{{ route('admin.dashboard') }}">Dashboard Maison</a>
                    <span aria-hidden="true"> / </span><span aria-current="page">{{ $pageTitle }}</span>
                @endif
            </nav>
            <h1>{{ $pageTitle }}</h1>
            <p class="admin-work-operator">Masuk sebagai {{ auth('admin')->user()->email }}</p>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button button-dark" type="submit">Keluar</button></form>
    </div>
    <nav class="admin-work-nav" aria-label="Navigasi konsol admin" data-admin-active="{{ $activePage }}">
        <a href="{{ route('admin.dashboard') }}"@if ($activePage === 'dashboard') aria-current="page"@endif>Dashboard</a>
        <a href="{{ route('admin.auth-events.index') }}"@if ($activePage === 'auth-events') aria-current="page"@endif>Log autentikasi</a>
        <a href="{{ route('home') }}">Lihat toko</a>
    </nav>
</header>
<main id="main-content" class="admin-work-main" tabindex="-1">
    @yield('content')
</main>
@yield('after-content')
</body>
</html>
