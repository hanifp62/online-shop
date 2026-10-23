import { useState } from 'react';
import { sanitizeMonogram } from '../../lib/storefront-preferences.js';
import { CloseButton, Dialog, Icon, Reveal } from './Primitives.jsx';
import { useStorefront } from './StorefrontProvider.jsx';

export function ProductCard({ product, related = false }) {
    const { copy: { pdp: p }, t, open, add, wishlist, wishlistKey, toggleFavorite } = useStorefront();
    const selected = wishlist.includes(wishlistKey(product));
    const image = <img src={product.image} srcSet={product.srcset || undefined} sizes="(max-width: 700px) 50vw, 25vw" decoding="async" alt={product.alt} loading="lazy" width="800" height="1000" />;
    return <Reveal as="article" className="product-card"><div className="product-media">{related ? <a href={product.href}>{image}</a> : image}{product.badge && <span className="product-badge">{product.badge}</span>}<button className={`wishlist-button${selected ? ' active' : ''}`} type="button" aria-label={t('wish_saved', { name: product.name }) || product.name} aria-pressed={selected} onClick={() => toggleFavorite(product)}><Icon name="heart" /></button><button className="quick-view-button" type="button" onClick={() => open('quick', product)}>{p.quick_view}</button></div><div className="product-info"><span>{product.category}</span><h3><button type="button" onClick={() => open('quick', product)}>{product.name}</button></h3><p translate="no">{product.priceLabel}</p>{product.stock <= 0 && <p>{p.sold_out}</p>}<div className="product-color"><i style={{ '--swatch': product.swatch }} />{product.color}</div><button className="product-add" type="button" disabled={product.stock <= 0} aria-label={`${product.name}: ${product.stock <= 0 ? p.sold_out : p.card_add}`} onClick={() => void add(product)}><Icon name="bag" /><span>{product.stock <= 0 ? p.sold_out : p.card_add}</span></button></div></Reveal>;
}
export function QuickView() {
    const { copy: { footer: f, pdp: p }, dialog, open, add } = useStorefront();
    const product = dialog?.type === 'quick' ? dialog.data : null;
    if (!product) return null;
    return <Dialog type="quick" title={product.name} className="quick-view-modal modal"><CloseButton label={f.close} /><div className="quick-view-media"><img src={product.image} alt={product.alt || product.name} width="896" height="1200" decoding="async" /></div><div className="quick-view-content"><span className="eyebrow">{product.category}</span><h2>{product.name}</h2><p className="quick-price" translate="no">{product.priceLabel}</p><p className="quick-description">{product.description}</p><div className="quick-detail"><span>{p.color}</span><strong>{product.color}</strong></div>{product.material && <div className="quick-detail"><span>{f.quick_material}</span><strong>{product.material}</strong></div>}{product.origin && <div className="quick-detail"><span>{f.quick_origin}</span><strong>{product.origin}</strong></div>}<div className="quick-detail"><span>{f.quick_availability}</span><strong>{product.stock <= 0 ? p.sold_out : f.quick_available}</strong></div><button className="button button-sapphire" type="button" disabled={product.stock <= 0} onClick={async () => { const result = await add(product); if (result) open('cart'); }}>{product.stock <= 0 ? p.sold_out : p.add}<Icon name="bag" /></button><button className="text-link" type="button" onClick={() => open('concierge')}>{p.consult}<Icon name="arrow" /></button><div className="assurance"><span><Icon name="check" />{f.shipping_complimentary}</span><span><Icon name="check" />{p.signature_wrapping}</span></div></div></Dialog>;
}
export function ProductDetail({ product }) {
    const { copy: { pdp: p }, links, t, open, addCatalog, notify } = useStorefront();
    const [size, setSize] = useState(''); const [monogram, setMonogram] = useState('');
    const purchase = async () => {
        if (size || monogram) {
            const summary = `${product.name}${size ? ` · ${t('size_label')}: ${size}` : ''}${monogram ? ` · ${t('monogram_label')}: ${monogram}` : ''}`;
            open('concierge', { summary, notes: t('inquiry_notes', { name: product.name }) + summary.slice(product.name.length) });
            notify(t('inquiry_toast')); return;
        }
        if (await addCatalog(product)) open('cart');
    };
    const share = async () => {
        try {
            if (navigator.share) await navigator.share({ title: `${product.name} · ZAFIRA`, url: product.href });
            else { await navigator.clipboard.writeText(product.href); notify(t('link_copied')); }
        } catch (error) { if (error?.name !== 'AbortError') notify(t('link_failed')); }
    };
    return <section className="product-detail section-shell"><a className="text-link back-link" href={links.home}><Icon name="arrow" /><span>{p.back}</span></a><div className="product-detail-grid"><div className="product-detail-media"><img src={product.heroImage} srcSet={product.heroSrcset || undefined} sizes="(max-width: 900px) 100vw, 55vw" decoding="async" alt={product.alt} fetchPriority="high" width="1200" height="1500" /></div><div className="product-detail-info"><span className="eyebrow">{product.category}</span><h1>{product.name}</h1>{product.badge && <span className="product-badge">{product.badge}</span>}<p className="product-detail-price" translate="no">{product.priceLabel}</p><p className="product-detail-description">{product.description}</p><div className="product-detail-color"><span>{p.color}</span><strong>{product.color}</strong></div>
        {(product.material || product.origin) && <dl className="product-provenance" aria-label={p.provenance_aria}>{['material', 'composition', 'origin'].map(key => product[key] && <div key={key}><dt>{p[key]}</dt><dd>{product[key]}</dd></div>)}</dl>}
        {(product.craft || product.care) && <details className="product-craft" open={product.craft ? true : undefined}><summary>{p.craft_summary}</summary>{product.craft && <p><strong>{p.craft}</strong> {product.craft}</p>}{product.care && <p><strong>{p.care}</strong> {product.care}</p>}</details>}
        {product.hasSizes && <div className="sf-size"><label htmlFor="pdp-size" className="eyebrow">{p.size_label}</label><select id="pdp-size" value={size} onChange={event => setSize(event.target.value)}><option value="">{p.size_pick}</option>{['FR 36', 'FR 38', 'FR 40', 'FR 42'].map(value => <option key={value}>{value}</option>)}</select><button className="text-link" type="button" onClick={() => open('size')}>{p.size_guide_open}</button></div>}
        <div className="sf-monogram-card"><div id="pdp-monogram-badge">{monogram || 'Z'}</div><div><div className="sf-monogram-label"><label htmlFor="pdp-monogram-input">{p.monogram_label}</label><span aria-live="polite">{monogram.length} / 3</span></div><input id="pdp-monogram-input" maxLength={3} value={monogram} placeholder="A-Z" aria-label={p.monogram_aria} aria-describedby="pdp-monogram-help" onChange={event => setMonogram(sanitizeMonogram(event.target.value))} /><small id="pdp-monogram-help">{p.monogram_help}</small></div></div>
        <div className="product-detail-assurance">{[p.assurance_shipping, p.signature_wrapping, p.care_for_life].map(label => <span key={label}><Icon name="check" />{label}</span>)}</div><p className="muted">{p.personalization}</p>{product.stock <= 0 && <p className="muted" id="pdp-sold-out-note">{p.sold_out_note}</p>}<div className="product-detail-actions"><button className="button button-sapphire" type="button" disabled={product.stock <= 0} aria-describedby={product.stock <= 0 ? 'pdp-sold-out-note' : undefined} onClick={() => void purchase()}>{product.stock <= 0 ? p.sold_out : p.add}<Icon name="bag" /></button><button className="text-link" type="button" onClick={() => open('concierge')}>{p.consult}<Icon name="arrow" /></button><button className="text-link" type="button" onClick={() => void share()}>{p.share}</button></div>
        </div></div></section>;
}
export function SizeGuide() {
    const { copy: { pdp: p } } = useStorefront();
    return <Dialog type="size" title={p.size_guide_title} className="drawer size-guide-drawer"><div className="drawer-header"><div><span className="eyebrow">{p.size_guide_eyebrow}</span><h2>{p.size_guide_title}</h2></div><CloseButton className="icon-button" label={p.size_guide_close} /></div><p>{p.size_guide_note}</p></Dialog>;
}
