@component('mail::message')
# Bonjour, {{ $request->name }}

Merci. Permintaan rendez-vous pribadi Anda telah kami terima.

**Detail permintaan Anda:**
- **Layanan :** {{ $request->service }}
@if ($request->preferred_date)
- **Tanggal pilihan :** {{ \Carbon\Carbon::parse($request->preferred_date)->translatedFormat('l, d F Y') }}
@endif
@if ($request->preferred_time)
- **Waktu :** {{ $request->preferred_time }}
@endif
@if ($request->notes)
- **Catatan :** {{ $request->notes }}
@endif

Client Advisor kami akan menghubungi Anda melalui **{{ $request->phone }}** atau email dalam kurang dari 1×24 jam untuk mengonfirmasi jadwal.

Jangan ragu membalas email ini jika ada kebutuhan tambahan.

Dengan hormat,
**ZAFIRA — La Maison de Lumière**
@endcomponent