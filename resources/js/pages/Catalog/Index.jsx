import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    buildCatalogFilterUrl,
    createCatalogActions,
    createSerialQueue,
    postCatalogJson,
    watchCatalogFilters,
} from '../../lib/catalog.js';

// URL-keyed remounts must not let a new page's SET overtake an accepted older SET.
const cartWrites = createSerialQueue();

function CatalogFilters({ t, filters, categories, colors, materials, links, locale, activeCount }) {
    const disclosure = useRef(null);

    useEffect(() => {
        if (!window.matchMedia) return;
        return watchCatalogFilters(disclosure.current, window.matchMedia('(max-width: 600px)'), activeCount);
    }, [activeCount]);

    function submit(event) {
        const form = event.currentTarget;
        const url = buildCatalogFilterUrl(form.action, new FormData(form));
        event.preventDefault();
        // Passing the completed URL and no data avoids indexed category-array serialization.
        router.get(url, {}, { preserveState: false });
    }

    return (
        <details ref={disclosure} id="catalog-filters" className="catalog-filter-disclosure" data-active-count={activeCount} open>
            <summary className="catalog-filter-summary">
                {t.filters}{activeCount > 0 && <> <span className="catalog-filter-count">({activeCount})</span></>}
            </summary>
            <form className="catalog-form" method="GET" autoComplete="off" action={links.index} role="search" aria-label={t.filters} onSubmit={submit}>
                <input type="hidden" name="locale" value={locale} />
                <div className="catalog-field">
                    <label htmlFor="filter-q">{t.search}</label>
                    <input id="filter-q" className="catalog-control" type="search" name="q" defaultValue={filters.q ?? ''} placeholder={t.search_ph} maxLength={100} />
                </div>
                {filters.category != null && <input type="hidden" name="category" value={filters.category} />}
                <fieldset className="catalog-field catalog-category-set">
                    <legend>{t.category}</legend>
                    {categories.map((category) => (
                        <label className="catalog-check" key={category.slug}>
                            <input type="checkbox" name="kategori" value={category.slug} defaultChecked={(filters.kategori ?? []).includes(category.slug)} />
                            {category.title}
                        </label>
                    ))}
                </fieldset>
                <div className="catalog-field">
                    <label htmlFor="filter-color">{t.color}</label>
                    <select id="filter-color" className="catalog-control" name="color" defaultValue={filters.color ?? ''}>
                        <option value="">{t.all}</option>
                        {colors.map((color) => <option key={color} value={color}>{color}</option>)}
                    </select>
                </div>
                <div className="catalog-field">
                    <label htmlFor="filter-material">{t.material}</label>
                    <select id="filter-material" className="catalog-control" name="material" defaultValue={filters.material ?? ''}>
                        <option value="">{t.all}</option>
                        {materials.map((material) => <option key={material} value={material}>{material}</option>)}
                    </select>
                </div>
                <div className="catalog-field">
                    <label htmlFor="filter-harga-min">{t.harga_min}</label>
                    <input id="filter-harga-min" className="catalog-control" type="number" name="harga_min" defaultValue={filters.harga_min ?? ''} min="0" step="1" />
                </div>
                <div className="catalog-field">
                    <label htmlFor="filter-harga-max">{t.harga_max}</label>
                    <input id="filter-harga-max" className="catalog-control" type="number" name="harga_max" defaultValue={filters.harga_max ?? ''} min="0" step="1" />
                </div>
                <div className="catalog-field">
                    <label htmlFor="filter-urut">{t.urut}</label>
                    <select id="filter-urut" className="catalog-control" name="urut" defaultValue={filters.urut ?? ''}>
                        <option value="">{t.all}</option>
                        <option value="harga">{t.price_sort}</option>
                        <option value="terbaru">{t.newest}</option>
                        <option value="tersedia">{t.available}</option>
                    </select>
                </div>
                <div className="catalog-field" style={{ justifyContent: 'center', alignItems: 'flex-start' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                        <input type="checkbox" name="tersedia" value="1" defaultChecked={filters.tersedia === '1'} style={{ width: 20, height: 20 }} />
                        {t.available}
                    </label>
                </div>
                <div className="catalog-form-actions">
                    <button type="submit" className="catalog-btn">{t.apply}</button>
                    <Link href={links.reset} className="catalog-btn catalog-btn-secondary" preserveState={false}>{t.reset}</Link>
                </div>
            </form>
        </details>
    );
}

function CatalogChips({ chips, t, clear }) {
    if (chips.length === 0) return null;
    return (
        <div className="catalog-chips-section">
            <span className="catalog-chips-title">{t.active_filters}:</span>
            <ul className="catalog-chips-list">
                {chips.map((chip, index) => (
                    <li key={`${chip.url}:${index}`}>
                        <Link href={chip.url} className="catalog-chip-link" aria-label={`${t.remove_filter}: ${chip.label}`} preserveState={false}>
                            <span>{chip.label}</span>
                            <span aria-hidden="true">✕</span>
                        </Link>
                    </li>
                ))}
            </ul>
            <Link href={clear} className="catalog-btn catalog-btn-secondary" style={{ minHeight: 44, padding: '0 12px', fontSize: 11 }} preserveState={false}>
                {t.clear_all}
            </Link>
        </div>
    );
}

function ProductAttributes({ product, id }) {
    return (
        <div className="catalog-card-attrs" id={id}>
            {product?.material && <span>{product.material}</span>}
            {product?.color && <span>{product.color}</span>}
        </div>
    );
}

function CatalogCard({ product, t, catalogCopy, links, signedIn, onQuickView, actions }) {
    const stock = Math.max(0, product.stock);
    return (
        <article className="catalog-card">
            <div className="catalog-card-media">
                {product.image && (
                    <img src={product.image} srcSet={product.imageSrcset || undefined} sizes={product.imageSrcset ? '(max-width: 700px) 50vw, 25vw' : undefined}
                        decoding="async" alt={product.alt} loading="lazy" width="400" height="500" />
                )}
            </div>
            <div className="catalog-card-body">
                <span className="catalog-card-category">{product.category}</span>
                <h2 className="catalog-card-title"><a href={product.href}>{product.title}</a></h2>
                <ProductAttributes product={product} />
                <div className="catalog-card-price" translate="no">{product.priceLabel}</div>
                <div className="catalog-card-attrs">{t.stock}: {stock}</div>
                <div className="catalog-card-actions">
                    <button type="button" className="catalog-btn catalog-btn-secondary" data-quick-view=""
                        data-title={product.title} data-category={product.category} data-price={product.priceLabel}
                        data-image={product.quickImage} data-alt={product.alt} data-description={product.description}
                        data-material={product.material} data-color={product.color} data-product-id={product.id}
                        data-stock={stock} data-slug={product.slug}
                        onClick={(event) => onQuickView({ product, opener: event.currentTarget })}>
                        {t.quick}
                    </button>
                    <form method="POST" action={links.cart} onSubmit={(event) => {
                        event.preventDefault();
                        if (stock > 0) actions.setQuantity(links.cart, { product_id: product.id, quantity: 1 });
                    }}>
                        <input type="hidden" name="product_id" value={product.id} />
                        <input type="hidden" name="quantity" value="1" />
                        <button type="submit" className="catalog-btn" aria-describedby={`replace-hint-${product.id}`} disabled={stock <= 0}>
                            {stock <= 0 ? t.sold_out : t.set_one}
                        </button>
                    </form>
                </div>
                <p id={`replace-hint-${product.id}`} className="catalog-card-attrs" style={{ margin: '6px 0 0' }}>{t.replace_hint}</p>
                {signedIn && (
                    <button type="button" className="catalog-btn catalog-btn-secondary" style={{ marginTop: 8 }} data-favorite={product.id}
                        onClick={() => actions.saveFavorite(links.favorites, product.id)}>
                        {catalogCopy.favorite}
                    </button>
                )}
            </div>
        </article>
    );
}

function CatalogPagination({ products, t }) {
    if (products.last_page <= 1) return null;
    return (
        <nav className="catalog-pagination" aria-label={t.title}>
            {products.current_page > 1 && products.prev_url
                ? <Link className="catalog-btn catalog-btn-secondary" href={products.prev_url} preserveState={false}>{t.prev}</Link>
                : <span className="catalog-btn catalog-btn-secondary" aria-disabled="true" style={{ opacity: 0.4 }}>{t.prev}</span>}
            <span>{t.page} {products.current_page} / {products.last_page}</span>
            {products.current_page < products.last_page && products.next_url
                ? <Link className="catalog-btn catalog-btn-secondary" href={products.next_url} preserveState={false}>{t.next}</Link>
                : <span className="catalog-btn catalog-btn-secondary" aria-disabled="true" style={{ opacity: 0.4 }}>{t.next}</span>}
        </nav>
    );
}

export function CatalogQuickView({ selection, t, cartUrl, onClose, onSetQuantity }) {
    const dialogRef = useRef(null);
    const closeRef = useRef(null);
    const product = selection?.product;
    const soldOut = !product || product.stock <= 0;

    useEffect(() => {
        if (!selection) return;
        const dialog = dialogRef.current;
        if (typeof dialog.showModal !== 'function') {
            window.location.assign(selection.product.href);
            onClose();
            return;
        }
        dialog.showModal();
        closeRef.current.focus();
        return () => {
            if (dialog.open) dialog.close();
            if (selection.opener?.isConnected) selection.opener.focus();
        };
    }, [selection, onClose]);

    return (
        <dialog ref={dialogRef} id="catalog-quick-dialog" className="catalog-dialog" aria-modal="true" aria-labelledby="dialog-title"
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClose={() => {
                // A queued close event from the previous selection must not close a reopened dialog.
                if (!dialogRef.current?.open) onClose();
            }}
            onClick={(event) => {
                if (event.target !== event.currentTarget) return;
                const rect = event.currentTarget.getBoundingClientRect();
                if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) onClose();
            }}>
            <button ref={closeRef} type="button" className="catalog-dialog-close" id="dialog-close-btn" aria-label={t.close} onClick={onClose}>✕</button>
            <div className="catalog-dialog-inner">
                <div className="catalog-dialog-media">
                    <img id="dialog-image" src={product?.quickImage || undefined} alt={product?.alt ?? ''} width="400" height="500" hidden={!product?.quickImage} />
                </div>
                <div className="catalog-dialog-content">
                    <span className="catalog-card-category" id="dialog-category">{product?.category}</span>
                    <h2 className="catalog-card-title" id="dialog-title" style={{ fontSize: 22, marginBottom: 12 }}>{product?.title}</h2>
                    <div className="catalog-card-price" id="dialog-price" translate="no" style={{ fontSize: 18 }}>{product?.priceLabel}</div>
                    <ProductAttributes product={product} id="dialog-attrs" />
                    <p id="dialog-desc" style={{ fontSize: 13, color: '#555', lineHeight: 1.5, margin: '0 0 16px' }}>{product?.description}</p>
                    <form id="dialog-cart-form" method="POST" action={cartUrl} className="catalog-cart-form" onSubmit={(event) => {
                        event.preventDefault();
                        if (soldOut) return;
                        onSetQuantity(product.id, new FormData(event.currentTarget).get('quantity'));
                        onClose();
                    }}>
                        <input type="hidden" name="product_id" id="dialog-product-id" value={product?.id ?? ''} />
                        <label htmlFor="dialog-qty" className="sr-only" style={{ position: 'absolute', left: '-9999px' }}>{t.qty}</label>
                        <input key={product?.id ?? 'empty'} type="number" id="dialog-qty" name="quantity" className="catalog-cart-qty" defaultValue="1" min="1" max="99" aria-describedby="dialog-replace-hint" disabled={soldOut} />
                        <button type="submit" id="dialog-add-button" className="catalog-btn" style={{ flex: 1 }} aria-describedby="dialog-replace-hint" disabled={soldOut}>
                            {product && soldOut ? t.sold_out : t.set_quantity}
                        </button>
                    </form>
                    <p id="dialog-replace-hint" className="catalog-card-attrs" style={{ margin: '8px 0 0' }}>{t.replace_hint}</p>
                    <div style={{ marginTop: 16 }}>
                        <a id="dialog-detail-link" href={product?.href ?? '#'} className="catalog-btn catalog-btn-secondary" style={{ width: '100%' }}>{t.detail}</a>
                    </div>
                </div>
            </div>
        </dialog>
    );
}

function CatalogPage({ t, catalogCopy, products, filters, categories, colors, materials, chips, links, signedIn, isFuzzy, searchRecovery, canonical, year, locale }) {
    const [feedback, setFeedback] = useState('');
    const [selection, setSelection] = useState(null);
    const closeQuickView = useCallback(() => setSelection(null), []);
    const actions = useMemo(() => createCatalogActions({
        post: (url, payload) => postCatalogJson(url, payload, catalogCopy.js),
        feedback: setFeedback,
        copy: catalogCopy.js,
        queue: cartWrites,
    }), [catalogCopy.js]);

    useEffect(() => {
        actions.activate();
        return () => actions.dispose();
    }, [actions]);

    useEffect(() => { document.documentElement.lang = locale; }, [locale]);

    return (
        <>
            <Head title={`${t.title} — ZAFIRA: La Maison de Lumière`}>
                <link head-key="canonical" rel="canonical" href={canonical} />
            </Head>
            <a className="catalog-skip" href="#main-catalog">{t.skip}</a>
            <header className="catalog-header">
                <a className="catalog-brand" href={links.home}>ZAFIRA</a>
                <a className="catalog-btn" href={links.shop}>{catalogCopy.bag}</a>
                <nav className="catalog-locales" aria-label={t.language}>
                    {links.locales.map(({ code, label, url }) => (
                        <Link key={code} href={url} className={locale === code ? 'active' : ''} aria-current={locale === code ? 'true' : 'false'} preserveState={false}>{label}</Link>
                    ))}
                </nav>
            </header>
            <main id="main-catalog" className="catalog-container">
                <section className="catalog-hero">
                    <span className="catalog-eyebrow">{t.eyebrow}</span>
                    <h1 className="catalog-title">{t.title}</h1>
                    <p className="catalog-lead">{t.lead}</p>
                </section>
                <CatalogFilters t={t} filters={filters} categories={categories} colors={colors} materials={materials} links={links} locale={locale} activeCount={chips.length} />
                <CatalogChips chips={chips} t={t} clear={links.clear} />
                <p id="catalog-shop-status" className="catalog-meta-bar" role="status" aria-live="polite">
                    {catalogCopy.notice} <a href={links.home}>{catalogCopy.home}</a> {catalogCopy.notice_end} <a href={links.shop}>{catalogCopy.shop}</a>. <strong id="catalog-feedback">{feedback}</strong>
                </p>
                {isFuzzy && <p className="catalog-meta-bar" role="status" aria-live="polite">{t.fuzzy_notice}</p>}
                <div className="catalog-meta-bar">
                    <span role="status" aria-live="polite"><strong>{products.total}</strong> {t.results}</span>
                    <small>{t.currency_note}</small>
                </div>
                {products.data.length === 0 ? (
                    <div className="catalog-empty">
                        <p>{t.empty}</p>
                        <p style={{ fontSize: 13, color: '#888' }}>{filters.q != null ? t.search_empty_hint : t.empty_hint}</p>
                        {searchRecovery === 'narrow' && <p style={{ fontSize: 13, color: '#888' }}>{t.search_narrow}</p>}
                        {filters.q != null && <Link href={links.withoutSearch} className="catalog-btn catalog-btn-secondary" preserveState={false}>{t.clear_search}</Link>}
                        <Link href={links.clear} className="catalog-btn" preserveState={false}>{t.clear_all}</Link>
                    </div>
                ) : (
                    <>
                        <div className="catalog-grid">
                            {products.data.map((product) => (
                                <CatalogCard key={product.id} product={product} t={t} catalogCopy={catalogCopy} links={links} signedIn={signedIn} onQuickView={setSelection} actions={actions} />
                            ))}
                        </div>
                        <CatalogPagination products={products} t={t} />
                    </>
                )}
            </main>
            <CatalogQuickView selection={selection} t={t} cartUrl={links.cart} onClose={closeQuickView}
                onSetQuantity={(productId, quantity) => actions.setQuantity(links.cart, { product_id: productId, quantity })} />
            <footer className="catalog-footer"><p>© {year} ZAFIRA Maison. {t.lead}</p></footer>
        </>
    );
}

export default function Index(props) {
    const { url } = usePage();
    // Filters are URL-owned; remount uncontrolled inputs (and discard any open preview) on navigation.
    return <CatalogPage key={`${url}:${props.locale}`} {...props} />;
}
