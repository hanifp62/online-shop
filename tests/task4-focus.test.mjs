import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
function harness() {
    let focused;
    const doc = { activeElement: null };
    const node = () => {
        const classes = new Set();
        return { style: {}, attrs: {}, inert: false, isConnected: true,
            classList: { contains: c => classes.has(c), add: c => classes.add(c), remove: c => classes.delete(c), toggle(c, on) { on ? classes.add(c) : classes.delete(c); } },
            setAttribute(k, v) { this.attrs[k] = v; }, focus() { focused = this; doc.activeElement = this; },
        };
    };
    const layers = Array.from({ length: 8 }, node);
    layers.forEach(layer => {
        layer.button = node();
        layer.contains = element => element === layer || element === layer.button;
    });
    const trigger = node();
    const context = { cartDrawer: layers[0], searchPanel: layers[1], quickViewModal: layers[2], conciergeModal: layers[3], profileDrawer: layers[4], sizeGuideDrawer: layers[5], shortcutsModal: layers[6], mobileMenu: layers[7], overlay: node(), document: Object.assign(doc, { activeElement: trigger, body: node() }), window: {}, setTimeout: cb => cb(), lastFocusedElement: null,
        select: (_, layer) => layer ? layer.button : null,
        selectAll: (_, layer) => layer ? [layer.button] : [],
    };
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf("    const mobileMenu = select('.mobile-menu');") + "    const mobileMenu = select('.mobile-menu');".length, source.indexOf('    const productCards =')), context);
    vm.runInContext(source.slice(source.indexOf('    function syncOverlay()'), source.indexOf('    function updateCounts()')), context);
    return { ...context, context, layers, trigger, focus: () => focused, run: code => vm.runInContext(code, context) };
}

test('opening retries focus until target accepts it', () => {
    const h = harness();
    let attempts = 0;
    h.quickViewModal.button.focus = () => {
        attempts += 1;
        if (attempts === 3) h.document.activeElement = h.quickViewModal.button;
    };
    h.openElement(h.quickViewModal, h.trigger);
    assert.equal(attempts, 3);
    assert.equal(h.document.activeElement, h.quickViewModal.button);
});

test('delayed focus never replaces focus already moved inside dialog', () => {
    const h = harness();
    let delayed;
    h.context.setTimeout = (cb) => { delayed = cb; };
    h.openElement(h.quickViewModal, h.trigger);
    h.quickViewModal.contains = (element) => element === h.quickViewModal.button;
    h.quickViewModal.button.focus();
    delayed();
    assert.equal(h.document.activeElement, h.quickViewModal.button);
});

test('focus retries are bounded when target cannot accept focus', () => {
    const h = harness();
    let attempts = 0;
    h.quickViewModal.button.focus = () => { attempts += 1; };
    h.openElement(h.quickViewModal, h.trigger);
    assert.equal(attempts, 11);
});

test('focusable selectors exclude SVG use href', () => {
    assert.doesNotMatch(source, /'button(?::not\(\[disabled\]\))?, \[href\]/);
});

test('all overlays start inert and hidden', () => {
    const h = harness();
    for (const layer of h.layers.slice(0, 7)) {
        assert.equal(layer.inert, true);
        assert.equal(layer.attrs['aria-hidden'], 'true');
    }
    h.openElement(h.cartDrawer, h.trigger);
    assert.equal(h.cartDrawer.inert, false);
    assert.equal(h.cartDrawer.attrs['aria-hidden'], 'false');
});

test('every overlay focuses inside, Escape lifecycle closes all and returns to external trigger', () => {
    const h = harness();
    for (const layer of h.layers.slice(0, 7)) {
        h.openElement(layer, h.trigger);
        assert.equal(h.focus(), layer.button);
        assert.equal(layer.attrs['aria-hidden'], 'false');
        h.closeAll();
        assert.equal(layer.attrs['aria-hidden'], 'true');
        assert.equal(h.focus(), h.trigger);
        assert.equal(h.document.body.classList.contains('no-scroll'), false);
    }
});

test('closing unopened shortcuts never steals returned focus', () => {
    const h = harness();
    h.openShortcuts(h.trigger);
    h.closeShortcuts();
    const newTrigger = { focus() { h.trigger.focus(); } };
    h.openElement(h.cartDrawer, newTrigger);
    h.closeAll();
    assert.equal(h.focus(), h.trigger);
});

test('handoff from quick view preserves outside opener when all dialogs close', () => {
    const h = harness();
    h.openElement(h.quickViewModal, h.trigger);
    h.quickViewModal.contains = () => true;
    h.openElement(h.conciergeModal, h.quickViewModal.button);
    h.closeAll();
    assert.equal(h.focus(), h.trigger);
});
