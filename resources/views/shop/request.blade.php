<script>
window.shopRequest = async function (url, method = 'GET', payload) {
    let response;
    try {
        response = await fetch(url, {
            method, credentials: 'same-origin',
            headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            ...(payload === undefined ? {} : {body: JSON.stringify(payload)})
        });
    } catch (_) {
        throw new Error('Jaringan terputus. Periksa koneksi, lalu coba lagi.');
    }
    const data = await response.json().catch(() => null);
    if (!response.ok || !data) {
        const messages = {401: 'Silakan masuk, lalu kembali ke Belanja.', 419: 'Sesi berakhir. Muat ulang halaman sebelum mencoba lagi.', 429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'};
        const error = new Error(messages[response.status] || (response.status >= 500 ? 'Layanan bermasalah. Periksa Pesanan sebelum mengulangi checkout.' : Object.values(data?.errors || {}).flat().join(' ') || data?.message || 'Permintaan gagal. Muat ulang lalu coba lagi.'));
        error.status = response.status;
        throw error;
    }
    return data;
};
</script>
