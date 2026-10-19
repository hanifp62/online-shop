import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const html = readFileSync(new URL('../prototype/index.html', import.meta.url), 'utf8');
const css = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
const core = readFileSync(new URL('../prototype/zafira-core.js', import.meta.url), 'utf8');
const app = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');

test('all click-driven navigation links are native keyboard links or buttons', () => {
  assert.doesNotMatch(html, /<a\b(?![^>]*\bhref=)[^>]*\bonclick=/g);
});

test('mobile drawer opener uses translated accessible name in all languages', () => {
  assert.match(html, /id="btn-mobile-nav-toggle"[^>]*data-i18n-aria-label="mobile_nav_open"/);
  for (const lang of ['id', 'en', 'fr', 'it']) {
    const dictionary = core.slice(core.indexOf(`    ${lang}: {`), core.indexOf('\n    },', core.indexOf(`    ${lang}: {`)));
    assert.match(dictionary, /mobile_nav_open:\s*"[^"]+"/);
  }
});

test('drawer open and close share a focus lifecycle and keyboard trap', () => {
  assert.match(app, /function openDrawer\(/);
  assert.match(app, /function closeDrawer\(/);
  assert.match(app, /e\.key === 'Tab'/);
  assert.match(app, /document\.activeElement/);
});

test('footer action links keep inherited colour after gaining href', () => {
  assert.match(css, /\.sf-footer a\s*\{[^}]*color:\s*inherit/);
});

test('cart, filter, search and dock controls meet internal 44px target', () => {
  for (const selector of ['.qty-btn', '.cat-pill', '.search-input', '.action-btn', '.dock-collapse-btn']) {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const rule = css.match(new RegExp(`${escaped}\\s*\\{([^}]*)\\}`));
    assert.ok(rule, `${selector} rule exists`);
    assert.match(rule[1], /min-height:\s*44px/, `${selector} min-height`);
  }
});
