import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { storefrontRequest } from '../../lib/storefront-cart.js';
import { CloseButton, Dialog, Icon } from './Primitives.jsx';
import { useStorefront } from './StorefrontProvider.jsx';

function useSubmission() {
    const { t, notify } = useStorefront(); const [busy, setBusy] = useState(false); const [errors, setErrors] = useState({}); const lock = useRef(false);
    const formRef = useRef(null);
    useEffect(() => { if (Object.keys(errors).length) formRef.current?.querySelector('[aria-invalid="true"]')?.focus(); }, [errors]);
    const submit = async (event, url, payload, success) => {
        event.preventDefault(); if (lock.current) return; lock.current = true; setBusy(true); setErrors({});
        formRef.current = event.currentTarget;
        try { const result = await storefrontRequest(url, 'POST', payload); success(result); }
        catch (error) {
            setErrors(error.errors || {});

            notify(t(`err_${error.kind}`) || t('check_info'));
        } finally { lock.current = false; setBusy(false); }
    };
    return { busy, errors, submit };
}
export function Concierge() {
    const { copy: { footer: f }, links, user, today, dialog, close, notify, t } = useStorefront();
    const [drafts, setDrafts] = useState({});
    const [statuses, setStatuses] = useState({});
    // This owner survives keyed form remounts. A draft can have only one unresolved request.
    const requests = useRef(new Map());
    const versions = useRef(new Map());
    const lastKey = useRef('generic');
    const currentDialog = useRef(dialog);
    const dialogEpoch = useRef(0);
    const alive = useRef(true);
    const formRef = useRef(null);
    const key = dialog?.type === 'concierge' ? dialog.data?.notes || 'generic' : lastKey.current;
    useLayoutEffect(() => {
        lastKey.current = key;
        if (currentDialog.current !== dialog) dialogEpoch.current++;
        currentDialog.current = dialog;
    }, [key, dialog]);
    useEffect(() => { alive.current = true; return () => { alive.current = false; }; }, []);
    const status = statuses[key];
    const errors = status?.errorEpoch === dialogEpoch.current && status?.errorVersion === (versions.current.get(key) || 0) ? status.errors : {};
    useEffect(() => {
        if (dialog?.type === 'concierge' && Object.keys(errors).length) formRef.current?.querySelector('[aria-invalid="true"]')?.focus();
    }, [status, dialog]);
    const inquiry = dialog?.type === 'concierge' ? dialog.data : null;
    const fields = drafts[key] || { name: user?.name || '', email: user?.email || '', phone: user?.mobile || '', service: key === 'generic' ? 'private-styling' : 'atelier', preferred_date: '', preferred_time: '10:00', notes: key === 'generic' ? '' : key, order_items: '' };
    const setFields = next => {
        versions.current.set(key, (versions.current.get(key) || 0) + 1);
        setDrafts(previous => ({ ...previous, [key]: next }));
    };
    const submit = async event => {
        event.preventDefault();
        if (requests.current.has(key)) return;
        const request = { version: versions.current.get(key) || 0, epoch: dialogEpoch.current, dialog: currentDialog.current };
        requests.current.set(key, request);
        setStatuses(previous => ({ ...previous, [key]: { busy: true, errors: {} } }));
        const ownsRequest = () => alive.current && requests.current.get(key) === request;
        const ownsView = () => ownsRequest() && (versions.current.get(key) || 0) === request.version
            && dialogEpoch.current === request.epoch && currentDialog.current === request.dialog;
        try {
            const result = await storefrontRequest(links.concierge, 'POST', { ...fields });
            if (!ownsRequest()) return;
            if (ownsView()) {
                versions.current.set(key, request.version + 1);
                setDrafts(previous => { const next = { ...previous }; delete next[key]; return next; });
                close();
            }
            notify(result.message);
        } catch (error) {
            if (!ownsRequest()) return;
            if (ownsView()) setStatuses(previous => ({ ...previous, [key]: {
                busy: true, errors: error.errors || {}, errorEpoch: request.epoch, errorVersion: request.version,
            } }));
            notify(t(`err_${error.kind}`) || t('check_info'));
        } finally {
            if (requests.current.get(key) === request) {
                requests.current.delete(key);
                if (alive.current) setStatuses(previous => ({ ...previous, [key]: { ...previous[key], busy: false } }));
            }
        }
    };
    return <ConciergeForm key={key} {...{ f, links, today, t, fields, setFields, submit, errors, formRef }} busy={Boolean(status?.busy)} inquiry={inquiry} />;
}
function ConciergeForm({ f, links, today, t, inquiry, fields, setFields, submit, errors, busy, formRef }) {
    const input = (name, label, type = 'text', extra = {}) => <div className="field"><label htmlFor={`sf-concierge-${name}`}>{label}</label><input id={`sf-concierge-${name}`} name={name} type={type} value={fields[name]} onChange={event => setFields({ ...fields, [name]: event.target.value })} aria-invalid={errors[name] ? 'true' : undefined} aria-describedby={errors[name] ? `sf-error-${name}` : undefined} {...extra} />{errors[name] && <p className="form-error" id={`sf-error-${name}`} role="alert">{errors[name][0]}</p>}</div>;
    return <Dialog type="concierge" title={`${f.concierge_title_1} ${f.concierge_title_2}`} className="concierge-modal modal"><CloseButton label={f.close} /><div className="concierge-intro"><span className="eyebrow light">ZAFIRA Concierge</span><h2>{f.concierge_title_1}<br />{f.concierge_title_2}</h2><p>{f.concierge_intro}</p><span className="concierge-hours">{f.hours}</span></div>
        {inquiry && <div className="concierge-order-summary"><h3>{f.order_summary}</h3><ul><li>{inquiry.summary}</li></ul><strong>{t('inquiry_total')}</strong></div>}
        <form ref={formRef} className="concierge-form" action={links.concierge} method="POST" aria-busy={busy} onSubmit={event => void submit(event)}>
            <input type="hidden" name="order_items" value="" />
            {input('name', f.name, 'text', { required: true, maxLength: 100, autoComplete: 'name' })}<div className="form-row">{input('email', f.email, 'email', { required: true, maxLength: 255, autoComplete: 'email', spellCheck: false })}{input('phone', f.phone, 'tel', { required: true, maxLength: 30, autoComplete: 'tel' })}</div>
            <div className="field"><label htmlFor="sf-concierge-service">{f.service}</label><select id="sf-concierge-service" name="service" required value={fields.service} onChange={event => setFields({ ...fields, service: event.target.value })}>{[['private-styling', f.svc_styling], ['virtual-consultation', f.svc_virtual], ['gifting', f.svc_gifting], ['atelier', f.svc_atelier]].map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{errors.service && <p role="alert">{errors.service[0]}</p>}</div>
            <div className="form-row schedule-row">{input('preferred_date', f.date, 'date', { min: today })}<div className="field"><label htmlFor="sf-concierge-time">{f.time}</label><select id="sf-concierge-time" name="preferred_time" value={fields.preferred_time} onChange={event => setFields({ ...fields, preferred_time: event.target.value })}>{['10:00', '12:00', '14:00', '16:00', '18:00'].map(time => <option key={time} value={time}>{time.replace(':', '.')} WIB</option>)}</select>{errors.preferred_time && <p role="alert">{errors.preferred_time[0]}</p>}</div></div>
            <div className="field"><label htmlFor="sf-concierge-notes">{f.notes} <span>{f.optional}</span></label><textarea id="sf-concierge-notes" name="notes" maxLength={1000} rows={2} value={fields.notes} placeholder={f.notes_placeholder} aria-invalid={errors.notes ? 'true' : undefined} onChange={event => setFields({ ...fields, notes: event.target.value })} />{errors.notes && <p className="form-error" role="alert">{errors.notes[0]}</p>}</div><button className="button button-sapphire" type="submit" disabled={busy}>{busy ? t('processing') : f.confirm}<Icon name="arrow" /></button>
        </form>
    </Dialog>;
}
export function Newsletter() {
    const { copy: { welcome: w }, links, notify, t } = useStorefront();
    const [email, setEmail] = useState(''); const { busy, errors, submit } = useSubmission();
    return <section className="newsletter-section"><div className="newsletter-gem" aria-hidden="true"><i /></div><div className="reveal is-visible"><span className="eyebrow light">{w.newsletter_eyebrow}</span><h2>{w.newsletter_title}</h2><p>{w.newsletter_desc}</p><form className="newsletter-form" action={links.newsletter} method="POST" aria-busy={busy} onSubmit={event => void submit(event, links.newsletter, { email }, result => { setEmail(''); notify(result.message); })}><label className="sr-only" htmlFor="newsletter-email">{w.email_label}</label><input id="newsletter-email" name="email" type="email" required maxLength={255} autoComplete="email" placeholder={w.newsletter_placeholder} spellCheck={false} value={email} aria-invalid={errors.email ? 'true' : undefined} aria-describedby={errors.email ? 'sf-newsletter-error' : undefined} onChange={event => setEmail(event.target.value)} /><button type="submit" disabled={busy} aria-label={w.newsletter_submit}>{busy ? t('processing') : <Icon name="arrow" />}</button></form>{errors.email && <p id="sf-newsletter-error" role="alert">{errors.email[0]}</p>}<small>{w.newsletter_privacy}</small></div></section>;
}
export function Search() {
    const { copy: { footer: f }, links } = useStorefront(); const [query, setQuery] = useState('');
    return <Dialog type="search" title={f.search_title} className="search-panel"><div className="search-top"><a className="brand" href={links.home}>ZAFIRA</a><CloseButton className="icon-button" label={f.search_close} /></div><div className="search-inner"><span className="eyebrow">{f.search_eyebrow}</span><h2>{f.search_title}</h2><form id="catalog-header-search" className="search-field" role="search" aria-label={f.search_label} method="get" action={links.catalog}><Icon name="search" /><label className="sr-only" htmlFor="sf-search-input">{f.search_label}</label><input id="sf-search-input" data-initial-focus type="search" name="q" maxLength={100} placeholder={f.search_placeholder} autoComplete="off" spellCheck={false} value={query} onChange={event => setQuery(event.target.value)} /><button type="button" onClick={() => { setQuery(''); document.getElementById('sf-search-input')?.focus(); }}>{f.search_clear}</button></form><div className="search-suggestions"><span>{f.search_popular}</span><a href={`${links.catalog}?kategori=tas-tangan`}>{f.term_bag}</a><a href={`${links.catalog}?q=Saphir`}>Saphir</a><a href={`${links.catalog}?urut=terbaru`}>{f.term_new}</a></div><button className="button button-dark" type="submit" form="catalog-header-search">{f.search_submit}</button></div></Dialog>;
}
