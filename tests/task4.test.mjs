import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const app = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
const product = readFileSync(new URL('../resources/views/products/show.blade.php', import.meta.url), 'utf8');
const footer = readFileSync(new URL('../resources/views/partials/product-footer.blade.php', import.meta.url), 'utf8');

test('storefront overlays include focus trap, opener return, and Escape close behavior', () => {
    assert.match(app, /function openElement\(element, trigger = document\.activeElement\)/);
    assert.match(app, /function closeElement\(element, restoreFocus = true\)/);
    assert.match(app, /element\.setAttribute\((['"])aria-hidden\1, \1false\1\)/);
    assert.match(app, /event\.key === (['"])Escape\1[\s\S]*?closeAll\(\)/);
    assert.match(app, /event\.key === (['"])Tab\1[\s\S]*?event\.preventDefault\(\)/);
});

test('search and catalog filters have accessible names and exposed state', () => {
    assert.match(welcome, /<form[^>]+role="search"[\s\S]*?<label[^>]+for="live-catalog-search-input"/);
    assert.match(welcome, /data-filter="Semua" aria-pressed="true"/);
    assert.match(welcome, /role="status" aria-live="polite"[\s\S]*?data-product-count/);
    assert.match(footer, /<form[^>]+role="search"[\s\S]*?<label[^>]+for="search-input-pdp"/);
});

test('PDP size guide is an accessible dialog and native alerts are absent', () => {
    assert.doesNotMatch(product, /alert\s*\(/);
    assert.match(product, /data-open-size-guide/);
    assert.match(product, /data-size-guide[^>]+role="dialog"[^>]+aria-modal="true"/);
    // PDP body carries .storefront so sizeGuideDrawer wiring inside if(storefront) is active.
    assert.match(product, /<body[^>]+class="storefront/);
    assert.match(app, /selectAll\((['"])\[data-open-size-guide\]\1\)/);
});

test('keyboard help participates in accessible dialog flow', () => {
    assert.match(welcome, /id="welcome-shortcuts-backdrop"[^>]+role="dialog"[^>]+aria-modal="true"/);
    assert.match(welcome, /data-open-shortcuts/);
    assert.match(app, /shortcutsModal/);
});
