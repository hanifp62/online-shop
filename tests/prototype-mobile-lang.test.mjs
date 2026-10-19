import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const html = readFileSync(new URL('../prototype/index.html', import.meta.url), 'utf8');
const css = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
const core = readFileSync(new URL('../prototype/zafira-core.js', import.meta.url), 'utf8');

test('mobile nav drawer offers every language via the delegated data-set-lang handler', () => {
  const drawer = html.slice(html.indexOf('id="mobile-nav-drawer"'), html.indexOf('</aside>', html.indexOf('id="mobile-nav-drawer"')));
  const group = drawer.match(/<div class="mobile-lang-group" role="group" aria-labelledby="([^"]+)">([\s\S]*?)<\/div>/);
  assert.ok(group, 'drawer must contain a labelled language group');
  assert.match(drawer, new RegExp(`id="${group[1]}"[^>]*data-i18n="lang_selector"`));
  const langs = [...group[2].matchAll(/<button type="button"[^>]*data-set-lang="(\w+)"[^>]*aria-pressed="(true|false)"/g)].map(m => m[1]);
  assert.deepEqual(langs, ['id', 'en', 'fr', 'it']);
  assert.match(core, /closest\('\[data-set-lang\]'\)/, 'delegated handler must still exist');
});

test('mobile drawer strings carry data-i18n in all four dictionaries', () => {
  const drawer = html.slice(html.indexOf('id="mobile-nav-drawer"'), html.indexOf('</aside>', html.indexOf('id="mobile-nav-drawer"')));
  for (const key of ['mobile_nav_label', 'mobile_nav_heading', 'mobile_nav_close', 'mobile_currency_heading', 'mobile_currency_description']) {
    const attr = key === 'mobile_nav_label' || key === 'mobile_nav_close' ? 'data-i18n-aria-label' : 'data-i18n';
    assert.ok(drawer.includes(`${attr}="${key}"`), `drawer must reference ${key} via ${attr}`);
  }
  for (const lang of ['id', 'en', 'fr', 'it']) {
    const context = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
    runInNewContext(core, context);
    const dict = context.window.Zafira.I18N[lang];
    for (const key of ['mobile_nav_label', 'mobile_nav_heading', 'mobile_nav_close', 'mobile_currency_heading', 'mobile_currency_description']) {
      assert.ok(typeof dict[key] === 'string' && dict[key].length, `${lang} dictionary must define ${key}`);
    }
  }
});

test('mobile language buttons meet 44px touch target', () => {
  const rule = css.match(/\.mobile-lang-group \.lang-opt \{([^}]*)\}/);
  assert.ok(rule);
  assert.match(rule[1], /min-width:\s*44px/);
  assert.match(rule[1], /min-height:\s*44px/);
});


test('switching language translates drawer text and accessible labels', () => {
  const exported = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
  runInNewContext(core, exported);
  const dictionaries = exported.window.Zafira.I18N;
  for (const lang of ['id', 'en', 'fr', 'it']) {
    assert.ok(['mobile_nav_label', 'mobile_nav_heading', 'mobile_nav_close', 'mobile_currency_heading', 'mobile_currency_description'].every(key => typeof dictionaries[lang][key] === 'string' && dictionaries[lang][key].trim()), `${lang} must expose all drawer strings`);
  }

  const attributes = new Map();
  const elements = [...html.slice(html.lastIndexOf('<aside', html.indexOf('id="mobile-nav-drawer"')), html.indexOf('</aside>', html.indexOf('id="mobile-nav-drawer"'))).matchAll(/<([\w-]+)([^>]*data-i18n(?:-aria-label)?="[^"]+"[^>]*)>([^<]*)/g)].map(([, tag, attrs, text]) => ({
    textContent: text,
    setAttribute(name, value) { attributes.set(`${tag}:${name}`, value); },
    getAttribute(name) { return attrs.match(new RegExp(`${name}="([^"]+)"`))?.[1]; },
  }));
  const document = {
    documentElement: { setAttribute() {} },
    querySelectorAll(selector) {
      if (selector === '[data-i18n]') return elements.filter(el => el.getAttribute('data-i18n'));
      if (selector === '[data-i18n-placeholder]') return [];
      if (selector === '[data-i18n-title]') return [];
      if (selector === '[data-i18n-aria-label]') return elements.filter(el => el.getAttribute('data-i18n-aria-label'));
      if (selector === '.zafira-lang-select' || selector === '[data-set-lang]') return [];
      return [];
    },
    getElementById() { return null; },
  };
  const window = { dispatchEvent() {} };
  const localStorage = { setItem() {} };
  const applyStart = core.indexOf('  function applyLanguage(lang)');
  const applyEnd = core.indexOf('\n  }\n', applyStart) + 4;
  const script = core.slice(core.indexOf('  const I18N ='), core.indexOf('\n  };', core.indexOf('  const I18N =')) + 5)
    + 'const LANG_KEY = "zafira_lang"; function getTheme() { return "light"; }\n'
    + 'let languageSetting; function safeSet(key, value) { localStorage.setItem(key, value); }\n'
    + core.slice(applyStart, applyEnd) + '\napplyLanguage("fr");';
  runInNewContext(script, { document, window, localStorage, CustomEvent: function () {} });
  assert.equal(elements.find(el => el.getAttribute('data-i18n') === 'mobile_nav_heading').textContent, 'Navigation des créations');
  assert.equal(elements.find(el => el.getAttribute('data-i18n') === 'mobile_currency_heading').textContent, 'Devise');
  assert.equal(elements.find(el => el.getAttribute('data-i18n') === 'mobile_currency_description').textContent, 'Tous les prix sont affichés en IDR (Rp).');
  assert.equal(attributes.get('aside:aria-label'), 'Navigation mobile des créations');
  assert.equal(attributes.get('button:aria-label'), 'Fermer la navigation');
});
