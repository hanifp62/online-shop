// Isolated-server audit with explicit verdicts.
// Usage: BASE=http://127.0.0.1:8015 PW_FILE=... node audit-nav.mjs <label>
// Exit: 0 = every assertion passed, 1 = at least one assertion failed, 2 = bad setup.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { writeFileSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';

const label = process.argv[2] || 'run';
const base = process.env.BASE;
if (!base || !/^http:\/\/127\.0\.0\.1:\d+$/.test(base) || /:(8000|8001)$/.test(base)) {
  console.error('BASE must be an isolated http://127.0.0.1:<port> (never 8000/8001)');
  process.exit(2);
}
const pw = readFileSync(process.env.PW_FILE, 'utf8').trim();
const out = join(dirname(fileURLToPath(import.meta.url)), label);
mkdirSync(out, { recursive: true });

const vps = [[320, 568], [390, 844], [768, 1024], [1024, 768], [1366, 768], [1440, 900], [1650, 927]];
// [name, path, auth, expected final pathname]
const pages = [
  ['home', '/', null, '/'],
  ['pdp', '/produk/celeste-trench', null, '/produk/celeste-trench'],
  ['login', '/login', null, '/login'],
  ['register', '/register', null, '/register'],
  ['adminlogin', '/admin/login', null, '/admin/login'],
  ['account', '/akun', 'customer', '/akun'],
  ['admin', '/admin', 'admin', '/admin'],
];
const MOBILE_MAX = 900; // must match the first mobile breakpoint in resources/css/app.css

const results = [];
const check = (scope, name, ok, detail = '') => results.push({ scope, name, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 300) });

const b = await chromium.launch({ executablePath: exe });

// Returns { path, errorShown }. Throws when the login page or POST itself fails,
// so a broken form can never be mistaken for a rejected credential.
async function login(ctx, kind, { password = pw, keepPage = false } = {}) {
  const p = await ctx.newPage();
  const path = kind === 'admin' ? '/admin/login' : '/login';
  const res = await p.goto(base + path, { waitUntil: 'networkidle' });
  if (!res || res.status() !== 200) throw new Error(`login page ${path} status ${res?.status()}`);
  await p.fill('input[name=email]', kind === 'admin' ? 'admin@gmail.com' : 'qa.customer@example.test');
  await p.fill('input[name=password]', password);
  const post = p.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === path);
  const navigation = p.waitForNavigation({ waitUntil: 'networkidle' });
  await p.locator('form button[type=submit]').first().click();
  const [postRes] = await Promise.all([post, navigation]);
  if (postRes.status() >= 500) throw new Error(`${kind} login POST status ${postRes.status()}`);
  await p.waitForLoadState('networkidle');
  const final = new URL(p.url()).pathname;
  const errorShown = await p.locator('text=/password (admin )?salah/i').count() > 0;
  if (!keepPage) await p.close();
  return { path: final, errorShown, status: postRes.status() };
}

async function loginOrThrow(ctx, kind, options) {
  const r = await login(ctx, kind, options);
  if (r.path === '/login' && r.errorShown && r.status === 302) {
    const error = new Error('Credentials rejected');
    error.code = 'AUTH_REJECTED';
    throw error;
  }
  const expected = kind === 'admin' ? '/admin' : '/';
  if (r.errorShown || r.path !== expected || r.status !== 302) {
    throw new Error(`${kind} login landed on ${r.path} (errorShown=${r.errorShown}, status=${r.status})`);
  }
}

