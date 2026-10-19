// Usage: BASE=http://127.0.0.1:8015 node wax-seal-keyboard.mjs
// Native button must activate on click, Enter, Space without submitting a form. Exits 1 on failure.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const base = process.env.BASE;
if (!base) throw new Error('BASE required');
const b = await chromium.launch({ executablePath: exe });
const results = [];
for (const action of ['click', 'Enter', 'Space']) {
  const p = await b.newPage();
  await p.goto(base + '/', { waitUntil: 'networkidle' });
  const seal = p.locator('#welcome-wax-seal');
  await seal.scrollIntoViewIfNeeded();
  await seal.click({ trial: true });
  await seal.focus();
  // Smooth scrolling may still be animating; wait until scrollY is stable before baseline.
  await p.evaluate(() => new Promise((res) => { let last = -1, n = 0; const t = () => { n = scrollY === last ? n + 1 : 0; last = scrollY; n >= 5 ? res() : requestAnimationFrame(t); }; t(); }));
  const before = await p.evaluate(() => ({ url: location.href, scrollY }));
  const native = await seal.evaluate((el) => {
    // offsetWidth ignores the hover scale transform; checks the CSS box is preserved.
    return { tag: el.tagName, type: el.getAttribute('type'), form: el.form ? el.form.id || 'unnamed-form' : null,
             name: el.getAttribute('aria-label'), w: el.offsetWidth, h: el.offsetHeight,
             radius: getComputedStyle(el).borderRadius, bg: getComputedStyle(el).backgroundColor };
  });
  let submits = 0;
  await p.exposeFunction('__submitSeen', () => { submits += 1; });
  await p.evaluate(() => document.addEventListener('submit', () => window.__submitSeen(), true));
  if (action === 'click') await seal.click();
  else { await seal.focus(); await p.keyboard.press(action); }
  const shown = await p.waitForFunction(() => document.querySelector('.toast')?.classList.contains('show'), null, { timeout: 1500 }).then(() => true, () => false);
  const after = await p.evaluate(() => ({ url: location.href, scrollY }));
  const r = { action, toastShown: shown, native, submits, urlUnchanged: before.url === after.url, scrollDelta: after.scrollY - before.scrollY };
  r.pass = shown && native.tag === 'BUTTON' && native.type === 'button' && !!native.name && submits === 0
    && r.urlUnchanged && Math.abs(r.scrollDelta) <= 1 && native.w === 56 && native.h === 56;
  results.push(r);
  await p.close();
}
await b.close();
console.log(JSON.stringify(results));
process.exit(results.every(r => r.pass) ? 0 : 1);
