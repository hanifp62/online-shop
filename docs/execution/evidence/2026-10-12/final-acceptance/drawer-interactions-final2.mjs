import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const label = process.argv[2] || 'run9';
const results = {};
const fresh = async (width = 1440) => {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto('http://127.0.0.1:8015/prototype/', { waitUntil: 'networkidle' });
  await page.evaluate(() => { localStorage.setItem('zafira_v3_5_cart', JSON.stringify([{ productId: 9, qty: 2, size: 'FR 38', monogram: 'AT' }])); });
  await page.reload({ waitUntil: 'networkidle' });
  return { ctx, page };
};
const inOpen = page => page.evaluate(() => !!document.querySelector('#cart-drawer.open, #profile-drawer.open, #mobile-nav-drawer.open')?.contains(document.activeElement));
const activeId = page => page.evaluate(() => document.activeElement?.id || document.activeElement?.className || document.activeElement?.tagName);

// 1. Repeated open with C restores the original header opener
{ const { ctx, page } = await fresh();
  await page.locator('#btn-header-profile').focus();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Escape');
  await page.locator('.sf-header .action-btn[onclick="openCartDrawer()"]').focus();
  await page.keyboard.press('Enter');
  await page.keyboard.press('c');
  const reopenStillOpen = await page.locator('#cart-drawer').evaluate(el => el.classList.contains('open'));
  await page.keyboard.press('Escape');
  results.repeatedOpen = { reopenStillOpen, restoredTo: await activeId(page), pass: await page.evaluate(() => document.activeElement.getAttribute('onclick') === 'openCartDrawer()') && await page.evaluate(() => document.activeElement.offsetParent !== null) };
  await ctx.close(); }

// 2. Switch cart -> profile via U keeps original opener
{ const { ctx, page } = await fresh();
  const opener = page.locator('.sf-header .action-btn[onclick="openCartDrawer()"]');
  await opener.focus(); await page.keyboard.press('Enter');
  await page.keyboard.press('u');
  const profileOpen = await page.locator('#profile-drawer').evaluate(el => el.classList.contains('open'));
  await page.keyboard.press('Escape');
  const backOnOpener = await opener.evaluate(el => document.activeElement === el);
  results.switchDrawer = { profileOpen, backOnOpener, pass: profileOpen && backOnOpener };
  await ctx.close(); }

// 3. Rerender removes focused qty button; next Tab must stay inside
{ const { ctx, page } = await fresh();
  await page.locator('.sf-header .action-btn[onclick="openCartDrawer()"]').click();
  const plus = page.locator('#cart-drawer .qty-btn').nth(1);
  await plus.focus(); await page.keyboard.press('Enter');
  const afterRender = await inOpen(page);
  await page.keyboard.press('Tab');
  const afterTab = await inOpen(page);
  results.rerender = { afterRender, afterTab, pass: afterTab };
  await ctx.close(); }

// 4. Slash shortcut must not move focus out of open drawer
{ const { ctx, page } = await fresh();
  await page.locator('.sf-header .action-btn[onclick="openCartDrawer()"]').click();
  await page.keyboard.press('/');
  const afterSlash = await inOpen(page);
  await page.keyboard.press('Tab');
  results.outsideShortcut = { afterSlash, afterTab: await inOpen(page), pass: afterSlash && await inOpen(page) };
  await ctx.close(); }

// 5. Programmatic outside focus is redirected back on Tab
{ const { ctx, page } = await fresh();
  await page.locator('.sf-header .action-btn[onclick="openCartDrawer()"]').click();
  await page.evaluate(() => document.getElementById('sf-search-input').focus());
  await page.keyboard.press('Tab');
  results.outsideFocus = { pass: await inOpen(page) };
  await ctx.close(); }

// 6. Endpoint wrapping both directions
{ const { ctx, page } = await fresh(390);
  await page.locator('#btn-mobile-nav-toggle').click();
  await page.keyboard.press('Shift+Tab');
  const backward = await inOpen(page);
  const lastIsActive = await page.evaluate(() => { const d = document.querySelector('#mobile-nav-drawer'); const f = [...d.querySelectorAll('a[href],button:not([disabled]),input,select,textarea')]; return f.at(-1) === document.activeElement; });
  await page.keyboard.press('Tab');
  const forward = await page.evaluate(() => document.activeElement === document.querySelector('#mobile-nav-drawer .btn-close'));
  results.endpointWrap = { backward, lastIsActive, forward, pass: backward && lastIsActive && forward };
  await ctx.close(); }

// 7. Escape and backdrop return focus to opener
{ const { ctx, page } = await fresh(390);
  const opener = page.locator('#btn-mobile-nav-toggle');
  await opener.click(); await page.keyboard.press('Escape');
  const esc = await opener.evaluate(el => document.activeElement === el);
  await opener.click(); await page.locator('#drawer-backdrop').click({ position: { x: 385, y: 500 }, force: true });
  const backdrop = await opener.evaluate(el => document.activeElement === el);
  results.escBackdrop = { esc, backdrop, pass: esc && backdrop };
  await ctx.close(); }

// 8. Contrast assertion from actual computed solid pair
{ const { ctx, page } = await fresh();
  const pair = await page.evaluate(() => { const s = getComputedStyle(document.querySelector('.cat-pill.active') || document.querySelector('.cat-pill')); return { color: s.color, background: s.backgroundColor, backgroundImage: s.backgroundImage, fontSize: s.fontSize, fontWeight: s.fontWeight }; });
  const rgb = v => v.match(/[\d.]+/g).slice(0, 3).map(Number);
  const lum = c => { const [r, g, b] = c.map(x => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const [a, b] = [lum(rgb(pair.color)), lum(rgb(pair.background))];
  const ratio = +((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)).toFixed(2);
  const solid = pair.backgroundImage === 'none' && !/rgba\([^)]*,\s*0(\.\d+)?\)/.test(pair.background);
  results.contrast = { selector: '.cat-pill.active', ...pair, ratio, threshold: 4.5, solid, pass: solid && ratio >= 4.5 };
  await ctx.close(); }

await browser.close();
const failures = Object.entries(results).filter(([, v]) => !v.pass).map(([k]) => k);
writeFileSync(new URL(`drawer-interactions-${label}-results.json`, import.meta.url), JSON.stringify({ results, failures }, null, 2) + '\n');
console.log(JSON.stringify({ failures, results }, null, 2));
process.exitCode = failures.length ? 1 : 0;
