import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useStorefront } from './StorefrontProvider.jsx';

const paths = {
    light: <><circle cx="12" cy="12" r="5" /><path d="M12 1v2m0 18v2M1 12h2m18 0h2M4 4l2 2m12 12 2 2M4 20l2-2M18 6l2-2" /></>,
    dark: <path d="M21 12.8A9 9 0 0 1 11.2 3a9 9 0 1 0 9.8 9.8Z" />,
    system: <><rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8m-4-4v4" /></>,
    menu: <path d="M4 7h16M4 17h16" />, search: <><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></>,
    user: <><circle cx="12" cy="8" r="3.5" /><path d="M5.5 20c.8-4 3-6 6.5-6s5.7 2 6.5 6" /></>,
    heart: <path d="M20.8 5.9c-1.7-2.1-5-2.1-6.9-.2L12 7.6l-1.9-1.9c-1.9-1.9-5.2-1.9-6.9.2-1.5 1.9-1.2 4.7.5 6.4L12 20l8.3-7.7c1.7-1.7 2-4.5.5-6.4Z" />,
    bag: <><path d="M5 8.5h14l-1 12H6l-1-12Z" /><path d="M9 9V6.5a3 3 0 0 1 6 0V9" /></>,
    close: <path d="m5 5 14 14M19 5 5 19" />, arrow: <path d="M4 12h15M14 6l6 6-6 6" />,
    plus: <path d="M12 5v14M5 12h14" />, minus: <path d="M5 12h14" />, check: <path d="m5 12 4 4L19 6" />,
    instagram: <><rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><path d="M17.5 6.5h.01" /></>,
};
export function Icon({ name, ...props }) { return <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.5" {...props}>{paths[name]}</svg>; }
export function Brand() {
    return <><span className="brand-emblem" aria-hidden="true"><svg viewBox="0 0 48 56" focusable="false"><polygon className="emblem-outer" points="24,1.5 41.5,9 47,27.5 38.5,47 24,54.5 9.5,47 1,27.5 6.5,9" /><polygon className="emblem-inner" points="24,9.5 35.5,14.5 39.5,27.5 33.5,41.5 24,46.5 14.5,41.5 8.5,27.5 12.5,14.5" /><path className="emblem-facets" d="M24 1.5v8M41.5 9l-6 5.5M47 27.5h-7.5M38.5 47l-5-5.5M24 54.5v-8M9.5 47l5-5.5M1 27.5h7.5M6.5 9l6 5.5" /><path className="emblem-z" d="M16 19h16L16 36.5h16" /></svg></span><span className="brand-copy"><span className="brand-name">ZAFIRA</span><span className="brand-est">Maison de Lumière</span></span></>;
}
export function Dialog({ type, title, className = 'modal', children }) {
    const { dialog, close } = useStorefront();
    const ref = useRef(null);
    const active = dialog?.type === type;
    useEffect(() => {
        if (!active) return;
        const element = ref.current;
        const focusable = () => [...element.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]')].filter(node => !node.disabled && !node.hidden);
        const initial = element.querySelector('[data-initial-focus]') || focusable()[0] || element;
        initial.focus();
        const previous = document.body.style.overflow; document.body.style.overflow = 'hidden';
        const keydown = event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            if (event.key === 'Tab') {
                const nodes = focusable(); const first = nodes[0]; const last = nodes.at(-1);
                if (!first) { event.preventDefault(); element.focus(); }
                else if (event.shiftKey && (document.activeElement === first || document.activeElement === element)) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && (document.activeElement === last || !element.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
            }
        };
        const contain = event => { if (!element.contains(event.target)) initial.focus(); };
        document.addEventListener('keydown', keydown); document.addEventListener('focusin', contain);
        return () => { document.body.style.overflow = previous; document.removeEventListener('keydown', keydown); document.removeEventListener('focusin', contain); };
    }, [active]);
    if (!active) return null;
    return <section ref={ref} className={`${className} open`} role="dialog" aria-modal="true" aria-labelledby={`sf-${type}-title`} tabIndex={-1}>
        <h2 id={`sf-${type}-title`} className="sr-only">{title}</h2>{children}
    </section>;
}
export function CloseButton({ label, className = 'icon-button modal-close' }) {
    const { close } = useStorefront();
    return <button className={className} type="button" onClick={close} aria-label={label}><Icon name="close" /></button>;
}
export function Toast() {
    const { toast, setToast } = useStorefront(); const action = useRef(null); const actionFocused = useRef(false);
    useLayoutEffect(() => {
        if (actionFocused.current && !toast?.action) {
            document.querySelector('[data-sf-cart-close]')?.focus();
            actionFocused.current = false;
        }
    }, [toast]);
    useEffect(() => {
        if (!toast) return;
        const timer = setTimeout(() => { if (document.activeElement !== action.current) setToast(null); }, toast.action ? 6000 : 3500);
        return () => clearTimeout(timer);
    }, [toast]);
    return <div className={`toast${toast ? ' show' : ''}`} role="status" aria-live="polite"><span><Icon name="check" /></span><p>{toast?.message}</p>{toast?.action && <button ref={action} type="button" className="text-link" onFocus={() => { actionFocused.current = true; }} onBlur={() => { actionFocused.current = false; }} onClick={() => { const run = toast.action.run; document.querySelector('[data-sf-cart-close]')?.focus(); void run(); }}>{toast.action.label}</button>}</div>;
}
export function Reveal({ as: Tag = 'div', className = '', children, ...props }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(() => !globalThis.IntersectionObserver || globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches);
    useEffect(() => {
        if (visible) return;
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) { setVisible(true); observer.disconnect(); }
        }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
        observer.observe(ref.current);
        return () => observer.disconnect();
    }, [visible]);
    return <Tag ref={ref} className={`${className} reveal${visible ? ' is-visible' : ''}`} {...props}>{children}</Tag>;
}
export function Image({ asset, alt, sizes = '(max-width: 900px) 100vw, 50vw', ...props }) {
    return <img src={asset.src} srcSet={`${asset.small} 600w, ${asset.webp} 896w`} sizes={sizes} decoding="async" alt={alt} loading="lazy" width="1200" height="1500" {...props} />;
}
