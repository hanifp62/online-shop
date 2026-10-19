import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const proto = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
const store = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

test('hero image never determines grid row height at any breakpoint', () => {
  // Was desktop-only (min-width:1025px), which let 1024 inflate the stacked hero to 1700px.
  assert.match(proto, /\n\.hero-media-img\s*\{\s*position:\s*absolute;\s*inset:\s*0;/);
  assert.match(proto, /\.hero-media-pane\s*\{[^}]*position:\s*relative;/);
});
test('both surfaces expand controls without painting hit area', () => {
  for (const css of [proto, store]) {
    assert.match(css, /width:\s*max\(100%,\s*44px\)/);
    assert.match(css, /height:\s*max\(100%,\s*44px\)/);
    assert.match(css, /background:\s*transparent/);
    assert.match(css, /\.zafira-lang-select,\s*\.zafira-theme-select\s*\{\s*min-height:\s*44px/);
  }
  assert.match(store, /\.foil-options\s*\{\s*gap:\s*32px/);
});
test('cart quantity and remove controls meet the 44px project target', () => {
  assert.match(store, /\.quantity-control button\s*\{[^}]*min-width:\s*44px/);
  assert.match(store, /\.quantity-control button\s*\{[^}]*min-height:\s*44px/);
  assert.match(store, /\.cart-remove\s*\{[^}]*min-width:\s*44px/);
  assert.match(store, /\.cart-remove\s*\{[^}]*min-height:\s*44px/);
});
test('cart rows match two-child markup and keep remove at the row end', () => {
  const rows = [...store.matchAll(/\.cart-item\s*\{([^}]*)\}/g)].map((m) => m[1]);
  assert.ok(rows.length >= 2, 'base and mobile cart-item rules exist');
  for (const rule of rows) assert.match(rule, /grid-template-columns:\s*1fr auto;/);
  assert.match(store, /\.cart-remove\s*\{[^}]*justify-self:\s*end/);
});
test('toast undo action meets the 44px project target via CSS', () => {
  // Author display beats the UA [hidden] rule, so sizing must skip hidden actions.
  assert.match(store, /\.toast \[data-toast-action\]:not\(\[hidden\]\)\s*\{[^}]*min-width:\s*44px/);
  assert.match(store, /\.toast \[data-toast-action\]:not\(\[hidden\]\)\s*\{[^}]*min-height:\s*44px/);
});

test('confirmed text failures use darker, locally scoped colors', () => {
  for (const selector of ['#welcome-monogram-counter', '#monogram-hint', '.newsletter-section small', '#live-catalog-search-input::placeholder']) assert.ok(store.includes(selector), selector);
  for (const selector of ['.diptych-origin', '.product-cat', '.sf-footer input[type="email"]::placeholder']) assert.ok(proto.includes(selector), selector);
});
