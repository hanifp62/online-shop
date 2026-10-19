const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const { execFileSync } = require('child_process');
const { chromium } = require('/Users/hanifpriyambodo/.npm/_npx/e41f203b7505f1fb/node_modules/playwright');

const root = process.env.MANUAL_ROOT;
const base = 'http://127.0.0.1:8018';
const evidence = __dirname;
const results = [];
const pageErrors = [];
const networkFailures = [];
const httpFailures = [];
const blockedExternal = [];
const safeUrl = value => { const u = new URL(value); return u.origin + u.pathname; };
let browser;
let page;
function save() {
    fs.writeFileSync(path.join(evidence, 'browser-results.json'), JSON.stringify({
        utc: new Date().toISOString(), root, results, pageErrors, networkFailures, httpFailures, blockedExternal,
    }, null, 2));
}
async function check(name, fn) {
    try { results.push({ name, status: 'PASS', evidence: await fn() }); }
    catch (error) {
        results.push({ name, status: 'FAIL', error: error.message.split('\n')[0] });
        await page.locator('input[type="password"]').fill('').catch(() => {});
        await page.screenshot({ path: path.join(evidence, 'failure-' + name + '.png'), fullPage: true }).catch(() => {});
        process.exitCode = 1;
    }
    save();
}
(async () => {
    assert.equal(process.env.MANUAL_PORT, '8018');
    assert.ok(root, 'MANUAL_ROOT required');
    execFileSync('python3', [path.join(root, 'scripts/manual/preflight.py'), 'browser'], { stdio: 'pipe' });
    const identity = await fetch(base + '/manual/identity.txt', { redirect: 'error' });
    assert.equal(identity.status, 200);
    assert.equal(await identity.text(), fs.readFileSync(path.join(root, 'public/manual/identity.txt'), 'utf8'));
    const creds = JSON.parse(fs.readFileSync(path.join(root, 'private-runtime/credentials.json'), 'utf8'));
    browser = await chromium.launch({ headless: true, executablePath: '/Users/hanifpriyambodo/Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' });
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    await ctx.route('**/*', async route => {
        if (new URL(route.request().url()).origin !== base) {
            blockedExternal.push(safeUrl(route.request().url()));
            await route.abort();
        } else await route.continue();
    });
    page = await ctx.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('requestfailed', request => networkFailures.push({ url: safeUrl(request.url()), error: request.failure()?.errorText }));
    page.on('response', response => { if (response.status() >= 400) httpFailures.push({ url: safeUrl(response.url()), status: response.status() }); });
    const shot = name => page.screenshot({ path: path.join(evidence, name + '.png'), fullPage: true });
    const cart = async () => {
        const response = await ctx.request.get(base + '/cart', { headers: { Accept: 'application/json' } });
        assert.equal(response.status(), 200);
        return response.json();
    };
    await check('customer-login', async () => {
        await page.goto(base + '/login');
        await page.locator('#email').fill(creds.customer.email);
        await page.locator('#password').fill(creds.customer.password);
        await Promise.all([page.waitForURL(u => u.pathname !== '/login'), page.locator('button[type=submit]').click()]);
        assert.equal((await ctx.request.get(base + '/akun')).status(), 200);
        assert.equal((await cart()).items.length, 0, 'Fresh browser session must start with empty cart');
        return { authenticated: true };
    });
    await check('home-add-toast-count', async () => {
        await page.goto(base + '/');
        await page.waitForFunction(() => ['Tas belanja kosong.', 'Tas belanja diperbarui.'].includes(document.querySelector('[data-cart-status]')?.textContent));
        await page.locator('[data-product-card][data-product-id="1"] [data-card-add]').click();
        // Badge updates before addToCart's .then toast; require both without weakening either assertion.
        await page.waitForFunction(() => {
            const badge = document.querySelector('.cart-count');
            const toast = document.querySelector('.toast.show [data-toast-message]');
            return badge?.textContent === '1' && getComputedStyle(badge).visibility === 'visible'
                && toast?.textContent.includes('Synthetic Sapphire') && toast.textContent.includes('ditambahkan');
        });
        assert.equal(await page.locator('.cart-count').first().innerText(), '1');
        assert.equal((await cart()).items[0].quantity, 1);
        const toast = await page.locator('.toast.show [data-toast-message]').innerText();
        await shot('home-add');
        return { count: 1, quantity: 1, toast };
    });
    await check('rapid-three-plus', async () => {
        await page.locator('[data-open-cart]').first().click();
        await page.locator('[data-quantity="plus"]').evaluate(el => { el.click(); el.click(); el.click(); });
        await page.waitForFunction(() => document.querySelector('.quantity-control span')?.textContent === '4');
        assert.equal((await cart()).items[0].quantity, 4);
        await shot('drawer-quantity');
        return { before: 1, clicks: 3, after: 4 };
    });
    await check('remove-undo', async () => {
        await page.locator('[data-remove-cart]').click();
        await page.locator('[data-toast-action]').waitFor({ state: 'visible' });
        assert.equal((await cart()).items.length, 0);
        await page.locator('[data-toast-action]').click();
        await page.waitForFunction(() => document.querySelector('.quantity-control span')?.textContent === '4');
        assert.equal((await cart()).items[0].quantity, 4);
        await shot('drawer-undo');
        return { removed: true, restoredQuantity: 4 };
    });
    await check('refresh-persistence', async () => {
        await page.reload();
        await page.waitForFunction(() => document.querySelector('.cart-count')?.textContent === '4'
            && document.querySelector('.quantity-control span')?.textContent === '4');
        assert.equal((await cart()).items[0].quantity, 4);
        return { quantityAfterRefresh: 4, serverQuantity: (await cart()).items[0].quantity };
    });
    await check('catalog-add-feedback', async () => {
        await page.goto(base + '/koleksi');
        const form = page.locator('form[action$="/cart/items"]').filter({ has: page.locator('input[name="product_id"][value="1"]') }).first();
        await form.locator('button[type="submit"]').click();
        await page.waitForFunction(() => document.querySelector('#catalog-feedback')?.textContent.includes('Jumlah 1'));
        assert.equal((await cart()).items[0].quantity, 1);
        await shot('catalog-add');
        return { quantity: 1, feedback: await page.locator('#catalog-feedback').innerText() };
    });
    await check('catalog-toast-count', async () => {
        await page.locator('.toast.show').waitFor({ state: 'visible' });
        assert.equal(await page.locator('.cart-count').first().innerText(), '1');
        return { toast: true, count: 1 };
    });
    await check('checkout-summary', async () => {
        const data = await cart();
        await page.goto(base + '/belanja');
        await page.waitForFunction(() => document.querySelector('#feedback')?.textContent === 'Tas belanja siap.');
        const lines = [];
        for (const item of data.items) {
            const expected = `Rp${Number(item.unit_price).toLocaleString('id-ID')} × ${item.quantity} = Rp${Number(item.subtotal).toLocaleString('id-ID')}`;
            assert.ok((await page.locator('#cart-list .item').filter({ hasText: item.product_title }).innerText()).includes(expected));
            lines.push(expected);
        }
        assert.equal(await page.locator('#cart-total').innerText(), 'Subtotal: Rp' + Number(data.subtotal).toLocaleString('id-ID'));
        await shot('checkout-summary');
        return { lines, subtotal: data.subtotal };
    });
    await check('place-order-reference', async () => {
        await page.locator('#checkout-button').click();
        await page.waitForFunction(() => /^Pesanan #\d+ dibuat:/.test(document.querySelector('#feedback')?.textContent || ''));
        const confirmation = await page.locator('#feedback').innerText();
        assert.match(confirmation, /pending \/ belum dibayar/);
        assert.equal((await cart()).items.length, 0);
        await shot('order-confirmation');
        return { confirmation };
    });
    await check('order-history-and-cancel', async () => {
        const row = page.locator('#order-list .item').filter({ hasText: /^Pesanan #/ }).first();
        await row.waitFor({ state: 'visible' });
        const id = Number((await row.locator('h3').innerText()).replace('Pesanan #', ''));
        assert.ok((await row.innerText()).includes('pending / belum dibayar'), await row.innerText());
        page.once('dialog', dialog => dialog.accept());
        await row.getByRole('button', { name: 'Batalkan pesanan' }).click();
        await page.waitForFunction(latest => {
            const article = [...document.querySelectorAll('#order-list .item')]
                .find(el => el.querySelector('h3')?.textContent === 'Pesanan #' + latest);
            return article?.textContent.includes('Status: cancelled');
        }, id);
        const api = await ctx.request.get(base + '/orders/' + id, { headers: { Accept: 'application/json' } });
        assert.equal(api.status(), 200);
        assert.equal((await api.json()).order.status, 'cancelled');
        await shot('order-history-cancelled');
        return { orderId: id, uiStatus: 'cancelled', serverStatus: 'cancelled' };
    });
    for (const width of [390, 320]) await check('catalog-mobile-' + width, async () => {
        await page.setViewportSize({ width, height: 844 });
        await page.goto(base + '/koleksi');
        assert.equal(await page.locator('#catalog-filters').evaluate(e => e.open), false);
        await page.locator('#catalog-filters summary').click();
        assert.equal(await page.locator('#catalog-filters').evaluate(e => e.open), true);
        const dims = await page.evaluate(() => ({ viewport: innerWidth, scroll: document.documentElement.scrollWidth }));
        assert.ok(dims.scroll <= width, JSON.stringify(dims));
        await shot('catalog-' + width);
        return dims;
    });
    await check('admin-login-pdf-download', async () => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto(base + '/admin/login');
        await page.locator('#email').fill(creds.admin.email);
        await page.locator('#password').fill(creds.admin.password);
        await Promise.all([page.waitForURL(u => u.pathname === '/admin'), page.locator('button[type=submit]').click()]);
        await page.locator('#report-period').selectOption('30d');
        const [download, response] = await Promise.all([
            page.waitForEvent('download'),
            page.waitForResponse(r => r.url().includes('/admin/reports/orders.pdf')),
            page.getByRole('button', { name: 'Unduh PDF', exact: true }).click(),
        ]);
        assert.equal(response.status(), 200);
        assert.match(response.headers()['content-type'], /^application\/pdf/);
        const target = path.join(evidence, 'admin-report.pdf');
        await download.saveAs(target);
        const data = fs.readFileSync(target);
        assert.equal(data.subarray(0, 5).toString(), '%PDF-');
        await shot('admin-report-download');
        return { status: 200, contentType: response.headers()['content-type'], bytes: data.length };
    });
    if (pageErrors.length || httpFailures.length || networkFailures.some(item => !blockedExternal.includes(item.url))) process.exitCode = 1;
})().catch(error => {
    results.push({ name: 'bootstrap', status: 'FAIL', error: error.message.split('\n')[0] });
    process.exitCode = 1;
}).finally(async () => { if (browser) await browser.close(); save(); });
