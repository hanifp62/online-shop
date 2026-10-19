import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
const html = readFileSync(new URL('../prototype/index.html', import.meta.url), 'utf8');

test('F2: fixed prototype dock has viewport-safe scroll margins and remains available', () => {
  // The dock band removes the dock's strip from the scroller itself, so no scroll-padding-bottom is needed.
  assert.match(css, /html\s*\{[^}]*--dock-band:\s*\d+px;[^}]*overflow:\s*hidden;/s);
  assert.match(css, /body\s*\{[^}]*height:\s*calc\(100dvh - var\(--dock-band\)\);[^}]*overflow-y:\s*auto;[^}]*scroll-padding-top:\s*96px;/s);
  assert.match(css, /\.floating-luxury-dock\s*\{[^}]*position:\s*fixed;/s);
  // body owns scrolling: window.scrollY/window.scrollTo would read and move the wrong element.
  const js = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');
  assert.doesNotMatch(js, /window\.scroll(Y|To)\b/);
  assert.match(js, /document\.body\.scrollTo\(\{ top: 0/);
  assert.match(html, /<nav class="floating-luxury-dock"/);
  assert.match(html, /id="btn-view-storefront"/);
  assert.match(html, /id="btn-view-studio"/);
  assert.match(html, /id="btn-view-account"/);
  assert.match(html, /id="btn-view-admin"/);
});

test('F8: announcement copy wraps at desktop and mobile without clipping or ellipsis', () => {
  const desktop = css.match(/\.announcement-col-center\s*\{([^}]+)\}/)[1];
  const mobileBlock = css.match(/@media \(max-width: 900px\)\s*\{([\s\S]*?)\n\}/)[1];
  const mobile = mobileBlock.match(/\.announcement-col-center\s*\{([^}]+)\}/)[1];
  assert.match(desktop, /white-space:\s*normal;/);
  assert.match(desktop, /overflow:\s*visible;/);
  assert.match(mobile, /white-space:\s*normal;/);
  assert.doesNotMatch(mobile, /text-overflow:\s*ellipsis/);
  assert.doesNotMatch(mobile, /overflow:\s*hidden/);
});
