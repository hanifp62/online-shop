import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
function harness() {
    let focused;
    const node = () => {
        const classes = new Set();
        return { style: {}, attrs: {}, inert: false, isConnected: true,
            classList: { contains: c => classes.has(c), add: c => classes.add(c), remove: c => classes.delete(c), toggle(c, on) { on ? classes.add(c) : classes.delete(c); } },
            setAttribute(k, v) { this.attrs[k] = v; }, focus() { focused = this; },
        };
    };
    const layers = Array.from({ length: 8 }, node);
    layers.forEach(layer => { layer.button = node(); });
    const trigger = node();
    const context = { cartDrawer: layers[0], searchPanel: layers[1], quickViewModal: layers[2], conciergeModal: layers[3], profileDrawer: layers[4], sizeGuideDrawer: layers[5], shortcutsModal: layers[6], mobileMenu: layers[7], overlay: node(), document: { activeElement: trigger, body: node() }, window: {}, lastFocusedElement: null,
        select: (_, layer) => layer ? layer.button : null,
        selectAll: (_, layer) => layer ? [layer.button] : [],
    };
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf("    const mobileMenu = select('.mobile-menu');") + "    const mobileMenu = select('.mobile-menu');".length, source.indexOf('    const productCards =')), context);
    vm.runInContext(source.slice(source.indexOf('    function syncOverlay()'), source.indexOf('    function updateCounts()')), context);
    return { ...context, layers, trigger, focus: () => focused, run: code => vm.runInContext(code, context) };
}

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
