import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const root = dirname(fileURLToPath(import.meta.url));
const base = 'http://127.0.0.1:8015';
const results = [], failures = [];

// Theme controls use 24px targets, not imaginary 44px boxes. Test inside their actual boundaries.
async function themeChecks() {
  for (const [surface, sel] of [['/', '.announcement-bar .theme-btn'], ['/prototype/', '.announcement .theme-opt']]) {
    const ctx = await browser.newContext({ viewport: { width: 1366, height: 844 } });
    const page = await ctx.newPage();
    await page.goto(base + surface, { waitUntil: 'networkidle' });
    await page.locator(sel).first().scrollIntoViewIfNeeded();
    const rows = [];
    const n = await page.locator(sel).count();
    for (let i = 0; i < n; i++) {
      const loc = page.locator(sel).nth(i);
      await loc.scrollIntoViewIfNeeded();
      const theme = await loc.getAttribute('data-set-theme');
      if (!theme) continue;
      const b = await loc.boundingBox();
      const x = b.x + b.width / 2; // centre of the visible control
      const y = b.y + b.height / 2;
      // what is actually under that pointer before the click
      const under = await page.evaluate(([px, py]) => {
        const h = document.elementFromPoint(px, py);
        return h ? (h.getAttribute('data-set-theme') || h.className) : null;
      }, [x, y]);
      await page.mouse.click(x, y);
      await page.waitForTimeout(150);
      const applied = await page.evaluate(() => document.documentElement.dataset.theme || getComputedStyle(document.documentElement).getPropertyValue('--data-theme') || document.documentElement.getAttribute('data-theme'));
      const ok = applied === theme;
      rows.push({ i, theme, pointerUnder: under, applied, ok });
      if (!ok) failures.push({ surface, check: 'theme', i, theme, applied, under });
    }
    results.push({ surface, check: 'theme', rows });
    await ctx.close();
  }
}

// 2. View-mode segmented buttons (prototype): click 2px inside Editorial's right edge and 2px inside
//    Gallery's left edge; each click must activate its own button, never the neighbour.
async function viewModeChecks() {
  for (const width of [1366, 390]) {
    const ctx = await browser.newContext({ viewport: { width, height: 844 } });
    const page = await ctx.newPage();
    await page.goto(base + '/prototype/', { waitUntil: 'networkidle' });
    await page.locator('.view-mode-btn').first().scrollIntoViewIfNeeded();
    const rows = [];
    const els = await page.$$eval('.view-mode-btn', els => els.map(e => { const r = e.getBoundingClientRect(); return { mode: e.dataset.mode, x: r.x, right: r.right, y: r.y + r.height / 2 }; }));
    for (let i = 0; i < els.length; i++) {
      const e = els[i];
      // sample near both inner edges (2px inside) — must stay own
      for (const px of [e.x + 2, e.right - 2]) {
        const hitMode = await page.evaluate(([px, py]) => { const h = document.elementFromPoint(px, py); return h?.closest?.('.view-mode-btn')?.dataset.mode ?? null; }, [px, e.y]);
        rows.push({ i, mode: e.mode, px: +px.toFixed(1), hitMode, ok: hitMode === e.mode });
        if (hitMode !== e.mode) failures.push({ surface: '/prototype/', check: 'view-mode', width, i, mode: e.mode, px, hitMode });
      }
    }
    // click Editorial's right edge → Editorial stays active
    await page.locator('.view-mode-btn[data-mode="gallery"]').click().catch(() => {});
    await page.locator('.view-mode-btn[data-mode="editorial"]').click().catch(() => {});
    const active = await page.evaluate(() => document.querySelector('.view-mode-btn.active')?.dataset.mode);
    rows.push({ clickEditorialActive: active, ok: active === 'editorial' });
    if (active !== 'editorial') failures.push({ surface: '/prototype/', check: 'view-mode-click', width, active });
    results.push({ surface: '/prototype/', check: 'view-mode', width, rows });
    await ctx.close();
  }
}

// 3. Brand vs search at 390 (storefront): brand right edge resolves to the home link; search keeps 44px vertical.
async function brandSearchCheck() {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  const info = await page.evaluate(() => {
    const b = document.querySelector('.site-header .brand').getBoundingClientRect();
    const s = document.querySelector('.site-header [data-open-search]');
    const sr = s.getBoundingClientRect();
    const pe = getComputedStyle(s, '::before');
    const pts = [b.right - 1, b.right - 4, b.right - 12].map(x => { const h = document.elementFromPoint(x, b.y + b.height / 2); return { x: +x.toFixed(1), onBrand: !!h?.closest('.brand') }; });
    return { pts, beforeW: pe.width, beforeH: pe.height, searchRectW: sr.width };
  });
  const ok = info.pts.every(p => p.onBrand) && parseFloat(info.beforeH) >= 44 && parseFloat(info.beforeW) <= info.searchRectW + .5;
  results.push({ surface: '/', check: 'brand-search', width: 390, info, ok });
  if (!ok) failures.push({ surface: '/', check: 'brand-search', width: 390, info });
  await ctx.close();
}

await themeChecks();
await viewModeChecks();
await brandSearchCheck();
await browser.close();
writeFileSync(join(root, 'hit-collision-final3.json'), JSON.stringify({ results, failures }, null, 2) + '\n');
writeFileSync(join(root, 'hit-collision-final3-exitcode'), failures.length ? '1\n' : '0\n');
console.log(JSON.stringify({ checks: results.length, failures: failures.length, detail: failures }));
process.exitCode = failures.length ? 1 : 0;
