import React, { useState } from 'react';
import { money } from '../../lib/shop-client.js';
import { OrderDetails } from './Fields.jsx';

function Review({ item, order, copy, text, run, api, urls, busy }) {
    const [sent, setSent] = useState(false);
    if (sent) return <p role="status">{text('review_sent')}</p>;
    return <form className="stack" aria-label={`${text('review')}: ${item.product_title}`} onSubmit={event => {
        event.preventDefault();
        const fields = new FormData(event.currentTarget);
        run(async () => { await api(urls.reviews, 'POST', { order_id: order.id, product_id: item.product_id, rating: Number(fields.get('rating')), body: fields.get('body') }); setSent(true); return text('review_saved'); });
    }}><fieldset className="stack" disabled={busy}><legend>{item.product_title}</legend><label>{text('rating')}<select name="rating" required disabled={busy} defaultValue=""><option value="">{text('rating_pick')}</option>{[1, 2, 3, 4, 5].map(value => <option key={value} value={value}>{value}</option>)}</select></label><label>{text('review')}<textarea name="body" maxLength={2000} disabled={busy} /></label><button disabled={busy}>{text('send_review')}</button></fieldset></form>;
}
export default function Orders({ data, copy, text, statuses, run, api, urls, busy, loadOrders }) {
    if (!data) return <p>{copy.loading}</p>;
    return <><div id="order-list">{!data.data.length && <p>{text('no_orders')}</p>}{data.data.map(order => <article className="item" key={order.id}><OrderDetails order={order} text={text} statuses={statuses} money={money} />{order.status === 'completed' && order.items.map((item, index) => <Review key={index} {...{ item, order, copy, text, run, api, urls, busy }} />)}{order.status === 'pending' && <button className="secondary" disabled={busy} onClick={() => run(async () => { if (!window.confirm(text('confirm_cancel', { id: order.id }))) return; await api(`${urls.orders}/${order.id}/cancel`, 'POST'); await loadOrders(data.current_page); return text('order_cancelled'); })}>{text('cancel_order')}</button>}{['paid', 'processing'].includes(order.status) && <><p>{text('paid_cancel_unavailable')}</p><a href={`${urls.home}#services`}>{text('contact_concierge')}</a></>}</article>)}</div><div className="row" id="order-pages">{data.current_page > 1 && <button disabled={busy} onClick={() => run(() => loadOrders(data.current_page - 1))}>{text('prev')}</button>}<span>{text('page', { page: data.current_page, total: data.last_page })}</span>{data.current_page < data.last_page && <button disabled={busy} onClick={() => run(() => loadOrders(data.current_page + 1))}>{text('next')}</button>}</div></>;
}
