import { createContext, useContext, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createStorefrontCart, formatCopy, readLegacyCart, rupiah, storefrontRequest } from '../../lib/storefront-cart.js';
import { connectThemePreference, createLocaleWriter, locales, postLocale, readThemePreference, themes } from '../../lib/storefront-preferences.js';

const Context = createContext(null);
export const useStorefront = () => useContext(Context);
export function StorefrontProvider({ children, ...props }) {
    const [dialog, setDialog] = useState(null);
    const [toast, setToast] = useState(null);
    const [cart, setCart] = useState({ items: [], subtotal: 0 });
    const [cartStatus, setCartStatus] = useState('loading');
    const [cartMessage, setCartMessage] = useState(props.copy.cart.loading);
    const [wishlist, setWishlist] = useState([]);
    const [wishlistOnly, setWishlistOnly] = useState(false);
    const [legacy, setLegacy] = useState([]);
    const [theme, updateTheme] = useState(() => readThemePreference());
    const themeClient = useRef(null);
    const setTheme = value => themeClient.current?.set(value);
    const [localeStatus, setLocaleStatus] = useState('');
    const opener = useRef(null);
    const epoch = useRef(0);
    const mounted = useRef(true);
    const favoritesReady = useRef(false);
    const favoriteQueue = useRef(Promise.resolve());
    const copy = props.copy;
    const t = (key, params) => formatCopy(copy.app_js[key] || '', params);
    const notify = (message, action = null) => { if (mounted.current) setToast({ message, action }); };
    const errorMessage = error => t(`err_${error.kind === 'validation' ? 'stock' : error.kind || 'network'}`) || t('generic_error');
    const cartClient = useRef(null);
    if (!cartClient.current) cartClient.current = createStorefrontCart({ url: props.links.cart, publish: data => { if (mounted.current) setCart(data); } });
    const cartAction = async task => {
        setCartStatus('loading'); setCartMessage(t('processing'));
        try {
            const result = await task();
            if (mounted.current) { setCartStatus('ready'); setCartMessage(result ? t(result.items.length ? 'bag_summary' : 'bag_empty', { count: result.items.reduce((n, item) => n + item.quantity, 0), subtotal: rupiah(result.subtotal) }) : t('bag_updated')); }
            return result;
        } catch (error) {
            if (mounted.current) { setCartStatus('error'); setCartMessage(errorMessage(error)); notify(errorMessage(error)); }
            return null;
        }
    };
    const refreshCart = () => cartAction(() => cartClient.current.refresh());
    const close = () => {
        epoch.current++; setToast(null); setDialog(null);
        queueMicrotask(() => opener.current?.isConnected && opener.current.focus());
    };
    const open = (type, data = null, trigger = globalThis.document?.activeElement) => {
        if (!dialog) opener.current = trigger;
        if (dialog?.type === 'cart' || type === 'cart') { epoch.current++; setToast(previous => previous?.action ? null : previous); }
        setDialog({ type, data });
        if (type === 'cart') void refreshCart();
    };
    const add = async product => {
        if (product.stock <= 0) return;
        if (product.category === 'Busana') { window.location.assign(product.href); return; }
        return addCatalog(product);
    };
    const addCatalog = async product => {
        const result = await cartAction(() => cartClient.current.adjust(product.productId, 1));
        if (result) notify(t('added_toast', { name: product.name }));
        return result;
    };
    const remove = async productId => {
        const started = epoch.current;
        const result = await cartAction(() => cartClient.current.remove(productId));
        if (!result?.removed_quantity || started !== epoch.current) return;
        notify(t('removed_toast'), { label: t('undo'), run: async () => {
            setToast(null);
            const restored = await cartAction(() => cartClient.current.adjust(productId, result.removed_quantity));
            if (!restored) setCartMessage(t('undo_failed', { name: '', status: t('err_bag') }));
        } });
    };
    const refreshFavorites = async () => {
        const data = await storefrontRequest(props.links.favorites);
        if (!Array.isArray(data?.favorites)) throw new Error('invalid');
        const ids = data.favorites.map(favorite => String(favorite.product_id));
        favoritesReady.current = true;
        favoriteState.current = ids;
        if (mounted.current) setWishlist(ids);
        return ids;
    };
    const wishlistKey = product => props.links.favorites ? String(product.productId) : product.id;
    const favoriteState = useRef([]);
    useEffect(() => { favoriteState.current = wishlist; }, [wishlist]);
    const toggleFavorite = product => {
        if (!props.links.favorites) {
            const previous = favoriteState.current;
            const key = wishlistKey(product); const removing = previous.includes(key);
            const next = removing ? previous.filter(id => id !== key) : [...previous, key];
            favoriteState.current = next;
            setWishlist(next);
            try { localStorage.setItem('zafira-wishlist:v1', JSON.stringify(next)); } catch { /* Device-only preference. */ }
            notify(t(removing ? 'wish_removed' : 'wish_saved_local', { name: product.name }));
            return;
        }
        favoriteQueue.current = favoriteQueue.current.catch(() => {}).then(async () => {
            try {
                const current = favoritesReady.current ? favoriteState.current : await refreshFavorites();
                const key = wishlistKey(product); const removing = current.includes(key);
                await storefrontRequest(removing ? `${props.links.favorites}/${product.productId}` : props.links.favorites, removing ? 'DELETE' : 'POST', removing ? undefined : { product_id: product.productId });
                const next = removing ? current.filter(id => id !== key) : [...current, key];
                favoriteState.current = next;
                if (mounted.current) { setWishlist(next); notify(t(removing ? 'wish_removed' : 'wish_saved', { name: product.name })); }
            } catch (error) { favoritesReady.current = false; notify(t(['session'].includes(error.kind) ? 'wish_session' : 'wish_failed')); }
        });
    };
    const showFavorites = () => {
        if (props.links.favorites) { window.location.assign(`${props.links.shop}#favorit`); return; }
        setWishlistOnly(true); close();
        const url = new URL(window.location.href); url.searchParams.set('tampilkan', 'favorit'); window.history.replaceState(null, '', url);
        document.getElementById('new-arrivals')?.scrollIntoView({ behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        notify(t(wishlist.length ? 'fav_showing' : 'fav_none'));
    };
    const localeWriter = useRef(null);
    if (!localeWriter.current) localeWriter.current = createLocaleWriter({
        initial: props.locale,
        post: locale => postLocale(props.links.locale, locale),
        accepted: locale => {
            try { localStorage.setItem('zafira_lang', locale); } catch { /* Server remains authoritative. */ }
            const url = new URL(window.location.href); url.searchParams.delete('locale'); window.history.replaceState(null, '', url);
            setLocaleStatus('');
        },
        failed: () => setLocaleStatus(t('generic_error')),
        reload: () => window.location.reload(),
    });
    const chooseLocale = locale => { setLocaleStatus(t('processing')); return localeWriter.current(locale); };
    useEffect(() => {
        mounted.current = true;
        try {
            setLegacy(readLegacyCart(localStorage));

            if (!props.links.favorites) {
                const saved = JSON.parse(localStorage.getItem('zafira-wishlist:v1') ?? localStorage.getItem('zafira-wishlist') ?? '[]');
                if (Array.isArray(saved)) setWishlist(saved.filter(id => typeof id === 'string'));
            }
        } catch { /* Blocked storage must not block purchasing. */ }
        setWishlistOnly(new URLSearchParams(window.location.search).get('tampilkan') === 'favorit');
        void refreshCart();
        const favorites = () => {
            if (props.links.favorites) favoriteQueue.current = favoriteQueue.current.catch(() => {}).then(refreshFavorites).catch(() => { favoritesReady.current = false; notify(t('wish_failed')); });
        };
        favorites();
        if (props.flash) notify(props.flash);
        const visible = () => { if (document.visibilityState === 'visible') { void refreshCart(); favorites(); } };
        const storage = event => { if (!props.links.favorites && event.key === 'zafira-wishlist:v1') { try { const next = JSON.parse(event.newValue || '[]'); if (Array.isArray(next)) setWishlist(next.filter(id => typeof id === 'string')); } catch { /* Ignore corrupt preference. */ } } };
        document.addEventListener('visibilitychange', visible); window.addEventListener('storage', storage);
        return () => { mounted.current = false; document.removeEventListener('visibilitychange', visible); window.removeEventListener('storage', storage); };
    }, []);
    useLayoutEffect(() => {
        const client = connectThemePreference(updateTheme);
        themeClient.current = client;
        return () => { client.dispose(); themeClient.current = null; };
    }, []);
    return <Context.Provider value={{ ...props, t, dialog, open, close, toast, setToast, notify, cart, cartStatus, cartMessage, refreshCart, add, addCatalog, remove, adjust: (id, delta) => cartAction(() => cartClient.current.adjust(id, delta)), legacy, wishlist, wishlistKey, wishlistOnly, toggleFavorite, showFavorites, theme, setTheme, chooseLocale, localeStatus, locales, themes }}>
        {children}
    </Context.Provider>;
}
