import AuthForm, { AuthFeedback, AuthField, AuthLayout, PasswordField, SocialAuthLinks } from '../../components/AuthForm';

export default function Login({ locale, copy, assets, urls, old = {}, providers = [], errors = {}, status = null }) {
    return <AuthLayout locale={locale} copy={copy} assets={assets} homeUrl={urls.home} backUrl={urls.home} backLabel={copy.common.back_home}>
        <AuthFeedback errors={errors} status={status} />
        <AuthForm action={urls.submit} submitLabel={copy.page.submit}>
            <AuthField name="email" label={copy.common.email} type="email" defaultValue={old.email ?? ''}
                required autoComplete="email" spellCheck={false} inputMode="email" error={errors.email} />
            <PasswordField label={copy.common.password} copy={copy.common} autoComplete="current-password" error={errors.password} />
            <div className="auth-options">
                <label><input type="checkbox" name="remember" value="1" /> {copy.page.remember}</label>
                <a href={urls.forgotPassword}>{copy.page.forgot}</a>
            </div>
        </AuthForm>
        <SocialAuthLinks providers={providers} label={copy.social.group} />
        <p className="auth-switch">{copy.page.new} <a href={urls.register}>{copy.page.register}</a></p>
    </AuthLayout>;
}
