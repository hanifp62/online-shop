import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import AuthForm, { AuthFeedback, AuthField } from './AuthForm';

function BrandMark() {
    return <><span className="brand-emblem" aria-hidden="true"><svg viewBox="0 0 48 56" focusable="false">
        <polygon className="emblem-outer" points="24,1.5 41.5,9 47,27.5 38.5,47 24,54.5 9.5,47 1,27.5 6.5,9" />
        <polygon className="emblem-inner" points="24,9.5 35.5,14.5 39.5,27.5 33.5,41.5 24,46.5 14.5,41.5 8.5,27.5 12.5,14.5" />
        <path className="emblem-facets" d="M24 1.5v8M41.5 9l-6 5.5M47 27.5h-7.5M38.5 47l-5-5.5M24 54.5v-8M9.5 47l5-5.5M1 27.5h7.5M6.5 9l6 5.5" />
        <path className="emblem-z" d="M16 19h16L16 36.5h16" />
    </svg></span><span className="brand-copy"><span className="brand-name">ZAFIRA</span><span className="brand-est">Maison de Lumière</span></span></>;
}

export function SecurityLayout({ locale, copy, assets, urls, visual = false, adminLogin = false, children }) {
    const { page, common } = copy;
    useEffect(() => { document.documentElement.lang = locale.replaceAll('_', '-'); }, [locale]);
    return <><Head title={page.title} /><main className="auth-page">
        {visual && <section className="auth-visual" aria-label={adminLogin ? page.visual_label : common.visual_label}>
            <img src={assets.hero} alt={adminLogin ? page.image_alt : common.image_alt}
                width={adminLogin ? 1400 : undefined} height={adminLogin ? 1750 : undefined} fetchPriority={adminLogin ? 'high' : undefined} />
            <a className="brand" href={urls.home} aria-label={adminLogin ? page.home_label : undefined}><BrandMark /></a>
            <div className="auth-quote">{adminLogin && <span className="eyebrow light">{page.eyebrow}</span>}<h2>{page.quote_heading}</h2><p>{page.quote_body}</p></div>
        </section>}
        <section className="auth-content">
            {adminLogin && <a className="auth-back text-link" href={urls.home} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg><span>{common.back_maison}</span>
            </a>}
            <div className="auth-card">
                {(common.admin_access || page.eyebrow) && <span className="eyebrow">{common.admin_access || page.eyebrow}</span>}
                <h1>{page.heading}</h1>{page.intro && <p>{page.intro}</p>}{children}
            </div>
        </section>
    </main></>;
}

// A native form variant preserves each existing recovery/logout button's styling.
export function SecurityNativeForm({ action, submitLabel, buttonClass, style, children }) {
    const csrf = () => typeof document === 'undefined' ? '' : document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    return <form method="POST" action={action} noValidate style={style}
        onSubmit={(event) => { event.currentTarget.elements.namedItem('_token').value = csrf(); }}>
        <input type="hidden" name="_token" defaultValue={csrf()} />
        {children}<button className={buttonClass} type="submit">{submitLabel}</button>
    </form>;
}

export function TotpChallenge({ admin = false, ...props }) {
    const { copy: { page, common }, urls, errors = {} } = props;
    return <SecurityLayout {...props}>
        <AuthFeedback errors={errors} />
        <AuthForm action={urls.submit} submitLabel={page.submit}>
            <AuthField name="code" label={page.code} inputMode="numeric" pattern={'\\d{6}'} maxLength={6}
                autoComplete="one-time-code" required autoFocus error={errors.code} />
        </AuthForm>
        <details style={admin ? undefined : { marginTop: 16 }}>
            <summary style={{ cursor: 'pointer', minHeight: 44 }}>{admin ? page.help : page.recovery_help}</summary>
            <SecurityNativeForm action={urls.recovery} submitLabel={page.recovery_submit} buttonClass={admin ? 'button' : 'button button-light'} style={admin ? undefined : { marginTop: 12 }}>
                <AuthField name="recovery_code" label={page.recovery_code} autoComplete="off" spellCheck={false} required error={errors.recovery_code} />
            </SecurityNativeForm>
        </details>
        {admin ? <SecurityNativeForm action={urls.logout} submitLabel={common.logout} buttonClass="text-link" />
            : <p className="auth-switch"><a href={urls.login}>{page.back}</a></p>}
    </SecurityLayout>;
}
