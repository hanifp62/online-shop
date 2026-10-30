import { isOneEditOrTransposition } from '../search-match.js';

// FR-402: deterministic guided assistant. No model; intents are keyword lists.
// v2: entries carry timestamps and the richer answer kinds of 2026-10-30.
export const ASSISTANT_STORAGE_KEY = 'zafira-assistant:v2';
export const ASSISTANT_MAX_ENTRIES = 60;
export const NOTES_LIMIT = 1000;

// Order is precedence: a damaged-item message that also says "pesanan" must reach a person.
const INTENTS = [
    ['issue', ['rusak', 'cacat', 'sobek', 'robek', 'salah', 'keliru', 'komplain', 'keluhan', 'tagihan', 'damaged', 'defect', 'defective', 'broken', 'wrong', 'complaint', 'billing', 'charged']],
    ['human', ['manusia', 'staf', 'staff', 'advisor', 'concierge', 'cs', 'admin', 'orang', 'human', 'agent', 'agen', 'operator', 'hubungi', 'telepon', 'whatsapp', 'wa', 'contact', 'person']],
    ['order', ['pesanan', 'order', 'orders', 'lacak', 'track', 'tracking', 'resi', 'paket', 'invoice']],
    ['payment', ['bayar', 'pembayaran', 'membayar', 'payment', 'pay', 'transfer', 'kartu', 'card', 'cicilan', 'paylater', 'cod', 'installment']],
    ['appointment', ['janji', 'temu', 'appointment', 'booking', 'book', 'fitting', 'jadwal', 'schedule', 'konsultasi', 'consultation', 'rendezvous', 'styling', 'butik', 'boutique']],
    ['delivery', ['kirim', 'pengiriman', 'dikirim', 'ongkir', 'ongkos', 'shipping', 'delivery', 'retur', 'return', 'returns', 'tukar', 'exchange', 'refund', 'pengembalian', 'kembalikan']],
    ['personalize', ['monogram', 'inisial', 'initial', 'initials', 'personalisasi', 'personalization', 'personalise', 'custom', 'bordir', 'grafir', 'engraving', 'atelier']],
    ['gift', ['hadiah', 'kado', 'gift', 'gifting', 'kemasan', 'packaging', 'bungkus', 'wrap', 'wrapping', 'pita', 'ribbon']],
    ['care', ['perawatan', 'care', 'cuci', 'mencuci', 'wash', 'rawat', 'merawat', 'laundry', 'setrika', 'iron', 'simpan', 'menyimpan', 'storage']],
    ['size', ['ukuran', 'size', 'sizes', 'sizing', 'fit', 'muat', 'lingkar', 'measurement']],
    ['product', ['bahan', 'material', 'kain', 'komposisi', 'composition', 'asal', 'origin', 'buatan', 'made', 'harga', 'price', 'stok', 'stock', 'tersedia', 'available', 'ready', 'warna', 'color', 'colour']],
    ['search', ['cari', 'carikan', 'mencari', 'rekomendasi', 'rekomendasikan', 'recommend', 'looking', 'find', 'search', 'koleksi', 'collection']],
];

export function normalizeText(value) {
    return String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('id');
}

// Everyday Indonesian adds -nya ("bahannya", "harganya"); the stem is matched too.
const tokens = value => normalizeText(value).split(/[^\p{L}\p{N}]+/u).filter(Boolean)
    .flatMap(word => word.length > 5 && word.endsWith('nya') ? [word, word.slice(0, -3)] : [word]);
// Short keywords (cs, wa, fit) are exact only; a one-edit typo on them would match unrelated words.
const matches = (token, keyword) => token === keyword || (keyword.length >= 5 && token.length >= 4 && isOneEditOrTransposition(token, keyword));

export function matchIntent(text) {
    const words = tokens(text);
    if (!words.length) return null;
    for (const [intent, keywords] of INTENTS) {
        if (words.some(word => keywords.some(keyword => matches(word, keyword)))) return intent;
    }
    return null;
}

