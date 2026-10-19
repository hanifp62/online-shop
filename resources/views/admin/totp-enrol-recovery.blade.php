<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simpan Kode Cadangan — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-content">
        <div class="auth-card">
            <span class="eyebrow">Admin Access</span>
            <h1>Simpan Kode Cadangan</h1>
            <p>Kode ini hanya tampil sekali. Setiap kode berlaku satu kali bila autentikator tidak tersedia.</p>
            <ul aria-label="Kode cadangan">
                @foreach ($codes as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
            <a class="button button-sapphire" href="{{ route('admin.dashboard') }}">Sudah saya simpan</a>
        </div>
    </section>
</main>
</body>
</html>