async function audit(page, scope, w) {
  return page.evaluate(({ mobileMax, w }) => {
    const de = document.documentElement;
    const vis = e => { const r = e.getBoundingClientRect(), s = getComputedStyle(e); return r.width > 0 && r.height > 0 && s.display !== 'none' && s.visibility !== 'hidden'; };
    const lines = e => { const r = document.createRange(); r.selectNodeContents(e); return [...r.getClientRects()].filter(x => x.width > 0 && x.height > 0).length || 1; };
    const hdr = document.querySelector('.site-header, header');
    const ctr = hdr ? [...hdr.querySelectorAll('a,button')].filter(vis).map(e => ({ e, r: e.getBoundingClientRect() })) : [];
    const overlaps = [];
    for (let i = 0; i < ctr.length; i++) for (let j = i + 1; j < ctr.length; j++) {
      if (ctr[i].e.contains(ctr[j].e) || ctr[j].e.contains(ctr[i].e)) continue;
      const a = ctr[i].r, c = ctr[j].r;
      const left = Math.max(a.left, c.left), right = Math.min(a.right, c.right);
      const top = Math.max(a.top, c.top), bottom = Math.min(a.bottom, c.bottom);
      if (left < right - 1 && top < bottom - 1) {
        const hit = document.elementFromPoint((left + right) / 2, (top + bottom) / 2);
        if (hit && (ctr[i].e.contains(hit) || ctr[j].e.contains(hit))) overlaps.push(`${ctr[i].e.textContent.trim().slice(0, 20)}|${ctr[j].e.textContent.trim().slice(0, 20)}`);
      }
    }
    const ann = document.querySelector('.storefront .announcement-bar');
    let annOverlap = null;
    if (ann && vis(ann)) {
      const p = ann.querySelector('p'), ctl = ann.querySelector('.desktop-only-flex');
      // Compare layout boxes, not text ranges: the <p> clips with overflow:hidden/ellipsis,
      // so a range rect reports width the user never sees.
      if (p && ctl && vis(ctl)) annOverlap = p.getBoundingClientRect().right > ctl.getBoundingClientRect().left + 1;
    }
    return {
      url: location.pathname,
      hOverflow: de.scrollWidth > de.clientWidth + 1 ? de.scrollWidth - de.clientWidth : 0,
      wrapped: [...document.querySelectorAll('.desktop-nav a')].filter(vis).filter(a => lines(a) > 1).map(a => a.textContent.trim()),
      desktopNavVisible: !!([...document.querySelectorAll('.desktop-nav')].find(vis)),
      expectMobile: w <= mobileMax,
      dock: [...document.querySelectorAll('.floating-luxury-dock')].filter(vis).length,
      protoLinks: [...document.querySelectorAll('a[href*="/prototype"]')].map(a => a.getAttribute('href')),
      hdrOut: ctr.filter(x => x.r.right > de.clientWidth + 1 || x.r.left < -1).map(x => x.e.textContent.trim().slice(0, 20)),
      overlaps,
      annOverlap,
      hasStorefrontHeader: !!document.querySelector('.storefront .site-header'),
    };
  }, { mobileMax: MOBILE_MAX, w });
}

function assertLayout(scope, info, expectedPath) {
  check(scope, 'final-path', info.url === expectedPath, `expected ${expectedPath} got ${info.url}`);
  check(scope, 'no-horizontal-overflow', info.hOverflow === 0, `${info.hOverflow}px`);
  check(scope, 'nav-no-wrap', info.wrapped.length === 0, info.wrapped.join(', '));
  check(scope, 'no-demo-dock', info.dock === 0, `${info.dock} visible`);
  check(scope, 'no-prototype-links', info.protoLinks.length === 0, info.protoLinks.join(', '));
  check(scope, 'header-in-viewport', info.hdrOut.length === 0, info.hdrOut.join(', '));
  check(scope, 'header-no-overlap', info.overlaps.length === 0, info.overlaps.join('; '));
  if (info.annOverlap !== null) check(scope, 'announcement-no-overlap', !info.annOverlap, 'text runs under controls');
  if (info.hasStorefrontHeader) check(scope, 'breakpoint-matches', info.desktopNavVisible === !info.expectMobile, `desktopNav=${info.desktopNavVisible} expectMobile=${info.expectMobile}`);
}

function watch(p) {
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + String(e).slice(0, 160)));
  p.on('console', m => m.type() === 'error' && errs.push('console: ' + m.text().slice(0, 160)));
  p.on('requestfailed', r => errs.push(`requestfailed: ${r.url()} ${r.failure()?.errorText}`));
  p.on('response', r => r.status() >= 400 && r.url().startsWith(base) && errs.push(`http ${r.status()}: ${r.url()}`));
  return errs;
}

