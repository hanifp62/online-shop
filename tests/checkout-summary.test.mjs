import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// FR-034-07: both checkout paths keep an honest, persistent estimate.
const blade = readFileSync(new URL('../resources/views/shop/index.blade.php', import.meta.url), 'utf8');
const loadCart = blade.slice(blade.indexOf('    async function loadCart()'), blade.indexOf('    async function loadAddresses()'));
function node() {
    return { children: [], textContent: '', style: {}, append(...children) { this.children.push(...children); }, replaceChildren(...children) { this.children = children; }, setAttribute() {}, get text() { return [this.textContent, ...this.children.map(child => child.text)].join(' '); } };
}
async function render(loggedIn, response) {
    const ids = new Map();
    const $ = id => { if (!ids.has(id)) ids.set(id, node()); return ids.get(id); };
    $('checkout-summary').append($('checkout-estimate'), $('checkout-summary-content'));
    const text = (tag, value) => Object.assign(node(), { tag, textContent: value });
    const context = { $, text, loggedIn, cart: {}, pendingCheckout: null, document: { createElement: node }, money: n => `Rp${n}`, api: async () => { if (response instanceof Error) throw response; return response; } };
    vm.createContext(context);
    await vm.runInContext(loadCart.replace(/@json\(route\('[^']+'\)\)/g, '"/route"').replace(/@json\(url\('[^']+'\)\)/g, '"/url"') + '\nloadCart().catch(() => {});', context);
    return $;
}
for (const loggedIn of [false, true]) {
    const who = loggedIn ? 'account' : 'guest';
    const id = 'checkout-summary';
    test(`${who}: estimate includes quantities and literal hostile/Unicode product names`, async () => {
        const $ = await render(loggedIn, { items: [{ product_title: '<img onerror=alert(1)> Sutra 🌸', quantity: 2, unit_price: 50, subtotal: 100, available: true }], subtotal: 100 });
        assert.ok($(id).text.includes('<img onerror=alert(1)> Sutra 🌸 × 2'));
        assert.match($('checkout-estimate').text, /2 barang · Subtotal: Rp100/);
        assert.doesNotMatch($(id).text, /\bTotal:|Diskon: Rp0/);
    });
    test(`${who}: empty cart clears estimate`, async () => {
        const $ = await render(loggedIn, { items: [], subtotal: 0 });
        assert.match($('checkout-estimate').text, /0 barang · Subtotal: Rp0/);
    });
    test(`${who}: failed cart read clears summary and estimate`, async () => {
        const $ = await render(loggedIn, new Error('offline'));
        assert.match($(id).text, /Ringkasan tidak tersedia/);
        assert.match($('checkout-estimate').text, /Subtotal belum tersedia/);
    });
}
function focusCheckout({ mobile = true, fieldTop = 20 } = {}) {
    let listener, observed, clearance, scrolled;
    const field = { matches: () => true, getBoundingClientRect: () => ({ top: fieldTop }), scrollIntoView: options => { scrolled = options; } };
    const context = {
        $: id => id === 'checkout-summary'
            ? { offsetHeight: 80, getBoundingClientRect: () => ({ bottom: 80 }) }
            : { addEventListener: (name, fn) => { listener = fn; } },
        document: { documentElement: { style: { setProperty: (name, value) => { clearance = [name, value]; } } } },
        window: { ResizeObserver: true, matchMedia: () => ({ matches: mobile }) },
        ResizeObserver: class { constructor(fn) { observed = fn; } observe() {} },
    };
    vm.runInNewContext(blade.slice(blade.indexOf('    const checkoutSummary ='), blade.indexOf('    let cart =')), context);
    observed();
    listener({ target: field });
    return { clearance, scrolled };
}
test('mobile focus clears measured summary height, including reverse keyboard navigation', () => {
    const { clearance, scrolled } = focusCheckout();
    assert.deepEqual(clearance, ['--checkout-summary-clearance', '96px']);
    assert.equal(scrolled.block, 'start');
    assert.equal(scrolled.behavior, 'instant');
});
test('visible mobile field and desktop focus do not force scrolling', () => {
    assert.equal(focusCheckout({ fieldTop: 140 }).scrolled, undefined);
    assert.equal(focusCheckout({ mobile: false }).scrolled, undefined);
});
test('gold button hover and keyboard focus preserve AA text contrast', () => {
    const rule = blade.match(/button:hover,button:focus-visible,\.button:hover,\.button:focus-visible\{([^}]+)\}/)[1];
    assert.match(rule, /background:var\(--gold\)/);
    assert.match(rule, /color:var\(--sapphire\)/);
    const luminance = name => {
        const hex = blade.match(new RegExp(`--${name}:#([0-9a-f]{6})`))[1];
        const channels = hex.match(/../g).map(value => parseInt(value, 16) / 255)
            .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
        return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    };
    assert.ok((luminance('gold') + 0.05) / (luminance('sapphire') + 0.05) >= 4.5);
});
test('shared aside stays visible before both forms, sticky on desktop and above mobile forms without nested scrolling', () => {
    const checkout = blade.slice(blade.indexOf('<section class="panel" id="checkout"'), blade.indexOf('<div class="grid"><section class="panel" id="pesanan"'));
    assert.equal((checkout.match(/id="checkout-summary"/g) || []).length, 1);
    assert.match(checkout, /<aside[^>]*id="checkout-summary"[^>]*aria-labelledby="checkout-summary-heading"/);
    for (const id of ['checkout-form', 'guest-checkout-form']) {
        assert.ok(checkout.indexOf('id="checkout-summary"') < checkout.indexOf(`id="${id}"`));
    }
    assert.doesNotMatch(checkout, /<details|<summary/);
    assert.match(checkout, /Voucher belum diperhitungkan/);
    assert.match(blade, /\.checkout-summary\{[^}]*position:sticky/);
    const mobile = blade.slice(blade.indexOf('@media(max-width:760px)'), blade.indexOf('</style>'));
    assert.match(mobile, /\.checkout-summary\{[^}]*position:sticky[^}]*top:/);
    assert.match(mobile, /\.checkout-summary\{[^}]*grid-row:1/);
    assert.match(mobile, /\.checkout-fields\{[^}]*grid-row:2/);
    assert.doesNotMatch(mobile, /overflow-y:auto|height:calc|max-height:/);
    // Compact on mobile (heading + estimate only) so the sticky bar cannot cover the form.
    assert.match(mobile, /\.checkout-summary>:not\(h3\):not\(#checkout-estimate\)\{display:none\}/);
    // Focused/scrolled-to fields must clear the sticky bar.
    assert.match(mobile, /\.checkout-fields :is\(input,select,button\)\{scroll-margin-top:var\(--checkout-summary-clearance/);
    assert.match(blade, /new ResizeObserver\(updateSummaryClearance\)/);
});
