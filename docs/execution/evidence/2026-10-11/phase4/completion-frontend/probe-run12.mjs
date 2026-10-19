import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
for (const [path, width, sel] of [['/', 1366, '.theme-btn'], ['/prototype/', 1366, '.theme-opt'], ['/', 390, '[data-open-search], .site-header .brand'], ['/prototype/', 390, '.view-mode-btn']]) {
  const page = await browser.newPage({ viewport: { width, height: 844 } });
  await page.goto('http://127.0.0.1:8015' + path, { waitUntil: 'networkidle' });
  console.log(path, width, JSON.stringify(await page.evaluate(sel => [...document.querySelectorAll(sel)].filter(e => e.offsetWidth).slice(0, 4).map(e => {
    const r = e.getBoundingClientRect(), b = getComputedStyle(e, '::before');
    return { cls: e.className, x: r.x, w: r.width, h: r.height, beforeContent: b.content, bw: b.width, bh: b.height, pos: getComputedStyle(e).position, parentGap: getComputedStyle(e.parentElement).gap };
  }), sel)));
  await page.close();
}
await browser.close();
