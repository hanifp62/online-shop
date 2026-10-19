import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const proto = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
const store = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
test('stacked 1024 hero image does not contribute intrinsic height', () => {
  assert.match(proto, /\n\.hero-media-img \{\s*position: absolute;\s*inset: 0;/);
  assert.doesNotMatch(proto, /@media \(min-width: 1025px\) \{\s*\.hero-media-img/);
});
test('prototype view-mode hit area expands vertically only', () => {
  assert.match(proto, /\.view-mode-btn::before\s*\{\s*width: calc\(100% - 2px\);/);
});
test('storefront search hit area does not expand into brand', () => {
  assert.match(store, /\.site-header \[data-open-search\]::before\s*\{\s*width: 100%;[\s\S]*?height: max\(100%, 44px\);/);
});
test('theme controls retain nonoverlapping 24px targets with spacing exception', () => {
  assert.match(store, /\.announcement-bar \.theme-btn \{ min-width: 24px; min-height: 24px; \}/);
  assert.match(proto, /\.announcement \.theme-opt\s*\{[^}]*min-width: 24px;[^}]*min-height: 24px;/);
  assert.doesNotMatch(proto, /\.theme-opt::before/);
  assert.doesNotMatch(store, /\.theme-btn::before/);
});
