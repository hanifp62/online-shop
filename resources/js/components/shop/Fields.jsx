import React from 'react';

const addressFields = [
    ['label', 'label', 50, 'off'], ['recipient_name', 'recipient', 120, 'shipping name'],
    ['phone', 'recipient_phone', 30, 'shipping tel'], ['line1', 'street', 255, 'shipping address-line1'],
    ['line2', 'detail', 255, 'shipping address-line2'], ['city', 'city', 120, 'shipping address-level2'],
    ['province', 'province', 120, 'shipping address-level1'], ['postal_code', 'postal', 20, 'shipping postal-code'],
];
export function AddressFields({ copy, guest = false }) {
    const field = ([name, label, maxLength, autoComplete]) => <label key={name}>{copy[!guest && name === 'phone' ? 'phone' : label] ?? copy.phone}<input name={name} required={name !== 'line2'} maxLength={maxLength} autoComplete={autoComplete} type={name === 'phone' ? 'tel' : 'text'} placeholder={!guest && name === 'label' ? copy.label_placeholder : undefined} defaultValue={guest && name === 'label' ? copy.label_default : ''} /></label>;
    return guest ? <>{addressFields.slice(0, 5).map(field)}<div className="field-pair">{addressFields.slice(5).map(field)}</div></> : addressFields.map(field);
}
export function MarkedCopy({ children, href }) {
    return String(children ?? '').split(/(\[\[.*?\]\])/g).map((part, index) => part.startsWith('[[')
        ? href ? <a key={index} href={href}>{part.slice(2, -2)}</a> : <strong key={index}>{part.slice(2, -2)}</strong>
        : part);
}
export function OrderDetails({ order, text, statuses, money }) {
    return <><h3>{text('order_title', { id: order.id })}</h3><p>{text('order_line', { status: statuses[order.status] ?? statuses.unknown, amount: money(order.total) })}</p>{order.items.map((item, index) => <p key={index}>{item.product_title} × {item.quantity} · {money(item.subtotal)}</p>)}</>;
}
