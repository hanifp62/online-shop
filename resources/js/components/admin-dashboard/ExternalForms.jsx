import { useRef } from 'react';
import { PostForm } from './Table';

export function CategoryCreate({ urls, csrfToken, copy: { common: c, dashboard: d } }) {
    return <PostForm action={urls.categoryStore} method="POST" token={csrfToken} className="inline-form" style={{ gridTemplateColumns: '1fr auto' }}><div className="field"><label htmlFor="cat_title">{d.add_category}</label><input id="cat_title" name="cat_title" type="text" required maxLength={255} placeholder={d.add_category_hint} autoComplete="off" /></div><button className="button button-sapphire" type="submit">{d.add}</button></PostForm>;
}
export function ProductCreate({ urls, csrfToken, copy: { common: c, dashboard: d }, options, initiallyOpen }) {
    // Like the Blade form outside swapped regions, keep its option list for this
    // document. A concurrent category deletion must not silently change the draft.
    const draftOptions = useRef(options).current;
    const fields = [
        ['product_title', d.product_name, 'text', true, 255, d.product_name_hint],
        ['product_price', d.price_rp, 'number', true, undefined, d.price_hint],
        ['stock', c.stock, 'number', true], ['product_image', d.image_url, 'url', true, 255, 'https://images.unsplash.com/...'],
        ['product_color', d.color, 'text', false, 50, d.color_hint], ['product_badge', d.badge, 'text', false, 50, d.badge_hint],
        ['product_description', d.description, 'textarea', false, 2000, d.description_hint],
        ['product_material', d.material, 'text', false, 255, d.material_hint], ['product_composition', d.composition, 'text', false, 500, d.composition_hint],
        ['product_origin', d.origin, 'text', false, 255, d.origin_hint], ['product_craft', d.craft, 'textarea', false, 2000, d.craft_hint], ['product_care', d.care, 'textarea', false, 2000, d.care_hint],
    ];
    return <details id="form-produk" open={initiallyOpen || undefined} className="dashboard-product-create"><summary>{d.add_new_product}</summary><PostForm action={urls.productStore} method="POST" token={csrfToken} className="inline-form dashboard-product-fields">
        {fields.map(([name, label, type, required, maxLength, placeholder]) => <div className={`field ${type === 'textarea' ? 'dashboard-wide' : ''}`} key={name}><label htmlFor={`create-${name}`}>{label}</label>{type === 'textarea' ? <textarea id={`create-${name}`} name={name} rows={2} maxLength={maxLength} placeholder={placeholder} /> : <input id={`create-${name}`} name={name} type={type} required={required} maxLength={maxLength} placeholder={placeholder} min={type === 'number' ? 0 : undefined} max={name === 'stock' ? 100000 : undefined} step={name === 'stock' ? 1 : undefined} defaultValue={name === 'stock' ? 0 : undefined} />}</div>)}
        <div className="field"><label htmlFor="create-product-cat">{c.category}</label><select id="create-product-cat" name="product_cat" required>{draftOptions.map(option => <option key={option.id} value={option.id}>{option.title}</option>)}</select></div>
        <div className="field"><label htmlFor="create-product-active">{d.show_catalog}</label><select id="create-product-active" name="is_active" defaultValue="1"><option value="1">{d.active}</option><option value="0">{d.inactive}</option></select></div><div className="dashboard-wide"><button className="button button-sapphire" type="submit">{d.add_product}</button></div>
    </PostForm></details>;
}
export function ReportForm({ urls, copy: { dashboard: d } }) {
    return <form method="GET" action={urls.report} className="inline-form"><div className="field"><label htmlFor="report-period">{d.report_period}</label><select id="report-period" name="period" defaultValue="today">{[['today', d.today], ['7d', d.last_7], ['30d', d.last_30], ['custom', d.date_range]].map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><div className="field"><label htmlFor="report-from">{d.from_utc}</label><input id="report-from" name="from" type="date" /></div><div className="field"><label htmlFor="report-to">{d.to_utc}</label><input id="report-to" name="to" type="date" /></div><button className="button button-sapphire" type="submit">{d.download_pdf}</button></form>;
}
export function SecurityForm({ urls, csrfToken, copy: { dashboard: d } }) {
    return <section id="panel-keamanan" className="admin-panel"><h2>{d.change_password}</h2><PostForm method="PATCH" action={urls.password} token={csrfToken} className="inline-form">{[['current_password', d.current_password], ['password', d.new_password], ['password_confirmation', d.confirm_password]].map(([name, label]) => <div className="field" key={name}><label htmlFor={name}>{label}</label><input id={name} name={name} type="password" required minLength={name === 'current_password' ? undefined : 8} autoComplete={name === 'current_password' ? 'current-password' : 'new-password'} /></div>)}<button className="button button-sapphire" type="submit">{d.change}</button></PostForm></section>;
}
