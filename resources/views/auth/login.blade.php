<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="Dunia ZAFIRA">
        <img src="https://images.unsplash.com/photo-1536243298747-ea8874136d64?auto=format&fit=crop&w=1400&q=88" alt="Editorial elegan ZAFIRA" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">Votre Monde Privé</span><h2>Keanggunan yang<br><em>mengenal Anda.</em></h2><p>Simpan pilihan, akses undangan privat, dan nikmati layanan personal dari Maison.</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}">← Kembali ke Maison</a>
        <div class="auth-card">
            <span class="eyebrow">Mon Compte</span>
            <h1>Selamat datang kembali.</h1>
            <p>Masuk untuk melanjutkan pengalaman pribadi Anda.</p>
            @if (session('success'))
                <div class="auth-errors" role="status" style="border-color: #16a34a; background: rgba(22, 163, 74, 0.08); color: #15803d; margin-bottom: 16px;"><ul><li>{{ session('success') }}</li></ul></div>
            @endif
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('login.process') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" inputmode="email"></div>
                <div class="field"><label for="password">Kata sandi</label><div class="password-wrap"><input id="password" name="password" type="password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button></div></div>
                <div class="auth-options"><label><input type="checkbox" name="remember" value="1"> Ingat saya</label><span>Hubungi Concierge jika butuh bantuan</span></div>
                <button class="button button-sapphire" type="submit">Masuk ke akun</button>
            </form>
            <p class="auth-switch">Baru di ZAFIRA? <a href="{{ route('register') }}">Buat akun pribadi</a></p>
        </div>
    </section>
</main>
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</body>
</html>
