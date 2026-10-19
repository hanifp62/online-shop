<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>Belanja — ZAFIRA La Maison de Lumière</title>
<link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=bodoni-moda:400,500,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
<style>
:root{--sapphire:#0d1b2a;--linen:#f4ede2;--gold:#a17d36;--ivory:#faf7f2;--line:#d8d0c5}
*{box-sizing:border-box}body{margin:0;background:var(--ivory);color:var(--sapphire);font:15px/1.65 Manrope,sans-serif}a{color:var(--sapphire)}a:hover{color:var(--gold)}h1,h2,h3,.brand{font-family:'Bodoni Moda',serif;font-weight:400}h1{font-size:clamp(2.3rem,5vw,4rem);margin:.2em 0}h2{font-size:2rem;margin:0 0 .5rem}h3{font-size:1.4rem;margin:.5rem 0}.brand{font-size:1.7rem;letter-spacing:.18em;text-decoration:none}header,main,footer{max-width:1100px;margin:auto;padding:24px}header{display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap}nav{display:flex;gap:16px;flex-wrap:wrap}nav a{min-height:44px;display:inline-flex;align-items:center}main{padding-bottom:72px}.intro{border-top:1px solid var(--line);padding:36px 0}.eyebrow{color:var(--gold);letter-spacing:.2em;text-transform:uppercase;font-size:.75rem}section.panel{background:white;border:1px solid var(--line);padding:clamp(18px,3vw,32px);margin:20px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px}.stack{display:grid;gap:12px}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.item{border-top:1px solid var(--line);padding:14px 0;overflow-wrap:anywhere}.muted{color:#555}label{display:block;font-size:.85rem;font-weight:600}input,select,textarea,button{font:inherit}input,select,textarea{width:100%;min-width:0;padding:10px 12px;overflow-wrap:anywhere;word-break:break-word;border:1px solid #827b72;background:white;min-height:44px;color:var(--sapphire)}textarea{min-height:80px}button,.button{border:1px solid var(--sapphire);background:var(--sapphire);color:white;cursor:pointer;padding:10px 16px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}button:hover,button:focus-visible,.button:hover,.button:focus-visible{background:var(--gold);border-color:var(--gold);color:var(--sapphire)}button:disabled{opacity:.5;cursor:not-allowed}.secondary{background:white;color:var(--sapphire)}:focus-visible{outline:3px solid var(--gold);outline-offset:2px}.notice{background:var(--linen);border-left:4px solid var(--gold);padding:14px 18px}.status{min-height:24px}footer{border-top:1px solid var(--line);color:#555}.skip{position:absolute;left:-9999px}.skip:focus{left:12px;top:12px;background:white;padding:12px;z-index:10}code{display:block;max-width:100%;overflow-wrap:anywhere;word-break:break-all;user-select:all}
.checkout-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,340px);grid-template-rows:auto minmax(0,1fr);gap:24px;align-items:start}.checkout-summary{position:sticky;top:16px;grid-column:2;grid-row:1;align-self:start;background:var(--linen);border:1px solid var(--line);padding:16px;min-width:0;overflow-wrap:anywhere}.checkout-summary p{margin:.35rem 0}.checkout-fields{grid-column:1;grid-row:1;min-width:0}.checkout-fields fieldset{margin:0;min-width:0;border:1px solid var(--line);padding:16px}.checkout-fields .field-pair{display:grid;grid-template-columns:1fr 1fr;gap:12px}.checkout-fields p{margin:0 0 12px}
@media(max-width:760px){.checkout-layout{display:block}.checkout-summary{position:sticky;top:0;z-index:1;grid-column:1;grid-row:1;padding:8px 12px}.checkout-summary>:not(h3):not(#checkout-estimate){display:none}.checkout-summary h3{margin:0;font-size:1rem}.checkout-fields{grid-column:1;grid-row:2;min-width:0}.checkout-fields :is(input,select,button){scroll-margin-top:var(--checkout-summary-clearance,100px)}.checkout-fields .field-pair{grid-template-columns:1fr}.checkout-fields input,.checkout-fields select{font-size:16px}}
</style>
</head>
<body>
<a class="skip" href="#main">Lewati ke konten utama</a>
<header><a class="brand" href="{{ route('home') }}">ZAFIRA</a><nav aria-label="Navigasi belanja"><a href="{{ route('catalog.index') }}">Koleksi</a><a href="#tas">Tas</a><a href="#alamat">Alamat</a><a href="#pesanan">Pesanan</a><a href="#favorit">Favorit</a>@auth<a href="{{ route('account.show') }}">Akun</a>@else<a href="{{ route('login') }}">Masuk</a>@endauth</nav></header>
<main id="main"><div class="intro"><span class="eyebrow">Le Salon Privé</span><h1>Belanja di Maison</h1><p>Kelola pesanan nyata dari koleksi ZAFIRA.</p><p class="notice">Beranda, halaman produk, dan koleksi kini menambah ke tas pembelian standar yang sama di bawah ini. Tas lama versi sebelumnya yang tersimpan di peramban <strong>tidak tersambung</strong> dan tidak digabung otomatis; lihat isinya di laci tas beranda, lalu tambahkan ulang bila perlu. Ukuran, warna pilihan, dan monogram <strong>belum didukung</strong> dalam pesanan ini; produk dikirim sesuai spesifikasi katalog. Untuk opsi tersebut, kirim permintaan concierge dan jangan lanjutkan checkout.</p></div>
<p id="feedback" role="status" aria-live="polite" class="notice status">Memuat belanja…</p>
<div class="grid"><section class="panel" id="tas"><h2>Tas pembelian standar</h2><div id="cart-list"></div><p id="cart-total"></p><button type="button" id="clear-cart" class="secondary">Kosongkan tas</button></section>
<section class="panel" id="alamat"><h2>Alamat pengiriman <span id="address-count" data-list-count="addresses" aria-live="polite"></span></h2><div id="address-list"></div>@auth<form id="address-form" class="stack"><h3>Tambah alamat</h3><label>Label<input name="label" required maxlength="50" placeholder="Rumah"></label><label>Nama penerima<input name="recipient_name" required maxlength="120" autocomplete="name"></label><label>Telepon<input name="phone" required maxlength="30" autocomplete="tel"></label><label>Alamat jalan<input name="line1" required maxlength="255" autocomplete="street-address"></label><label>Detail tambahan<input name="line2" maxlength="255"></label><label>Kota<input name="city" required maxlength="120" autocomplete="address-level2"></label><label>Provinsi<input name="province" required maxlength="120" autocomplete="address-level1"></label><label>Kode pos<input name="postal_code" required maxlength="20" autocomplete="postal-code"></label><label class="row"><input style="width:auto" type="checkbox" name="is_default" value="1"> Jadikan alamat utama</label><button>Tambah alamat</button></form>@endauth
</section></div>
<section class="panel" id="checkout"><h2>Checkout</h2><p class="notice">Checkout membuat pesanan berstatus <strong>pending / belum dibayar</strong>. Tidak ada pembayaran online atau konfirmasi pembayaran otomatis. Total dihitung ulang oleh server; periksa hasil pesanan sebelum mencoba lagi.</p>
<div class="checkout-layout">
<aside id="checkout-summary" class="checkout-summary" aria-live="polite" aria-labelledby="checkout-summary-heading">
<h3 id="checkout-summary-heading">Ringkasan pesanan</h3><strong id="checkout-estimate" role="status" aria-live="polite">Memuat ringkasan pesanan…</strong>
<p class="muted">Diskon voucher dihitung server setelah kode diterapkan. Total memakai tarif server terbaru.</p>
</aside>
<div class="checkout-fields" role="region" aria-label="Formulir checkout">
@auth<form id="checkout-form" class="stack"><label>Alamat tersimpan<select id="checkout-address" name="address_id" required></select></label><label>Kode voucher (opsional)<input name="voucher_code" maxlength="40"></label><button id="checkout-button">Buat pesanan pending</button></form>@else<p>Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>. Atau lengkapi kontak dan alamat untuk memesan sebagai tamu.</p><form id="guest-checkout-form" class="stack" method="post"><fieldset class="stack"><legend>Langkah 1/2 · Kontak</legend><label>Nama lengkap<input name="customer_name" required maxlength="120" autocomplete="name"></label><label>Email<input type="email" name="customer_email" required maxlength="255" autocomplete="email"></label><label>Telepon<input type="tel" name="customer_phone" required maxlength="30" autocomplete="tel" placeholder="+62 812 3456 7890"></label></fieldset><fieldset class="stack"><legend>Langkah 2/2 · Alamat pengiriman</legend><label>Label<input name="label" required maxlength="50" value="Utama"></label><label>Nama penerima<input name="recipient_name" required maxlength="120" autocomplete="shipping name"></label><label>Telepon penerima<input type="tel" name="phone" required maxlength="30" autocomplete="shipping tel"></label><label>Alamat jalan<input name="line1" required maxlength="255" autocomplete="shipping address-line1"></label><label>Detail tambahan<input name="line2" maxlength="255" autocomplete="shipping address-line2"></label><div class="field-pair"><label>Kota<input name="city" required maxlength="120" autocomplete="shipping address-level2"></label><label>Provinsi<input name="province" required maxlength="120" autocomplete="shipping address-level1"></label><label>Kode pos<input name="postal_code" required maxlength="20" autocomplete="shipping postal-code"></label></div></fieldset><button id="guest-checkout-button" disabled>Buat pesanan tamu</button></form>@endauth
</div></div></section>
<div class="grid"><section class="panel" id="pesanan"><h2>Pesanan <span id="order-count" data-list-count="orders" aria-live="polite"></span></h2>@guest<div id="guest-order-confirmation" aria-live="polite"></div><button id="guest-clear-token" type="button" class="secondary">Hapus token dari formulir</button><form id="guest-track-form" class="stack" method="post"><label>Token pelacakan pesanan<input name="token" required minlength="64" maxlength="64" autocomplete="off"></label><button type="submit">Lacak pesanan</button></form><div id="guest-track-result" aria-live="polite"></div>@endguest<div id="order-list"></div><div id="order-pages" class="row"></div></section><section class="panel" id="favorit"><h2>Favorit <span id="favorite-count" data-list-count="favorites" aria-live="polite"></span></h2><p>Tambahkan favorit dari <a href="{{ route('catalog.index') }}">katalog</a>.</p><div id="favorite-list"></div></section></div>
</main><footer>© {{ date('Y') }} ZAFIRA Maison · La Maison de Lumière</footer>
@include('shop.request')
<script>
(() => {
    const api = window.shopRequest;
    const loggedIn = @json(auth()->check());
    const $ = id => document.getElementById(id);
    // Mobile: ringkasan sticky menutupi bagian atas viewport; ukur tingginya agar fokus field tidak tertutup.
    const checkoutSummary = $('checkout-summary');
    const updateSummaryClearance = () => document.documentElement.style.setProperty('--checkout-summary-clearance', `${checkoutSummary.offsetHeight + 16}px`);
    if ('ResizeObserver' in window) new ResizeObserver(updateSummaryClearance).observe(checkoutSummary);
    function revealCheckoutField(event) {
        if (!window.matchMedia('(max-width:760px)').matches || !event.target.matches('.checkout-fields input, .checkout-fields select, .checkout-fields button')) return;
        updateSummaryClearance();
        if (event.target.getBoundingClientRect().top < checkoutSummary.getBoundingClientRect().bottom + 16) event.target.scrollIntoView({block: 'start', behavior: 'instant'});
    }
    $('checkout').addEventListener('focusin', revealCheckoutField);
    const money = value => 'Rp' + Number(value).toLocaleString('id-ID');
    const text = (tag, value) => { const node = document.createElement(tag); node.textContent = String(value ?? ''); return node; };
    const message = value => { $('feedback').textContent = value; };
    let cart = {items: [], subtotal: 0};
    let checkoutKey = null;
    let pendingCheckout = null;
    let ordersPage = 1;
    let busy = false;
    async function action(fn) {
        if (busy) return;
        busy = true;
        const controls = [...document.querySelectorAll('main button, main input, main select, main textarea')];
        const disabled = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        document.querySelector('main').setAttribute('aria-busy', 'true');
        message('Memproses…');
        try { await fn(); } catch (error) { message(error.message); }
        finally {
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            $('clear-cart').disabled = !cart.items.length;
            if (loggedIn) $('checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(item => !item.available));
            else $('guest-checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(item => !item.available));
            document.querySelector('main').removeAttribute('aria-busy');
            busy = false;
        }
    }
    async function loadCart() {
        const list = $('cart-list');
        try { cart = await api(@json(route('cart.show'))); } catch (error) {
            cart = {items: [], subtotal: 0};
            list.replaceChildren();
            const retry = text('button', 'Coba lagi'); retry.type = 'button'; retry.onclick = () => action(async () => { await loadCart(); message('Tas belanja berhasil dimuat.'); });
            list.append(text('p', 'Tas belum dapat dimuat.'), retry);
            $('cart-total').textContent = '';
            $('clear-cart').disabled = true;
            renderCheckoutSummary(false);
            $(loggedIn ? 'checkout-button' : 'guest-checkout-button').disabled = true;
            throw error;
        }
        list.replaceChildren();
        if (!cart.items.length) list.append(text('p', 'Tas kosong. Tambahkan kreasi dari katalog.'));
        for (const item of cart.items) {
            const row = text('div', ''); row.className = 'item stack';
            row.append(text('strong', item.product_title), text('span', `${money(item.unit_price)} × ${item.quantity} = ${money(item.subtotal)}`));
            if (!item.available) row.append(text('p', 'Stok tidak tersedia. Hapus atau periksa kembali sebelum checkout.'));
            const controls = text('div', ''); controls.className = 'row';
            const qty = document.createElement('input'); qty.type = 'number'; qty.min = 1; qty.max = 99; qty.value = item.quantity; qty.setAttribute('aria-label', `Jumlah ${item.product_title}`); qty.style.maxWidth = '90px';
            const update = text('button', 'Ubah jumlah'); update.type = 'button'; update.onclick = () => action(async () => { await api(@json(route('cart.items.store')), 'POST', {product_id:item.product_id, quantity:Number(qty.value)}); checkoutKey = pendingCheckout = null; await loadCart(); message('Jumlah di tas belanja diperbarui.'); });
            const remove = text('button', 'Hapus'); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { await api(@json(url('/cart/items')).concat('/' + item.product_id), 'DELETE'); checkoutKey = pendingCheckout = null; await loadCart(); message('Kreasi dihapus dari tas.'); });
            controls.append(qty, update, remove); row.append(controls); list.append(row);
        }
        $('cart-total').textContent = 'Subtotal: ' + money(cart.subtotal);
        $('clear-cart').disabled = !cart.items.length;
        renderCheckoutSummary();
        $(loggedIn ? 'checkout-button' : 'guest-checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(i => !i.available));
    }
    function renderCheckoutSummary(available = true) {
        const summary = $('checkout-summary');
        const heading = text('h3', 'Ringkasan pesanan'); heading.id = 'checkout-summary-heading';
        const estimate = $('checkout-estimate');
        const content = text('div', '');
        if (!available) {
            estimate.textContent = 'Subtotal belum tersedia';
            content.append(text('p', 'Ringkasan tidak tersedia. Muat ulang sebelum checkout.'));
            summary.replaceChildren(heading, estimate, content, text('p', 'Diskon voucher dan total akhir dikonfirmasi server setelah pesanan dibuat.'));
            return;
        }
        const count = cart.items.reduce((sum, item) => sum + Number(item.quantity), 0);
        estimate.textContent = `${count} barang · Subtotal: ${money(cart.subtotal)}`;
        for (const item of cart.items) content.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
        if (!cart.items.length) content.append(text('p', 'Tas kosong. Tambahkan kreasi dari katalog.'));

        // Rincian hanya dari angka server; klien tidak pernah menebak ongkir, pajak, atau total.
        const quoted = cart.items.length && ['shipping_fee', 'discount', 'tax', 'total'].every(k => Number.isInteger(cart[k]));
        if (quoted) {
            const breakdown = text('div', ''); breakdown.className = 'stack';
            breakdown.append(
                text('p', `Ongkir: ${money(cart.shipping_fee)}`),
                text('p', `Diskon: ${money(cart.discount)} (voucher dihitung saat pesanan dibuat)`),
                text('p', `Pajak: ${money(cart.tax)}`),
                text('strong', `Perkiraan total: ${money(cart.total)}`)
            );
            content.append(breakdown);
        } else {
            content.append(text('p', 'Diskon dan ongkir dihitung server saat pesanan dibuat'));
        }
        content.append(text('p', 'Status: pending / belum dibayar'));
        summary.replaceChildren(heading, estimate, content, text('p', 'Total akhir dikonfirmasi server setelah pesanan dibuat.'));
    }
    async function loadAddresses() {
        const data = await api(@json(route('addresses.index')));
        const list = $('address-list'), select = $('checkout-address'); list.replaceChildren(); select.replaceChildren();
        if (!data.addresses.length) list.append(text('p', 'Belum ada alamat. Tambahkan di bawah.'));
        for (const address of data.addresses) {
            const row = text('div', ''); row.className = 'item';
            row.append(text('strong', address.label + (address.is_default ? ' · Utama' : '')), text('p', `${address.recipient_name} · ${address.phone} · ${address.line1}${address.line2 ? ', ' + address.line2 : ''}, ${address.city}, ${address.province} ${address.postal_code}`));
            const remove = text('button', 'Hapus alamat'); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { if (!confirm('Hapus alamat ini? Pesanan lama tetap menyimpan salinannya.')) return; await api(@json(url('/addresses')).concat('/' + address.id), 'DELETE'); checkoutKey = pendingCheckout = null; await loadAddresses(); message('Alamat dihapus.'); }); row.append(remove); list.append(row);
            const option = new Option(address.label + ' — ' + address.line1, address.id); option.selected = !!address.is_default; select.add(option);
        }
        if (!data.addresses.some(a => a.is_default) && select.options.length) select.selectedIndex = 0;
        $('address-count').textContent = `(${data.addresses.length} alamat)`;
    }
    async function loadOrders(page = 1) {
        const data = await api(@json(route('orders.index')) + '?page=' + page);
        ordersPage = data.current_page;
        const list = $('order-list'); list.replaceChildren();
        if (!data.data.length) list.append(text('p', 'Belum ada pesanan.'));
        for (const order of data.data) {
            const row = text('article', ''); row.className = 'item';
            row.append(text('h3', `Pesanan #${order.id}`), text('p', `Status: ${order.status === 'pending' ? 'pending / belum dibayar' : order.status} · Total ${money(order.total)}`));
            for (const item of order.items) {
                row.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                if (order.status === 'completed') {
                    const form = document.createElement('form'); form.className = 'stack';
                    const ratingLabel = text('label', 'Nilai');
                    const rating = document.createElement('select'); rating.name = 'rating'; rating.required = true;
                    rating.add(new Option('Pilih 1–5', ''));
                    for (let value = 1; value <= 5; value++) rating.add(new Option(String(value), String(value)));
                    ratingLabel.append(rating);
                    const bodyLabel = text('label', 'Ulasan');
                    const body = document.createElement('textarea'); body.name = 'body'; body.maxLength = 2000;
                    bodyLabel.append(body);
                    form.append(ratingLabel, bodyLabel, text('button', 'Kirim ulasan'));
                    form.onsubmit = event => { event.preventDefault(); const fields = new FormData(form); action(async () => { await api(@json(route('reviews.store')), 'POST', {order_id:order.id, product_id:item.product_id, rating:Number(fields.get('rating')), body:fields.get('body')}); form.replaceWith(text('p', 'Ulasan terkirim. Terima kasih.')); message('Ulasan tersimpan.'); }); }; row.append(form);
                }
            }
            if (['pending', 'paid', 'processing'].includes(order.status)) {
                const cancel = text('button', 'Batalkan pesanan'); cancel.type = 'button'; cancel.className = 'secondary'; cancel.onclick = () => action(async () => { if (!confirm('Batalkan pesanan #' + order.id + '?')) return; await api(@json(url('/orders')).concat('/' + order.id + '/cancel'), 'POST'); await loadOrders(ordersPage); message('Pesanan dibatalkan.'); }); row.append(cancel);
            }
            list.append(row);
        }
        const pages = $('order-pages'); pages.replaceChildren();
        if (data.current_page > 1) { const prev = text('button', 'Sebelumnya'); prev.type = 'button'; prev.onclick = () => action(() => loadOrders(ordersPage - 1)); pages.append(prev); }
        pages.append(text('span', `Halaman ${data.current_page} / ${data.last_page}`));
        if (data.current_page < data.last_page) { const next = text('button', 'Berikutnya'); next.type = 'button'; next.onclick = () => action(() => loadOrders(ordersPage + 1)); pages.append(next); }
        $('order-count').textContent = `(${data.total} pesanan)`;
    }
    async function loadFavorites() {
        const data = await api(@json(route('favorites.index'))); const list = $('favorite-list'); list.replaceChildren();
        if (!data.favorites.length) list.append(text('p', 'Belum ada favorit.'));
        for (const favorite of data.favorites) {
            const row = text('div', ''); row.className = 'item row';
            const link = text('a', favorite.product?.product_title || 'Kreasi tidak tersedia');
            if (favorite.product?.product_slug) link.href = @json(url('/produk')).concat('/' + encodeURIComponent(favorite.product.product_slug));
            const remove = text('button', 'Hapus favorit'); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { await api(@json(url('/favorites')).concat('/' + favorite.product_id), 'DELETE'); await loadFavorites(); message('Favorit dihapus.'); }); row.append(link, remove); list.append(row);
        }
        $('favorite-count').textContent = `(${data.favorites.length} favorit)`;
    }
    $('clear-cart').onclick = () => action(async () => { if (!confirm('Kosongkan seluruh tas belanja?')) return; await api(@json(route('cart.clear')), 'DELETE'); checkoutKey = pendingCheckout = null; await loadCart(); message('Tas belanja dikosongkan.'); });
    if (loggedIn) {
        $('address-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => { await api(@json(route('addresses.store')), 'POST', Object.fromEntries(fields)); event.target.reset(); await loadAddresses(); message('Alamat tersimpan.'); }); };
        $('checkout-form').addEventListener('input', () => { checkoutKey = pendingCheckout = null; });
        $('checkout-form').onsubmit = event => { event.preventDefault(); action(async () => {
            if (!pendingCheckout) {
                if (!$('checkout-address').value) { message('Pilih alamat pengiriman terlebih dahulu.'); return; }
                await loadCart(); // Validate only new attempts; an uncertain response must replay the original payload.
                if (!cart.items.length || cart.items.some(i => !i.available)) { message('Periksa tas dan stok sebelum checkout.'); return; }
                const form = event.target;
                pendingCheckout = {address_id:Number(form.address_id.value), voucher_code:form.voucher_code.value.trim() || null, idempotency_key:checkoutKey ||= crypto.randomUUID()};
            }
            try { const result = await api(@json(route('checkout.store')), 'POST', pendingCheckout); checkoutKey = pendingCheckout = null; await loadCart(); await loadOrders(); message(`Pesanan #${result.order.id} dibuat: pending / belum dibayar. Total ${money(result.order.total)}. Tidak ada pembayaran online.`); }
            catch (error) { if ([409, 422].includes(error.status)) checkoutKey = pendingCheckout = null; throw error; }
        }); };
    }
    if (!loggedIn) {
        $('guest-checkout-form').addEventListener('input', () => { checkoutKey = pendingCheckout = null; });
        $('guest-checkout-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => {
            if (!pendingCheckout) {
                await loadCart(); // Validate only new attempts; an uncertain response must replay the original payload.
                if (!cart.items.length || cart.items.some(i => !i.available)) { message('Periksa tas dan stok sebelum checkout.'); return; }
                const payload = Object.fromEntries(fields); payload.idempotency_key = checkoutKey ||= crypto.randomUUID();
                pendingCheckout = payload;
            }
            try {
                const result = await api(@json(route('checkout.store')), 'POST', pendingCheckout); checkoutKey = pendingCheckout = null;
                const confirmation = $('guest-order-confirmation'); confirmation.replaceChildren();
                confirmation.append(text('h3', `Pesanan #${result.order.id}`), text('p', `Status: ${result.order.status} · Total ${money(result.order.total)}`));
                for (const item of result.order.items) confirmation.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                if (result.tracking_token) {
                    const note = text('p', 'Token pelacakan (tampil sekali, simpan sekarang):'); note.className = 'token-note';
                    confirmation.append(note, text('code', result.tracking_token));
                    document.querySelector('#guest-track-form [name=token]').value = result.tracking_token;
                } else if (result.message) {
                    confirmation.append(text('p', result.message));
                }
                event.target.reset(); await loadCart();
                message(`Pesanan #${result.order.id} dibuat: pending / belum dibayar. Total ${money(result.order.total)}. Tidak ada pembayaran online.`);
            } catch (error) { if ([200, 201, 409, 422].includes(error.status)) checkoutKey = pendingCheckout = null; throw error; }
        }); };
        $('guest-clear-token').onclick = () => {
            document.querySelector('#guest-track-form [name=token]').value = '';
            $('guest-order-confirmation').querySelectorAll('code, .token-note').forEach(node => node.remove());
            message('Token pelacakan dihapus dari halaman. Pastikan Anda sudah menyimpannya.');
        };
        $('guest-track-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => {
            try {
                const result = await api(@json(route('orders.track')), 'POST', {token: String(fields.get('token')).trim()});
                const tracked = $('guest-track-result'); tracked.replaceChildren();
                tracked.append(text('h3', `Pesanan #${result.order.id}`), text('p', `Status: ${result.order.status} · Total ${money(result.order.total)}`));
                for (const item of result.order.items) tracked.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                message('Pesanan ditemukan.');
            }
            catch (error) { $('guest-track-result').replaceChildren(); throw error; }
        }); };
    }
    action(async () => {
        const results = await Promise.allSettled([loadCart()]);
        if (loggedIn) results.push(...await Promise.allSettled([loadAddresses(), loadOrders(), loadFavorites()]));
        else {
            $('address-list').append(text('p', 'Masuk untuk menyimpan alamat.'));
            $('order-list').append(text('p', 'Masuk untuk melihat pesanan.'));
            $('favorite-list').append(text('p', 'Masuk untuk melihat favorit.'));
        }
        const failure = results.find(result => result.status === 'rejected');
        if (failure) throw failure.reason;
        message('Tas belanja siap.');
    });
})();
</script>
</body></html>
