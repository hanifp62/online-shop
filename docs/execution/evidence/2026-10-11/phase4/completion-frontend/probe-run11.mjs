import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const page = await browser.newPage({ viewport: { width: 1024, height: 768 } });
await page.goto('http://127.0.0.1:8015/prototype/', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
console.log(JSON.stringify(await page.evaluate(() => {
  const i = document.querySelector('#sf-search-input'), cs = getComputedStyle(i), icon = document.querySelector('.search-icon');
  const c = document.createElement('canvas').getContext('2d'); c.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
  return { width: i.offsetWidth, padding: [cs.paddingLeft, cs.paddingRight], icon: icon && icon.getBoundingClientRect().right - i.getBoundingClientRect().left,
    texts: Object.fromEntries(['Chercher…', 'Search…', 'Cari…', 'Cerca…', 'Cerca'].map(t => [t, c.measureText(t).width])) };
})));
const pdp = await browser.newPage();
await pdp.goto('http://127.0.0.1:8015/produk/celeste-trench', { waitUntil: 'networkidle' });
console.log(JSON.stringify(await pdp.evaluate(() => ({ status: document.title, coreScripts: [...document.scripts].map(s => s.src).filter(Boolean), hasCore: typeof window.ZafiraCore !== 'undefined' || [...document.scripts].some(s => s.src.includes('zafira-core')) }))));
await browser.close();
