import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthForm, { AuthFeedback, AuthField } from './AuthForm';
import { SecurityLayout, SecurityNativeForm } from './AuthSecurity';

function useMaterial() {
    const { flash = {} } = usePage();
    const [concealed, conceal] = useState(false);
    useEffect(() => {
        const leave = () => conceal(true);
        window.addEventListener('zafira:private-leave', leave);
        window.addEventListener('pagehide', leave);
        return () => {
            window.removeEventListener('zafira:private-leave', leave);
            window.removeEventListener('pagehide', leave);
        };
    }, []);
    return concealed ? {} : (flash.totpMaterial ?? {});
}

export function TotpEnrolment({ admin = false, ...props }) {
    const material = useMaterial();
    const { copy: { page, common }, urls, errors = {} } = props;
    const available = typeof material.secret === 'string' && typeof material.qr === 'string'
        && material.qr.startsWith('data:image/svg+xml;base64,');
    return <SecurityLayout {...props}>
        <AuthFeedback errors={errors} />
        {available && <>
            <img src={material.qr} alt={page.qr_alt} width="220" height="220" style={{ display: 'block', margin: admin ? '0 auto 1rem' : '16px 0', maxWidth: '100%', height: 'auto' }} />
            {admin ? <>
                <AuthField name="setup_key" label={page.setup_key} value={material.secret} readOnly spellCheck={false} autoComplete="off" />
                <AuthField name="setup_uri" label={page.uri} value={material.uri ?? ''} readOnly spellCheck={false} autoComplete="off" />
            </> : <p style={{ fontSize: 12, color: 'var(--muted)' }}>{page.manual} <code style={{ overflowWrap: 'anywhere' }}>{material.secret}</code></p>}
            <AuthForm action={urls.submit} submitLabel={page.submit}>
                <AuthField name="code" label={page.code} inputMode="numeric" pattern={'\\d{6}'} maxLength={6} autoComplete="one-time-code" required autoFocus error={errors.code} />
            </AuthForm>
        </>}
        {admin ? <SecurityNativeForm action={urls.logout} submitLabel={common.logout} buttonClass="text-link" />
            : <p className="auth-switch"><a href={urls.exit}>{page.cancel}</a></p>}
    </SecurityLayout>;
}

export function TotpRecoveryCodes({ admin = false, ...props }) {
    const { codes } = useMaterial();
    const { copy: { page }, urls } = props;
    return <SecurityLayout {...props}>
        {Array.isArray(codes) && <ul aria-label={admin ? page.codes : undefined}
            style={admin ? undefined : { listStyle: 'none', margin: '16px 0', padding: 0, display: 'grid', gap: 8, fontFamily: 'ui-monospace,monospace' }}>
            {codes.filter((code) => typeof code === 'string').map((code, index) => <li key={index}
                style={admin ? undefined : { border: '1px solid var(--line)', padding: '10px 14px', background: 'var(--white)', overflowWrap: 'anywhere' }}><code>{code}</code></li>)}
        </ul>}
        <a className="button button-sapphire" href={urls.exit}>{admin ? page.saved : page.submit}</a>
    </SecurityLayout>;
}
