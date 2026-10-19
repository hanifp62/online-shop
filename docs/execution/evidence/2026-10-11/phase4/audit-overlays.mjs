// Read-only overlay audit. Run: node audit-overlays.mjs <unique-label>
// Only the isolated port 8015 is permitted. Exit 1 on failed assertions, 2 on setup error.
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const root = dirname(fileURLToPath(import.meta.url));
const label = process.argv[2];
if (!label || !/^[a-z0-9-]+$/i.test(label) || existsSync(join(root, label))) {
  console.error('Provide a new alphanumeric run label; existing evidence cannot be overwritten.');
  process.exit(2);
}
const { chromium } = require(require('node:child_process').execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const executablePath = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const base = 'http://127.0.0.1:8015';
const out = join(root, label);
mkdirSync(out);
const report = { base, auth: 'guest', startedAt: new Date().toISOString(), assertions: { total: 0, passed: 0, failed: 0 }, cases: [], screenshots: [], errors: [], failedRequests: [] };
const specs = [
  { name: 'cart', path: '/', trigger: '[data-open-cart]', layer: '.cart-drawer', close: '[data-close-cart]' },
  { name: 'search', path: '/', trigger: '[data-open-search]', layer: '.search-panel', close: '[data-close-search]' },
  { name: 'quick-view', path: '/', trigger: '[data-product-card] [data-quick-view]', layer: '.quick-view-modal', close: '[data-close-modal]' },
  { name: 'concierge', path: '/', trigger: '[data-open-concierge]:not(.mobile-concierge)', layer: '.concierge-modal', close: '[data-close-modal]' },
  { name: 'profile', path: '/', trigger: '[data-open-profile]', layer: '#welcome-profile-drawer', close: '[data-close-profile]' },
  { name: 'size-guide', path: '/produk/celeste-trench', trigger: '[data-open-size-guide]', layer: '[data-size-guide]', close: '[data-close-size-guide]' },
  { name: 'shortcuts', path: '/', trigger: '[data-open-shortcuts]', layer: '#welcome-shortcuts-backdrop', close: '.modal-window [data-close-shortcuts]', backdrop: '#welcome-shortcuts-backdrop' },
];
const viewports = [{ width: 1440, height: 900 }, { width: 390, height: 844 }];
const focusables = 'button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
function assert(row, name, ok, detail = '') {
  row.assertions.push({ name, ok: Boolean(ok), detail: ok ? '' : String(detail).slice(0, 500) });
  report.assertions.total++;
  report.assertions[ok ? 'passed' : 'failed']++;
}
const browser = await chromium.launch({ executablePath });
try {
  for (const viewport of viewports) for (const spec of specs) {
    const ctx = await browser.newContext({ viewport, reducedMotion: 'reduce' });
    await ctx.route('**/*', route => {
      const request = route.request();
      const url = new URL(request.url());
      const local = ['127.0.0.1', 'localhost', '[::1]'].includes(url.hostname);
      if (!['GET', 'HEAD'].includes(request.method()) || (local && url.origin !== base)) return route.abort('blockedbyclient');
      return route.continue();
    });
    const page = await ctx.newPage();
    page.setDefaultTimeout(5000);
    const row = { name: spec.name, route: spec.path, viewport, auth: 'guest', trigger: spec.trigger, status: 'pending', assertions: [] };
    report.cases.push(row);
    page.on('pageerror', error => report.errors.push({ name: spec.name, viewport, route: spec.path, message: error.message }));
    page.on('console', message => { if (message.type() === 'error') report.errors.push({ name: spec.name, viewport, route: spec.path, message: message.text() }); });
    page.on('requestfailed', request => report.failedRequests.push({ name: spec.name, viewport, route: spec.path, url: request.url(), error: request.failure()?.errorText }));
    page.on('response', response => { if (response.status() >= 400) report.failedRequests.push({ name: spec.name, viewport, route: spec.path, url: response.url(), status: response.status() }); });
    try {
      const response = await page.goto(base + spec.path, { waitUntil: 'networkidle', timeout: 15000 });
      assert(row, 'route HTTP 200', response?.status() === 200, response?.status());
      const trigger = page.locator(spec.trigger).filter({ visible: true }).first();
      const count = await trigger.count();
      if (!count) {
        row.status = 'unreachable-trigger';
        assert(row, 'visible real trigger exists', false, `No visible ${spec.trigger} on ${spec.path}`);
        continue;
      }
      assert(row, 'visible real trigger exists', true);
      await trigger.click({ timeout: 5000 });
      const layer = page.locator(spec.layer);
      await layer.waitFor({ state: 'visible', timeout: 3000 });
      assert(row, 'trigger opens dialog', await layer.evaluate(e => e.classList.contains('open') && e.getAttribute('aria-hidden') === 'false' && !e.inert));
      await page.waitForFunction(selector => document.querySelector(selector)?.contains(document.activeElement), spec.layer, { timeout: 2000 }).catch(() => {});
      assert(row, 'focus moves inside', await layer.evaluate(e => e.contains(document.activeElement)), await page.evaluate(() => document.activeElement?.outerHTML.slice(0, 200)));
      const shot = `${spec.name}-${viewport.width}x${viewport.height}.png`;
      await page.screenshot({ path: join(out, shot) });
      report.screenshots.push({ file: shot, route: new URL(page.url()).pathname, viewport, auth: 'guest', overlay: spec.name });
      const endpoints = await layer.evaluate((e, selector) => [...e.querySelectorAll(selector)].filter(x => x.offsetParent !== null).length, focusables);
      row.focusableCount = endpoints;
      assert(row, 'has keyboard focus targets', endpoints > 0, endpoints);
      if (spec.name === 'shortcuts' && !endpoints) {
        const el = page.locator('#welcome-shortcuts-backdrop .modal-window');
        assert(row, 'shortcuts inner window displayed', await el.evaluate(e => getComputedStyle(e).display !== 'none'), 'CSS class .modal-window defaults to display:none unless .open is added, hiding shortcuts modal content');
        await page.keyboard.press('Escape');
        assert(row, 'Escape closes', await layer.getAttribute('aria-hidden') === 'true');
        assert(row, 'Escape restores opener focus', await trigger.evaluate(e => document.activeElement === e));
        row.status = 'failed';
        continue;
      }
      if (endpoints) {
        await layer.locator(focusables).filter({ visible: true }).last().focus();
        await page.keyboard.press('Tab');
        assert(row, 'Tab wraps last to first', await layer.evaluate((e, selector) => document.activeElement === [...e.querySelectorAll(selector)].filter(x => x.offsetParent !== null)[0], focusables));
        await page.keyboard.press('Shift+Tab');
        assert(row, 'Shift+Tab wraps first to last', await layer.evaluate((e, selector) => { const items = [...e.querySelectorAll(selector)].filter(x => x.offsetParent !== null); return document.activeElement === items.at(-1); }, focusables));
      }
      await page.keyboard.press('Escape');
      assert(row, 'Escape closes', await layer.getAttribute('aria-hidden') === 'true' && !(await layer.evaluate(e => e.classList.contains('open'))));
      assert(row, 'Escape restores opener focus', await trigger.evaluate(e => document.activeElement === e));
      await trigger.click();
      await layer.waitFor({ state: 'visible' });
      await layer.locator(spec.close).first().click();
      assert(row, 'close button closes', await layer.getAttribute('aria-hidden') === 'true');
      assert(row, 'close button restores opener focus', await trigger.evaluate(e => document.activeElement === e));
      await trigger.click();
      await layer.waitFor({ state: 'visible' });
      // Find a viewport point whose top hit-target is the backdrop itself, not the dialog surface.
      const locateBackdrop = ({ backdropSelector }) => {
        const backdrop = document.querySelector(backdropSelector || '[data-overlay]');
        for (let y = 12; y < innerHeight; y += 32) for (let x = 12; x < innerWidth; x += 32) {
          if (document.elementFromPoint(x, y) === backdrop) return { x, y };
        }
        return null;
      };
      await page.waitForFunction(locateBackdrop, { backdropSelector: spec.backdrop }, { timeout: 1200 }).catch(() => {});
      const point = await page.evaluate(locateBackdrop, { backdropSelector: spec.backdrop });
      row.backdropPoint = point;
      if (!point) {
        row.backdrop = 'not-available-at-viewport';
        await page.keyboard.press('Escape');
      } else {
        await page.mouse.click(point.x, point.y);
        assert(row, 'backdrop closes', await layer.getAttribute('aria-hidden') === 'true');
        assert(row, 'backdrop restores opener focus', await trigger.evaluate(e => document.activeElement === e));
      }
      row.status = row.assertions.some(a => !a.ok) ? 'failed' : 'passed';
    } catch (error) {
      assert(row, 'execution completed', false, error.stack || error.message);
      row.status = 'failed';
    } finally {
      await ctx.close();
    }
  }
} finally {
  await browser.close();
  report.finishedAt = new Date().toISOString();
  writeFileSync(join(out, 'results.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify({ output: out, assertions: report.assertions, cases: report.cases.map(({ name, viewport, status }) => ({ name, viewport: viewport.width, status })), errors: report.errors.length, failedRequests: report.failedRequests.length }));
}
process.exitCode = report.assertions.failed || report.errors.length || report.failedRequests.length ? 1 : 0;
