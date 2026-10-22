import AuthForm, { AuthFeedback, AuthField } from '../../components/AuthForm';
import { SecurityLayout } from '../../components/AuthSecurity';

export default function Login(props) {
    const { copy: { page, common }, urls, old = {}, errors = {}, status = null } = props;
    return <SecurityLayout {...props} visual adminLogin>
        <AuthFeedback errors={errors} status={status} />
        <AuthForm action={urls.submit} submitLabel={page.submit}>
            <AuthField name="email" label={common.email} type="email" defaultValue={old.email ?? ''}
                required autoComplete="email" spellCheck={false} inputMode="email" error={errors.email} />
            <AuthField name="password" label={common.password} type="password" required autoComplete="current-password" error={errors.password} />
        </AuthForm>
    </SecurityLayout>;
}
