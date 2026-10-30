// Browser verification for the enriched assistant (FR-401/FR-402) against an isolated local server.
import { chromium } from '/Users/hanifpriyambodo/Documents/online-shop/node_modules/playwright/index.mjs';
import { mkdirSync, writeFileSync } from 'node:fs';

const BASE = process.argv[2] || 'http://127.0.0.1:8023';
const OUT = process.argv[3];
mkdirSync(`${OUT}/shots`, { recursive: true });
const results = [];
const check = (id, pass, detail = '') => { results.push({ id, pass, detail }); console.log(`${pass ? 'PASS' : 'FAIL'} ${id} ${detail}`); };
const browser = await chromium.launch();
async function page(width, height, theme = 'light') {
    const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, locale: 'id-ID', colorScheme: theme, timezoneId: 'Asia/Jakarta' });
    await context.addInitScript(value => { try { localStorage.setItem('zafira_theme', value); } catch {} }, theme);
    const p = await context.newPage();
    const origins = []; const errors = []; const api = [];
    p.on('request', request => { const url = new URL(request.url()); if (!['127.0.0.1', 'localhost'].includes(url.hostname) && url.protocol.startsWith('http')) origins.push(url.origin); if (url.pathname.startsWith('/bantuan')) api.push(`${request.method()} ${url.pathname}${url.search}`); });
    p.on('response', response => { if (new URL(response.url()).pathname.startsWith('/bantuan')) api.push(`<- ${response.status()} ${new URL(response.url()).pathname}`); });
    p.on('pageerror', error => errors.push(error.message));
    p.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    return { context, p, origins, errors, api };
}
const targets = p => p.evaluate(() => [...document.querySelectorAll('.sf-assistant button, .sf-assistant a, .sf-assistant input')].filter(node => node.offsetParent).map(node => { const r = node.getBoundingClientRect(); return { t: (node.textContent || node.id || node.getAttribute('aria-label') || '').trim().slice(0, 28), w: Math.round(r.width), h: Math.round(r.height) }; }).filter(r => r.h < 44 || r.w < 44));
const logText = p => p.locator('.sf-assistant-log').textContent();

// A. Desktop home: welcome menu, product search, add to bag with confirm + undo, feedback.
{
    const { context, p, origins, errors, api } = await page(1280, 900);
    await p.goto(`${BASE}/?locale=id`, { waitUntil: 'networkidle' });
    const before = new Set(origins);
    check('CB-13 no auto-open', await p.locator('.sf-assistant').count() === 0);
    check('NFR-402 no assistant call before opening', api.length === 0, api.join(','));
    await p.locator('.sf-assistant-launcher').click(); await p.waitForSelector('.sf-assistant.open'); await p.waitForTimeout(500);
    const status = (await p.locator('.sf-assistant-status').textContent()).trim();
    check('live concierge status shown', status.length > 10, status);
    check('topic menu has 8 tiles', await p.locator('.sf-assistant-tiles button').count() === 8);
    await p.screenshot({ path: `${OUT}/shots/01-welcome-desktop.png` });
    await p.getByRole('button', { name: 'Cari kreasi' }).click();
    await p.locator('#sf-assistant-input').fill('tas sutra');
    await p.keyboard.press('Enter');
    await p.waitForSelector('.sf-assistant-card');
    await p.waitForTimeout(300);
    const titles = await p.locator('.sf-assistant-card-title').allTextContents();
    check('search returns Minaudière Saphir', titles.includes('Minaudière Saphir'), titles.join('|'));
    await p.screenshot({ path: `${OUT}/shots/02-search-cards.png` });
    const countBefore = Number(await p.locator('.cart-count').first().textContent());
    await p.locator('.sf-assistant-card', { hasText: 'Minaudière Saphir' }).getByRole('button', { name: 'Tambah ke tas' }).click();
    check('CB-11 confirmation before cart change', (await logText(p)).includes('ke Tas Belanja?') && !api.some(line => line.includes('delta')));
    await p.getByRole('button', { name: 'Ya, tambahkan' }).click();
    await p.waitForFunction(() => document.querySelector('.sf-assistant-log').textContent.includes('sudah ada di Tas Belanja'));
    const countAfter = Number(await p.locator('.cart-count').first().textContent());
    check('cart count increments', countAfter === countBefore + 1, `${countBefore}->${countAfter}`);
    await p.screenshot({ path: `${OUT}/shots/03-added-undo.png` });
    await p.getByRole('button', { name: 'Urungkan' }).click();
    await p.waitForFunction(() => document.querySelector('.sf-assistant-log').textContent.includes('dikeluarkan lagi'));
    check('undo restores cart count', Number(await p.locator('.cart-count').first().textContent()) === countBefore);
    await p.getByRole('button', { name: 'Semua topik' }).click();
    await p.locator('.sf-assistant-tiles').last().getByRole('button', { name: 'Pengiriman & retur' }).click();
    await p.getByRole('button', { name: 'Ya', exact: true }).click();
    await p.waitForTimeout(400);
    check('feedback stored (204)', api.includes('<- 204 /bantuan/umpan-balik'), api.join(' ; '));
    check('NFR-402 assistant adds no external origin', [...new Set(origins)].every(origin => before.has(origin)), [...new Set(origins)].join(','));
    check('no console errors A', errors.length === 0, errors.join(' | '));
    await context.close();
}

