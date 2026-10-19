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
        <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="Editorial adibusana ZAFIRA Haute Couture" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">Administration</span><h2>Ruang Komando &amp; Sistem Informasi Atelier Maison ZAFIRA</h2><p>Kelola data kriya, pesanan adibusana, arsip audit, dan layanan concierge.</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>Kembali ke Maison</span>
        </a>
        <div class="auth-card">
            <span class="eyebrow">Admin Access</span>
            <h1>Masuk admin.</h1>
            @if (session('success'))
                <div class="auth-errors" role="status"><ul><li>{{ session('success') }}</li></ul></div>
            @endif
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.login.process') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<p id="email-error" class="form-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<p id="password-error" class="form-error">{{ $message }}</p>@enderror</div>
                <button class="button button-sapphire" type="submit">Masuk ke dashboard</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
