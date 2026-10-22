import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { AuthFeedback, AuthField } from '../../components/AuthForm';

const textButton = { border: 0, background: 'none', cursor: 'pointer', minHeight: 44 };
const sectionStyle = { marginTop: 32 };
const muted = { color: 'var(--muted)', fontSize: 12, margin: '6px 0 12px' };
const listStyle = { listStyle: 'none', margin: '12px 0 0', padding: 0, display: 'grid', gap: 10 };
const detailForm = { display: 'grid', gap: 12, marginTop: 12 };
const summaryStyle = { cursor: 'pointer', minHeight: 44 };
const csrfToken = () => typeof document === 'undefined' ? '' : document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Keep credentials exclusively in native DOM controls and refresh CSRF at the point of submission.
function NativeForm({ action, method, confirm, children, ...attributes }) {
    const formRef = useRef(null);
    const submitting = useRef(false);
    useEffect(() => {
        const restore = () => {
            submitting.current = false;
            formRef.current?.querySelectorAll('[type="submit"]').forEach((button) => {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
        };
        window.addEventListener('pageshow', restore);
        return () => window.removeEventListener('pageshow', restore);
    }, []);
    return <form ref={formRef} method="POST" action={action} {...attributes} onSubmit={(event) => {
        if (submitting.current || (confirm && !window.confirm(confirm))) { event.preventDefault(); return; }
        event.currentTarget.elements.namedItem('_token').value = csrfToken();
        submitting.current = true;
        event.currentTarget.querySelectorAll('[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }}>
        <input type="hidden" name="_token" defaultValue={csrfToken()} />
        {method && <input type="hidden" name="_method" value={method} />}
        {children}
    </form>;
}

function BrandMark() {
    return <><span className="brand-emblem" aria-hidden="true">
        <svg viewBox="0 0 48 56" aria-hidden="true" focusable="false">
            <polygon className="emblem-outer" points="24,1.5 41.5,9 47,27.5 38.5,47 24,54.5 9.5,47 1,27.5 6.5,9" />
            <polygon className="emblem-inner" points="24,9.5 35.5,14.5 39.5,27.5 33.5,41.5 24,46.5 14.5,41.5 8.5,27.5 12.5,14.5" />
            <path className="emblem-facets" d="M24 1.5v8M41.5 9l-6 5.5M47 27.5h-7.5M38.5 47l-5-5.5M24 54.5v-8M9.5 47l5-5.5M1 27.5h7.5M6.5 9l6 5.5" />
            <path className="emblem-z" d="M16 19h16L16 36.5h16" />
        </svg>
    </span><span className="brand-copy"><span className="brand-name">ZAFIRA</span><span className="brand-est">Maison de Lumière</span></span></>;
}

export default function Show({ locale, copy, assets, urls, profile, verified, hasPassword, providers = [], totp, history = [], errors = {}, status }) {
    useEffect(() => { document.documentElement.lang = locale.replaceAll('_', '-'); }, [locale]);
    const field = (name, attributes = {}) => <AuthField name={name} label={copy.profile[name]} defaultValue={profile[name]} error={errors[name]} {...attributes} />;
    return <>
        <Head title={copy.show.title} />
        <main className="auth-page">
            <section className="auth-visual" aria-label={copy.common.brand_world}>
                <img src={assets.hero} alt={copy.common.editorial} width="1400" height="1750" fetchPriority="high" />
                <a className="brand" href={urls.home} aria-label={copy.common.home}><BrandMark /></a>
                <div className="auth-quote"><span className="eyebrow light">Le Salon Privé</span><h2>{copy.show.salon}</h2><p>{copy.show.intro}</p></div>
            </section>
            <section className="auth-content">
                <a className="auth-back text-link" href={urls.home} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg><span>{copy.show.back}</span>
                </a>
                <div className="auth-card" style={{ maxWidth: 580 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, flexWrap: 'wrap', gap: 6 }}>
                        <span className="eyebrow" style={{ marginBottom: 0 }}>Votre Compte</span>
                        <NativeForm action={urls.logout}><button type="submit" className="text-link" style={textButton}>{copy.show.logout}</button></NativeForm>
                    </div>
                    <h1>{copy.greeting}</h1><p>{copy.show.contact_intro}</p>
                    <AuthFeedback errors={errors} status={status} />
                    {!verified && <div className="auth-errors" role="status" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                        <span>{copy.unverified}</span><NativeForm action={urls.resend}><button type="submit" className="text-link" style={textButton}>{copy.show.resend}</button></NativeForm>
                    </div>}
                    <NativeForm action={urls.update} method="PATCH" data-auth-form data-error-summary="auth-error-summary" noValidate>
                        {field('name', { required: true, autoComplete: 'name' })}
                        <div className="form-row">{field('first_name', { autoComplete: 'given-name' })}{field('last_name', { autoComplete: 'family-name' })}</div>
                        {field('mobile', { type: 'tel', autoComplete: 'tel', inputMode: 'tel', placeholder: copy.profile.mobile_placeholder })}
                        {field('address1', { autoComplete: 'street-address' })}
                        {field('address2', { autoComplete: 'address-level2' })}
                        <div className="field"><label htmlFor="email">{copy.profile.email}</label><input id="email" type="email" value={profile.email} disabled /></div>
                        <div className="field"><input type="hidden" name="cart_reminder_opt_in" value="0" /><label htmlFor="cart_reminder_opt_in"><input id="cart_reminder_opt_in" name="cart_reminder_opt_in" type="checkbox" value="1" defaultChecked={profile.cart_reminder_opt_in} /> {copy.profile.reminders}</label></div>
                        <button className="button button-sapphire" type="submit">{copy.profile.save}</button>
                    </NativeForm>
                    {providers.length > 0 && <div style={sectionStyle}><span className="eyebrow">{copy.social.heading}</span><ul style={listStyle}>
                        {providers.map((provider) => <li key={provider.id} style={provider.linked ? { display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 } : undefined}>
                            {provider.linked ? <><span>{provider.label}</span><NativeForm action={provider.url} method="DELETE" confirm={provider.confirm}><button type="submit" className="text-link" style={textButton}>{copy.social.unlink}</button></NativeForm></> : <a className="button button-dark" href={provider.url}>{provider.label}</a>}
                        </li>)}
                    </ul></div>}
                    <div style={sectionStyle}><span className="eyebrow">{copy.totp.heading}</span>
                        {totp.enabled ? <><p style={muted}>{totp.active}</p><details open={errors.disable_code ? true : undefined}>
                            <summary style={summaryStyle}>{copy.totp.disable_heading}</summary>
                            <NativeForm action={urls.totpDestroy} method="DELETE" style={detailForm} noValidate>
                                <AuthField name="disable_code" label={copy.totp.code} inputMode="numeric" pattern="\d{6}" maxLength={6} autoComplete="one-time-code" required error={errors.disable_code} />
                                <button className="button button-dark" type="submit">{copy.totp.disable}</button>
                            </NativeForm>
                        </details></> : <><p style={muted}>{copy.totp.intro}</p><a className="button button-light" href={urls.totpEnrol}>{copy.totp.enable}</a></>}
                    </div>
                    <div style={sectionStyle}><span className="eyebrow">{copy.privacy.heading}</span><p style={muted}>{copy.privacy.intro}</p>
                        <a className="button button-light" href={urls.export}>{copy.privacy.export}</a>
                        <details style={{ marginTop: 16 }} open={errors.password || errors.confirmation || errors.delete ? true : undefined}>
                            <summary style={summaryStyle}>{copy.privacy.delete}</summary>
                            <NativeForm action={urls.destroy} method="DELETE" style={detailForm} confirm={copy.privacy.confirm}>
                                {errors.delete && <p role="alert" style={{ color: '#a32' }}>{errors.delete}</p>}
                                <div className="field">
                                    <label htmlFor={hasPassword ? 'delete_password' : 'delete_confirmation'}>{hasPassword ? copy.privacy.password : copy.deletePhrase}</label>
                                    <input id={hasPassword ? 'delete_password' : 'delete_confirmation'} name={hasPassword ? 'password' : 'confirmation'} type={hasPassword ? 'password' : 'text'} autoComplete={hasPassword ? 'current-password' : 'off'} required aria-invalid={errors[hasPassword ? 'password' : 'confirmation'] ? 'true' : undefined} aria-describedby={errors[hasPassword ? 'password' : 'confirmation'] ? 'delete-credential-error' : undefined} />
                                    {errors[hasPassword ? 'password' : 'confirmation'] && <small id="delete-credential-error" role="alert">{errors[hasPassword ? 'password' : 'confirmation']}</small>}
                                </div>
                                <button className="button button-dark" type="submit">{copy.privacy.submit}</button>
                            </NativeForm>
                        </details>
                    </div>
                    <div style={sectionStyle}><span className="eyebrow">{copy.history.heading}</span>
                        {history.length === 0 ? <p style={{ ...muted, marginTop: 6 }}>{copy.history.empty}</p> : <ul style={{ ...listStyle, gap: 16 }}>
                            {history.map((item, index) => <li key={index} style={{ border: '1px solid var(--line)', padding: '18px 20px', background: 'var(--white)', borderRadius: 6, boxShadow: '0 2px 8px rgba(0,0,0,0.03)' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}><strong style={{ fontSize: 15, color: 'var(--ink)' }}>{item.service}</strong><span className={`badge-status badge-${item.status}`}>{item.statusLabel}</span></div>
                                <div style={{ color: 'var(--muted)', fontSize: 12 }}>{item.schedule} · {item.submitted}</div>
                                {item.notes && <div style={{ marginTop: 10, fontSize: 12, lineHeight: 1.45, color: 'var(--ink)', background: 'var(--ivory)', padding: '10px 12px', borderRadius: 4, borderLeft: '3px solid var(--gold)', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{item.notes}</div>}
                            </li>)}
                        </ul>}
                    </div>
                </div>
            </section>
        </main>
        <div className="toast" role="status" aria-live="polite" data-flash={status || undefined}><span><svg aria-hidden="true"><use href="#icon-check" /></svg></span><p data-toast-message /></div>
        <svg className="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6" /></symbol></svg>
    </>;
}
