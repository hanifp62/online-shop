<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifikasi Dua Langkah — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">Admin Access</span>
            <h1>Verifikasi dua langkah.</h1>
            <p>Masukkan 6 digit dari aplikasi autentikator Anda.</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('admin.totp.verify') }}" novalidate>
                @csrf
                <div class="field"><label for="code">Kode 6 digit</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
                <button class="button button-sapphire" type="submit">Verifikasi</button>
            </form>
            <details>
                <summary>Tidak bisa membuka autentikator?</summary>
                <form method="POST" action="{{ route('admin.totp.recovery') }}" novalidate>
                    @csrf
                    <div class="field"><label for="recovery_code">Kode cadangan</label><input id="recovery_code" name="recovery_code" type="text" autocomplete="off" spellcheck="false" required></div>
                    <button class="button" type="submit">Pakai kode cadangan</button>
                </form>
            </details>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="text-link" type="submit">Keluar</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
