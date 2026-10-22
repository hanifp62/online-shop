<script type="application/json" id="shop-request-copy">@json(__('shop_ui.request'))</script>
<script>
window.shopRequest = async function (url, method = 'GET', payload) {
    const copy = (() => { try { return JSON.parse(document.getElementById('shop-request-copy')?.textContent || '{}') || {}; } catch (_) { return {}; } })();
    const controller = new AbortController();
    let timedOut = false;
    let timer;
    const timeoutError = () => Object.assign(new Error(copy.timeout || 'Permintaan terlalu lama. Hasil tindakan belum pasti; periksa Pesanan sebelum mencoba checkout lagi.'), {timeout: true});
    // Race the complete operation, not only fetch headers: body reads can also stall.
    const deadline = new Promise((_, reject) => {
        timer = setTimeout(() => { timedOut = true; reject(timeoutError()); controller.abort(); }, 10000);
    });
    const operation = async () => {
        let response;
        try {
            response = await fetch(url, {
                method, credentials: 'same-origin', signal: controller.signal,
                headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                ...(payload === undefined ? {} : {body: JSON.stringify(payload)})
            });
        } catch (_) {
            if (timedOut) throw timeoutError();
            throw new Error(copy.network || 'Jaringan terputus. Periksa koneksi, lalu coba lagi.');
        }
        const data = await response.json().catch(() => null);
        if (timedOut) throw timeoutError();
        if (!response.ok || !data) {
            const messages = {401: copy[401] || 'Silakan masuk, lalu kembali ke Belanja.', 403: copy[403] || 'Akses tidak diizinkan untuk tindakan ini.', 404: copy[404] || 'Data atau pesanan tidak ditemukan.', 409: copy[409] || 'Terjadi konflik data atau pesanan sudah diproses. Muat ulang halaman.', 419: copy[419] || 'Sesi berakhir demi keamanan. Muat ulang halaman sebelum mencoba lagi.', 429: copy[429] || 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.'};
            const validation = response.status === 422 && data?.errors && typeof data.errors === 'object'
                ? Object.values(data.errors).flat().filter(value => typeof value === 'string').join(' ') : '';
            const error = new Error(messages[response.status] || (response.status >= 500 ? (copy.server || 'Layanan sedang bermasalah di peladen. Periksa Pesanan sebelum mengulangi checkout.') : validation || copy.generic || 'Permintaan gagal diproses. Muat ulang lalu coba lagi.'));
            // An unreadable successful response is uncertain, not a confirmed rejection.
            if (!response.ok) error.status = response.status;
            throw error;
        }
        return data;
    };
    try { return await Promise.race([operation(), deadline]); }
    finally { clearTimeout(timer); }
};
</script>
