<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aktifkan Verifikasi Dua Langkah — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">Admin Access</span>
            <h1>Aktifkan verifikasi dua langkah.</h1>
            <p>Tambahkan kunci ini ke aplikasi autentikator, lalu masukkan 6 digit pertama.</p>
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <div class="field">
                <label for="setup_key">Kunci setup</label>
                <input id="setup_key" type="text" value="{{ $secret }}" readonly spellcheck="false" autocomplete="off">
            </div>
            <div class="field">
                <label for="setup_uri">URI autentikator</label>
                <input id="setup_uri" type="text" value="{{ $uri }}" readonly spellcheck="false" autocomplete="off">
            </div>
            <form method="POST" action="{{ route('admin.totp.enrol.store') }}" novalidate>
                @csrf
                <div class="field"><label for="code">Kode 6 digit</label><input id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
                <button class="button button-sapphire" type="submit">Aktifkan</button>
            </form>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="text-link" type="submit">Keluar</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
