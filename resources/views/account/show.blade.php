<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profil Saya — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-page">
    <section class="auth-visual" aria-label="Dunia ZAFIRA">
        <img src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1400&q=88" alt="Editorial busana ZAFIRA" width="1400" height="1750" fetchpriority="high">
        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
        <div class="auth-quote"><span class="eyebrow light">Mon Profil</span><h2>Profil yang<br><em>mengenal Anda.</em></h2><p>Data ini dipakai untuk prefill checkout concierge agar tidak isi ulang.</p></div>
    </section>
    <section class="auth-content">
        <a class="auth-back text-link" href="{{ route('home') }}">← Kembali ke Maison</a>
        <div class="auth-card">
            <span class="eyebrow">Votre Compte</span>
            <h1>Bonjour, {{ $user->first_name ?? $user->name }}.</h1>
            <p>Lengkapi profil — tersimpan otomatis ke arsip audit.</p>
            @if (session('success'))
                <div class="auth-errors" role="status" style="border-color: green"><ul><li>{{ session('success') }}</li></ul></div>
            @endif
            @if ($errors->any())
                <div class="auth-errors" role="alert" tabindex="-1"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('account.update') }}" data-auth-form novalidate>
                @csrf
                @method('PATCH')
                <div class="field"><label for="name">Nama lengkap</label><input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name"></div>
                <div class="form-row">
                    <div class="field"><label for="first_name">Nama depan</label><input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" autocomplete="given-name"></div>
                    <div class="field"><label for="last_name">Nama belakang</label><input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name"></div>
                </div>
                <div class="field"><label for="mobile">Nomor HP</label><input id="mobile" name="mobile" type="tel" value="{{ old('mobile', $user->mobile) }}" autocomplete="tel" inputmode="tel" placeholder="08…"></div>
                <div class="field"><label for="address1">Alamat 1</label><input id="address1" name="address1" type="text" value="{{ old('address1', $user->address1) }}" autocomplete="street-address"></div>
                <div class="field"><label for="address2">Alamat 2 / Kota</label><input id="address2" name="address2" type="text" value="{{ old('address2', $user->address2) }}" autocomplete="address-level2"></div>
                <div class="field"><label for="email">Email (tidak bisa diubah)</label><input id="email" type="email" value="{{ $user->email }}" disabled></div>
                <button class="button button-sapphire" type="submit">Simpan profil</button>
            </form>
            <div style="margin-top:32px">
                <span class="eyebrow">Riwayat Concierge</span>
                @if (($history ?? collect())->isEmpty())
                    <p style="color:var(--muted)">Belum ada permintaan concierge dari email ini.</p>
                @else
                    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:12px">
                        @foreach ($history as $item)
                            <li style="border:1px solid var(--line);padding:14px 16px;background:var(--white);border-radius:4px">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                    <strong style="font-size:14px">{{ $item->service }}</strong>
                                    <span class="badge-status badge-{{ $item->status }}">{{ $item->status }}</span>
                                </div>
                                <div style="color:var(--muted);font-size:12px">
                                    {{ $item->preferred_date?->format('d M Y') ?? 'Tanpa jadwal spesifik' }} {{ $item->preferred_time ?? '' }} · Diajukan {{ $item->created_at?->format('d M Y H:i') }}
                                </div>
                                @if ($item->notes)
                                    <div style="margin-top:8px;font-size:12px;line-height:1.4;color:var(--ink);background:#f8fafc;padding:8px 12px;border-radius:4px;border-left:3px solid var(--sapphire);white-space:pre-wrap;word-break:break-word">
                                        {{ $item->notes }}
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</main>
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</body>
</html>
