import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
assert.equal(process.env.BASE, 'http://127.0.0.1:8015');
const out = new URL(`./${process.argv[2] || 'layout-after'}/`, import.meta.url);
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const results = [];
try {
  for (const width of [320,390,768,820,821,900,901,1024,1366,1440,1650]) {
    for (const lang of ['id','en','fr','it']) {
      const page = await browser.newPage({ viewport: { width, height: width === 320 ? 568 : 844 } });
      await page.addInitScript(lang => localStorage.setItem('zafira_lang', lang), lang);
      await page.goto(process.env.BASE, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      const failures = [];
      const check = (condition, message) => { try { assert.ok(condition, message); } catch (e) { failures.push(e.message); } };
      const announcement = await page.evaluate(() => {
        const p = document.querySelector('.announcement-bar p');
        const controls = document.querySelector('.announcement-bar .desktop-only-flex');
        const a = p.getBoundingClientRect(), b = controls.getBoundingClientRect();
        const bar = document.querySelector('.announcement-bar').getBoundingClientRect();
        return { clipped: a.top < bar.top - 0.5 || a.bottom > bar.bottom + 0.5, overlap: b.width > 0 && a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom, lang: document.documentElement.lang };
      });
      check(!announcement.overlap, 'announcement overlaps controls');
      check(!announcement.clipped, 'announcement text exceeds bar height');
      check(announcement.lang === lang, 'language was not applied');
      check(await page.locator('.mobile-menu-button').isVisible() === (width <= 900), 'navigation breakpoint mismatch');
      if (width <= 900) {
        await page.locator('.mobile-menu-button').click();
        await page.waitForTimeout(500);
        const labels = await page.locator('.mobile-menu nav a > span[data-i18n]').evaluateAll(elements => elements.map(e => {
          const s = getComputedStyle(e), r = e.getBoundingClientRect();
          const range = document.createRange(); range.selectNodeContents(e);
          return { size: parseFloat(s.fontSize), italic: s.fontStyle, lines: range.getClientRects().length, right: r.right };
        }));
        check(labels.length === 6, 'six mobile labels required');
        check(labels.every(e => e.size >= 24 && e.italic === 'normal'), 'mobile labels inherit ordinal typography');
        check(labels.every(e => e.lines === 1 && e.right <= width), 'mobile labels wrap or overflow');
        await page.locator('.mobile-concierge').scrollIntoViewIfNeeded();
        check(await page.locator('.mobile-concierge').isVisible(), 'menu bottom action unreachable');
        await page.locator('[data-close-menu]').scrollIntoViewIfNeeded();
      }
      if ([320,390,901,1024].includes(width) && lang === 'id') await page.screenshot({ path: new URL(`home-${width}.png`, out).pathname });
      results.push({ width, lang, failures });
      await page.close();
    }
  }
} finally { await browser.close(); }
writeFileSync(new URL('results.json', out), JSON.stringify(results, null, 2));
for (const r of results) console.log(`${r.width} ${r.lang}: ${r.failures.length ? r.failures.join('; ') : 'PASS'}`);
assert.equal(results.filter(r => r.failures.length).length, 0, 'layout regression failures');
