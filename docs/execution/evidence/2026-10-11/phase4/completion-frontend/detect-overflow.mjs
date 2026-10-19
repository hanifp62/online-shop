// Locate the real root-scroll cause: an element overflowing the viewport whose
// every ancestor allows visible overflow (clipped ancestors cannot widen the page).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const lang = process.argv[2] || 'en';
const width = Number(process.argv[3] || 320);
const ctx = await browser.newContext({ viewport: { width, height: 568 } });
await ctx.addInitScript(l => localStorage.setItem('zafira_lang', l), lang);
const page = await ctx.newPage();
await page.goto(process.env.URL || 'http://127.0.0.1:8015/', { waitUntil: 'networkidle' });
console.log(JSON.stringify(await page.evaluate(() => {
  const de = document.documentElement, cw = de.clientWidth, out = [];
  const clipped = el => {
    for (let p = el.parentElement; p && p !== de; p = p.parentElement) {
      const s = getComputedStyle(p);
      if (s.overflowX !== 'visible' || s.overflow !== 'visible') return p.tagName + '.' + p.className?.toString().slice(0, 40);
    }
    return null;
  };
  document.querySelectorAll('*').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.right > cw + 1 && !clipped(el)) {
      out.push({ tag: el.tagName, id: el.id, class: el.className?.toString().slice(0, 60), right: +r.right.toFixed(1), width: +r.width.toFixed(1), text: el.textContent?.trim().slice(0, 40), parent: el.parentElement?.tagName + '.' + el.parentElement?.className?.toString().slice(0, 40) });
    }
  });
  return { cw, scrollWidth: de.scrollWidth, unclippedOffenders: out.slice(0, 12) };
}), null, 2));
await browser.close();
