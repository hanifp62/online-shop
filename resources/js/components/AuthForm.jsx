import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function BrandMark() {
    return <>
        <span className="brand-emblem" aria-hidden="true">
            <svg viewBox="0 0 48 56" aria-hidden="true" focusable="false">
                <polygon className="emblem-outer" points="24,1.5 41.5,9 47,27.5 38.5,47 24,54.5 9.5,47 1,27.5 6.5,9" />
                <polygon className="emblem-inner" points="24,9.5 35.5,14.5 39.5,27.5 33.5,41.5 24,46.5 14.5,41.5 8.5,27.5 12.5,14.5" />
                <path className="emblem-facets" d="M24 1.5v8M41.5 9l-6 5.5M47 27.5h-7.5M38.5 47l-5-5.5M24 54.5v-8M9.5 47l5-5.5M1 27.5h7.5M6.5 9l6 5.5" />
                <path className="emblem-z" d="M16 19h16L16 36.5h16" />
            </svg>
        </span>
        <span className="brand-copy"><span className="brand-name">ZAFIRA</span><span className="brand-est">Maison de Lumière</span></span>
    </>;
}

export function AuthLayout({ locale, copy, assets, homeUrl, backUrl, backLabel, simple = false, children }) {
    useEffect(() => {
        document.documentElement.lang = locale.replaceAll('_', '-');
    }, [locale]);

    return <>
        <Head title={copy.page.title} />
        <main className="auth-page">
            <section className="auth-visual" aria-label={copy.common.visual_label}>
                <img src={assets.hero} alt={copy.common.image_alt}
                    srcSet={simple ? undefined : assets.heroSrcSet}
                    sizes={simple ? undefined : '(max-width: 900px) 100vw, 50vw'}
                    decoding={simple ? undefined : 'async'}
                    width={simple ? undefined : 1400} height={simple ? undefined : 1750}
                    fetchPriority={simple ? undefined : 'high'} />
                <a className="brand" href={homeUrl} aria-label={copy.common.home_label}><BrandMark /></a>
                <div className="auth-quote">
                    {copy.page.quote_eyebrow && <span className="eyebrow light">{copy.page.quote_eyebrow}</span>}
                    <h2>{copy.page.quote_heading}</h2><p>{copy.page.quote_body}</p>
                </div>
            </section>
            <section className="auth-content">
                <a className="auth-back text-link" href={backUrl} style={simple ? undefined : { display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                    {!simple && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>}
                    <span>{backLabel}</span>
                </a>
                <div className="auth-card">
                    {copy.page.eyebrow && <span className="eyebrow">{copy.page.eyebrow}</span>}
                    <h1>{copy.page.heading}</h1><p>{copy.page.intro}</p>
                    {children}
                </div>
            </section>
        </main>
    </>;
}

export function AuthFeedback({ errors = {}, status }) {
    const summary = useRef(null);
    const messages = Object.values(errors).flat().filter((value) => typeof value === 'string' && value !== '');
    useEffect(() => {
        summary.current?.focus();
    }, [errors]);

    return <>
        {status && <div className="auth-errors" role="status" style={{ borderColor: 'var(--success)', background: 'var(--success-surface)', color: 'var(--success)', marginBottom: 16 }}><ul><li>{status}</li></ul></div>}
        {messages.length > 0 && <div id="auth-error-summary" className="auth-errors" role="alert" tabIndex={-1} ref={summary}>
            <ul>{messages.map((message, index) => <li key={index}>{message}</li>)}</ul>
        </div>}
    </>;
}

function firstError(error) {
    return Array.isArray(error) ? error[0] : error;
}

export function AuthField({ name, label, optional, error, ...inputProps }) {
    const message = firstError(error);
    const errorId = `${name.replaceAll('_', '-')}-error`;
    return <div className="field">
        <label htmlFor={name}>{label}{optional && <> <span>{optional}</span></>}</label>
        <input id={name} name={name} type="text" {...inputProps}
            aria-invalid={message ? 'true' : undefined} aria-describedby={message ? errorId : undefined} />
        {message && <small id={errorId}>{message}</small>}
    </div>;
}

export function PasswordField({ name = 'password', label, copy, error, autoComplete, minLength, hint }) {
    const [visible, setVisible] = useState(false);
    const message = firstError(error);
    const errorId = `${name.replaceAll('_', '-')}-error`;
    const hintId = `${name}-hint`;
    const confirmation = name === 'password_confirmation';
    const showLabel = confirmation ? copy.show_password_confirmation : copy.show_password;
    const hideLabel = confirmation ? copy.hide_password_confirmation : copy.hide_password;
    const describedBy = [message && errorId, hint && hintId].filter(Boolean).join(' ') || undefined;

    return <div className="field">
        <label htmlFor={name}>{label}</label>
        <div className="password-wrap">
            <input id={name} name={name} type={visible ? 'text' : 'password'} required
                autoComplete={autoComplete} minLength={minLength}
                aria-invalid={message ? 'true' : undefined} aria-describedby={describedBy} />
            <button className="password-toggle" type="button" aria-label={visible ? hideLabel : showLabel}
                aria-pressed={visible} onClick={() => setVisible((value) => !value)}>{visible ? copy.hide : copy.show}</button>
        </div>
        {message && <small id={errorId}>{message}</small>}
        {hint && <p id={hintId} style={{ fontSize: 11, color: 'var(--muted)', marginTop: 4, lineHeight: 1.4 }}>{hint}</p>}
    </div>;
}

export function SocialAuthLinks({ providers = [], label }) {
    if (providers.length === 0) return null;
    return <div className="social-auth" role="group" aria-label={label}>
        {providers.map((provider) => <a key={provider.id} className="button button-dark" href={provider.url} data-social-provider={provider.id}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                {provider.id === 'google'
                    ? <path d="M21.35 11.1H12v2.98h5.35c-.23 1.45-1.67 4.25-5.35 4.25a5.9 5.9 0 0 1 0-11.8c1.87 0 3.12.8 3.83 1.48l2.6-2.5A9.4 9.4 0 0 0 12 2.6a9.4 9.4 0 1 0 0 18.8c5.43 0 9.03-3.82 9.03-9.2 0-.62-.07-1.09-.15-1.58z" />
                    : <path d="M16.37 1.43c0 1.14-.46 2.22-1.2 3-.8.84-2.1 1.49-3.15 1.4-.13-1.1.42-2.25 1.15-3 .82-.85 2.2-1.46 3.2-1.4zM20.5 17.3c-.55 1.27-.82 1.84-1.52 2.96-.99 1.57-2.38 3.53-4.1 3.54-1.53.02-1.93-1-4.01-.99-2.08.01-2.52 1.01-4.05.99-1.72-.02-3.04-1.78-4.03-3.35C-.12 16.1-.4 11.1 1.3 8.7c1.2-1.7 3.1-2.7 4.88-2.7 1.82 0 2.96 1 4.46 1 1.46 0 2.35-1 4.45-1 1.6 0 3.3.87 4.5 2.38-3.95 2.17-3.31 7.8.9 8.92z" />}
            </svg>
            <span>{provider.label}</span>
        </a>)}
    </div>;
}

function csrfToken() {
    return typeof document === 'undefined' ? '' : document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export default function AuthForm({ action, submitLabel, children }) {
    const [busy, setBusy] = useState(false);
    const submitting = useRef(false);
    useEffect(() => {
        const restore = () => { submitting.current = false; setBusy(false); };
        window.addEventListener('pageshow', restore);
        return () => window.removeEventListener('pageshow', restore);
    }, []);

    function submit(event) {
        if (submitting.current) {
            event.preventDefault();
            return;
        }
        // Native navigation keeps redirects/429s intact; credentials never enter Inertia state.
        event.currentTarget.elements.namedItem('_token').value = csrfToken();
        submitting.current = true;
        setBusy(true);
    }

    return <form method="POST" action={action} noValidate data-auth-form data-error-summary="auth-error-summary" onSubmit={submit}>
        <input type="hidden" name="_token" defaultValue={csrfToken()} />
        {children}
        <button className="button button-sapphire" type="submit" disabled={busy} aria-busy={busy || undefined}>{submitLabel}</button>
    </form>;
}
