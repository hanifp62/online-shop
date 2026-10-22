import AuthForm, { AuthFeedback, AuthField, AuthLayout, PasswordField, SocialAuthLinks } from '../../components/AuthForm';

export default function Register({ locale, copy, assets, urls, old = {}, providers = [], errors = {}, status = null }) {
    return <AuthLayout locale={locale} copy={copy} assets={assets} homeUrl={urls.home} backUrl={urls.home} backLabel={copy.common.back_home}>
        <AuthFeedback errors={errors} status={status} />
        <AuthForm action={urls.submit} submitLabel={copy.page.submit}>
            <AuthField name="name" label={copy.page.full_name} defaultValue={old.name ?? ''} required autoComplete="name" error={errors.name} />
            <div className="form-row">
                <AuthField name="first_name" label={copy.page.first_name} optional={copy.common.optional}
                    defaultValue={old.first_name ?? ''} autoComplete="given-name" error={errors.first_name} />
                <AuthField name="last_name" label={copy.page.last_name} optional={copy.common.optional}
                    defaultValue={old.last_name ?? ''} autoComplete="family-name" error={errors.last_name} />
            </div>
            <AuthField name="email" label={copy.common.email} type="email" defaultValue={old.email ?? ''}
                required autoComplete="email" spellCheck={false} inputMode="email" error={errors.email} />
            <AuthField name="mobile" label={copy.page.mobile} type="tel" optional={copy.common.optional}
                defaultValue={old.mobile ?? ''} autoComplete="tel" inputMode="tel" placeholder={copy.page.mobile_placeholder} error={errors.mobile} />
            <AuthField name="address1" label={copy.page.address} optional={copy.common.optional}
                defaultValue={old.address1 ?? ''} autoComplete="street-address" error={errors.address1} />
            <PasswordField label={copy.common.password} copy={copy.common} autoComplete="new-password" minLength={8}
                hint={copy.page.password_hint} error={errors.password} />
            <PasswordField name="password_confirmation" label={copy.page.confirm_password} copy={copy.common}
                autoComplete="new-password" minLength={8} error={errors.password_confirmation} />
        </AuthForm>
        <SocialAuthLinks providers={providers} label={copy.social.group} />
        <p className="auth-switch">{copy.page.existing} <a href={urls.login}>{copy.page.login}</a></p>
    </AuthLayout>;
}
