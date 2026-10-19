// UI audit: horizontal overflow, console errors, focus visibility, bottom-dock occlusion.
// Usage: node audit.mjs <label> [baseUrl]   -> writes <label>.json (+ screenshots) next to this file.
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const label = process.argv[2] || 'run';
const base = process.argv[3] || 'http://127.0.0.1:8011';
const out = join(dirname(fileURLToPath(import.meta.url)), label);
mkdirSync(out, { recursive: true });

const viewports = [[320, 568], [390, 844], [768, 1024], [1024, 768], [1366, 768], [1440, 900], [1650, 927]];
const appPages = ['/', '/login', '/register', '/admin/login', '/akun', '/admin', '/produk/sac-seraphine'];
const protoViews = ['storefront', 'studio', 'account', 'admin'];

// Visible elements whose right edge exceeds viewport and are not clipped by an overflow ancestor.
const overflowProbe = () => {
    const vw = document.documentElement.clientWidth;
    const clipped = (el) => {
        for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
            const s = getComputedStyle(p);
            if (s.overflowX !== 'visible' || s.position === 'fixed') return true;
        }
        return false;
    };
    const offenders = [...document.querySelectorAll('body *')].filter((el) => {
        const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && r.right > vw + 0.5 && s.visibility !== 'hidden' && s.position !== 'fixed' && !clipped(el);
    }).slice(0, 8).map((el) => ({ el: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : ''), right: Math.round(el.getBoundingClientRect().right) }));
    return { scrollWidth: document.documentElement.scrollWidth, clientWidth: vw, overflowX: document.documentElement.scrollWidth - vw, offenders };
};

// After scrolling to the end: are the last content controls hidden under a fixed bottom bar?
const occlusionProbe = (rootSel) => {
    document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important');
    window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' });
    for (const el of document.querySelectorAll('*')) if (el.scrollHeight > el.clientHeight + 4 && /auto|scroll/.test(getComputedStyle(el).overflowY)) el.scrollTop = el.scrollHeight;
    const root = rootSel ? document.querySelector(rootSel) : document.body;
    const bars = [...document.querySelectorAll('body *')].filter((el) => {
        const s = getComputedStyle(el); const r = el.getBoundingClientRect();
        return s.position === 'fixed' && s.visibility !== 'hidden' && s.display !== 'none' && r.height > 20 && r.height < 200 && r.bottom >= innerHeight - 40 && r.top > innerHeight / 2 && s.pointerEvents !== 'none' && +s.opacity > 0.05;
    });
    const candidates = [...root.querySelectorAll('a, button, input, select, textarea, p, h1, h2, h3, h4, small, span, li')].filter((el) => {
        const r = el.getBoundingClientRect(); const s = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight && s.visibility !== 'hidden' && !bars.some((b) => b.contains(el)) && !el.closest('[aria-hidden="true"], .drawer, .modal, aside');
    });
    const covered = candidates.filter((el) => {
        const r = el.getBoundingClientRect();
        const x = Math.min(Math.max(r.left + r.width / 2, 1), innerWidth - 1);
        const y = Math.min(Math.max(r.top + r.height / 2, 1), innerHeight - 1);
        const hit = document.elementFromPoint(x, y);
        return hit && bars.some((b) => b.contains(hit));
    }).map((el) => el.tagName.toLowerCase() + ':' + (el.textContent || el.value || '').trim().slice(0, 40));
    return { bars: bars.map((b) => ({ cls: String(b.className), top: Math.round(b.getBoundingClientRect().top), height: Math.round(b.getBoundingClientRect().height) })), covered, docEnd: document.documentElement.scrollHeight };
};

const focusProbe = async (page) => {
    const results = [];
    for (let i = 0; i < 6; i++) {
        await page.keyboard.press('Tab');
        results.push(await page.evaluate(() => {
            const el = document.activeElement; if (!el || el === document.body) return null;
            const s = getComputedStyle(el);
            const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== 'none';
            return { el: el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0], visible };
        }));
    }
    return results.filter(Boolean);
};

const browser = await chromium.launch({ executablePath: exe });
const report = [];
for (const [w, h] of viewports) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h } });
    const page = await ctx.newPage();
    let errs = [];
    page.on('console', (m) => m.type() === 'error' && errs.push(m.text()));
    page.on('pageerror', (e) => errs.push(String(e)));
    for (const path of appPages) {
        errs = [];
        const res = await page.goto(base + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);
        const row = { kind: 'app', path, finalUrl: page.url().replace(base, ''), status: res?.status(), w, h, ...(await page.evaluate(overflowProbe)) };
        row.focus = await focusProbe(page);
        row.occlusion = await page.evaluate(occlusionProbe, null);
        row.errs = [...errs];
        if (w === 320 || w === 1440) await page.screenshot({ path: join(out, `app${path.replace(/\W+/g, '_') || '_'}-${w}x${h}.png`) });
        report.push(row);
    }
    for (const view of protoViews) {
        errs = [];
        await page.goto(base + '/prototype/', { waitUntil: 'networkidle' });
        await page.evaluate((v) => (v === 'studio' ? window.openProductStudio(9) : window.switchView(v)), view);
        await page.waitForTimeout(500);
        const row = { kind: 'prototype', path: '/prototype/#' + view, w, h, ...(await page.evaluate(overflowProbe)) };
        row.occlusion = await page.evaluate(occlusionProbe, '.view-container.active');
        await page.waitForTimeout(450); // let dock tuck transition settle, then re-measure
        row.occlusionSettled = await page.evaluate(occlusionProbe, '.view-container.active');
        row.errs = [...errs];
        if (w === 320 || w === 1440) await page.screenshot({ path: join(out, `proto_${view}-end-${w}x${h}.png`) });
        report.push(row);
    }
    await ctx.close();
}
await browser.close();
writeFileSync(join(out, 'metrics.json'), JSON.stringify(report, null, 1));
const bad = report.filter((r) => r.overflowX > 0 || r.errs.length || r.occlusion.covered.length || r.occlusionSettled?.covered.length);
console.log(JSON.stringify(bad.map((r) => ({ p: r.path, vp: `${r.w}x${r.h}`, ox: r.overflowX, off: r.offenders, cov: r.occlusion.covered, covSettled: r.occlusionSettled?.covered, errs: r.errs })), null, 1));
console.log('rows', report.length, 'problem rows', bad.length);
