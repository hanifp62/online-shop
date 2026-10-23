import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import AuthForm from '../../components/AuthForm';

export default function CartReminderUnsubscribe({ locale, copy: { page, common }, urls, done = false }) {
    useEffect(() => { document.documentElement.lang = locale.replaceAll('_', '-'); }, [locale]);
    return <><Head title={page.title} />
        <main className="auth-page" style={{ display: 'block', padding: '48px 24px', maxWidth: 560, margin: '0 auto' }}>
            <a className="brand" href={urls.home} aria-label={common.home}><span className="brand-emblem" aria-hidden="true"><svg viewBox="0 0 48 56" focusable="false">
                            <polygon className="emblem-outer" points="24,1.5 41.5,9 47,27.5 38.5,47 24,54.5 9.5,47 1,27.5 6.5,9" />
                            <polygon className="emblem-inner" points="24,9.5 35.5,14.5 39.5,27.5 33.5,41.5 24,46.5 14.5,41.5 8.5,27.5 12.5,14.5" />
                            <path className="emblem-facets" d="M24 1.5v8M41.5 9l-6 5.5M47 27.5h-7.5M38.5 47l-5-5.5M24 54.5v-8M9.5 47l5-5.5M1 27.5h7.5M6.5 9l6 5.5" />
                            <path className="emblem-z" d="M16 19h16L16 36.5h16" />
                        </svg></span><span className="brand-copy"><span className="brand-name">ZAFIRA</span><span className="brand-est">Maison de Lumière</span></span></a>
            <h1>{page.heading}</h1><p role={done ? 'status' : undefined}>{page.intro}</p>
            {!done && <AuthForm action={urls.submit} submitLabel={page.submit} />}
            <p><a className="button button-sapphire" href={urls.shop}>{page.shop}</a></p>
        </main>
    </>;
}
