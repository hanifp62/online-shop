<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('shop_ui.shop.title') }}</title>
<link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=bodoni-moda:400,500,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
<style>
:root{--sapphire:#0d1b2a;--linen:#f4ede2;--gold:#a17d36;--ivory:#faf7f2;--line:#d8d0c5}
*{box-sizing:border-box}body{margin:0;background:var(--ivory);color:var(--sapphire);font:15px/1.65 Manrope,sans-serif}a{color:var(--sapphire)}a:hover{color:var(--gold)}h1,h2,h3,.brand{font-family:'Bodoni Moda',serif;font-weight:400}h1{font-size:clamp(2.3rem,5vw,4rem);margin:.2em 0}h2{font-size:2rem;margin:0 0 .5rem}h3{font-size:1.4rem;margin:.5rem 0}.brand{font-size:1.7rem;letter-spacing:.18em;text-decoration:none}header,main,footer{max-width:1100px;margin:auto;padding:24px}header{display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap}nav{display:flex;gap:16px;flex-wrap:wrap}nav a{min-height:44px;display:inline-flex;align-items:center}main{padding-bottom:72px}.intro{border-top:1px solid var(--line);padding:36px 0}.eyebrow{color:var(--gold);letter-spacing:.2em;text-transform:uppercase;font-size:.75rem}section.panel{background:white;border:1px solid var(--line);padding:clamp(18px,3vw,32px);margin:20px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px}.stack{display:grid;gap:12px}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.item{border-top:1px solid var(--line);padding:14px 0;overflow-wrap:anywhere}.muted{color:#555}label{display:block;font-size:.85rem;font-weight:600}input,select,textarea,button{font:inherit}input,select,textarea{width:100%;min-width:0;padding:10px 12px;overflow-wrap:anywhere;word-break:break-word;border:1px solid #827b72;background:white;min-height:44px;color:var(--sapphire)}textarea{min-height:80px}button,.button{border:1px solid var(--sapphire);background:var(--sapphire);color:white;cursor:pointer;padding:10px 16px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}button:hover,button:focus-visible,.button:hover,.button:focus-visible{background:var(--gold);border-color:var(--gold);color:var(--sapphire)}button:disabled{opacity:.5;cursor:not-allowed}.secondary{background:white;color:var(--sapphire)}:focus-visible{outline:3px solid var(--gold);outline-offset:2px}.notice{background:var(--linen);border-left:4px solid var(--gold);padding:14px 18px}.status{min-height:24px}.notice.is-error{border-left-color:#9b443b;background:#ecddd7;color:#793c34}footer{border-top:1px solid var(--line);color:#555}.skip{position:absolute;left:-9999px}.skip:focus{left:12px;top:12px;background:white;padding:12px;z-index:10}code{display:block;max-width:100%;overflow-wrap:anywhere;word-break:break-all;user-select:all}
.checkout-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,340px);grid-template-rows:auto minmax(0,1fr);gap:24px;align-items:start}.checkout-summary{position:sticky;top:16px;grid-column:2;grid-row:1;align-self:start;background:var(--linen);border:1px solid var(--line);padding:16px;min-width:0;overflow-wrap:anywhere}.checkout-summary p{margin:.35rem 0}.checkout-fields{grid-column:1;grid-row:1;min-width:0}.checkout-fields fieldset{margin:0;min-width:0;border:1px solid var(--line);padding:16px}.checkout-fields .field-pair{display:grid;grid-template-columns:1fr 1fr;gap:12px}.checkout-fields p{margin:0 0 12px}
@media(max-width:760px){.checkout-layout{display:block}.checkout-summary{position:sticky;top:0;z-index:1;grid-column:1;grid-row:1;padding:8px 12px}.checkout-summary>:not(h3):not(#checkout-estimate){display:none}.checkout-summary h3{margin:0;font-size:1rem}.checkout-fields{grid-column:1;grid-row:2;min-width:0}.checkout-fields :is(input,select,button){scroll-margin-top:var(--checkout-summary-clearance,100px)}.checkout-fields .field-pair{grid-template-columns:1fr}.checkout-fields input,.checkout-fields select{font-size:16px}}
</style>
@isset($page)
@viteReactRefresh
@vite('resources/js/inertia.jsx')
@endisset
</head>
<body>
<x-private-page-lifecycle />
<x-react-root :page="$page ?? null">
<a class="skip" href="#main">{{ __('shop_ui.shop.skip') }}</a>
<header><a class="brand" href="{{ route('home') }}">ZAFIRA</a><nav aria-label="{{ __('shop_ui.shop.nav_label') }}"><a href="{{ route('catalog.index') }}">{{ __('shop_ui.shop.nav_collection') }}</a><a href="#tas">{{ __('shop_ui.shop.nav_bag') }}</a><a href="#alamat">{{ __('shop_ui.shop.nav_address') }}</a><a href="#pesanan">{{ __('shop_ui.shop.nav_orders') }}</a><a href="#favorit">{{ __('shop_ui.shop.nav_favorites') }}</a>@auth<a href="{{ route('account.show') }}">{{ __('shop_ui.shop.nav_account') }}</a>@else<a href="{{ route('login') }}">{{ __('shop_ui.shop.nav_login') }}</a>@endauth</nav></header>
<main id="main"><div class="intro"><span class="eyebrow">Le Salon Privé</span><h1>{{ __('shop_ui.shop.heading') }}</h1><p>{{ __('shop_ui.shop.lead') }}</p><p class="notice">{!! str_replace(['[[', ']]'], ['<strong>', '</strong>'], e(__('shop_ui.shop.notice'))) !!}</p></div>
<p id="feedback" role="status" aria-live="polite" class="notice status">{{ __('shop_ui.shop.loading') }}</p>
<div class="grid"><section class="panel" id="tas"><h2>{{ __('shop_ui.shop.bag_title') }}</h2><div id="cart-list"></div><p id="cart-total"></p><button type="button" id="clear-cart" class="secondary">{{ __('shop_ui.shop.clear_bag') }}</button></section>
<section class="panel" id="alamat"><h2>{{ __('shop_ui.shop.address_title') }} <span id="address-count" data-list-count="addresses" aria-live="polite"></span></h2><div id="address-list"></div>@auth<form id="address-form" class="stack"><h3>{{ __('shop_ui.shop.add_address') }}</h3><label>{{ __('shop_ui.shop.label') }}<input name="label" required maxlength="50" placeholder="{{ __('shop_ui.shop.label_placeholder') }}"></label><label>{{ __('shop_ui.shop.recipient') }}<input name="recipient_name" required maxlength="120" autocomplete="name"></label><label>{{ __('shop_ui.shop.phone') }}<input name="phone" required maxlength="30" autocomplete="tel"></label><label>{{ __('shop_ui.shop.street') }}<input name="line1" required maxlength="255" autocomplete="street-address"></label><label>{{ __('shop_ui.shop.detail') }}<input name="line2" maxlength="255"></label><label>{{ __('shop_ui.shop.city') }}<input name="city" required maxlength="120" autocomplete="address-level2"></label><label>{{ __('shop_ui.shop.province') }}<input name="province" required maxlength="120" autocomplete="address-level1"></label><label>{{ __('shop_ui.shop.postal') }}<input name="postal_code" required maxlength="20" autocomplete="postal-code"></label><label class="row"><input style="width:auto" type="checkbox" name="is_default" value="1"> {{ __('shop_ui.shop.make_default') }}</label><button>{{ __('shop_ui.shop.add_address') }}</button></form>@endauth
</section></div>
<section class="panel" id="checkout"><h2>{{ __('shop_ui.shop.checkout') }}</h2><p class="notice">{!! str_replace(['[[', ']]'], ['<strong>', '</strong>'], e(__('shop_ui.shop.checkout_notice'))) !!}</p>
<div class="checkout-layout">
<aside id="checkout-summary" class="checkout-summary" aria-live="polite" aria-labelledby="checkout-summary-heading">
<h3 id="checkout-summary-heading">{{ __('shop_ui.shop.summary_heading') }}</h3><strong id="checkout-estimate" role="status" aria-live="polite">{{ __('shop_ui.shop.summary_loading') }}</strong>
<p class="muted">{{ __('shop_ui.shop.summary_note') }}</p>{{-- Potongan voucher dan total akhir dipastikan saat pesanan dibuat --}}
</aside>
<div class="checkout-fields" role="region" aria-label="{{ __('shop_ui.shop.form_label') }}">
@auth<form id="checkout-form" class="stack"><label>{{ __('shop_ui.shop.saved_address') }}<select id="checkout-address" name="address_id" required></select></label><label>{{ __('shop_ui.shop.voucher') }}<input name="voucher_code" maxlength="40"></label><button id="checkout-button">{{ __('shop_ui.shop.confirm_order') }}</button></form>@else<p>{!! str_replace(['[[', ']]'], ['<a href="' . route('login') . '">', '</a>'], e(__('shop_ui.shop.guest_intro'))) !!}</p><form id="guest-checkout-form" class="stack" method="post"><fieldset class="stack"><legend>{{ __('shop_ui.shop.step_contact') }}</legend><label>{{ __('shop_ui.shop.full_name') }}<input name="customer_name" required maxlength="120" autocomplete="name"></label><label>{{ __('shop_ui.shop.email') }}<input type="email" name="customer_email" required maxlength="255" autocomplete="email"></label><label>{{ __('shop_ui.shop.phone') }}<input type="tel" name="customer_phone" required maxlength="30" autocomplete="tel" placeholder="+62 812 3456 7890"></label></fieldset><fieldset class="stack"><legend>{{ __('shop_ui.shop.step_address') }}</legend><label>{{ __('shop_ui.shop.label') }}<input name="label" required maxlength="50" value="{{ __('shop_ui.shop.label_default') }}"></label><label>{{ __('shop_ui.shop.recipient') }}<input name="recipient_name" required maxlength="120" autocomplete="shipping name"></label><label>{{ __('shop_ui.shop.recipient_phone') }}<input type="tel" name="phone" required maxlength="30" autocomplete="shipping tel"></label><label>{{ __('shop_ui.shop.street') }}<input name="line1" required maxlength="255" autocomplete="shipping address-line1"></label><label>{{ __('shop_ui.shop.detail') }}<input name="line2" maxlength="255" autocomplete="shipping address-line2"></label><div class="field-pair"><label>{{ __('shop_ui.shop.city') }}<input name="city" required maxlength="120" autocomplete="shipping address-level2"></label><label>{{ __('shop_ui.shop.province') }}<input name="province" required maxlength="120" autocomplete="shipping address-level1"></label><label>{{ __('shop_ui.shop.postal') }}<input name="postal_code" required maxlength="20" autocomplete="shipping postal-code"></label></div></fieldset><button id="guest-checkout-button" disabled>{{ __('shop_ui.shop.guest_button') }}</button></form>@endauth
</div></div></section>
<div class="grid"><section class="panel" id="pesanan"><h2>{{ __('shop_ui.shop.orders_title') }} <span id="order-count" data-list-count="orders" aria-live="polite"></span></h2>@guest<div id="guest-order-confirmation" aria-live="polite"></div><button id="guest-clear-token" type="button" class="secondary">{{ __('shop_ui.shop.clear_token') }}</button><form id="guest-track-form" class="stack" method="post"><label>{{ __('shop_ui.shop.track_label') }}<input name="token" required minlength="64" maxlength="64" autocomplete="off"></label><button type="submit">{{ __('shop_ui.shop.track_button') }}</button></form><div id="guest-track-result" aria-live="polite"></div>@endguest<div id="order-list"></div><div id="order-pages" class="row"></div></section><section class="panel" id="favorit"><h2>{{ __('shop_ui.shop.favorites_title') }} <span id="favorite-count" data-list-count="favorites" aria-live="polite"></span></h2><p>{!! str_replace(['[[', ']]'], ['<a href="' . route('catalog.index') . '">', '</a>'], e(__('shop_ui.shop.favorites_intro'))) !!}</p><div id="favorite-list"></div></section></div>
</main><footer>© {{ date('Y') }} ZAFIRA Maison · La Maison de Lumière</footer>
</x-react-root>
@include('shop.request')
<script type="application/json" id="shop-status-copy">@json(__('shop_ui.status'))</script>
<script type="application/json" id="shop-copy">@json(__('shop_ui.shop'))</script>
<script>
(function shopFallback() {
    const root = document.getElementById('app');
    if (root?.dataset?.reactBoot === 'pending') {
        const timer = setTimeout(() => {
            if (root.dataset.reactBoot === 'pending') root.dataset.reactBoot = 'fallback';
        }, 10000);
        const observer = new MutationObserver(() => {
            if (root.dataset.reactBoot === 'pending') return;
            clearTimeout(timer);
            observer.disconnect();
            if (root.dataset.reactBoot === 'fallback') shopFallback();
        });
        root.addEventListener('zafira:react-claim', () => { clearTimeout(timer); observer.disconnect(); }, {once: true});
        observer.observe(root, {attributes: true, attributeFilter: ['data-react-boot']});
        return;
    }
    if (['ready', 'failed'].includes(root?.dataset?.reactBoot)) return;
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
    const _shopStatusCatalog = (() => {
        try {
            const raw = document.getElementById('shop-status-copy')?.textContent;
            return raw ? JSON.parse(raw) : null;
        } catch (_) {
            return null;
        }
    })() || {
        pending: 'menunggu pembayaran',
        paid: 'dibayar',
        processing: 'diproses',
        shipped: 'dikirim',
        completed: 'selesai',
        cancelled: 'dibatalkan',
        unknown: 'Status belum tersedia'
    };
    const statusLabel = status => _shopStatusCatalog[status] || _shopStatusCatalog.unknown || 'Status belum tersedia';
    const text = (tag, value) => { const node = document.createElement(tag); node.textContent = String(value ?? ''); return node; };
    const message = (value, error = false) => { const feedback = $('feedback'); feedback.textContent = value; feedback.classList.toggle('is-error', error); feedback.setAttribute('role', error ? 'alert' : 'status'); };
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
        message(shopText('processing', 'Memproses…'));
        try { await fn(); } catch (error) { message(error.message, true); }
        finally {
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            $('clear-cart').disabled = !cart.items.length;
            if (loggedIn) $('checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(item => !item.available));
            else $('guest-checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(item => !item.available));
            // A replay uses the frozen payload, so edited/invalid visible fields must not
            // block it at the browser's constraint-validation gate before submit fires.
            $(loggedIn ? 'checkout-button' : 'guest-checkout-button').formNoValidate = Boolean(pendingCheckout);
            document.querySelector('main').removeAttribute('aria-busy');
            busy = false;
        }
    }
    async function loadCart() {
        const list = $('cart-list');
        try { cart = await api(@json(route('cart.show'))); } catch (error) {
            cart = {items: [], subtotal: 0};
            list.replaceChildren();
            const retry = text('button', shopText('retry', 'Coba lagi')); retry.type = 'button'; retry.onclick = () => action(async () => { await loadCart(); message(shopText('cart_reloaded', 'Tas belanja berhasil dimuat.')); });
            list.append(text('p', shopText('cart_failed', 'Tas belum dapat dimuat.')), retry);
            $('cart-total').textContent = '';
            $('clear-cart').disabled = true;
            renderCheckoutSummary(false);
            $(loggedIn ? 'checkout-button' : 'guest-checkout-button').disabled = true;
            throw error;
        }
        list.replaceChildren();
        if (!cart.items.length) list.append(text('p', shopText('cart_empty', 'Tas kosong. Tambahkan kreasi dari katalog.')));
        for (const item of cart.items) {
            const row = text('div', ''); row.className = 'item stack';
            row.append(text('strong', item.product_title), text('span', `${money(item.unit_price)} × ${item.quantity} = ${money(item.subtotal)}`));
            if (!item.available) row.append(text('p', shopText('unavailable', 'Stok tidak tersedia. Hapus atau periksa kembali sebelum checkout.')));
            const controls = text('div', ''); controls.className = 'row';
            const qty = document.createElement('input'); qty.type = 'number'; qty.min = 1; qty.max = 99; qty.value = item.quantity; qty.setAttribute('aria-label', shopText('qty_aria', `Jumlah ${item.product_title}`, {title: item.product_title})); qty.style.maxWidth = '90px';
            const update = text('button', shopText('update', 'Ubah jumlah')); update.type = 'button'; update.onclick = () => action(async () => { requireSettledCheckout(); await api(@json(route('cart.items.store')), 'POST', {product_id:item.product_id, quantity:Number(qty.value)}); checkoutKey = pendingCheckout = null; await loadCart(); message(shopText('qty_updated', 'Jumlah di tas belanja diperbarui.')); });
            const remove = text('button', shopText('remove', 'Hapus')); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { requireSettledCheckout(); await api(@json(url('/cart/items')).concat('/' + item.product_id), 'DELETE'); checkoutKey = pendingCheckout = null; await loadCart(); message(shopText('removed', 'Kreasi dihapus dari tas.')); });
            controls.append(qty, update, remove); row.append(controls); list.append(row);
        }
        $('cart-total').textContent = shopText('subtotal', 'Subtotal: :amount', {amount: money(cart.subtotal)});
        $('clear-cart').disabled = !cart.items.length;
        renderCheckoutSummary();
        $(loggedIn ? 'checkout-button' : 'guest-checkout-button').disabled = !pendingCheckout && (!cart.items.length || cart.items.some(i => !i.available));
    }
    function renderCheckoutSummary(available = true) {
        const summary = $('checkout-summary');
        const heading = text('h3', shopText('summary_heading', 'Ringkasan pesanan')); heading.id = 'checkout-summary-heading';
        const estimate = $('checkout-estimate');
        const content = text('div', '');
        if (!available) {
            estimate.textContent = shopText('estimate_unavailable', 'Subtotal belum tersedia');
            content.append(text('p', shopText('summary_unavailable', 'Ringkasan tidak tersedia. Muat ulang sebelum checkout.')));
            summary.replaceChildren(heading, estimate, content, text('p', shopText('summary_note', 'Potongan voucher dan total akhir dipastikan saat pesanan dibuat.')));
            return;
        }
        const count = cart.items.reduce((sum, item) => sum + Number(item.quantity), 0);
        estimate.textContent = shopText('estimate', ':count barang · Subtotal: :amount', {count, amount: money(cart.subtotal)});
        for (const item of cart.items) content.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
        if (!cart.items.length) content.append(text('p', shopText('cart_empty', 'Tas kosong. Tambahkan kreasi dari katalog.')));

        // Rincian hanya dari angka server; klien tidak pernah menebak ongkir, pajak, atau total.
        const quoted = cart.items.length && ['shipping_fee', 'discount', 'tax', 'total'].every(k => Number.isInteger(cart[k]));
        if (quoted) {
            const breakdown = text('div', ''); breakdown.className = 'stack';
            breakdown.append(
                text('p', shopText('shipping', 'Ongkir: :amount', {amount: money(cart.shipping_fee)})),
                text('p', cart.voucher_code
                    ? shopText('discount_voucher', 'Diskon voucher :code: −:amount', {code: cart.voucher_code, amount: money(cart.discount)})
                    : shopText('discount_hint', 'Diskon: :amount (masukkan kode voucher untuk melihat potongan)', {amount: money(cart.discount)})),
                text('p', shopText('tax', 'Pajak: :amount', {amount: money(cart.tax)})),
                text('strong', shopText('estimate_total', 'Perkiraan total: :amount', {amount: money(cart.total)}))
            );
            content.append(breakdown);
        } else {
            content.append(text('p', shopText('quote_pending', 'Potongan dan ongkir dipastikan saat pesanan dibuat')));
        }
        content.append(text('p', shopText('status_pending_line', 'Status: menunggu pembayaran')));
        summary.replaceChildren(heading, estimate, content, text('p', shopText('final_total', 'Total akhir dipastikan saat pesanan dibuat.')));
    }
    async function refreshVoucherQuote(form) {
        const code = form?.voucher_code?.value.trim();
        if (!loggedIn || !code || !cart.items.length) { await loadCart(); return; }
        try {
            cart = await api(@json(route('checkout.quote')), 'POST', {voucher_code: code});
            renderCheckoutSummary();
        } catch (error) { message(error.message); }
    }
    function shopText(key, fallback, params = {}) {
        let copy;
        try { copy = JSON.parse(document.getElementById('shop-copy')?.textContent || '{}'); } catch (_) { copy = {}; }
        const value = copy?.js?.[key] ?? copy?.[key] ?? fallback;
        return String(value).replace(/:([a-z_]+)/g, (match, name) => Object.hasOwn(params, name) ? String(params[name]) : match);
    }
    async function loadAddresses() {
        const data = await api(@json(route('addresses.index')));
        const list = $('address-list'), select = $('checkout-address'); list.replaceChildren(); select.replaceChildren();
        if (!data.addresses.length) list.append(text('p', shopText('no_address', 'Belum ada alamat. Tambahkan di bawah.')));
        for (const address of data.addresses) {
            const row = text('div', ''); row.className = 'item';
            row.append(text('strong', address.label + (address.is_default ? ' · ' + shopText('default_suffix', 'Utama') : '')), text('p', `${address.recipient_name} · ${address.phone} · ${address.line1}${address.line2 ? ', ' + address.line2 : ''}, ${address.city}, ${address.province} ${address.postal_code}`));
            const remove = text('button', shopText('remove_address', 'Hapus alamat')); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { if (!confirm(shopText('confirm_remove_address', 'Hapus alamat ini? Pesanan lama tetap menyimpan salinannya.'))) return; requireSettledCheckout(); await api(@json(url('/addresses')).concat('/' + address.id), 'DELETE'); checkoutKey = pendingCheckout = null; await loadAddresses(); message(shopText('address_removed', 'Alamat dihapus.')); }); row.append(remove); list.append(row);
            const option = new Option(address.label + ' — ' + address.line1, address.id); option.selected = !!address.is_default; select.add(option);
        }
        if (!data.addresses.some(a => a.is_default) && select.options.length) select.selectedIndex = 0;
        $('address-count').textContent = shopText('address_count', '(:count alamat)', {count: data.addresses.length});
    }
    async function loadOrders(page = 1) {
        const data = await api(@json(route('orders.index')) + '?page=' + page);
        ordersPage = data.current_page;
        const list = $('order-list'); list.replaceChildren();
        if (!data.data.length) list.append(text('p', shopText('no_orders', 'Belum ada pesanan.')));
        for (const order of data.data) {
            const row = text('article', ''); row.className = 'item';
            row.append(text('h3', shopText('order_title', 'Pesanan #:id', {id: order.id})), text('p', shopText('order_line', 'Status: :status · Total :amount', {status: statusLabel(order.status), amount: money(order.total)})));
            for (const item of order.items) {
                row.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                if (order.status === 'completed') {
                    const form = document.createElement('form'); form.className = 'stack';
                    const ratingLabel = text('label', shopText('rating', 'Nilai'));
                    const rating = document.createElement('select'); rating.name = 'rating'; rating.required = true;
                    rating.add(new Option(shopText('rating_pick', 'Pilih 1–5'), ''));
                    for (let value = 1; value <= 5; value++) rating.add(new Option(String(value), String(value)));
                    ratingLabel.append(rating);
                    const bodyLabel = text('label', shopText('review', 'Ulasan'));
                    const body = document.createElement('textarea'); body.name = 'body'; body.maxLength = 2000;
                    bodyLabel.append(body);
                    form.append(ratingLabel, bodyLabel, text('button', shopText('send_review', 'Kirim ulasan')));
                    form.onsubmit = event => { event.preventDefault(); const fields = new FormData(form); action(async () => { await api(@json(route('reviews.store')), 'POST', {order_id:order.id, product_id:item.product_id, rating:Number(fields.get('rating')), body:fields.get('body')}); form.replaceWith(text('p', shopText('review_sent', 'Ulasan terkirim. Terima kasih.'))); message(shopText('review_saved', 'Ulasan tersimpan.')); }); }; row.append(form);
                }
            }
            if (order.status === 'pending') {
                const cancel = text('button', shopText('cancel_order', 'Batalkan pesanan')); cancel.type = 'button'; cancel.className = 'secondary'; cancel.onclick = () => action(async () => { if (!confirm(shopText('confirm_cancel', 'Batalkan pesanan #:id?', {id: order.id}))) return; await api(@json(url('/orders')).concat('/' + order.id + '/cancel'), 'POST'); await loadOrders(ordersPage); message(shopText('order_cancelled', 'Pesanan dibatalkan.')); }); row.append(cancel);
            } else if (['paid', 'processing'].includes(order.status)) {
                row.append(text('p', shopText('paid_cancel_unavailable', 'Pesanan yang sudah dibayar atau diproses tidak dapat dibatalkan di sini. Hubungi concierge untuk membahas langkah berikutnya; pengembalian dana tidak otomatis.')));
                                const help = text('a', shopText('contact_concierge', 'Hubungi concierge')); help.href = @json(route('home')) + '#services'; row.append(help);
            }
            list.append(row);
        }
        const pages = $('order-pages'); pages.replaceChildren();
        if (data.current_page > 1) { const prev = text('button', shopText('prev', 'Sebelumnya')); prev.type = 'button'; prev.onclick = () => action(() => loadOrders(ordersPage - 1)); pages.append(prev); }
        pages.append(text('span', shopText('page', 'Halaman :page / :total', {page: data.current_page, total: data.last_page})));
        if (data.current_page < data.last_page) { const next = text('button', shopText('next', 'Berikutnya')); next.type = 'button'; next.onclick = () => action(() => loadOrders(ordersPage + 1)); pages.append(next); }
        $('order-count').textContent = shopText('order_count', '(:count pesanan)', {count: data.total});
    }
    async function loadFavorites() {
        const data = await api(@json(route('favorites.index'))); const list = $('favorite-list'); list.replaceChildren();
        if (!data.favorites.length) list.append(text('p', shopText('no_favorites', 'Belum ada favorit.')));
        for (const favorite of data.favorites) {
            const row = text('div', ''); row.className = 'item row';
            const link = text('a', favorite.product?.product_title || shopText('item_unavailable', 'Kreasi tidak tersedia'));
            if (favorite.product?.product_slug) link.href = @json(url('/produk')).concat('/' + encodeURIComponent(favorite.product.product_slug));
            const remove = text('button', shopText('remove_favorite', 'Hapus favorit')); remove.type = 'button'; remove.className = 'secondary'; remove.onclick = () => action(async () => { await api(@json(url('/favorites')).concat('/' + favorite.product_id), 'DELETE'); await loadFavorites(); message(shopText('favorite_removed', 'Favorit dihapus.')); }); row.append(link, remove); list.append(row);
        }
        $('favorite-count').textContent = shopText('favorite_count', '(:count favorit)', {count: data.favorites.length});
    }
    function pendingCheckoutMessage() {
        return shopText('checkout_pending', 'Hasil checkout sebelumnya belum pasti. Percobaan berikutnya memakai rincian pesanan semula; periksa Pesanan sebelum membuat pesanan baru.');
    }
    function validateCheckoutResult(result) {
        if (!result?.order || !Number.isInteger(result.order.id) || result.order.id <= 0 || !Number.isInteger(result.order.total)
            || typeof result.order.status !== 'string' || !Array.isArray(result.order.items)
            || !result.order.items.every(item => item !== null && typeof item === 'object'
                && typeof item.product_title === 'string' && Number.isInteger(item.quantity) && item.quantity > 0 && Number.isInteger(item.subtotal))) {
            throw new Error(pendingCheckoutMessage());
        }
    }
    function requireSettledCheckout() {
        if (pendingCheckout) throw new Error(pendingCheckoutMessage());
    }
    function checkoutInputChanged() {
        if (pendingCheckout) { message(pendingCheckoutMessage(), true); return; }
        checkoutKey = null;
    }
    $('clear-cart').onclick = () => action(async () => { if (!confirm(shopText('confirm_clear', 'Kosongkan seluruh tas belanja?'))) return; requireSettledCheckout(); await api(@json(route('cart.clear')), 'DELETE'); checkoutKey = pendingCheckout = null; await loadCart(); message(shopText('cart_cleared', 'Tas belanja dikosongkan.')); });
    if (loggedIn) {
        $('address-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => { await api(@json(route('addresses.store')), 'POST', Object.fromEntries(fields)); event.target.reset(); await loadAddresses(); message(shopText('address_saved', 'Alamat tersimpan.')); }); };
        $('checkout-form').addEventListener('input', checkoutInputChanged);
        $('checkout-form')?.voucher_code?.addEventListener?.('change', event => action(() => refreshVoucherQuote(event.target.form)));
        $('checkout-form').onsubmit = event => { event.preventDefault(); action(async () => {
            if (!pendingCheckout) {
                if (!$('checkout-address').value) { message(shopText('pick_address', 'Pilih alamat pengiriman terlebih dahulu.')); return; }
                await loadCart(); // Validate only new attempts; an uncertain response must replay the original payload.
                if (!cart.items.length || cart.items.some(i => !i.available)) { message(shopText('check_cart', 'Periksa tas dan stok sebelum checkout.')); return; }
                const form = event.target;
                pendingCheckout = {address_id:Number(form.address_id.value), voucher_code:form.voucher_code.value.trim() || null, idempotency_key:checkoutKey ||= crypto.randomUUID()};
            }
            try { const result = await api(@json(route('checkout.store')), 'POST', pendingCheckout); validateCheckoutResult(result); checkoutKey = pendingCheckout = null; await loadCart(); await loadOrders(); message(shopText('order_created', 'Pesanan #:id dibuat: menunggu pembayaran. Total :amount. Pembayaran online belum tersedia dan tidak ada konfirmasi pembayaran otomatis.', {id: result.order.id, amount: money(result.order.total)})); }
            catch (error) { if ([409, 422].includes(error.status)) checkoutKey = pendingCheckout = null; throw error; }
        }); };
    }
    if (!loggedIn) {
        $('guest-checkout-form').addEventListener('input', checkoutInputChanged);
        $('guest-checkout-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => {
            if (!pendingCheckout) {
                await loadCart(); // Validate only new attempts; an uncertain response must replay the original payload.
                if (!cart.items.length || cart.items.some(i => !i.available)) { message(shopText('check_cart', 'Periksa tas dan stok sebelum checkout.')); return; }
                const payload = Object.fromEntries(fields); payload.idempotency_key = checkoutKey ||= crypto.randomUUID();
                pendingCheckout = payload;
            }
            try {
                const result = await api(@json(route('checkout.store')), 'POST', pendingCheckout); validateCheckoutResult(result); checkoutKey = pendingCheckout = null;
                const confirmation = $('guest-order-confirmation'); confirmation.replaceChildren();
                confirmation.append(text('h3', shopText('order_title', 'Pesanan #:id', {id: result.order.id})), text('p', shopText('order_line', 'Status: :status · Total :amount', {status: statusLabel(result.order.status), amount: money(result.order.total)})));
                for (const item of result.order.items) confirmation.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                if (result.tracking_token) {
                    const note = text('p', shopText('token_note', 'Kode pelacakan (tampil sekali, simpan sekarang):')); note.className = 'token-note';
                    confirmation.append(note, text('code', result.tracking_token));
                    document.querySelector('#guest-track-form [name=token]').value = result.tracking_token;
                } else if (result.message) {
                    confirmation.append(text('p', result.message));
                }
                event.target.reset(); await loadCart();
                message(shopText('order_created', 'Pesanan #:id dibuat: menunggu pembayaran. Total :amount. Pembayaran online belum tersedia dan tidak ada konfirmasi pembayaran otomatis.', {id: result.order.id, amount: money(result.order.total)}));
            } catch (error) { if ([409, 422].includes(error.status)) checkoutKey = pendingCheckout = null; throw error; }
        }); };
        $('guest-clear-token').onclick = () => {
            document.querySelector('#guest-track-form [name=token]').value = '';
            $('guest-order-confirmation').querySelectorAll('code, .token-note').forEach(node => node.remove());
            message(shopText('token_cleared', 'Kode pelacakan dihapus dari halaman. Pastikan Anda sudah menyimpannya.'));
        };
        $('guest-track-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => {
            try {
                const result = await api(@json(route('orders.track')), 'POST', {token: String(fields.get('token')).trim()});
                const tracked = $('guest-track-result'); tracked.replaceChildren();
                tracked.append(text('h3', shopText('order_title', 'Pesanan #:id', {id: result.order.id})), text('p', shopText('order_line', 'Status: :status · Total :amount', {status: statusLabel(result.order.status), amount: money(result.order.total)})));
                for (const item of result.order.items) tracked.append(text('p', `${item.product_title} × ${item.quantity} · ${money(item.subtotal)}`));
                message(shopText('order_found', 'Pesanan ditemukan.'));
            }
            catch (error) { $('guest-track-result').replaceChildren(); throw error; }
        }); };
    }
    action(async () => {
        const results = await Promise.allSettled([loadCart()]);
        if (loggedIn) results.push(...await Promise.allSettled([loadAddresses(), loadOrders(), loadFavorites()]));
        else {
            $('address-list').append(text('p', shopText('login_address', 'Masuk untuk menyimpan alamat.')));
            $('order-list').append(text('p', shopText('login_orders', 'Masuk untuk melihat pesanan.')));
            $('favorite-list').append(text('p', shopText('login_favorites', 'Masuk untuk melihat favorit.')));
        }
        const failure = results.find(result => result.status === 'rejected');
        if (failure) throw failure.reason;
        message(shopText('ready', 'Tas belanja siap.'));
    });
})();
</script>
</body></html>
