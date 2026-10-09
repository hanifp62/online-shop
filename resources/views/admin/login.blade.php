<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Admin — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="Panel admin">
        <img src="https://images.unsplash.com/photo-1536243298747-ea8874136d64?auto=format&fit=crop&w=1400&q=88" alt="Editorial elegan ZAFIRA" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">Administration</span><h2>Ruang kendali<br><em>Maison.</em></h2><p>Kelola user, arsip audit, concierge, dan subscriber.</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}">← Kembali ke Maison</a>
        <div class="auth-card">
            <span class="eyebrow">Admin Access</span>
            <h1>Masuk admin.</h1>
            <p>Akun seed: admin@gmail.com — ganti password setelah login pertama.</p>
            @if (session('success'))
                <div class="auth-errors" role="status"><ul><li>{{ session('success') }}</li></ul></div>
            @endif
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.login.process') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" inputmode="email"></div>
                <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
                <div class="auth-options"><label><input type="checkbox" name="remember" value="1"> Ingat saya</label></div>
                <button class="button button-sapphire" type="submit">Masuk ke dashboard</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
