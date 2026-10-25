import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
const core = readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8');
const app = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');

test('profile navigation uses server-localized account/login labels without client copy replacement', () => {
  assert.match(welcome, /href="\{\{ route\('account.show'\) \}\}">\{\{ __\('shop_ui.header.auth_profile'\) \}\}/);
  assert.match(welcome, /href="\{\{ route\('login'\) \}\}">\{\{ __\('shop_ui.header.login_aria'\) \}\}/);
  assert.doesNotMatch(welcome, /data-i18n="profile_full_portal"/);
  // The legacy dictionary remains compatible, but no longer overwrites the profile navigation.
  const context = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
  runInNewContext(core, context);
  const labels = {
    id: 'Buka Ruang Klien →',
    en: 'Open Client Room →',
    fr: "Ouvrir l'Espace Client →",
    it: 'Apri Area Clienti →',
    zh: '打开客户空间 →',
  };
  for (const [language, expected] of Object.entries(labels)) {
    assert.equal(context.window.Zafira.I18N[language].profile_full_portal, expected);
  }
});

test('personalization toast uses the catalog-purchase term', () => {
  assert.match(app, /Ukuran dan monogram dikirim sebagai permintaan concierge, bukan pembelian katalog\./);
  assert.doesNotMatch(app, /pembelian standar/i);
});