// B. Gift -> personalisation -> appointment handoff -> concierge -> reference.
{
    const { context, p, errors } = await page(1280, 900);
    await p.goto(`${BASE}/?locale=id`, { waitUntil: 'networkidle' });
    await p.keyboard.press('h');
    await p.waitForSelector('.sf-assistant.open'); await p.waitForTimeout(400);
    check('H shortcut opens help', true);
    await p.getByRole('button', { name: 'Hadiah & kemasan' }).click();
    check('gift answer from official copy', (await logText(p)).includes('kotak kaku safir'));
    await p.locator('.sf-assistant-follow').getByRole('button', { name: 'Personalisasi' }).click();
    await p.locator('.sf-assistant-follow').getByRole('button', { name: 'Buat janji temu' }).click();
    await p.screenshot({ path: `${OUT}/shots/04-handoff-preview.png` });
    await p.getByRole('button', { name: 'Lanjutkan ke formulir' }).click();
    await p.waitForSelector('.concierge-modal.open');
    check('service preselected private-styling', await p.locator('#sf-concierge-service').inputValue() === 'private-styling');
    await p.locator('#sf-concierge-name').fill('Uji Asisten');
    await p.locator('#sf-concierge-email').fill('uji.asisten@example.test');
    await p.locator('#sf-concierge-phone').fill('0812 0000 0000');
    await p.locator('.concierge-form button[type="submit"]').click();
    await p.waitForSelector('.sf-assistant.open'); await p.waitForTimeout(400);
    const reference = (await logText(p)).match(/ZC-\d{6}/)?.[0];
    check('CB-06 reference closure', Boolean(reference), reference);
    await p.screenshot({ path: `${OUT}/shots/05-sent-reference.png` });
    check('no console errors B', errors.length === 0, errors.join(' | '));
    writeFileSync(`${OUT}/reference.txt`, `${reference}\n`);
    await context.close();
}

// C. 320 px PDP in both themes.
for (const theme of ['light', 'dark']) {
    const { context, p, errors } = await page(320, 640, theme);
    await p.goto(`${BASE}/produk/sac-seraphine?locale=id`, { waitUntil: 'networkidle' });
    const box = await p.locator('.sf-assistant-launcher').boundingBox();
    check(`CB-14 launcher within 320 (${theme})`, box.x >= 0 && box.x + box.width <= 320);
    await p.locator('.sf-assistant-launcher').click(); await p.waitForSelector('.sf-assistant.open'); await p.waitForTimeout(500);
    await p.screenshot({ path: `${OUT}/shots/06-pdp-320-${theme}-welcome.png` });
    await p.getByRole('button', { name: 'Detail produk ini' }).click(); await p.waitForTimeout(300);
    await p.screenshot({ path: `${OUT}/shots/07-pdp-320-${theme}-product.png` });
    await p.locator('#sf-assistant-input').fill('gaun biru'); await p.keyboard.press('Enter');
    await p.waitForSelector('.sf-assistant-card'); await p.waitForTimeout(300);
    await p.screenshot({ path: `${OUT}/shots/08-pdp-320-${theme}-cards.png` });
    const overflow = await p.evaluate(() => { const node = document.querySelector('.sf-assistant'); return node.scrollWidth - node.clientWidth; });
    check(`CB-18 no horizontal overflow (${theme})`, overflow <= 0, `${overflow}px`);
    const small = await targets(p);
    check(`CB-19 targets >= 44 (${theme})`, small.length === 0, JSON.stringify(small));
    const logHeight = await p.evaluate(() => document.querySelector('.sf-assistant-log').getBoundingClientRect().height);
    check(`log keeps reading room (${theme})`, logHeight >= 260, `${Math.round(logHeight)}px`);
    const colors = await p.evaluate(() => {
        const read = selector => { const node = document.querySelector(selector); if (!node) return null; const style = getComputedStyle(node); let bgNode = node; let bg = style.backgroundColor; while (bg === 'rgba(0, 0, 0, 0)' && bgNode.parentElement) { bgNode = bgNode.parentElement; bg = getComputedStyle(bgNode).backgroundColor; } return { selector, fg: style.color, bg }; };
        return ['.sf-assistant-entry.is-bot', '.sf-assistant-entry.is-user', '.sf-assistant-status', '.sf-assistant-muted', '.sf-assistant-time', '.sf-assistant-card-category', '.sf-assistant-stock', '.sf-assistant-card-actions a', '.sf-assistant-tiles button', '.sf-assistant-feedback', '.sf-assistant-footer p', '.sf-assistant-human', '.sf-assistant-launcher'].map(read).filter(Boolean);
    });
    writeFileSync(`${OUT}/colors-${theme}.json`, JSON.stringify(colors, null, 2));
    check(`no console errors C (${theme})`, errors.length === 0, errors.join(' | '));
    await context.close();
}

// D. Persistence across navigation and English smoke.
{
    const { context, p } = await page(1280, 900);
    await p.goto(`${BASE}/?locale=id`, { waitUntil: 'networkidle' });
    await p.locator('.sf-assistant-launcher').click(); await p.waitForSelector('.sf-assistant.open');
    await p.getByRole('button', { name: 'Status pesanan' }).click();
    await p.goto(`${BASE}/produk/elan-dress?locale=id`, { waitUntil: 'networkidle' });
    await p.locator('.sf-assistant-launcher').click(); await p.waitForSelector('.sf-assistant.open'); await p.waitForTimeout(400);
    const text = await logText(p);
    check('CB-21 transcript survives navigation', text.includes('kode pelacakan') && text.includes('Anda sekarang melihat Robe Élan'));
    await p.screenshot({ path: `${OUT}/shots/09-carried-pdp.png` });
    await p.goto(`${BASE}/?locale=en`, { waitUntil: 'networkidle' });
    check('en launcher', (await p.locator('.sf-assistant-launcher').textContent()).trim() === 'Help & Concierge');
    await context.close();
}
await browser.close();
writeFileSync(`${OUT}/results.json`, JSON.stringify(results, null, 2));
const failed = results.filter(result => !result.pass);
console.log(`\n${results.length - failed.length}/${results.length} passed`);
process.exit(failed.length ? 1 : 0);
