import React, { useEffect, useRef, useState } from 'react';
import { createCheckoutClient, createShopRequest, money, shopText, validCart, validOrder } from '../../lib/shop-client.js';
import { AddressFields, MarkedCopy, OrderDetails } from '../../components/shop/Fields.jsx';
import Summary from '../../components/shop/Summary.jsx';
import Orders from '../../components/shop/Orders.jsx';
import '../../../css/shop-react.css';

function GoogleEmblem() {
    return <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="#4285F4" d="M21.35 11.1H12v2.98h5.35c-.23 1.45-1.67 4.25-5.35 4.25a5.9 5.9 0 0 1 0-11.8c1.87 0 3.12.8 3.83 1.48l2.6-2.5A9.4 9.4 0 0 0 12 2.6a9.4 9.4 0 1 0 0 18.8c5.43 0 9.03-3.82 9.03-9.2 0-.62-.07-1.09-.15-1.58z" />
    </svg>;
}

function csrfToken() {
    return typeof document === 'undefined' ? '' : document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function variantPayload(item) {
    const extra = {};
    if (item.size) extra.size = item.size;
    if (item.monogram) extra.monogram = item.monogram;
    return extra;
}

export default function Shop({ loggedIn, googleEnabled = false, requireAuthCheckout = false, authErrors = [], oldEmail = '', oldName = '', copy, requestCopy, statuses, urls, year, request: injectedRequest }) {
    const text = (key, params) => shopText(copy, key, params);
    const [api] = useState(() => injectedRequest ?? createShopRequest(requestCopy));
    const [cart, setCart] = useState(null);
    const [loading, setLoading] = useState(true);
    const [addresses, setAddresses] = useState(null);
    const [orders, setOrders] = useState(null);
    const [favorites, setFavorites] = useState(null);
    const [busy, setBusy] = useState(false);
    const [pending, setPending] = useState(null);
    const [feedback, setFeedback] = useState({ message: copy.loading, error: false });
    const [confirmation, setConfirmation] = useState(null);
    const [tracked, setTracked] = useState(null);
    const [token, setToken] = useState('');
    const [authTab, setAuthTab] = useState(oldName ? 'register' : 'login');
    const gate = useRef(false);
    const alive = useRef(true);
    const authWallActive = !loggedIn && Boolean(requireAuthCheckout);

    const loadCart = async () => {
        try {
            const next = await api(urls.cart);
            if (!validCart(next)) throw new Error(text('cart_failed'));
            if (alive.current) setCart(next);
            return next;
        } catch (error) { if (alive.current) setCart(null); throw error; }
    };
    const loadAddresses = async () => { const data = await api(urls.addresses); setAddresses(data.addresses); };
    const loadOrders = async (page = 1) => { setOrders(await api(`${urls.orders}?page=${page}`)); };
    const loadFavorites = async () => { const data = await api(urls.favorites); setFavorites(data.favorites); };
    const [checkout] = useState(() => createCheckoutClient({ api, urls, loggedIn, loadCart, text, onPending: setPending }));

    async function run(operation) {
        if (gate.current) return;
        gate.current = true; setBusy(true); setFeedback({ message: text('processing'), error: false });
        try { const message = await operation(); if (alive.current) setFeedback({ message: typeof message === 'string' ? message : text('ready'), error: false }); }
        catch (error) { if (alive.current) setFeedback({ message: error.message, error: true }); }
        finally { gate.current = false; if (alive.current) setBusy(false); }
    }

    useEffect(() => {
        alive.current = true;
        void run(async () => {
            const results = await Promise.allSettled([loadCart(), ...(loggedIn ? [loadAddresses(), loadOrders(), loadFavorites()] : [])]);
            setLoading(false);
            const failure = results.find(result => result.status === 'rejected');
            if (failure) throw failure.reason;
        });
        return () => { alive.current = false; };
    }, []);

    const settled = () => { if (checkout.pending) throw new Error(text('checkout_pending')); };
    const mutateCart = (url, method, payload, message) => run(async () => { settled(); await api(url, method, payload); await loadCart(); return text(message); });
    const submitCheckout = event => {
        event.preventDefault();
        const form = event.currentTarget;
        const fields = new FormData(form);
        void run(async () => {
            const result = await checkout.submit(fields);
            if (!result) return;
            setConfirmation({ ...result, tracking_token: /^[a-f0-9]{64}$/.test(result.tracking_token ?? '') ? result.tracking_token : null });
            if (!loggedIn) { setToken(/^[a-f0-9]{64}$/.test(result.tracking_token ?? '') ? result.tracking_token : ''); form.reset(); }
            const success = text('order_created', { id: result.order.id, amount: money(result.order.total) });
            const refresh = await Promise.allSettled([loadCart(), ...(loggedIn ? [loadOrders()] : [])]);
            const failure = refresh.find(entry => entry.status === 'rejected');
            if (failure) { setFeedback({ message: `${success} ${failure.reason.message}`, error: true }); return `${success} ${failure.reason.message}`; }
            return success;
        });
    };
    const revealField = event => {
        if (!window.matchMedia?.('(max-width:760px)').matches || !event.target.matches('input,select,button')) return;
        const summary = document.getElementById('checkout-summary');
        if (summary && event.target.getBoundingClientRect().top < summary.getBoundingClientRect().bottom + 16) event.target.scrollIntoView({ block: 'start', behavior: 'instant' });
    };
    const blocked = busy || Boolean(pending);
    const checkoutDisabled = busy || (!pending && (!cart?.items.length || cart.items.some(item => !item.available)));
    const retry = operation => <button type="button" disabled={busy} onClick={() => run(operation)}>{text('retry')}</button>;
    const loginHref = urls.loginCheckout || urls.login;
    const registerHref = urls.registerCheckout || urls.login;
    const googleHref = urls.googleCheckout || '/auth/google/redirect?intended=%2Fbelanja%23checkout';

    return <div className="shop-react"><a className="skip" href="#main">{copy.skip}</a><header><a className="brand" href={urls.home}>ZAFIRA</a><nav aria-label={copy.nav_label}><a href={urls.catalog}>{copy.nav_collection}</a>{[['tas', 'nav_bag'], ['alamat', 'nav_address'], ['pesanan', 'nav_orders'], ['favorit', 'nav_favorites']].map(([id, key]) => <a key={id} href={`#${id}`}>{copy[key]}</a>)}<a href={loggedIn ? urls.account : loginHref}>{loggedIn ? copy.nav_account : copy.nav_login}</a></nav></header>
        <main id="main" aria-busy={busy}><div className="intro"><span className="eyebrow">Le Salon Privé</span><h1>{copy.heading}</h1><p>{copy.lead}</p><p className="notice"><MarkedCopy>{copy.notice}</MarkedCopy></p></div><p id="feedback" role={feedback.error ? 'alert' : 'status'} aria-live="polite" className={`notice status${feedback.error ? ' is-error' : ''}`}>{feedback.message}</p>
            <div className="grid"><section className="panel" id="tas"><h2>{copy.bag_title}</h2><div id="cart-list">{loading ? <p>{copy.loading}</p> : !cart ? <><p>{text('cart_failed')}</p>{retry(async () => { await loadCart(); return text('cart_reloaded'); })}</> : !cart.items.length ? <p>{text('cart_empty')}</p> : cart.items.map(item => {
                const lineKey = item.line_key || item.product_id;
                const extra = variantPayload(item);
                const hasVariant = Object.keys(extra).length > 0;
                return <div key={lineKey} className="item stack">
                    <div className="item-head">
                        <strong>{item.product_title}</strong>
                        {hasVariant && <div className="variant-badges">{item.size && <span className="variant-pill">{text('variant_size', { size: item.size })}</span>}{item.monogram && <span className="variant-pill">{text('variant_monogram', { monogram: item.monogram })}</span>}</div>}
                    </div>
                    <span>{money(item.unit_price)} × {item.quantity} = {money(item.subtotal)}</span>
                    {!item.available && <p>{text('unavailable')}</p>}
                    <form className="row" onSubmit={event => { event.preventDefault(); const fields = new FormData(event.currentTarget); const quantity = Number(fields.get('quantity')); if (Number.isInteger(quantity) && quantity >= 1 && quantity <= 99) mutateCart(urls.cartStore, 'POST', { product_id: item.product_id, quantity, ...extra }, 'qty_updated'); }}>
                        <input className="quantity" name="quantity" type="number" min="1" max="99" step="1" required defaultValue={item.quantity} key={item.quantity} aria-label={text('qty_aria', { title: item.product_title })} disabled={blocked} />
                        <button disabled={blocked}>{text('update')}</button>
                        <button type="button" className="secondary" disabled={blocked} onClick={() => mutateCart(`${urls.cartItems}/${item.product_id}`, 'DELETE', hasVariant ? extra : undefined, 'removed')}>{text('remove')}</button>
                    </form>
                </div>;
            })}</div><p id="cart-total">{cart && text('subtotal', { amount: money(cart.subtotal) })}</p><button type="button" className="secondary" disabled={blocked || !cart?.items.length} onClick={() => { if (window.confirm(text('confirm_clear'))) mutateCart(urls.cartClear, 'DELETE', undefined, 'cart_cleared'); }}>{copy.clear_bag}</button></section>
                <section className="panel" id="alamat"><h2>{copy.address_title} <span aria-live="polite">{addresses && text('address_count', { count: addresses.length })}</span></h2>{!loggedIn ? <p>{text('login_address')}</p> : <><div id="address-list">{addresses === null ? <><p>{copy.loading}</p>{!loading && retry(loadAddresses)}</> : !addresses.length ? <p>{text('no_address')}</p> : addresses.map(address => <div className="item" key={address.id}><strong>{address.label}{address.is_default ? ` · ${text('default_suffix')}` : ''}</strong><p>{address.recipient_name} · {address.phone} · {address.line1}{address.line2 ? `, ${address.line2}` : ''}, {address.city}, {address.province} {address.postal_code}</p><button className="secondary" disabled={blocked} onClick={() => run(async () => { settled(); if (!window.confirm(text('confirm_remove_address'))) return; await api(`${urls.addresses}/${address.id}`, 'DELETE'); await loadAddresses(); return text('address_removed'); })}>{text('remove_address')}</button></div>)}</div><form id="address-form" className="stack" onSubmit={event => { event.preventDefault(); const form = event.currentTarget; const fields = new FormData(form); run(async () => { await api(urls.addressStore, 'POST', Object.fromEntries(fields)); form.reset(); await loadAddresses(); return text('address_saved'); }); }}><h3>{copy.add_address}</h3><fieldset className="stack" disabled={busy}><AddressFields copy={copy} /><label className="row"><input className="checkbox" type="checkbox" name="is_default" value="1" />{copy.make_default}</label><button>{copy.add_address}</button></fieldset></form></>}</section></div>
            <section className="panel" id="checkout" onFocus={revealField}><h2>{copy.checkout}</h2><p className="notice"><MarkedCopy>{copy.checkout_notice}</MarkedCopy></p>{pending && <p role="alert">{text('checkout_pending')}</p>}<div className="checkout-layout"><Summary cart={cart} text={text} loading={loading} /><div className="checkout-fields" role="region" aria-label={copy.form_label}>
                {authWallActive ? <div id="checkout-auth-wall" className="auth-wall stack">
                    <div className="auth-wall-header">
                        <span className="eyebrow">{copy.auth_wall_eyebrow}</span>
                        <h3>{copy.auth_wall_title}</h3>
                        <p>{copy.auth_wall_lead}</p>
                        <div className="auth-wall-sync-badge" role="status">
                            <span>✓ {copy.auth_wall_bag_safe}</span>
                            {cart?.items?.length > 0 && <strong> · {cart.items.reduce((n, i) => n + i.quantity, 0)} item ({money(cart.subtotal)})</strong>}
                        </div>
                    </div>
                    {authErrors?.length > 0 && <div className="notice is-error" role="alert"><ul>{authErrors.map((err, index) => <li key={index}>{err}</li>)}</ul></div>}
                    <a id="google-checkout-login" className="google-auth-btn" href={googleHref} data-social-provider="google" aria-disabled={!googleEnabled || undefined} onClick={event => { if (!googleEnabled && copy.auth_wall_google_setup) { event.preventDefault(); setFeedback({ message: copy.auth_wall_google_setup, error: false }); } }}>
                        <GoogleEmblem />
                        <span>{copy.auth_wall_google}</span>
                    </a>
                    {!googleEnabled && copy.auth_wall_google_setup && <small className="auth-wall-hint">{copy.auth_wall_google_setup}</small>}
                    <div className="auth-wall-divider"><span>{copy.auth_wall_or}</span></div>
                    <div className="auth-wall-tabs" role="tablist">
                        <button type="button" role="tab" aria-selected={authTab === 'login'} className={authTab === 'login' ? 'is-active' : 'secondary'} onClick={() => setAuthTab('login')}>{copy.auth_wall_tab_login}</button>
                        <button type="button" role="tab" aria-selected={authTab === 'register'} className={authTab === 'register' ? 'is-active' : 'secondary'} onClick={() => setAuthTab('register')}>{copy.auth_wall_tab_register}</button>
                    </div>
                    {authTab === 'login' ? <form id="checkout-login-form" method="POST" action={urls.loginSubmit || urls.login} className="stack" onSubmit={event => { const t = event.currentTarget.elements.namedItem('_token'); if (t) t.value = csrfToken(); }}>
                        <input type="hidden" name="_token" defaultValue={csrfToken()} />
                        <input type="hidden" name="intended" value="/belanja#checkout" />
                        <label>{copy.email}<input name="email" type="email" required maxLength={255} autoComplete="email" defaultValue={oldEmail} /></label>
                        <label>{copy.auth_wall_password}<input name="password" type="password" required autoComplete="current-password" /></label>
                        <button type="submit">{copy.auth_wall_login_btn}</button>
                        <p className="auth-wall-links"><a href={loginHref}>{copy.auth_wall_full_login}</a></p>
                    </form> : <form id="checkout-register-form" method="POST" action={urls.registerSubmit || registerHref} className="stack" onSubmit={event => { const t = event.currentTarget.elements.namedItem('_token'); if (t) t.value = csrfToken(); }}>
                        <input type="hidden" name="_token" defaultValue={csrfToken()} />
                        <input type="hidden" name="intended" value="/belanja#checkout" />
                        <label>{copy.full_name}<input name="name" type="text" required maxLength={120} autoComplete="name" defaultValue={oldName} /></label>
                        <label>{copy.email}<input name="email" type="email" required maxLength={255} autoComplete="email" defaultValue={oldEmail} /></label>
                        <label>{copy.phone}<input name="mobile" type="tel" maxLength={30} autoComplete="tel" /></label>
                        <label>{copy.auth_wall_password}<input name="password" type="password" required minLength={8} autoComplete="new-password" /><small className="auth-wall-hint">{copy.auth_wall_password_hint}</small></label>
                        <label>{copy.auth_wall_password_confirm}<input name="password_confirmation" type="password" required minLength={8} autoComplete="new-password" /></label>
                        <button type="submit">{copy.auth_wall_register_btn}</button>
                        <p className="auth-wall-links"><a href={registerHref}>{copy.auth_wall_full_register}</a></p>
                    </form>}
                </div> : <>
                    {!loggedIn && <p><MarkedCopy href={loginHref}>{copy.guest_intro}</MarkedCopy></p>}
                    <form id={loggedIn ? 'checkout-form' : 'guest-checkout-form'} className="stack" onSubmit={submitCheckout} onInput={() => { if (pending) setFeedback({ message: text('checkout_pending'), error: true }); }}>
                        {loggedIn ? <><label>{copy.saved_address}<select name="address_id" required disabled={busy} key={JSON.stringify(addresses?.map(a => [a.id, a.is_default]))} defaultValue={addresses?.find(a => a.is_default)?.id ?? addresses?.[0]?.id ?? ''}><option value="" disabled>{text('pick_address')}</option>{addresses?.map(address => <option key={address.id} value={address.id}>{address.label} · {address.line1}</option>)}</select></label><label>{copy.voucher}<input name="voucher_code" maxLength={40} disabled={busy} onBlur={event => { if (pending || event.relatedTarget?.id === 'checkout-button') return; const code = event.target.value.trim(); run(async () => { if (!code) { await loadCart(); return; } const quote = await api(urls.quote, 'POST', { voucher_code: code }); if (!validCart(quote)) throw new Error(text('cart_failed')); setCart(quote); }); }} /></label></> : <><fieldset className="stack" disabled={busy}><legend>{copy.step_contact}</legend>{[['customer_name', 'full_name', 'text', 120, 'name'], ['customer_email', 'email', 'email', 255, 'email'], ['customer_phone', 'phone', 'tel', 30, 'tel']].map(([name, label, type, maxLength, autoComplete]) => <label key={name}>{copy[label]}<input {...{ name, type, maxLength, autoComplete }} required /></label>)}</fieldset><fieldset className="stack" disabled={busy}><legend>{copy.step_address}</legend><AddressFields copy={copy} guest /></fieldset></>}
                        <button id={loggedIn ? 'checkout-button' : 'guest-checkout-button'} disabled={checkoutDisabled} formNoValidate={Boolean(pending)}>{loggedIn ? copy.confirm_order : copy.guest_button}</button>
                    </form>
                </>}
            </div></div></section>
            <div className="grid"><section className="panel" id="pesanan"><h2>{copy.orders_title} <span aria-live="polite">{orders && text('order_count', { count: orders.total })}</span></h2>{loggedIn ? <>{orders ? <Orders {...{ data: orders, copy, text, statuses, run, api, urls, busy, loadOrders }} /> : <>{copy.loading}{!loading && retry(loadOrders)}</>}</> : <><div id="guest-order-confirmation" aria-live="polite">{confirmation && <><OrderDetails order={confirmation.order} {...{ text, statuses, money }} />{confirmation.tracking_token ? <><p>{text('token_note')}</p><code>{confirmation.tracking_token}</code></> : confirmation.message && <p>{confirmation.message}</p>}</>}</div><button type="button" className="secondary" disabled={busy} onClick={() => { setToken(''); setConfirmation(value => value ? { ...value, tracking_token: null } : null); setFeedback({ message: text('token_cleared'), error: false }); }}>{copy.clear_token}</button><form id="guest-track-form" className="stack" onSubmit={event => { event.preventDefault(); const fields = new FormData(event.currentTarget); const value = String(fields.get('token')).trim(); if (!/^[a-f0-9]{64}$/.test(value)) return; run(async () => { setTracked(null); const result = await api(urls.track, 'POST', { token: value }); if (!validOrder(result)) throw new Error(requestCopy.generic); setTracked(result.order); return text('order_found'); }); }}><label>{copy.track_label}<input name="token" required minLength={64} maxLength={64} pattern="[a-f0-9]{64}" autoComplete="off" value={token} onChange={event => setToken(event.target.value)} disabled={busy} /></label><button disabled={busy}>{copy.track_button}</button></form><div id="guest-track-result" aria-live="polite">{tracked && <OrderDetails order={tracked} {...{ text, statuses, money }} />}</div><p>{text('login_orders')}</p></>}</section>
                <section className="panel" id="favorit"><h2>{copy.favorites_title} <span aria-live="polite">{favorites && text('favorite_count', { count: favorites.length })}</span></h2><p><MarkedCopy href={urls.catalog}>{copy.favorites_intro}</MarkedCopy></p>{!loggedIn ? <p>{text('login_favorites')}</p> : favorites === null ? <>{copy.loading}{!loading && retry(loadFavorites)}</> : <div id="favorite-list">{!favorites.length && <p>{text('no_favorites')}</p>}{favorites.map(favorite => <div className="item row" key={favorite.product_id}>{favorite.product?.product_slug ? <a href={`${urls.products}/${encodeURIComponent(favorite.product.product_slug)}`}>{favorite.product.product_title}</a> : <span>{text('item_unavailable')}</span>}<button disabled={busy} className="secondary" onClick={() => run(async () => { await api(`${urls.favorites}/${favorite.product_id}`, 'DELETE'); await loadFavorites(); return text('favorite_removed'); })}>{text('remove_favorite')}</button></div>)}</div>}</section></div>
        </main><footer>© {year} ZAFIRA Maison · La Maison de Lumière</footer></div>;
}
