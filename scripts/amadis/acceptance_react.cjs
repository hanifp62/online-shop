// FR-FE-001..003 / NFR-FE-001..004: native renderer, not just the HTML fallback.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.AM_PLAYWRIGHT);
const securityFlows = require('./acceptance_react_security.cjs');
const completeFlows = require('./acceptance_react_complete.cjs');
const networkMonitor = require('./acceptance_react_monitor.cjs');
const origin = process.env.AM_ACCEPTANCE_URL;
assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
const output = process.env.AM_ACCEPTANCE_OUTPUT;
const checks = [];
let stage = 'launch', browser, consoleErrors = 0, diagnostic = null;
const monitorFinalizers = [];
const unexpectedEvents = [];
function monitor(page, options = {}) {
    monitorFinalizers.push(networkMonitor(page, { ...options, stage: () => stage, unexpected: detail => {
        consoleErrors += 1;
        if (unexpectedEvents.length < 20) unexpectedEvents.push(detail);
    } }));
}
async function pageFor(options = {}) {
    const context = await browser.newContext(options);
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    await page.route('**/*', route => new URL(route.request().url()).origin === origin
        ? route.continue() : route.fulfill({ status: 200, body: '', contentType: 'text/css' }));
    return page;
}
async function native(page, url) {
    assert.equal((await page.goto(origin + url, { waitUntil: 'domcontentloaded' })).status(), 200);
    await page.locator('#app[data-react-boot="ready"]').waitFor();
    assert.equal(await page.locator('script[data-page="app"]').count(), 0);
}
async function modal(page) {
    const button = page.locator('[data-quick-view]').first();
    await button.focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#catalog-quick-dialog').open);
    assert.equal(await page.locator(':focus').getAttribute('id'), 'dialog-close-btn');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#catalog-quick-dialog').open);
    assert(await button.evaluate(el => el === document.activeElement));
}
(async () => {
    let outcome;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.AM_CHROMIUM });
        const page = await pageFor({ viewport: { width: 1440, height: 1000 } });
        page.on('pageerror', () => { consoleErrors += 1; });
        page.on('console', message => { if (message.type() === 'error') consoleErrors += 1; });
        stage = 'catalog-native';
        await native(page, '/koleksi');
        assert.equal(await page.locator('.catalog-card').count(), 8);
        await page.evaluate(() => { window.__reactAcceptanceDocument = true; });
        await page.screenshot({ path: path.join(path.dirname(output), 'catalog-react-desktop.png'), fullPage: true });
        checks.push(stage);

        stage = 'catalog-spa-pagination-history';
        await Promise.all([page.waitForURL(url => url.searchParams.get('page') === '2'), page.locator('.catalog-pagination a').last().click()]);
        assert.equal(await page.evaluate(() => window.__reactAcceptanceDocument), true);
        await page.goBack();
        await page.waitForURL(url => !url.searchParams.has('page'));
        assert.equal(await page.evaluate(() => window.__reactAcceptanceDocument), true);
        await page.goForward();
        await page.waitForURL(url => url.searchParams.get('page') === '2');
        assert.equal(await page.evaluate(() => window.__reactAcceptanceDocument), true);
        checks.push(stage);

        stage = 'catalog-repeated-filters';
        await page.locator('[name="kategori"]').nth(0).check();
        await page.locator('[name="kategori"]').nth(1).check();
        const response = page.waitForResponse(response => response.request().headers()['x-inertia'] === 'true');
        await page.locator('.catalog-form button[type="submit"]').click();
        const filtered = await response;
        assert.equal(filtered.status(), 200);
        const filteredPage = await filtered.json();
        diagnostic = {
            requestedCategories: new URL(filtered.request().url()).searchParams.getAll('kategori').length,
            responseCategories: new URL(filteredPage.url, origin).searchParams.getAll('kategori').length,
            propCategories: filteredPage.props.filters.kategori?.length ?? 0,
        };
        await page.waitForURL(url => url.searchParams.getAll('kategori').length === 2);
        assert.equal(await page.locator('[name="kategori"]:checked').count(), 2);
        assert.equal(new URL(page.url()).searchParams.has('page'), false);
        checks.push(stage);

        stage = 'catalog-locale-head';
        await Promise.all([page.waitForURL(url => url.searchParams.get('locale') === 'fr'), page.locator('.catalog-locales a', { hasText: /^FR$/ }).click()]);
        await page.waitForFunction(() => document.documentElement.lang === 'fr');
        assert.equal(await page.locator('title').count(), 1);
        assert.equal(await page.locator('link[rel="canonical"]').count(), 1);
        assert.equal(new URL(await page.locator('link[rel="canonical"]').getAttribute('href')).searchParams.get('locale'), 'fr');
        assert.equal(await page.evaluate(() => window.__reactAcceptanceDocument), true);
        checks.push(stage);

        stage = 'catalog-dialog-cart';
        await modal(page);
        await page.locator('[data-quick-view]').first().click();
        await page.locator('#dialog-qty').fill('2');
        const cartResponse = page.waitForResponse(response => response.url().endsWith('/cart/items') && response.request().method() === 'POST');
        await page.locator('#dialog-add-button').click();
        const cart = await cartResponse;
        assert.equal(cart.status(), 200);
        assert.equal((await cart.json()).items[0].quantity, 2);
        await page.waitForFunction(() => document.querySelector('#catalog-feedback').textContent.includes('2'));
        checks.push(stage);

        stage = 'catalog-mobile';
        await page.setViewportSize({ width: 375, height: 812 });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
        await modal(page);
        await page.screenshot({ path: path.join(path.dirname(output), 'catalog-react-mobile.png'), fullPage: true });
        checks.push(stage);

        stage = 'auth-native-post-errors';
        await native(page, '/login?locale=en');
        assert.equal(await page.locator('title').count(), 1);
        await page.locator('#email').fill('native-fixture@example.test');
        await page.locator('#password').fill('Synthetic-not-a-real-password1!');
        await page.locator('.password-toggle').click();
        assert.equal(await page.locator('#password').getAttribute('type'), 'text');
        const authPost = page.waitForResponse(response => response.url().endsWith('/login') && response.request().method() === 'POST');
        await page.locator('form[data-auth-form] button[type="submit"]').click();
        assert.equal((await authPost).status(), 302);
        await page.waitForLoadState('domcontentloaded');
        await page.locator('#app[data-react-boot="ready"]').waitFor();
        await page.locator('#auth-error-summary').waitFor();
        assert.equal(await page.locator(':focus').getAttribute('id'), 'auth-error-summary');
        assert.equal(await page.locator('#password').inputValue(), '');
        assert(await page.evaluate(() => !JSON.stringify(history.state).includes('Synthetic-not-a-real-password1!')));
        checks.push(stage);

        stage = 'auth-other-pages';
        await native(page, '/register?locale=it');
        assert.equal(await page.locator('form[data-auth-form] input[type="password"]').count(), 2);
        assert.equal(await page.locator('html').getAttribute('lang'), 'it');
        await native(page, '/lupa-kata-sandi?locale=zh');
        assert.equal(await page.locator('#email').count(), 1);
        assert.equal(await page.locator('html').getAttribute('lang'), 'zh');
        checks.push(stage);

        stage = 'catalog-delayed-chunk-draft';
        const delayed = await pageFor();
        let release;
        const gate = new Promise(resolve => { release = resolve; });
        await delayed.route('**/build/assets/Index-*.js', async route => { await gate; await route.continue(); });
        await delayed.goto(origin + '/koleksi', { waitUntil: 'domcontentloaded' });
        await delayed.locator('#filter-q').fill('Draft not to erase');
        release();
        await delayed.waitForLoadState('networkidle');
        assert.equal(await delayed.locator('#filter-q').inputValue(), 'Draft not to erase');
        assert.equal(await delayed.locator('#app').getAttribute('data-react-boot'), 'fallback');
        await modal(delayed);
        checks.push(stage);

        stage = 'entry-failure-fallback';
        const blocked = await pageFor();
        await blocked.route('**/build/assets/inertia-*.js', route => route.abort());
        await blocked.goto(origin + '/koleksi', { waitUntil: 'domcontentloaded' });
        await modal(blocked);
        await blocked.goto(origin + '/login', { waitUntil: 'domcontentloaded' });
        await blocked.locator('#email').fill('kept@example.test');
        await blocked.locator('[data-password-toggle]').click();
        assert.equal(await blocked.locator('#password').getAttribute('type'), 'text');
        assert.equal(await blocked.locator('#email').inputValue(), 'kept@example.test');
        checks.push(stage);

        stage = 'no-js-and-explicit-rollback';
        const noJs = await pageFor({ javaScriptEnabled: false });
        assert.equal((await noJs.goto(origin + '/koleksi')).status(), 200);
        assert.equal(await noJs.locator('.catalog-card').count(), 8);
        assert.equal((await noJs.goto(origin + '/login')).status(), 200);
        assert.equal(await noJs.locator('form[method="POST"] [name="_token"]').count(), 1);
        await blocked.unroute('**/build/assets/inertia-*.js');
        await blocked.goto(origin + '/koleksi?frontend=blade');
        assert.equal(await blocked.locator('#app').count(), 0);
        await modal(blocked);
        checks.push(stage);

        stage = 'admin-private-history';
        const root = fs.realpathSync(process.env.AMADIS_ROOT);
        assert.equal(path.dirname(root), '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode');
        assert.match(path.basename(root), /^amadis-[a-f0-9]{24}$/);
        const file = path.join(root, 'private-runtime/credentials.json');
        assert.equal(fs.realpathSync(file), file);
        assert.equal(fs.statSync(file).mode & 0o077, 0);
        const fixtureCredentials = JSON.parse(fs.readFileSync(file, 'utf8'));
        const credentials = fixtureCredentials.admin;
        const admin = await pageFor();
        admin.on('pageerror', () => { consoleErrors += 1; });
        admin.on('console', message => { if (message.type() === 'error') consoleErrors += 1; });
        await native(admin, '/admin/login');
        await admin.locator('#email').fill(credentials.email);
        await admin.locator('#password').fill(credentials.password);
        await Promise.all([admin.waitForURL(url => url.pathname === '/admin'), admin.locator('button[type="submit"]').click()]);
        await native(admin, '/admin/auth-events');
        assert.equal(await admin.locator('.auth-events-table-wrap').count(), 1);
        await admin.waitForFunction(() => history.state?.page instanceof ArrayBuffer);
        assert.equal(await admin.evaluate(() => document.documentElement.style.visibility), '');
        await Promise.all([admin.waitForURL(url => url.pathname === '/admin/login'), admin.locator('form[action$="/admin/logout"] button').click()]);
        await admin.goBack();
        await admin.waitForURL(url => url.pathname === '/admin/login');
        assert.equal(await admin.locator('.auth-events-table-wrap').count(), 0);
        assert.equal(await admin.locator('.admin-work-operator').count(), 0);
        checks.push(stage);
        const flowOptions = { pageFor, native, origin, credentials: fixtureCredentials,
            start: name => { stage = name; }, done: () => checks.push(stage), monitor,
            diagnose: values => { diagnostic = { ...diagnostic, ...values }; } };
        const complete = await completeFlows(flowOptions);
        const security = await securityFlows(flowOptions);
        const unavailable = [...complete.unavailable, ...security.unavailable];
        const optional = [...completeFlows.OPTIONAL_STAGES, ...securityFlows.OPTIONAL_STAGES];
        assert.deepEqual(checks.filter(name => !optional.includes(name)), completeFlows.MANDATORY_ORDER.slice(0, -1));
        assert(optional.every(name => checks.includes(name) !== unavailable.includes(name)));
        stage = 'console-clean';
        outcome = { status: 'PASS', checks, consoleErrors, unavailable, expectedFaultCounts: { ...complete.expectedFaultCounts, ...security.expectedFaultCounts } };
    } catch (error) {
        const location = String(error?.stack ?? '').match(/acceptance_react(?:_security|_complete)?\.cjs:(\d+):/);
        const frames = [...String(error?.stack ?? '').matchAll(/(acceptance_react(?:_security|_complete)?\.cjs):(\d+):/g)]
            .slice(0, 5).map(match => ({ file: match[1], line: Number(match[2]) }));
        outcome = { status: 'FAIL', checks, failedStage: stage, sourceLine: location ? Number(location[1]) : null, consoleErrors, diagnostic,
            timeout: error?.name === 'TimeoutError', bodyUnavailable: /No (?:resource|data found) (?:with|for).*identifier/.test(String(error?.message ?? '')),
            actualNumber: typeof error.actual === 'number' ? error.actual : null,
            expectedNumber: typeof error.expected === 'number' ? error.expected : null, frames,
                        navigation: error.migrationNavigation ?? [] };
        process.exitCode = 1;
    } finally {
        try { await browser?.close(); }
        catch { outcome = { status: 'FAIL', checks, failedStage: 'browser-cleanup', consoleErrors }; process.exitCode = 1; }
        monitorFinalizers.forEach(finish => finish());
        if (outcome.status === 'PASS') {
            if (consoleErrors === 0) checks.push('console-clean');
            else { outcome = { status: 'FAIL', checks, failedStage: 'console-clean', consoleErrors }; process.exitCode = 1; }
        }
        outcome.consoleErrors = consoleErrors;
        if (outcome.status === 'FAIL') outcome.unexpectedEvents = unexpectedEvents;
        fs.writeFileSync(output, JSON.stringify(outcome, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
        console.log(JSON.stringify(outcome));
    }
})();
