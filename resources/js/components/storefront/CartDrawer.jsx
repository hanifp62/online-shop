import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { rupiah } from '../../lib/storefront-cart.js';
import { Dialog, Icon, Toast } from './Primitives.jsx';
import { useStorefront } from './StorefrontProvider.jsx';

export default function CartDrawer() {
    const { copy: { cart: c }, t, cart, cartStatus, cartMessage, refreshCart, legacy, links, open, close, adjust, remove } = useStorefront();
    const focus = useRef(null); const root = useRef(null);
    const [settled, setSettled] = useState(null);
    useEffect(() => {
        const moved = event => { if (focus.current && event.target !== focus.current.origin) focus.current.cancelled = true; };
        document.addEventListener('focusin', moved);
        return () => document.removeEventListener('focusin', moved);
    }, []);
    useLayoutEffect(() => {
        if (!settled || focus.current !== settled) return;
        focus.current = null;
        if (settled.cancelled || !root.current) return;
        const active = document.activeElement;
        if (active !== settled.origin && !(active === document.body && !settled.origin.isConnected)) return;
        const target = root.current.querySelector(`[data-line="${settled.id}"] [data-action="${settled.action}"]`) || root.current.querySelector('[data-sf-cart-close]');
        target?.focus();
    }, [settled]);
    const mutate = async (id, action, task) => {
        const origin = document.activeElement;
        const intent = root.current?.querySelector(`[data-line="${id}"]`)?.contains(origin) ? { id, action, origin, cancelled: false } : null;
        focus.current = intent;
        await task();
        // A fresh GET publishes before the write. Restore only after the complete mutation commits.
        if (intent && focus.current === intent) setSettled(intent);
    };
    return <Dialog type="cart" title={c.title} className="drawer cart-drawer"><div ref={root} className="sf-cart-wrapper">
        <div className="drawer-header"><div><span className="eyebrow">{c.catalog}</span><h2>{c.title} <small className="cart-count-text">({cart.items.reduce((n, item) => n + item.quantity, 0)})</small></h2></div><button data-sf-cart-close className="icon-button" type="button" onClick={close} aria-label={c.close}><Icon name="close" /></button></div>
        <p>{c.notice}</p><p role="status" aria-live="polite" data-cart-status>{cartMessage}</p>{cartStatus === 'error' && <button className="text-link" type="button" onClick={() => void refreshCart()}>{c.loading}</button>}
        {!!legacy.length && <details><summary>{c.legacy_title}</summary><p>{c.legacy_notice}</p><ul>{legacy.map((item, index) => <li key={index}>{String(item.name || item.id || t('legacy_item'))} × {String(item.quantity || 1)}{item.size ? ` · ${t('size_label')}: ${item.size}` : ''}{item.monogram ? ` · ${t('monogram_label')}: ${item.monogram}` : ''}</li>)}</ul><button className="text-link" type="button" onClick={() => open('concierge')}>{c.legacy_consult}</button></details>}
        <div className="cart-body">{cart.items.map(item => <article className="cart-item" key={item.product_id} data-line={item.product_id}><div className="cart-item-info"><h3>{item.product_title}</h3><p translate="no">{rupiah(item.unit_price)}</p>{!item.available && <p>{t('unavailable')}</p>}<div className="quantity-control" aria-label={t('qty_group', { name: item.product_title })}><button type="button" data-action="minus" aria-label={t('qty_minus')} onClick={() => void mutate(item.product_id, 'minus', () => adjust(item.product_id, -1))}><Icon name="minus" /></button><span>{item.quantity}</span><button type="button" data-action="plus" aria-label={t('qty_plus')} onClick={() => void mutate(item.product_id, 'plus', () => adjust(item.product_id, 1))}><Icon name="plus" /></button></div></div><button className="cart-remove" type="button" data-action="remove" aria-label={t('remove_item', { name: item.product_title })} onClick={() => void mutate(item.product_id, 'remove', () => remove(item.product_id))}><Icon name="close" /></button></article>)}</div>
        {!cart.items.length && cartStatus === 'ready' && <div className="cart-empty"><span className="empty-gem" /><h3>{c.empty}</h3><p>{c.empty_hint}</p><button className="button button-dark" type="button" onClick={close}>{c.explore}</button></div>}
        {!!cart.items.length && <div className="cart-footer"><div className="cart-total"><span>{c.subtotal}</span><strong translate="no">{rupiah(cart.subtotal)}</strong></div><a className="button button-sapphire" href={links.shop}>{c.continue}<Icon name="arrow" /></a></div>}
        <Toast />
    </div></Dialog>;
}
