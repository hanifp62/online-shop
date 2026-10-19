// Dark-theme contrast: text vs nearest opaque ancestor background. Skips text over images/gradients (reported as unmeasured).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const rows = [];
for (const [surface, width] of [['/', 390], ['/', 1366], ['/prototype/', 390], ['/prototype/', 1366]]) {
  const page = await browser.newPage({ viewport: { width, height: 844 }, reducedMotion: 'reduce', colorScheme: 'dark' });
  await page.addInitScript(() => { localStorage.setItem('zafira_theme', 'dark'); localStorage.setItem('zafira_v3_theme', 'dark'); });
  await page.goto('http://127.0.0.1:8015' + surface, { waitUntil: 'networkidle' });
  const r = await page.evaluate(() => {
    const rgba = s => { const m = s.match(/[\d.]+/g)?.map(Number) || [0, 0, 0, 0]; return [m[0], m[1], m[2], m[3] ?? 1]; };
    const lum = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
    const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)]; return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
    const fails = []; let measured = 0, unmeasured = 0;
    for (const el of document.querySelectorAll('body *')) {
      if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) continue;
      const s = getComputedStyle(el), b = el.getBoundingClientRect();
      if (!b.width || s.visibility === 'hidden' || s.display === 'none' || +s.opacity === 0 || el.closest('[aria-hidden="true"],[hidden],dialog:not([open])')) continue;
      let bg = null, n = el;
      for (; n; n = n.parentElement) { const c = getComputedStyle(n); if (c.backgroundImage !== 'none' || n.tagName === 'IMG' || n.querySelector(':scope > img,:scope > picture,:scope > video')) break; const v = rgba(c.backgroundColor); if (v[3] >= .95) { bg = v; break; } }
      if (!bg) { unmeasured++; continue; }
      const fg = rgba(s.color); if (fg[3] < 1) fg.splice(0, 3, ...fg.slice(0, 3).map((v, i) => v * fg[3] + bg[i] * (1 - fg[3])));
      const large = parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && +s.fontWeight >= 700);
      const value = ratio(fg, bg); measured++;
      if (value < (large ? 3 : 4.5)) fails.push({ tag: el.tagName, cls: String(el.className).slice(0, 50), text: el.textContent.trim().slice(0, 40), ratio: +value.toFixed(2), large });
    }
    return { theme: document.documentElement.dataset.theme || document.documentElement.className, measured, unmeasured, fails: fails.slice(0, 40), failCount: fails.length };
  });
  rows.push({ surface, width, ...r });
  await page.close();
}
await browser.close();
writeFileSync(new URL('dark-contrast.json', import.meta.url), JSON.stringify(rows, null, 2) + '\n');
console.log(JSON.stringify(rows.map(({ surface, width, theme, measured, unmeasured, failCount }) => ({ surface, width, theme, measured, unmeasured, failCount }))));
process.exitCode = rows.some(r => r.failCount) ? 1 : 0;
