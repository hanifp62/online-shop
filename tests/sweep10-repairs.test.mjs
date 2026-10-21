import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read = path => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const proto = read('prototype/app.css');
const dictionaries = ['prototype/zafira-core.js', 'public/js/zafira-core.js'];

test('D2: each dictionary uses compact translated search prompts', () => {
  for (const path of dictionaries) {
    const values = [...read(path).matchAll(/search_placeholder: "([^"]+)"/g)].map(m => m[1]);
    assert.deepEqual(values, path.startsWith('public/') ? ['搜索…', 'Cari…', 'Search…', 'Chercher…', 'Cerca'] : ['Cari…', 'Search…', 'Chercher…', 'Cerca']);
    assert.ok(!values.includes('Soie…'), 'FR placeholder must be a search verb, not "Silk"');
  }
  // 1024 keeps its 100px input (wider overflows the header); compact strings must fit that.
  assert.match(proto, /@media \(max-width: 1024px\)[\s\S]*?\.search-input \{ width: 100px; padding: 8px 6px 8px 30px; \}/);
});
test('D3: desktop provenance gets explicit first-paint dock clearance', () => {
  assert.match(proto, /@media \(min-width: 1025px\)\s*\{\s*\.provenance-strip\s*\{\s*margin-top: 40px;/);
});
test('D4: footer legal text clears AA after alpha compositing', () => {
  const block = read('resources/css/app.css').match(/\.footer-bottom \{([^}]+)\}/)[1];
  const alpha = Number(block.match(/color: rgba\(255,255,255,([.\d]+)\)/)[1]);
  const luminance = rgb => rgb.map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4).reduce((n, v, i) => n + v * [.2126,.7152,.0722][i], 0);
  const bg = [24,25,22];
  assert.ok((luminance(bg.map(v => 255 * alpha + v * (1 - alpha))) + .05) / (luminance(bg) + .05) >= 4.5);
});
test('D5: Maison motto is localized, not repeated English', () => {
  for (const path of dictionaries) {
    const values = [...read(path).matchAll(/footer_motto:\s*["']([\s\S]*?)["']\s*,/g)].map(m => m[1]);
    const expectedCount = path.startsWith('public/') ? 5 : 4;
    assert.equal(values.length, expectedCount);
    assert.equal(new Set(values).size, expectedCount);
    if (path.startsWith('public/')) {
      assert.match(values[0], /巴黎/);
      assert.match(values[1], /Paris/);
      assert.match(values[3], /Paris/);
      assert.match(values[4], /Parigi/);
    } else {
      assert.match(values[0], /Paris/);
      assert.match(values[2], /Paris/);
      assert.match(values[3], /Parigi/);
    }
  }
});
test('D6: theme choices and language select accessible names use existing i18n', () => {
  for (const path of ['prototype/index.html', 'resources/views/welcome.blade.php']) {
    const html = read(path);
    for (const mode of ['light','dark','system']) {
      assert.match(html, new RegExp(`<option value="${mode}" data-i18n="theme_${mode}">`));
      for (const tag of html.matchAll(new RegExp(`<button[^>]*data-set-theme="${mode}"[^>]*>`, 'g'))) {
        assert.ok(tag[0].includes(`data-i18n-title="theme_${mode}"`));
        assert.ok(tag[0].includes(`data-i18n-aria-label="theme_${mode}"`));
      }
    }
    for (const tag of html.matchAll(/<select[^>]*class="[^"]*zafira-lang-select[^>]*>/g)) assert.ok(tag[0].includes('data-i18n-aria-label="lang_selector"'));
  }
  for (const path of dictionaries) assert.ok((/document\.querySelectorAll\((?:'|\")\[data-i18n-aria-label\](?:'|\")\)/).test(read(path)));
});
test('D7: medium desktop actions reserve header space without clipping', () => {
  assert.match(proto, /@media \(min-width: 1025px\) and \(max-width: 1400px\)\s*\{\s*\.sf-actions \{ gap: 8px; \}\s*\.sf-actions \.action-btn \{ padding-inline: 8px; \}/);
});
