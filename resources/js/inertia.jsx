import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { Component, useEffect } from 'react';
import { installTraversalDispatch } from './lib/frontend-traversal';

installTraversalDispatch(window);

class PageBoundary extends Component {
    state = { failed: false };
    static getDerivedStateFromError() { return { failed: true }; }
    componentDidCatch() { this.props.root.dataset.reactBoot = 'failed'; }
    render() {
        if (!this.state.failed) return this.props.children;
        const url = new URL(window.location.href);
        url.searchParams.set('frontend', 'blade');
        return <main role="alert"><h1>ZAFIRA</h1><p>{this.props.recovery}</p><a href={url.href}>{this.props.retry}</a></main>;
    }
}

// A slow bundle must not erase input entered into the server-rendered fallback.
function hasEditedForm(root) {
    return [...root.querySelectorAll('input, textarea, select')].some((field) => {
        if (field.type === 'hidden' || field.type === 'submit') return false;
        if (field.type === 'checkbox' || field.type === 'radio') return field.checked !== field.defaultChecked;
        if (field.tagName === 'SELECT') return [...field.options].some((option) => option.selected !== option.defaultSelected) && field.value !== (field.querySelector('[selected]')?.value ?? field.options[0]?.value);
        return field.value !== field.defaultValue;
    });
}

function canTakeOver(root) {
    // Native TOTP pages recreate their declared autofocus. Pointer/keyboard/input ownership
    // still wins; an untouched browser-applied autofocus alone must not disable React forever.
    const autofocus = root.dataset.reactBoot === 'pending' && document.activeElement?.hasAttribute('autofocus');
    return root.dataset.reactBoot !== 'fallback' && !hasEditedForm(root)
        && (!root.contains(document.activeElement) || autofocus) && !root.querySelector('dialog[open]');
}

function MountedPage({ root, children }) {
    useEffect(() => { root.dataset.reactBoot = 'ready'; }, [root]);
    return children;
}

const root = document.getElementById('app');
const initial = document.querySelector('script[data-page="app"]');
async function boot() {
    if (!canTakeOver(root)) { root.dataset.reactBoot = 'fallback'; return; }
    try {
        const page = JSON.parse(initial.textContent);
        await createInertiaApp({
            page,
            resolve: async (name) => {
                const pages = import.meta.glob('./pages/**/*.jsx');
                const load = pages[`./pages/${name}.jsx`];
                if (!load) throw new Error('Unknown frontend page');
                return load();
            },
            setup({ el, App, props }) {
                if (!canTakeOver(el)) { el.dataset.reactBoot = 'fallback'; return; }
                el.dispatchEvent(new Event('zafira:react-claim'));
                initial.remove();
                const mounted = createRoot(el);
                // Commit in the same task as the ownership check; no input event may fall in between.
                flushSync(() => mounted.render(<PageBoundary root={el} recovery={page.props.recovery?.message ?? 'Halaman belum dapat ditampilkan.'} retry={page.props.recovery?.action ?? 'Buka tampilan standar'}><MountedPage root={el}><App {...props} /></MountedPage></PageBoundary>));
                return mounted;
            },
            progress: { color: '#a17d36', showSpinner: false },
        });
    } catch {
        // Inline fallback owns its controls independently of the entry and lazy chunks.
        root.dataset.reactBoot = 'fallback';
    }
}
if (root && initial) {
    window.addEventListener('zafira:private-leave', () => router.clearHistory());
    void boot();
}
