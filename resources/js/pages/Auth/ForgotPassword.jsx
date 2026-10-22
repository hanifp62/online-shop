import AuthForm, { AuthFeedback, AuthField, AuthLayout } from '../../components/AuthForm';

export default function ForgotPassword({ locale, copy, assets, urls, old = {}, errors = {}, status = null }) {
    return <AuthLayout locale={locale} copy={copy} assets={assets} homeUrl={urls.home} backUrl={urls.login} backLabel={copy.page.back} simple>
        <AuthFeedback errors={errors} status={status} />
        <AuthForm action={urls.submit} submitLabel={copy.page.submit}>
            <AuthField name="email" label={copy.common.email} type="email" defaultValue={old.email ?? ''}
                required autoComplete="email" error={errors.email} />
        </AuthForm>
    </AuthLayout>;
}
