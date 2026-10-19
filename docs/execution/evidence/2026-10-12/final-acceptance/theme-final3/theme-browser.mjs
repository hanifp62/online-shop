import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
let cases = 0;
try {
  for (const surface of ['/', '/prototype/']) for (const mode of ['light', 'dark', 'system']) for (const osDark of [false, true]) {
    const context = await browser.newContext({ colorScheme: osDark ? 'dark' : 'light' });
    try {
      await context.addInitScript(({ mode }) => {
        localStorage.setItem('zafira_theme', mode);
        localStorage.setItem('zafira_lang', 'fr');
        // Old keys must not override current choices.
        localStorage.setItem('zafira-theme', mode === 'dark' ? 'light' : 'dark');
        localStorage.setItem('zafira-lang', 'en');
      }, { mode });
      const page = await context.newPage();
      await page.goto('http://127.0.0.1:8015' + surface, { waitUntil: 'load' });
      const inspect = () => page.evaluate(() => ({
        theme: document.documentElement.getAttribute('data-theme'),
        setting: document.documentElement.getAttribute('data-theme-setting'),
        lang: document.documentElement.lang,
        stored: localStorage.getItem('zafira_theme'),
        locale: localStorage.getItem('zafira_lang'),
      }));
      const expected = mode === 'system' ? (osDark ? 'dark' : 'light') : mode;
      assert.deepEqual(await inspect(), { theme: expected, setting: mode, lang: 'fr', stored: mode, locale: 'fr' }, `${surface} ${mode} OS dark=${osDark}`);
      const flipped = !osDark;
      await page.emulateMedia({ colorScheme: flipped ? 'dark' : 'light' });
      await page.waitForFunction(expected => document.documentElement.getAttribute('data-theme') === expected, mode === 'system' ? (flipped ? 'dark' : 'light') : mode);
      assert.equal((await inspect()).theme, mode === 'system' ? (flipped ? 'dark' : 'light') : mode, 'OS change');
      await page.reload({ waitUntil: 'load' });
      assert.equal((await inspect()).theme, mode === 'system' ? (flipped ? 'dark' : 'light') : mode, 'reload');
      assert.equal((await inspect()).lang, 'fr', 'locale reload');
      cases++;
    } finally { await context.close(); }
  }
  console.log(JSON.stringify({ cases, passed: cases }));
} finally { await browser.close(); }