// Mirrors shop_ui.footer.hours ("Senin–Sabtu · 10.00–19.00 WIB"); change both together.
export const CONCIERGE_HOURS = { timeZone: 'Asia/Jakarta', days: [1, 2, 3, 4, 5, 6], open: 10, close: 19 };

export function conciergeAvailability(now = new Date(), hours = CONCIERGE_HOURS) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', { timeZone: hours.timeZone, weekday: 'short', hour: 'numeric', hourCycle: 'h23' })
        .formatToParts(now).map(part => [part.type, part.value]));
    const day = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday);
    const hour = Number(parts.hour);
    const open = hours.days.includes(day) && hour >= hours.open && hour < hours.close;
    if (open) return { open: true, next: null };
    if (hours.days.includes(day) && hour < hours.open) return { open: false, next: 'today' };
    const tomorrow = (day + 1) % 7;
    return { open: false, next: hours.days.includes(tomorrow) ? 'tomorrow' : 'later' };
}

export function readTranscript(storage) {
    try {
        const saved = JSON.parse(storage?.getItem(ASSISTANT_STORAGE_KEY) ?? 'null');
        if (!saved || !Array.isArray(saved.entries)) return null;
        const entries = saved.entries.filter(entry => entry && typeof entry.id === 'string' && ['bot', 'user'].includes(entry.from) && typeof entry.kind === 'string' && entry.params && typeof entry.params === 'object').slice(-ASSISTANT_MAX_ENTRIES);
        return { entries, failures: Number.isInteger(saved.failures) ? saved.failures : 0, mode: saved.mode === 'search' ? 'search' : null };
    } catch {
        return null;
    }
}

export function writeTranscript(storage, state) {
    try {
        storage?.setItem(ASSISTANT_STORAGE_KEY, JSON.stringify({ entries: state.entries.slice(-ASSISTANT_MAX_ENTRIES), failures: state.failures, mode: state.mode }));
    } catch { /* The conversation stays usable without storage. */ }
}

export function clearTranscript(storage) {
    try { storage?.removeItem(ASSISTANT_STORAGE_KEY); } catch { /* Nothing to clear. */ }
}

// WCAG 3.3.7: the handoff carries what the client already told the assistant.
export function conciergeNotes({ heading, labels, product, page, topic, questions = [] }) {
    const lines = [`[${heading}]`];
    if (product) lines.push(`${labels.product}: ${product.name} (${product.href})`);
    else if (page) lines.push(`${labels.page}: ${page}`);
    if (topic) lines.push(`${labels.topic}: ${topic}`);
    const asked = questions.map(question => String(question).trim()).filter(Boolean).slice(-3);
    if (asked.length) lines.push(`${labels.questions}:`, ...asked.map(question => `- ${question}`));
    const notes = lines.join('\n');
    return notes.length > NOTES_LIMIT ? `${notes.slice(0, NOTES_LIMIT - 1)}…` : notes;
}

// Accept only the fields the cards render; the endpoint is ours but the shape is still checked.
export function acceptSearchResult(data) {
    if (!data || !Array.isArray(data.items) || !Number.isInteger(data.total)) throw Object.assign(new Error('invalid'), { kind: 'invalid' });
    const items = data.items.filter(item => item && typeof item.name === 'string' && typeof item.href === 'string' && Number.isInteger(item.productId)).slice(0, 4)
        .map(item => ({ id: String(item.id), productId: item.productId, name: item.name, category: String(item.category ?? ''), priceLabel: String(item.priceLabel ?? ''), available: item.available === true, image: typeof item.image === 'string' ? item.image : null, alt: String(item.alt ?? item.name), href: item.href, hasSizes: item.hasSizes === true }));
    return { items, total: data.total, partial: data.partial === true, catalog: typeof data.catalog === 'string' ? data.catalog : null };
}
