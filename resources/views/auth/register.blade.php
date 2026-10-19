<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buat Akun — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="Dunia ZAFIRA">
        <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="Editorial adibusana ZAFIRA Haute Couture" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">Le Cercle ZAFIRA</span><h2>Sebuah dunia adibusana yang dipersembahkan untuk Anda.</h2><p>Temukan pilihan personal, undangan koleksi kriya, dan akses istimewa ke Maison ZAFIRA.</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>Kembali ke Maison</span>
        </a>
        <div class="auth-card">
            <span class="eyebrow">Créer un Compte</span>
            <h1>Bergabung dengan Maison.</h1>
            <p>Buat ruang pribadi Anda dalam dunia ZAFIRA.</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('register.store') }}" data-auth-form novalidate>
                @csrf
                <div class="field"><label for="name">Nama lengkap</label><input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name"></div>
                <div class="form-row">
                    <div class="field"><label for="first_name">Nama depan <span>Opsional</span></label><input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name"></div>
                    <div class="field"><label for="last_name">Nama belakang <span>Opsional</span></label><input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name"></div>
                </div>
                <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" spellcheck="false" inputmode="email"></div>
                <div class="field"><label for="mobile">Nomor HP <span>Opsional</span></label><input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}" autocomplete="tel" inputmode="tel" placeholder="08…"></div>
                <div class="field"><label for="address1">Alamat 1 <span>Opsional</span></label><input id="address1" name="address1" type="text" value="{{ old('address1') }}" autocomplete="street-address"></div>
                <div class="field">
                    <label for="password">Kata sandi</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                        <button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan kata sandi" aria-pressed="false">Lihat</button>
                    </div>
                    <p style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4">Minimal 8 karakter dengan kombinasi huruf besar, huruf kecil, angka, dan simbol.</p>
                </div>
                <div class="field"><label for="password_confirmation">Konfirmasi kata sandi</label><div class="password-wrap"><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false">Lihat</button></div></div>
                <button class="button button-sapphire" type="submit">Buat akun pribadi</button>
            </form>
            <p class="auth-switch">Sudah menjadi bagian ZAFIRA? <a href="{{ route('login') }}">Masuk di sini</a></p>
        </div>
    </section>
</main>
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</body>
</html>
