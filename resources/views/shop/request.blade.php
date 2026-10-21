<script type="application/json" id="shop-request-copy">@json(__('shop_ui.request'))</script>
<script>
window.shopRequest = async function (url, method = 'GET', payload) {
    const copy = (() => { try { return JSON.parse(document.getElementById('shop-request-copy')?.textContent || '{}') || {}; } catch (_) { return {}; } })();
    let response;
    try {
        response = await fetch(url, {
            method, credentials: 'same-origin',
            headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            ...(payload === undefined ? {} : {body: JSON.stringify(payload)})
        });
    } catch (_) {
        throw new Error(copy.network || 'Jaringan terputus. Periksa koneksi, lalu coba lagi.');
    }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data) {
        const messages = {401: copy[401] || 'Silakan masuk, lalu kembali ke Belanja.', 403: copy[403] || 'Akses tidak diizinkan untuk tindakan ini.', 404: copy[404] || 'Data atau pesanan tidak ditemukan.', 409: copy[409] || 'Terjadi konflik data atau pesanan sudah diproses. Muat ulang halaman.', 419: copy[419] || 'Sesi berakhir demi keamanan. Muat ulang halaman sebelum mencoba lagi.', 429: copy[429] || 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.'};
        const error = new Error(messages[response.status] || (response.status >= 500 ? (copy.server || 'Layanan sedang bermasalah di peladen. Periksa Pesanan sebelum mengulangi checkout.') : Object.values(data?.errors || {}).flat().join(' ') || data?.message || copy.generic || 'Permintaan gagal diproses. Muat ulang lalu coba lagi.'));
        error.status = response.status;
        throw error;
    }
    return data;
};
</script>
