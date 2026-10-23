import React, { useEffect, useRef } from 'react';
import { money } from '../../lib/shop-client.js';

export default function Summary({ cart, text, loading }) {
    const ref = useRef(null);
    useEffect(() => {
        const node = ref.current;
        const measure = () => document.documentElement.style.setProperty('--checkout-summary-clearance', `${node.offsetHeight + 16}px`);
        measure();
        const observer = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(measure) : null;
        observer?.observe(node);
        return () => observer?.disconnect();
    }, []);
    const quoted = cart?.items.length > 0 && ['shipping_fee', 'discount', 'tax', 'total'].every(key => Number.isInteger(cart[key]));
    return <aside ref={ref} id="checkout-summary" className="checkout-summary" aria-live="polite" aria-labelledby="checkout-summary-heading">
        <h3 id="checkout-summary-heading">{text('summary_heading')}</h3>
        <strong id="checkout-estimate" role="status">{loading ? text('summary_loading') : cart ? text('estimate', { count: cart.items.reduce((sum, item) => sum + item.quantity, 0), amount: money(cart.subtotal) }) : text('estimate_unavailable')}</strong>
        <div>{!cart ? <p>{text('summary_unavailable')}</p> : <>{cart.items.map(item => <p key={item.product_id}>{item.product_title} × {item.quantity} · {money(item.subtotal)}</p>)}{!cart.items.length && <p>{text('cart_empty')}</p>}{quoted ? <div className="stack"><p>{text('shipping', { amount: money(cart.shipping_fee) })}</p><p>{text(cart.voucher_code ? 'discount_voucher' : 'discount_hint', { code: cart.voucher_code, amount: money(cart.discount) })}</p><p>{text('tax', { amount: money(cart.tax) })}</p><strong>{text('estimate_total', { amount: money(cart.total) })}</strong></div> : <p>{text('quote_pending')}</p>}<p>{text('status_pending_line')}</p></>}</div><p>{text('final_total')}</p>
    </aside>;
}
