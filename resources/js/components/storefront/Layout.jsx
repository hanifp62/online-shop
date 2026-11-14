import { useEffect } from 'react';
import { Header, Footer, Profile, MobileMenu } from './Chrome.jsx';
import { CloseButton, Dialog, Toast } from './Primitives.jsx';
import { Concierge, Search } from './Forms.jsx';
import { Assistant, AssistantLauncher } from './Assistant.jsx';
import { QuickView, SizeGuide } from './Products.jsx';
import CartDrawer from './CartDrawer.jsx';
import { StorefrontProvider, useStorefront } from './StorefrontProvider.jsx';
import '../../../css/storefront-react.css';

function Shortcuts() {
    const { copy: { welcome: w, header: h, assistant: a } } = useStorefront();
    return <Dialog type="shortcuts" title={w.shortcuts_title} className="sf-shortcuts"><div className="modal-window"><div className="drawer-header"><h3>{w.shortcuts_title}</h3><CloseButton className="icon-button" label={w.shortcuts_dismiss} /></div><p>{w.shortcuts_desc}</p>{[[w.shortcut_close, 'ESC'], [h.cart_open, 'C'], [w.shortcut_search, '/'], [w.shortcut_monogram, 'M'], [a.shortcut, 'H']].map(([label, key]) => <div className="sf-shortcut" key={key}><span>{label}</span><kbd>{key}</kbd></div>)}</div></Dialog>;
}
function Frame({ home, children }) {
    const { copy, dialog, open, close } = useStorefront();
    useEffect(() => {
        const keydown = event => {
            if (event.ctrlKey || event.metaKey || event.altKey || dialog || event.target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName)) return;
            const key = event.key.toLowerCase();
            // H (help) works on every storefront page; the other accelerators stay home-only.
            if (!home && key !== 'h') return;
            if (['?', 'c', 'u', '/', 'm', 'h'].includes(key)) event.preventDefault();
            if (key === '?') open('shortcuts');
            else if (key === 'c') open('cart');
            else if (key === 'u') open('profile');
            else if (key === 'h') open('assistant');
            else if (key === '/') { document.getElementById('home-catalog-query')?.focus(); document.getElementById('home-catalog-query')?.scrollIntoView({ block: 'center', behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
            else if (key === 'm') document.getElementById('atelier')?.scrollIntoView({ behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        };
        document.addEventListener('keydown', keydown);
        return () => document.removeEventListener('keydown', keydown);
    }, [home, dialog]);
    return <div className="sf-native"><div inert={dialog ? true : undefined} aria-hidden={dialog ? 'true' : undefined}><a className="skip-link" href="#main-content">{copy.pdp.skip}</a><Header home={home} /><main id="main-content">{children}</main><Footer home={home} /><AssistantLauncher /></div>
        {dialog && <div className={`overlay open${dialog.type === 'assistant' ? ' is-assistant-backdrop' : ''}`} onClick={close} aria-hidden="true" />}
        <MobileMenu home={home} /><CartDrawer /><Search /><Concierge /><Assistant /><QuickView /><SizeGuide />{home && <><Profile /><Shortcuts /></>}{dialog?.type !== 'cart' && <Toast />}
    </div>;
}
export default function Layout({ home = false, children, ...props }) {
    return <StorefrontProvider {...props}><Frame home={home}>{children}</Frame></StorefrontProvider>;
}
