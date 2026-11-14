import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { formatCopy, storefrontRequest } from '../../lib/storefront-cart.js';
import { acceptSearchResult, clearTranscript, conciergeAvailability, conciergeNotes, matchIntent, readTranscript, writeTranscript } from '../../lib/storefront-assistant.js';
import { CloseButton, Dialog, Icon } from './Primitives.jsx';
import { useStorefront } from './StorefrontProvider.jsx';

const session = () => { try { return globalThis.sessionStorage; } catch { return null; } };
let sequence = 0;
const entry = (from, kind, params = {}) => ({ id: `${Date.now().toString(36)}-${++sequence}`, from, kind, params, at: Date.now() });
const HANDOFF = ['appointment', 'human', 'issue'];
const SERVICE = { appointment: 'private-styling', human: 'virtual-consultation', issue: 'virtual-consultation', fallback: 'virtual-consultation', gift: 'gifting', personalize: 'atelier' };
const ICONS = { product: 'sparkle', search: 'search', size: 'ruler', delivery: 'truck', order: 'package', care: 'drop', gift: 'gift', personalize: 'pen', payment: 'card', appointment: 'chat' };
const FOLLOW = { ai_reply: ['size', 'delivery', 'appointment'], product: ['care', 'size', 'personalize'], results: ['delivery', 'gift'], results_empty: ['appointment'], size: ['appointment', 'delivery'], delivery: ['order', 'payment'], order: ['delivery', 'payment'], care: ['product', 'appointment'], gift: ['personalize', 'appointment'], personalize: ['gift', 'appointment'], payment: ['order', 'delivery'], feedback_no: ['appointment'] };
const FEEDBACK = ['ai_reply', 'product', 'results', 'size', 'delivery', 'order', 'care', 'gift', 'personalize', 'payment'];
const DIRECT = ['issue', 'human', 'order', 'payment', 'appointment'];

export function AssistantLauncher() {
    const { copy: { assistant: a }, dialog, open } = useStorefront();
    const hidden = dialog?.type === 'assistant';
    return <button type="button" className={`sf-assistant-launcher${hidden ? ' is-open-behind' : ''}`} aria-haspopup="dialog" aria-keyshortcuts="H" aria-label={a.launcher} title={a.launcher} onClick={() => open('assistant')}><Icon name="chat" /><span className="sf-assistant-dot" aria-hidden="true" /><span className="sr-only">{a.launcher}</span></button>;
}

function Card({ item, a, p, f, onAdd }) {
    return <li className="sf-assistant-card">
        {item.image && <img src={item.image} alt="" width="72" height="90" loading="lazy" decoding="async" />}
        <div><span className="sf-assistant-card-category">{item.category}</span><a className="sf-assistant-card-title" href={item.href}>{item.name}</a>
            <span className="sf-assistant-card-meta" translate="no">{item.priceLabel}</span><span className={`sf-assistant-stock${item.available ? ' is-available' : ''}`}>{item.available ? f.quick_available : p.sold_out}</span>
            <div className="sf-assistant-card-actions">{item.available && !item.hasSizes && <button type="button" onClick={() => onAdd(item)}>{a.add_to_bag}</button>}{item.hasSizes && <a href={item.href}>{a.choose_size}</a>}{!item.available && <a href={item.href}>{a.view_product}</a>}</div>
        </div>
    </li>;
}

function sanitizeAiCards(items) {
    if (!Array.isArray(items)) return [];
    return items.filter(item => item && typeof item.name === 'string' && typeof item.href === 'string' && Number.isInteger(item.productId))
        .slice(0, 4)
        .map(item => ({
            id: String(item.id), productId: item.productId, name: item.name,
            category: String(item.category ?? ''), priceLabel: String(item.priceLabel ?? ''),
            available: item.available === true, image: typeof item.image === 'string' ? item.image : null,
            alt: String(item.alt ?? item.name), href: item.href, hasSizes: item.hasSizes === true,
        }));
}

