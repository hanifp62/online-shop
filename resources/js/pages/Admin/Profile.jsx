
import AuthForm, { AuthFeedback, AuthField } from '../../components/AuthForm';

export default function Profile({ locale, copy: c, identity, security, urls, errors = {}, status }) {
    return <div className="profile-page profile-admin" lang={locale.replaceAll('_', '-')}>

        <header className="profile-identity">
            <span className="profile-initials" aria-hidden="true">{identity.initials}</span>
            <div><h2>{identity.name}</h2><p>{identity.email}</p><p>{identity.role}</p></div>
        </header>
        <nav className="profile-section-nav" aria-label={c.navigation}>
            <a href="#profile-identity">{c.identity}</a>
            <a href="#profile-security">{c.security}</a>
            <a href="#profile-password">{c.password_heading}</a>
        </nav>
        <AuthFeedback errors={errors} status={status} />
        <div className="profile-admin-grid">
            <section className="profile-section" id="profile-identity" aria-labelledby="profile-identity-heading">
                <h2 id="profile-identity-heading">{c.identity}</h2>
                <p className="profile-note">{c.readonly}</p>
                <dl className="profile-facts">
                    <div><dt>{c.name}</dt><dd>{identity.name}</dd></div>
                    <div><dt>{c.email}</dt><dd>{identity.email}</dd></div>
                    <div><dt>{c.role}</dt><dd>{identity.role}</dd></div>
                </dl>
                <div className="field profile-theme-field">
                    <label htmlFor="profile-theme">{c.theme}</label>
                    <select id="profile-theme" data-theme-select defaultValue={globalThis.document?.documentElement.dataset.themePreference || 'system'}>
                        {['system', 'light', 'dark'].map(mode => <option key={mode} value={mode}>{c[`theme_${mode}`]}</option>)}
                    </select>
                </div>
            </section>
            <div>
                <section className="profile-section" id="profile-security" aria-labelledby="profile-security-heading">
                    <h2 id="profile-security-heading">{c.security}</h2>
                    <p>{security.totpEnabled ? c.totp_active : c.totp_inactive}</p>
                    <div className="profile-links">
                        {urls.totpEnrol && <a className="text-link" href={urls.totpEnrol}>{c.totp_enrol}</a>}
                        {urls.authEvents && <a className="text-link" href={urls.authEvents}>{c.auth_events}</a>}
                    </div>
                </section>
                <section className="profile-section" id="profile-password" aria-labelledby="profile-password-heading">
                    <h2 id="profile-password-heading">{c.password_heading}</h2>
                    <p id="profile-password-hint" className="profile-note">{c.password_hint}</p>
                    {security.totpEnabled && <p className="profile-note">{c.password_effect}</p>}
                    <AuthForm action={urls.password} submitLabel={c.password_submit}>
                        <input type="hidden" name="_method" value="PATCH" />
                        <AuthField name="current_password" type="password" label={c.current_password} autoComplete="current-password" required error={errors.current_password} />
                        <AuthField name="password" type="password" label={c.new_password} autoComplete="new-password" minLength={8} required error={errors.password} />
                        <AuthField name="password_confirmation" type="password" label={c.confirm_password} autoComplete="new-password" minLength={8} required error={errors.password_confirmation} />
                    </AuthForm>
                </section>
            </div>
        </div>
    </div>;
}
