import { useEffect, useRef, useState } from 'react';
import { formatCopy } from '../../lib/storefront-cart.js';
import { Brand, CloseButton, Dialog, Icon } from './Primitives.jsx';
import { useStorefront } from './StorefrontProvider.jsx';

export function Preferences({ compact = false, profile = false }) {
    const { copy: { welcome: w, header: h }, theme, setTheme, chooseLocale, locale, locales, themes } = useStorefront();
    if (profile) return <>
        <div className="profile-setting-row"><div className="setting-info"><span className="setting-name">{w.profile_theme_label}</span><span className="setting-desc">{w.profile_theme_desc}</span></div><div className="profile-theme-segmented" role="group" aria-label={w.theme_selector}>{themes.map(mode => <button type="button" className={`theme-seg-btn${theme === mode ? ' active' : ''}`} key={mode} title={w[`theme_${mode}`]} aria-label={w[`theme_${mode}`]} aria-pressed={theme === mode} onClick={() => setTheme(mode)}><Icon name={mode} /><span>{({ light: 'Clair', dark: 'Sombre', system: 'Auto' })[mode]}</span></button>)}</div></div>
        <div className="profile-setting-row"><div className="setting-info"><span className="setting-name">{w.profile_lang_label}</span><span className="setting-desc">{w.profile_lang_desc}</span></div><select className="zafira-lang-select profile-lang-select" aria-label={h.language_selector} value={locale} onChange={event => void chooseLocale(event.target.value)}>{locales.map(code => <option key={code} value={code}>{({ id: 'Bahasa Indonesia', en: 'English', fr: 'Français', it: 'Italiano', zh: '中文' })[code]}</option>)}</select></div>
    </>;
    return <div className={compact ? 'storefront-preferences compact' : 'storefront-preferences'}>
        <label><span>{w.footer_lang_label || h.language_selector}</span><select className="zafira-lang-select" value={locale} aria-label={h.language_selector} onChange={event => void chooseLocale(event.target.value)}>{locales.map(code => <option key={code} value={code}>{({ id: 'Bahasa Indonesia', en: 'English', fr: 'Français', it: 'Italiano', zh: '中文' })[code]}</option>)}</select></label>
        <label><span>{w.footer_theme_label || w.theme_selector}</span><select className="zafira-theme-select" aria-label={w.theme_selector} value={theme} onChange={event => setTheme(event.target.value)}>{themes.map(mode => <option key={mode} value={mode}>{w[`theme_${mode}`]}</option>)}</select></label>
    </div>;
}
export function Header({ home = false }) {
    const { copy: { welcome: w, header: h }, links, user, cart, wishlist, dialog, open, showFavorites, locale, chooseLocale, locales, theme, setTheme, themes, localeStatus, close } = useStorefront();
    const [account, setAccount] = useState(false); const [fixed, setFixed] = useState(!home); const [hidden, setHidden] = useState(false); const accountRef = useRef(null);
    useEffect(() => {
        let last = window.scrollY;
        const scroll = () => {
            const position = window.scrollY;
            setFixed(!home || position > 110);
            setHidden(position > 450 && position > last + 8);
            last = position;
        };
        scroll(); window.addEventListener('scroll', scroll, { passive: true });
        return () => window.removeEventListener('scroll', scroll);
    }, [home]);
    useEffect(() => {
        if (!account) return;
        const dismiss = event => { if (!accountRef.current?.contains(event.target)) setAccount(false); };
        const escape = event => { if (event.key === 'Escape') { setAccount(false); accountRef.current?.querySelector('button')?.focus(); } };
        document.addEventListener('pointerdown', dismiss); document.addEventListener('keydown', escape);
        return () => { document.removeEventListener('pointerdown', dismiss); document.removeEventListener('keydown', escape); };
    }, [account]);
    const nav = home ? [['collection', w.nav_collection], ['new-arrivals', w.nav_arrivals], ['heritage', w.nav_heritage], ['atelier', w.nav_atelier], ['packaging-section', w.nav_packaging], ['services', w.nav_services]] : [['collection', h.nav_collection], ['new-arrivals', h.nav_new], ['heritage', h.nav_heritage], ['maison', 'La Maison']];
    const href = hash => `${home ? '' : links.home}#${hash}`;
    const logout = <form method="POST" action={links.logout}><input type="hidden" name="_token" value={globalThis.document?.querySelector('meta[name="csrf-token"]')?.content || ''} /><button type="submit">{h.auth_logout}</button></form>;
    return <>
        <div className="announcement-bar">
            {home ? <><p id="locale-status" role="status" aria-live="polite">{localeStatus}</p><p>{w.announcement}</p><div className="desktop-only-flex"><div className="lang-switcher" role="group" aria-label={h.language_selector}>{locales.map(code => <button type="button" key={code} className={`lang-btn${locale === code ? ' active' : ''}`} aria-pressed={locale === code} onClick={() => void chooseLocale(code)}>{code.toUpperCase()}</button>)}</div><div className="theme-switcher" role="group" aria-label={w.theme_selector}>{themes.map(mode => <button key={mode} className={`theme-btn${theme === mode ? ' active' : ''}`} type="button" aria-pressed={theme === mode} onClick={() => setTheme(mode)} title={w[`theme_${mode}`]} aria-label={w[`theme_${mode}`]}><Icon name={mode} /></button>)}</div><button type="button" onClick={() => open('concierge')}>{w.rendezvous_btn}</button></div></> : <><nav className="lang-switcher" aria-label={h.language_selector}>{links.locales.map(item => <a key={item.code} href={item.url} hrefLang={item.code} aria-current={locale === item.code ? 'true' : undefined}>{item.code.toUpperCase()}</a>)}</nav><p>{h.announcement}</p><button type="button" onClick={() => open('concierge')}>Rendez-vous privé</button></>}
        </div>
        <header className={`site-header${fixed ? ' is-fixed' : ''}${hidden ? ' is-hidden' : ''}`} id="site-header">
            <button className="icon-button mobile-menu-button" type="button" aria-label={h.menu_open} aria-controls="mobile-menu" aria-expanded={dialog?.type === 'menu'} onClick={() => open('menu')}><Icon name="menu" /></button>
            <nav className="desktop-nav" aria-label={h.nav_label}>{nav.map(([hash, label]) => <a key={hash} href={href(hash)}>{label}</a>)}</nav>
            <a className="brand" href={links.home} aria-label={h.home_aria}><Brand /></a>
            <div className="header-actions"><button className="icon-button" type="button" onClick={() => open('search')} aria-label={h.search}><Icon name="search" /></button>
                {home ? <button className="icon-button" type="button" onClick={() => open('profile')} aria-label={w.profile_aria}><Icon name="user" /></button> : user ? <div className="account-wrap" ref={accountRef}><button className="icon-button account-button" type="button" aria-expanded={account} aria-label={formatCopy(h.auth_account_aria, { name: user.name })} onClick={() => setAccount(!account)}><Icon name="user" /></button><div className={`account-menu${account ? ' open' : ''}`} hidden={!account}><span>{formatCopy(h.auth_greeting, { name: user.name })}</span><a href={links.account}>{h.auth_profile}</a>{logout}</div></div> : <a className="icon-button" href={links.login} aria-label={h.login_aria}><Icon name="user" /></a>}
                <button className="icon-button wishlist-trigger" type="button" onClick={showFavorites} aria-label={h.wishlist_show}><Icon name="heart" /><span className={`action-count wishlist-count${wishlist.length ? ' has-items' : ''}`}>{wishlist.length}</span></button>
                <button className="icon-button" type="button" onClick={() => open('cart')} aria-label={h.cart_open}><Icon name="bag" /><span className={`action-count cart-count${cart.items.length ? ' has-items' : ''}`}>{cart.items.reduce((n, item) => n + item.quantity, 0)}</span></button>
            </div>
        </header>

    </>;
}
export function MobileMenu({ home = false }) {
    const { copy: { welcome: w, header: h }, links, close, open } = useStorefront();
    const nav = home ? [['collection', w.nav_collection], ['new-arrivals', w.nav_arrivals], ['heritage', w.nav_heritage], ['atelier', w.nav_atelier], ['packaging-section', w.nav_packaging], ['services', w.nav_services]] : [['collection', h.nav_collection], ['new-arrivals', h.nav_new], ['heritage', h.nav_heritage], ['maison', 'La Maison']];
    const href = hash => `${home ? '' : links.home}#${hash}`;
    return <Dialog type="menu" title={h.nav_label} className="mobile-menu"><div id="mobile-menu"><div className="mobile-menu-top"><a className="brand brand-horizontal" href={links.home}><Brand /></a><CloseButton className="icon-button" label={h.menu_close} /></div><nav>{nav.map(([hash, label], index) => <a key={hash} href={href(hash)} onClick={close}><span className="mobile-menu-ordinal" aria-hidden="true">{String(index + 1).padStart(2, '0')}</span>{label}</a>)}</nav><MenuUtilities home={home} />{home && <Preferences compact />}<button type="button" className="text-link light mobile-concierge" onClick={() => open('concierge')}>{h.arrange}<Icon name="arrow" /></button></div></Dialog>;
}
function MenuUtilities({ home }) {
    const { links, copy: { header: h }, user, showFavorites, wishlist, close } = useStorefront();
    return <div className="mobile-utilities"><a href={user ? links.account : links.login}>{user ? h.auth_profile : h.login_aria}</a><button type="button" onClick={showFavorites}>{h.favorites} <span className="wishlist-count">{wishlist.length}</span></button>{!home && user && <form method="POST" action={links.logout}><input type="hidden" name="_token" value={globalThis.document?.querySelector('meta[name="csrf-token"]')?.content || ''} /><button>{h.auth_logout_full}</button></form>}</div>;
}
export function Footer({ home = false }) {
    const { copy: { footer: f, welcome: w }, links, open, year } = useStorefront();
    const href = hash => `${home ? '' : links.home}#${hash}`;
    return <footer className="site-footer"><div className="footer-brand"><a className="brand brand-horizontal" href={links.home}><Brand /></a><p>{f.motto}</p></div>
        <div className="footer-links"><h3>{home ? w.footer_col_collection : f.collection}</h3>{['women', 'men', 'handbags', 'accessories'].map(key => <a key={key} href={href('new-arrivals')}>{f[key]}</a>)}</div>
        <div className="footer-links"><h3>La Maison</h3><a href={href('maison')}>{f.story}</a><a href={href('atelier')}>Atelier Z</a><a href={href('services')}>{f.services}</a>{home && <a href={href('returns-policy')}>{f.shipping_returns}</a>}<button type="button" onClick={() => open('concierge')}>Rendez-vous</button></div>
        {!home && <div className="footer-links"><h3>{f.help}</h3><a href="mailto:bonjour@zafira.maison">{f.contact}</a><a href={href('returns-policy')}>{f.shipping_returns}</a><a href={href('services')}>{f.care}</a><a href={href('services')}>{f.size_guide}</a></div>}
        {home && <div className="footer-preferences-bar"><Preferences /></div>}
        <div className="footer-bottom"><span>© {year} <span translate="no">ZAFIRA</span> Maison. {f.rights}</span><div>{home ? <button type="button" className="text-link" onClick={() => open('shortcuts')} aria-label={w.shortcuts_open} title={w.shortcuts_hint}>{w.shortcuts_trigger}</button> : <a href={href('maison')}>{f.privacy}</a>}<a href={href('services')}>{f.terms}</a><a href="#main-content">{home ? w.region_badge : f.language}</a><a href={href('main-content')} aria-label="Instagram ZAFIRA"><Icon name="instagram" /></a></div></div>
    </footer>;
}
export function Profile() {
    const { copy: { welcome: w }, user, links, open } = useStorefront();
    return <Dialog type="profile" title={w.profile_drawer_title} className="drawer profile-drawer"><div className="drawer-header"><div><span className="profile-eyebrow">{w.profile_drawer_eyebrow}</span><h3>{w.profile_drawer_title}</h3></div><CloseButton className="icon-button" label={w.profile_close} /></div><div className="drawer-body profile-drawer-body"><div className="profile-vic-card"><div className="profile-vic-avatar"><span>{user?.name?.split(/\s+/).map(part => part[0]).slice(0, 2).join('') || 'Z'}</span></div><div className="profile-vic-info"><h4 className="profile-vic-name">{user?.name || w.guest}</h4><p className="profile-vic-email">{user?.email || w.guest_hint}</p></div></div><div className="profile-quick-actions"><button type="button" className="profile-action-pill" onClick={() => open('concierge')}>{w.profile_action_fitting}</button><a className="profile-action-pill" href={links.account}>{w.profile_action_orders}</a></div><div className="profile-section-card profile-settings-group"><div className="profile-section-header"><span className="profile-section-title">{w.profile_settings_title}</span></div><Preferences profile /></div><div className="profile-footer-links"><a className="button button-gold" href={links.account}>{w.profile_full_portal}</a></div></div></Dialog>;
}
