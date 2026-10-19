// Inspect .products-heading flex layout: which child is pushed past the viewport at EN/320.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const [lang = 'en', width = '320'] = process.argv.slice(2);
const ctx = await browser.newContext({ viewport: { width: Number(width), height: 568 }, reducedMotion: 'reduce' });
await ctx.addInitScript(l => localStorage.setItem('zafira_lang', l), lang);
const page = await ctx.newPage();
await page.goto('http://127.0.0.1:8015/', { waitUntil: 'networkidle' });
await page.waitForTimeout(400);
console.log(JSON.stringify(await page.evaluate(() => {
  const de = document.documentElement;
  const box = el => { const r = el.getBoundingClientRect(), s = getComputedStyle(el); return { tag: el.tagName, cls: String(el.className).slice(0, 60), left: +r.left.toFixed(1), right: +r.right.toFixed(1), width: +r.width.toFixed(1), display: s.display, flex: s.flex, minWidth: s.minWidth, whiteSpace: s.whiteSpace, text: el.textContent.trim().replace(/\s+/g, ' ').slice(0, 50) }; };
  const heading = document.querySelector('#new-arrivals .products-heading');
  const h2 = heading.querySelector('h2');
  const range = document.createRange(); range.selectNodeContents(h2);
  return {
    viewport: de.clientWidth, scrollWidth: de.scrollWidth,
    heading: box(heading),
    children: [...heading.children].map(box),
    h2MinContent: +[...range.getClientRects()].reduce((m, r) => Math.max(m, r.width), 0).toFixed(1),
    h2Words: h2.textContent.trim().split(/\s+/),
    section: box(document.querySelector('#new-arrivals .section-shell')),
  };
}), null, 2));
await browser.close();
