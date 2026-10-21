import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const core = readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8');
const unbacked = /complimentary|cuma-cuma|gratis|offert|omaggio|免费|gracieusement|a vita|à vie|for life|sepanjang masa|generations|冷链|berpendingin|insured|assurée|worldwide|international|nel mondo/i;

test('client dictionary does not re-inject unbacked commercial claims', () => {
  const context = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
  runInNewContext(core, context);
  for (const [language, dict] of Object.entries(context.window.Zafira.I18N)) {
    for (const key of ['announcement', 'packaging_complimentary', 'service_3_title', 'service_3_desc', 'atelier_desc', 'atelier_step_3', 'heritage_2_desc', 'cart_service_note']) {
      if (dict[key] !== undefined) assert.doesNotMatch(dict[key], unbacked, `${language}.${key}`);
    }
  }
});
