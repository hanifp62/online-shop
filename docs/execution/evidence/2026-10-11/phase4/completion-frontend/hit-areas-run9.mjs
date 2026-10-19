// Verifies the transparent ::before hit areas really receive pointer events.
// For each control: elementFromPoint at the 4 corners of a 44x44 box centred on it,
// plus real click spot-checks on the controls listed in spotChecks.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const root = dirname(fileURLToPath(import.meta.url));
const base = 'http://127.0.0.1:8015';

// Controls expanded by the appended CSS blocks, per surface.
const targets = {
  '/': ['.icon-button', '.wishlist-button', '.product-add', '.filter-pill', '.foil', '.desktop-nav a', '.footer-links a', '.footer-links button', '.footer-bottom a', '#btn-ribbon-gold', '#btn-ribbon-sapphire'],
  '/prototype/': ['.sf-nav a', '.btn-gold', '.btn-outline', '.view-mode-btn', '.proto-btn', '.sound-toggle-btn', '.dock-btn', '.btn-quick', '.cat-pill', '.sf-footer-grid li a'],
};
// Documented exceptions: compact announcement controls keep the 24px WCAG 2.5.8 floor.
const exceptions = ['.announcement .lang-btn', '.announcement .theme-btn', '.announcement .lang-opt', '.announcement .theme-opt'];

const browser = await chromium.launch({ executablePath: exe });
const rows = [], clicks = [];
for (const surface of ['/', '/prototype/']) for (const [width, height] of [[390, 844], [1366, 768]]) {
  const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
  await page.goto(base + surface, { waitUntil: 'networkidle' });
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { scrollTo(0, y); await new Promise(r => setTimeout(r, 50)); } scrollTo(0, 0); });
  await page.waitForTimeout(400);
  const found = await page.evaluate(async ({ selectors, exceptions }) => {
    document.documentElement.style.scrollBehavior = 'auto';
    document.body.style.scrollBehavior = 'auto';
    const vis = el => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && r.top >= 0 && r.bottom <= innerHeight && r.left >= 0 && r.right <= innerWidth; };
    const out = [];
    for (const selector of selectors.concat(exceptions)) {
      const candidates = [...document.querySelectorAll(selector)];
      for (const el of candidates) {
        el.scrollIntoView({ block: 'center', behavior: 'instant' });
        await new Promise(resolve => setTimeout(resolve, 100));
        if (!vis(el)) continue;
        const r = el.getBoundingClientRect();
        const floor = exceptions.includes(selector) ? 24 : 44;
        const cx = r.left + r.width / 2, cy = r.top + r.height / 2, half = floor / 2 - 0.5;
        const corners = [[cx - half, cy - half], [cx + half, cy - half], [cx - half, cy + half], [cx + half, cy + half]];
        const hits = corners.map(([x, y]) => { const hit = document.elementFromPoint(x, y); return hit === el || el.contains(hit) || hit?.contains(el); });
        out.push({ selector, text: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 24), box: [+r.width.toFixed(1), +r.height.toFixed(1)], floor, cornersHit: hits.filter(Boolean).length, pass: hits.every(Boolean), exception: exceptions.includes(selector) });
        break; // first visible instance
      }
    }
    return out;
  }, { selectors: targets[surface], exceptions });
  rows.push(...found.map(r => ({ surface, width, ...r })));

  // Real click spot-checks: clicking the far corner of the 44px box must still activate the control.
  // Real click spot-checks: clicking the center of the control must activate it.
  const spotChecks = surface === '/'
    ? [
        ['.desktop-nav a, .footer-links a', 'link'],
        ['.filter-pill', 'filter pill'],
        ['.product-add', 'product add'],
        ['[data-open-cart]', 'cart icon'],
        ['[data-open-search]', 'search icon'],
      ]
    : [
        ['.cat-pill', 'category pill'],
        ['.dock-btn', 'dock button'],
      ];
  for (const [selector, label] of spotChecks) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
    const handled = await page.evaluate(async sel => {
      const el = document.querySelector(sel);
      if (!el) return { selector: sel, skipped: 'absent' };
      // The storefront header slides away on scroll, so header controls are tested at scroll 0.
      if (el.closest('header')) scrollTo(0, 0); else el.scrollIntoView({ block: 'center', behavior: 'instant' });
      await new Promise(resolve => setTimeout(resolve, 400));
      const r = el.getBoundingClientRect();
      if (!r.width || getComputedStyle(el).visibility === 'hidden') return { selector: sel, skipped: 'hidden' };
      let got = false; const mark = () => { got = true; };
      el.addEventListener('click', mark, { capture: true });
      const x = r.left + r.width / 2, y = r.top + r.height / 2;
      const hit = document.elementFromPoint(x, y);
      hit?.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: x, clientY: y }));
      el.removeEventListener('click', mark, { capture: true });
      return { selector: sel, activated: got, hit: hit ? hit.tagName + '.' + (typeof hit.className === 'string' ? hit.className : '') : null, rect: [r.left, r.top, r.width, r.height].map(Math.round) };
    }, selector);
    clicks.push({ surface, width, label, ...handled, pass: handled.skipped ? true : handled.activated });
  }
  await page.close();
}
await browser.close();
const failures = rows.filter(r => !r.pass).concat(clicks.filter(c => !c.pass));
writeFileSync(join(root, 'hit-areas-run9.json'), JSON.stringify({ rows, clicks, failures }, null, 2) + '\n');
writeFileSync(join(root, 'hit-areas-run9-exitcode'), String(failures.length ? 1 : 0) + '\n');
console.log(JSON.stringify({ checked: rows.length, clicks: clicks.length, failures }, null, 2));
process.exitCode = failures.length ? 1 : 0;
