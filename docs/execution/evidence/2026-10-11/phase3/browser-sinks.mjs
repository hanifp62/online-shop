// Isolated browser DOM harness: real renderer source, synthetic storage, no app server or DB.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
// SOURCE=<file> lets the same harness run against a pre-fix copy to prove it fails (RED).
const app = readFileSync(process.env.SOURCE || 'prototype/app.js', 'utf8');
function fn(name) {
  const start = app.indexOf(`function ${name}(`);
  return start < 0 ? '' : app.slice(start, app.indexOf('\n}', start) + 2);
}
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const results = [];
try {
  for (const width of [320, 1440]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    await context.route('**/*', route => route.fulfill({ contentType: 'text/html', body: '<div id="catalog-content-area"></div><div id="product-studio-view"></div><div id="cart-items-container"></div><div id="cart-subtotal"></div>' }));
    const page = await context.newPage();
    await page.goto('http://synthetic.test');
    await page.addScriptTag({ content: ['escapeHtml', 'safeImageSrc', 'renderCatalog', 'renderProductCardHtml', 'openProductStudio', 'renderCartDrawer'].map(fn).join('\n') });
    const outcome = await page.evaluate(() => {
      globalThis.injected = 0;
      const payload = '<img src=x onerror="globalThis.injected=1">';
      const key = "FR 38');globalThis.injected=1;//";
      const p = { id: 901, cat_id: 1, title: payload, badge: payload, desc: payload, hero_quote: payload, material: payload, origin: payload, craft: payload, care: payload, formattedPrice: 'Rp1', price: 1, images: ['java\nscript:alert(1)', 'x" onerror="globalThis.injected=1'], measurements: { [key]: payload } };
      localStorage.setItem('zafira_v3_5_products', JSON.stringify([p, { ...p, id: 902 }]));
      globalThis.store = { products: JSON.parse(localStorage.getItem('zafira_v3_5_products')), cart: [], searchQuery: '', viewMode: 'editorial', getFilteredProducts() { return this.products; }, getProductLocalized: p => p, getCategoryTitle: () => payload };
      globalThis.switchView = () => {};
      globalThis.selectStudioSize = value => { globalThis.selected = value; };
      renderCatalog();
      openProductStudio(901);
      document.querySelector('.size-pill').click();
      const literal = document.querySelector('.studio-product-title').textContent === payload;
      const noHandlers = document.querySelectorAll('[onerror]').length === 0;
      const noActiveSrc = [...document.images].every(img => !/^(javascript|data|vbscript):/i.test(img.src));
      window.Zafira = { getLang: () => 'id', t: () => payload, formatPrice: () => 'Rp0' };
      renderCartDrawer();
      return { literal, noHandlers, noActiveSrc, sizeRoundTrip: globalThis.selected === key, emptyLiteral: document.getElementById('cart-items-container').textContent === payload, injected: globalThis.injected };
    });
    await page.waitForTimeout(100);
    outcome.injectedAfterEvents = await page.evaluate(() => globalThis.injected);
    for (const [name, value] of Object.entries(outcome)) {
      assert.equal(value, name.startsWith('injected') ? 0 : true, name);
      results.push({ width, name, pass: true });
    }
    await context.close();
  }
} finally {
  await browser.close();
  writeFileSync(new URL('browser-results.json', import.meta.url), JSON.stringify(results, null, 2));
}
console.log(`${results.length} assertions passed; isolated real-source DOM harness, no live DB`);
