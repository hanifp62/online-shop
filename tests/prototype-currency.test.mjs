import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const core = readFileSync(new URL('../prototype/zafira-core.js', import.meta.url), 'utf8');
const app = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');
const html = readFileSync(new URL('../prototype/index.html', import.meta.url), 'utf8');

// Evaluate the actual formatting function in isolation without booting a browser or reading user storage.
const body = core.slice(core.indexOf('  function formatPrice('), core.indexOf('  // --- 4. ATTACH LISTENERS'));
test('harga prototype selalu dalam IDR tanpa kurs simulasi', () => {
  const context = { Number };
  vm.runInNewContext(`${body}\nthis.formatPrice = formatPrice;`, context);
  for (const locale of ['id', 'en', 'fr', 'it', 'EUR', 'USD', null]) {
    assert.equal(context.formatPrice(17200000, locale), 'Rp 17.200.000');
  }
  assert.equal(context.formatPrice(0, 'USD'), 'Rp 0');
});

test('pemilihan bahasa tidak mengubah mata uang dan UI hanya menawarkan IDR', () => {
  assert.doesNotMatch(html, /data-curr="(EUR|USD)"|\((EUR|USD) [€$]\)/);
  assert.doesNotMatch(app, /store\.currency\s*=\s*['"](?:EUR|USD)['"]/);
  assert.doesNotMatch(app, /\b(?:17200|16200)\b/);
});