export function Assistant() {
    const { copy: { assistant: a, pdp: p, footer: f, welcome: w }, links, user, product, locale, dialog, open, adjust } = useStorefront();
    const [state, setState] = useState({ entries: [], failures: 0, mode: null });
    const [loaded, setLoaded] = useState(false);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const logRef = useRef(null);
    const inputRef = useRef(null);
    const scrollTo = useRef(null);
    const handled = useRef(null);
    const searchSeq = useRef(0);
    const stateRef = useRef(state);
    stateRef.current = state;
    const active = dialog?.type === 'assistant';
    const fresh = () => ({ entries: [entry('bot', 'greeting')], failures: 0, mode: null });
    const label = topic => a[`topic_${topic}`] || a.human;
    const time = at => { try { return new Intl.DateTimeFormat(String(locale).replaceAll('_', '-'), { hour: '2-digit', minute: '2-digit' }).format(at); } catch { return ''; } };

    useEffect(() => {
        const saved = readTranscript(session());
        setState(saved?.entries.length ? saved : fresh());
        setLoaded(true);
    }, []);
    useEffect(() => { if (loaded) writeTranscript(session(), state); }, [state, loaded]);
    useEffect(() => {
        if (!active || !loaded) return;
        const reference = dialog.data?.reference;
        if (reference && handled.current !== reference) {
            handled.current = reference;
            append([entry('bot', 'sent', { reference })], { failures: 0, resolve: true });
            return;
        }
        const lastContext = [...stateRef.current.entries].reverse().find(item => item.kind === 'context' || item.kind === 'greeting');
        if (product && lastContext?.params?.href !== product.href) append([entry('bot', 'context', { name: product.name, href: product.href })]);
    }, [active, loaded, dialog]);
    useLayoutEffect(() => {
        const log = logRef.current; const target = scrollTo.current && log?.querySelector(`[data-entry="${scrollTo.current}"]`);
        if (target) log.scrollTop = Math.max(0, target.offsetTop - 8);
        scrollTo.current = null;
    }, [state.entries, active]);
    useEffect(() => () => { searchSeq.current++; }, []);

    function append(items, { failures, mode, resolve = false } = {}) {
        if (!items.length) return;
        scrollTo.current = items[0].id;
        setState(previous => ({
            entries: [...previous.entries.map(item => resolve && ['handoff', 'cart_confirm'].includes(item.kind) ? { ...item, params: { ...item.params, resolved: true } } : item), ...items],
            failures: failures ?? previous.failures,
            mode: mode === undefined ? previous.mode : mode,
        }));
    }
    const patch = (id, params) => setState(previous => ({ ...previous, entries: previous.entries.map(item => item.id === id ? { ...item, params: { ...item.params, ...params } } : item) }));
    const lastTopic = () => [...stateRef.current.entries].reverse().find(item => item.from === 'user' && item.kind === 'topic' && !HANDOFF.includes(item.params.topic))?.params.topic;
    const handoff = (reason, pending = []) => {
        const topic = lastTopic();
        const notes = conciergeNotes({
            heading: a.notes_heading, labels: { product: a.ctx_product, page: a.ctx_page, topic: a.ctx_topic, questions: a.ctx_questions },
            product, page: product ? null : a.page_home, topic: topic ? label(topic) : null,
            questions: [...stateRef.current.entries.filter(item => item.from === 'user' && item.kind === 'text').map(item => item.params.text), ...pending],
        });
        return entry('bot', 'handoff', { reason, notes, service: SERVICE[reason] || SERVICE.human });
    };
    const reply = (intent, question) => {
        if (intent === 'product') return product ? entry('bot', 'product', { name: product.name, href: product.href, image: product.image, price: product.priceLabel, available: product.stock > 0, material: product.material, composition: product.composition, origin: product.origin, color: product.color }) : entry('bot', 'search_prompt');
        if (intent === 'size') return entry('bot', 'size', { applies: product ? Boolean(product.hasSizes) : null });
        if (intent === 'care') return entry('bot', 'care', product?.care ? { name: product.name, care: product.care, craft: product.craft } : {});
        if (['delivery', 'order', 'gift', 'personalize', 'payment'].includes(intent)) return entry('bot', intent, intent === 'order' ? { signedIn: Boolean(user) } : {});
        if (intent === 'search') return entry('bot', 'search_prompt');
        return handoff(intent, question ? [question] : []);
    };
    const choose = topic => {
        const answer = reply(topic);
        append([entry('user', 'topic', { topic }), answer], { failures: 0, resolve: true, mode: answer.kind === 'search_prompt' ? 'search' : null });
        if (answer.kind === 'search_prompt') queueMicrotask(() => inputRef.current?.focus());
    };
    const search = async (question, countsAsMiss) => {
        const seq = ++searchSeq.current; setBusy(true);
        try {
            const data = acceptSearchResult(await storefrontRequest(`${links.assistantProducts}?q=${encodeURIComponent(question)}`));
            if (seq !== searchSeq.current) return;
            if (data.items.length) { append([entry('bot', 'results', { query: question.slice(0, 80), ...data })], { failures: 0, mode: null }); return; }
            if (!countsAsMiss) { append([entry('bot', 'results_empty', { query: question.slice(0, 80), catalog: data.catalog })], { mode: null }); return; }
            const failures = stateRef.current.failures + 1;
            append([entry('bot', 'fallback', { text: question.slice(0, 80) }), ...(failures >= 2 ? [handoff('fallback')] : [])], { failures: failures >= 2 ? 0 : failures, resolve: true, mode: null });
        } catch {
            if (seq === searchSeq.current) append([entry('bot', 'results_error')], { mode: null });
        } finally {
            if (seq === searchSeq.current) setBusy(false);
        }
    };
    const askAi = async question => {
        const seq = ++searchSeq.current; setBusy(true);
        const history = stateRef.current.entries
            .filter(item => (item.from === 'user' && item.kind === 'text') || (item.from === 'bot' && item.kind === 'ai_reply'))
            .slice(-6)
            .map(item => ({ role: item.from, text: String(item.params?.text || item.params?.reply || '') }))
            .filter(turn => turn.text !== '');
        try {
            const data = await storefrontRequest(links.assistantChat, 'POST', {
                message: question,
                product_slug: product?.id || null,
                history,
            }, { timeout: 20000 });
            if (seq !== searchSeq.current) return;
            const replyText = typeof data?.reply === 'string' ? data.reply.trim() : '';
            if (!replyText) throw new Error('empty_ai');
            const items = sanitizeAiCards(data.items);
            const botEntries = [entry('bot', 'ai_reply', {
                reply: replyText,
                items,
                mode: data.mode === 'ai' ? 'ai' : 'fallback',
                catalog: typeof data.catalog_url === 'string' ? data.catalog_url : links.catalog,
            })];
            if (data.escalate_concierge === true) {
                const reason = matchIntent(question) === 'issue' ? 'issue' : 'human';
                botEntries.push(handoff(reason, [question]));
            }
            append(botEntries, { failures: 0, mode: null });
        } catch {
            if (seq !== searchSeq.current) return;
            const intent = matchIntent(question);
            if (intent && intent !== 'search' && !(intent === 'product' && !product)) {
                const answer = reply(intent, question);
                append([answer], { failures: 0, mode: answer.kind === 'search_prompt' ? 'search' : null });
                setBusy(false);
                return;
            }
            setBusy(false);
            await search(question, !intent);
            return;
        } finally {
            if (seq === searchSeq.current) setBusy(false);
        }
    };
    const ask = event => {
        event.preventDefault();
        const question = text.trim().slice(0, 200);
        if (!question || busy) return;
        setText('');
        const intent = matchIntent(question);
        const asked = entry('user', 'text', { text: question });
        if (stateRef.current.mode === 'search' && !DIRECT.includes(intent)) {
            append([asked], { resolve: true }); void search(question, false); return;
        }
        if (links.assistantChat) {
            append([asked], { resolve: true }); void askAi(question); return;
        }
        const wantsSearch = intent === 'search' || (intent === 'product' && !product);
        if (wantsSearch) { append([asked], { resolve: true }); void search(question, false); return; }
        if (intent) { const answer = reply(intent, question); append([asked, answer], { failures: 0, resolve: true, mode: answer.kind === 'search_prompt' ? 'search' : null }); return; }
        append([asked], { resolve: true }); void search(question, true);
    };
    const proceed = item => {
        patch(item.id, { resolved: true });
        open('concierge', { notes: item.params.notes, service: item.params.service, source: 'assistant' });
    };
    const confirmAdd = item => append([entry('bot', 'cart_confirm', { item })], { resolve: true });
    const addToBag = async confirm => {
        patch(confirm.id, { resolved: true });
        const result = await adjust(confirm.params.item.productId, 1);
        append([entry('bot', result ? 'cart_added' : 'cart_failed', { item: confirm.params.item })]);
    };
    const undoAdd = async added => {
        patch(added.id, { undone: true });
        const result = await adjust(added.params.item.productId, -1);
        append([entry('bot', result ? 'cart_undone' : 'cart_failed', { item: added.params.item })]);
    };
    const vote = (item, helpful) => {
        patch(item.id, { vote: helpful ? 'yes' : 'no' });
        void storefrontRequest(links.assistantFeedback, 'POST', { topic: item.kind, helpful }).catch(() => {});
        if (!helpful) append([entry('bot', 'feedback_no')]);
    };
    const restart = () => { searchSeq.current++; setBusy(false); clearTranscript(session()); handled.current = null; const next = fresh(); scrollTo.current = next.entries[0].id; setState(next); };

    const menu = [product && 'product', 'search', (!product || product.hasSizes) && 'size', 'delivery', 'order', 'gift', 'personalize', 'care', 'payment'].filter(Boolean);
    const available = topic => topic === 'appointment' || menu.includes(topic);
    const latest = state.entries.at(-1);
    const availability = conciergeAvailability();
    const tiles = <div className="sf-assistant-tiles" role="group" aria-label={a.topics_label}>{menu.map(topic => <button type="button" key={topic} onClick={() => choose(topic)}><Icon name={ICONS[topic]} /><span>{label(topic)}</span></button>)}</div>;
    const facts = rows => <dl className="sf-assistant-facts">{rows.filter(([, value]) => value).map(([key, value]) => <div key={key}><dt>{key}</dt><dd>{value}</dd></div>)}</dl>;
    const link = (href, text) => <a className="text-link" href={href}>{text}<Icon name="arrow" /></a>;

    const body = item => {
        const params = item.params || {};
        if (item.from === 'user') return <p>{item.kind === 'topic' ? label(params.topic) : params.text}</p>;
        switch (item.kind) {
        case 'greeting': return <p>{user ? formatCopy(a.greeting_named, { name: user.name.split(/\s+/)[0] }) : a.greeting}</p>;
        case 'menu': return <p>{a.menu_intro}</p>;
        case 'context': return <p>{formatCopy(a.context, { name: params.name })}</p>;
        case 'ai_reply': return <>{params.mode === 'ai' && a.ai_badge && <span className="sf-assistant-ai-badge">{a.ai_badge}</span>}<p>{params.reply}</p>
            {Array.isArray(params.items) && params.items.length > 0 && <><ul className="sf-assistant-cards">{params.items.map(card => <Card key={card.id} item={card} a={a} p={p} f={f} onAdd={confirmAdd} />)}</ul><p className="sf-assistant-muted">{a.results_source}</p></>}</>;
        case 'product': return <><div className="sf-assistant-product">{params.image && <img src={params.image} alt="" width="72" height="90" loading="lazy" decoding="async" />}<div><p><strong>{params.name}</strong></p>{facts([[a.price, params.price], [f.quick_availability, params.available ? f.quick_available : p.sold_out], [p.color, params.color], [p.material, params.material], [p.composition, params.composition], [p.origin, params.origin]])}</div></div><p className="sf-assistant-muted">{a.product_source}</p>{link(params.href, a.product_open)}</>;
        case 'search_prompt': return <p>{a.search_prompt}</p>;
        case 'results': return <><p>{params.partial ? formatCopy(a.results_partial, { query: params.query }) : formatCopy(a.results_intro, { count: params.total, query: params.query })}</p>
            <ul className="sf-assistant-cards">{params.items.map(card => <Card key={card.id} item={card} a={a} p={p} f={f} onAdd={confirmAdd} />)}</ul>
            {params.total > params.items.length && link(params.catalog || links.catalog, formatCopy(a.results_more, { count: params.total }))}<p className="sf-assistant-muted">{a.results_source}</p></>;
        case 'results_empty': return <><p>{formatCopy(a.results_empty, { query: params.query })}</p>{link(params.catalog || links.catalog, a.open_catalog)}</>;
        case 'results_error': return <p>{a.results_error}</p>;
        case 'size': return params.applies === false ? <p>{a.size_not_applicable}</p> : <><p>{p.size_guide_note}</p><button type="button" className="text-link" onClick={() => open('size')}>{p.size_guide_open}<Icon name="arrow" /></button></>;
        case 'delivery': return <><p>{w.returns_policy}</p>{link(`${links.home}#returns-policy`, a.read_policy)}</>;
        case 'order': return params.signedIn
            ? <><p>{a.order_signed_in}</p>{link(`${links.account}#account-history`, a.order_history)}</>
            : <><p>{a.order_guest}</p>{link(`${links.shop}#pesanan`, a.order_tracking)}{link(links.login, a.order_login)}</>;
        case 'care': return params.care ? <><p>{formatCopy(a.care_product, { name: params.name })}</p>{facts([[a.care, params.care], [a.craft, params.craft]])}<p className="sf-assistant-muted">{a.care_help}</p></> : <><p>{w.service_3_desc}</p>{link(`${links.home}#services`, a.care_link)}</>;
        case 'gift': return <><p>{w.packaging_desc}</p><p className="sf-assistant-muted">{w.packaging_complimentary}</p>{link(`${links.home}#packaging-section`, a.gift_link)}</>;
        case 'personalize': return <><p>{w.atelier_desc}</p><p>{p.personalization}</p>{link(`${links.home}#atelier`, a.personalize_link)}</>;
        case 'payment': return <><p>{a.payment_answer}</p>{link(links.shop, a.payment_link)}</>;
        case 'cart_confirm': return <><p>{formatCopy(a.cart_confirm, { name: params.item.name, price: params.item.priceLabel })}</p>
            {item.id === latest?.id && !params.resolved && <div className="sf-assistant-confirm"><button type="button" className="button button-sapphire" onClick={() => void addToBag(item)}>{a.cart_yes}<Icon name="bag" /></button><button type="button" className="text-link" onClick={() => append([entry('bot', 'cart_cancelled')], { resolve: true })}>{a.handoff_cancel}</button></div>}</>;
        case 'cart_added': return <><p>{formatCopy(a.cart_added, { name: params.item.name })}</p>{!params.undone && <div className="sf-assistant-confirm"><button type="button" className="text-link" onClick={() => void undoAdd(item)}>{a.cart_undo}</button><button type="button" className="text-link" onClick={() => open('cart')}>{a.cart_open}<Icon name="arrow" /></button></div>}</>;
        case 'cart_undone': return <p>{formatCopy(a.cart_undone, { name: params.item.name })}</p>;
        case 'cart_cancelled': return <p>{a.handoff_cancelled}</p>;
        case 'cart_failed': return <p>{a.cart_failed}</p>;
        case 'handoff': return <><p>{a[`handoff_${params.reason}`] || a.handoff_human}</p><p className="sf-assistant-muted">{formatCopy(a.handoff_hours, { hours: f.hours })}</p>
            <figure className="sf-assistant-notes"><figcaption>{a.handoff_preview}</figcaption><p>{params.notes}</p></figure>
            {item.id === [...state.entries].reverse().find(current => current.kind === 'handoff')?.id && !params.resolved && <div className="sf-assistant-confirm"><button type="button" className="button button-sapphire" onClick={() => proceed(item)}>{a.handoff_continue}<Icon name="arrow" /></button><button type="button" className="text-link" onClick={() => append([entry('bot', 'handoff_cancelled')], { resolve: true })}>{a.handoff_cancel}</button></div>}</>;
        case 'handoff_cancelled': return <p>{a.handoff_cancelled}</p>;
        case 'fallback': return <p>{formatCopy(a.fallback, { text: params.text })}</p>;
        case 'feedback_no': return <p>{a.feedback_no}</p>;
        case 'sent': return <p>{formatCopy(a.sent, { reference: params.reference, hours: f.hours })}</p>;
        default: return null;
        }
    };
    const after = item => {
        if (item.id !== latest?.id || item.from !== 'bot') return null;
        const next = (FOLLOW[item.kind] || []).filter(available);
        return <>
            {FEEDBACK.includes(item.kind) && <div className="sf-assistant-feedback">{item.params?.vote ? <span role="status">{a.feedback_thanks}</span> : <><span>{a.feedback_question}</span><button type="button" onClick={() => vote(item, true)}>{a.feedback_yes}</button><button type="button" onClick={() => vote(item, false)}>{a.feedback_no_button}</button></>}</div>}
            {next.length > 0 && <div className="sf-assistant-follow" role="group" aria-label={a.follow_label}>{next.map(topic => <button type="button" key={topic} onClick={() => choose(topic)}><Icon name={ICONS[topic]} />{label(topic)}</button>)}</div>}
        </>;
    };

    return <Dialog type="assistant" title={a.title} className="drawer sf-assistant">
        <div className="drawer-header">
            <div className="sf-assistant-heading">
                <div>
                    {a.eyebrow && <span className="sf-assistant-eyebrow">{a.eyebrow}</span>}
                    <h3>{a.title}</h3>
                    <p className={`sf-assistant-status${availability.open ? ' is-open' : ''}`}>
                        <i className="sf-assistant-live-dot" aria-hidden="true" />
                        <span>{availability.open ? a.status_open : a[`status_next_${availability.next}`]}</span>
                    </p>
                </div>
            </div>
            <div className="sf-assistant-head-tools">
                <button type="button" className="icon-button sf-assistant-reset" title={a.restart} aria-label={a.restart} onClick={restart}><Icon name="refresh" /><span className="sr-only">{a.restart}</span></button>
                <CloseButton className="icon-button" label={a.close} />
            </div>
        </div>
        <div className="sf-assistant-log" ref={logRef} role="log" aria-label={a.log_label} tabIndex={0}>
            {state.entries.map(item => <div key={item.id} data-entry={item.id} className={`sf-assistant-row is-${item.from}`}>
                <div className="sf-assistant-col">
                    <div className={`sf-assistant-entry is-${item.from}`}><span className="sr-only">{item.from === 'user' ? a.you : a.bot}: </span>{body(item)}
                        {item.at && <time className="sf-assistant-time" dateTime={new Date(item.at).toISOString()}>{time(item.at)}</time>}</div>
                    {(item.kind === 'greeting' || item.kind === 'menu') && tiles}
                    {after(item)}
                </div>
            </div>)}
        </div>
        <p className={`sf-assistant-busy${busy ? '' : ' is-idle'}`} role="status">{busy ? a.searching : ''}</p>
        <div className="sf-assistant-actions">
            <form className="sf-assistant-form" onSubmit={ask}><label htmlFor="sf-assistant-input" className="sr-only">{state.mode === 'search' ? a.search_label : a.input_label}</label><div className="sf-assistant-bar">
                <button type="button" className="sf-assistant-menu" aria-label={a.menu} title={a.menu} onClick={() => append([entry('bot', 'menu')], { resolve: true, mode: null })}><Icon name="grid" /></button>
                <input id="sf-assistant-input" data-initial-focus ref={inputRef} type="text" maxLength={200} autoComplete="off" enterKeyHint="send" value={text} placeholder={state.mode === 'search' ? a.search_placeholder : a.input_placeholder} onChange={event => setText(event.target.value)} />
                <button type="submit" className="sf-assistant-send" disabled={!text.trim() || busy} aria-label={a.send} title={a.send}><Icon name="send" /><span className="sr-only">{a.send}</span></button></div></form>
            <div className="sf-assistant-footer">
                <button type="button" className="sf-assistant-human" onClick={() => append([entry('user', 'topic', { topic: 'human' }), handoff('human')], { failures: 0, resolve: true, mode: null })}><span>{a.human}</span><Icon name="arrow" /></button>
                <p className="sf-assistant-meta"><span className="sf-assistant-disclosure">{a.disclosure}</span> <span>{a.privacy_short}</span></p>
            </div>
        </div>
    </Dialog>;
}