// 1. Page × viewport matrix
for (const [name, path, auth, expected] of pages) for (const [w, h] of vps) {
  const scope = `${name}@${w}x${h}`;
  const ctx = await b.newContext({ viewport: { width: w, height: h } });
  try {
    if (auth) await loginOrThrow(ctx, auth);
    const p = await ctx.newPage();
    const errs = watch(p);
    const res = await p.goto(base + path, { waitUntil: 'networkidle' });
    check(scope, 'http-200', res && res.status() === 200, `status ${res?.status()}`);
    await p.waitForTimeout(300);
    const info = await audit(p, scope, w);
    assertLayout(scope, info, expected);
    await p.screenshot({ path: join(out, `${name}-${w}x${h}.png`) });
    if (info.hasStorefrontHeader && w <= MOBILE_MAX) {
      const btn = p.locator('.mobile-menu-button:visible').first();
      check(scope, 'mobile-menu-button-visible', await btn.count() === 1, 'missing');
      if (await btn.count()) {
        await btn.click();
        await p.waitForFunction(() => {
          const el = document.querySelector('#mobile-menu');
          // Wait for open state and for the slide-in transition to settle (no running animations).
          return el && (el.getAttribute('aria-hidden') === 'false' || el.classList.contains('open') || el.classList.contains('is-open'))
            && el.getAnimations({ subtree: true }).every(a => a.playState !== 'running');
        }, null, { timeout: 3000 }).catch(() => {});
        const m = await p.evaluate(() => {
          const el = document.querySelector('#mobile-menu'); if (!el) return null;
          const r = el.getBoundingClientRect();
          const label = el.querySelector('nav a');
          return { open: el.getAttribute('aria-hidden') === 'false' || el.classList.contains('open') || el.classList.contains('is-open'),
                   fits: r.right <= innerWidth + 1 && r.left >= -1,
                   labelPx: label ? parseFloat(getComputedStyle(label).fontSize) : null };
        });
        await p.screenshot({ path: join(out, `${name}-${w}x${h}-menu.png`) });
        check(scope, 'mobile-menu-open', m?.open, JSON.stringify(m));
        check(scope, 'mobile-menu-fits', m?.fits, JSON.stringify(m));
        await p.keyboard.press('Escape');
        await p.waitForFunction(() => {
          const el = document.querySelector('#mobile-menu');
          const btn = document.querySelector('.mobile-menu-button');
          if (!el) return false;
          const open = el.classList.contains('open') || el.classList.contains('is-open');
          const hidden = el.getAttribute('aria-hidden') === 'true';
          const expanded = btn?.getAttribute('aria-expanded') === 'false';
          return !open && hidden && el.inert && expanded && document.activeElement === btn;
        }, null, { timeout: 3000 }).catch(() => {});
        const closed = await p.evaluate(() => {
          const el = document.querySelector('#mobile-menu'); const btn = document.querySelector('.mobile-menu-button');
          if (!el) return { ok: false, why: 'menu missing' };
          const s = { open: el.classList.contains('open') || el.classList.contains('is-open'), hidden: el.getAttribute('aria-hidden'), inert: el.inert, expanded: btn?.getAttribute('aria-expanded'), focusInMenu: el.contains(document.activeElement) };
          return { ok: !s.open && s.hidden === 'true' && s.inert && s.expanded === 'false' && document.activeElement === btn, ...s };
        });
        check(scope, 'mobile-menu-escape-closes', closed.ok, JSON.stringify(closed));
      }
    }
    check(scope, 'no-console-or-request-errors', errs.length === 0, errs.join(' | '));
  } catch (e) {
    check(scope, 'no-exception', false, e.message);
  } finally {
    await ctx.close();
  }
}

// 2. Language sweep: desktop nav must not wrap/overflow/overlap in any locale near the breakpoint.
for (const lang of ['id', 'en', 'fr', 'it']) for (const w of [901, 960, 1024, 1100, 1366]) {
  const scope = `lang-${lang}@${w}`;
  const ctx = await b.newContext({ viewport: { width: w, height: 800 } });
  try {
    await ctx.addInitScript(l => localStorage.setItem('zafira_lang', l), lang);
    const p = await ctx.newPage();
    const errs = watch(p);
    await p.goto(base + '/', { waitUntil: 'networkidle' });
    await p.waitForTimeout(300);
    const applied = await p.evaluate(() => document.documentElement.lang);
    check(scope, 'lang-applied', applied === lang, `html lang=${applied}`);
    const info = await audit(p, scope, w);
    assertLayout(scope, info, '/');
    if (w === 901 || w === 1024) await p.screenshot({ path: join(out, `lang-${lang}-${w}.png`) });
    check(scope, 'no-console-or-request-errors', errs.length === 0, errs.join(' | '));
  } catch (e) {
    check(scope, 'no-exception', false, e.message);
  } finally {
    await ctx.close();
  }
}

// 3. Negative control: wrong password must be detected as a failure (proves the login guard is not swallowed).
{
  const ctx = await b.newContext();
  try {
    let rejected = false;
    try {
      await loginOrThrow(ctx, 'customer', { password: 'definitely-wrong-' + Date.now() });
    } catch (e) {
      if (e.code !== 'AUTH_REJECTED') throw e;
      rejected = true;
    }
    check('negative-control', 'guard-flags-rejected-login', rejected, 'loginOrThrow accepted bad credentials');
  } catch (e) {
    check('negative-control', 'no-exception', false, e.message);
  }
  await ctx.close();
}

await b.close();

const failed = results.filter(r => !r.ok);
writeFileSync(join(out, 'report.json'), JSON.stringify({ base, label, total: results.length, failed: failed.length, results }, null, 1));
for (const f of failed) console.log('FAIL', f.scope.padEnd(22), f.name.padEnd(30), f.detail);
console.log(`assertions=${results.length} passed=${results.length - failed.length} failed=${failed.length}`);
process.exit(failed.length ? 1 : 0);
