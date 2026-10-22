import { usePage } from '@inertiajs/react';
import AuthForm, { AuthFeedback, AuthField } from '../../components/AuthForm';
import { SecurityLayout } from '../../components/AuthSecurity';

export default function ResetPassword(props) {
    const { flash } = usePage();
    const { copy: { page, common }, urls, old = {}, errors = {} } = props;
    return <SecurityLayout {...props} visual>
        <AuthFeedback errors={errors} />
        <AuthForm action={urls.submit} submitLabel={page.submit}>
            <input type="hidden" name="token" value={typeof flash?.resetToken === 'string' ? flash.resetToken : ''} readOnly />
            <AuthField name="email" label={common.email} type="email" defaultValue={old.email ?? ''} required autoComplete="email" error={errors.email} />
            <AuthField name="password" label={page.new_password} type="password" required autoComplete="new-password" error={errors.password} />
            <AuthField name="password_confirmation" label={page.confirm_password} type="password" required autoComplete="new-password" error={errors.password_confirmation} />
        </AuthForm>
    </SecurityLayout>;
}
