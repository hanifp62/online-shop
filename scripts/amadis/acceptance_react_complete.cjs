// Run on the fresh guarded demo fixture BEFORE securityFlows enables TOTP.
// Deliberately no screenshots, HTML dumps, console output, or credential-bearing diagnostics.
const assert = require('node:assert/strict');
const TIMEOUT = 15000;
const STAGES = Object.freeze([
    'home-native-mobile-keyboard-theme-reflow', 'home-native-search-category-locale',
    'pdp-native-inquiry-draft-cart-delta-undo', 'shop-guest-frozen-idempotent-replay',
    'shop-account-address-voucher-orders-favorites', 'shop-account-completed-order-review',
    'dashboard-native-seven-regions-atomic-drafts',
    'dashboard-native-inline-consent-late-edit', 'dashboard-native-back-failure-recovery',
    'dashboard-native-order-json-once-refresh', 'dashboard-native-private-history',
]);
const OPTIONAL_STAGES = Object.freeze([]);
// Parent monitor calls expectedNetworkFailure with correlated request metadata, never just console text.
const EXPECTED_FAULTS = Object.freeze([
    Object.freeze({ id: 'checkout-abort-requestfailed', stage: STAGES[3], event: 'requestfailed', method: 'POST', pathname: '/checkout', errorPattern: '^net::ERR_FAILED$', min: 1, max: 1 }),
    Object.freeze({ id: 'checkout-abort-console', stage: STAGES[3], event: 'console', method: 'POST', pathname: '/checkout', errorPattern: '^Failed to load resource: net::ERR_FAILED$', min: 0, max: 1 }),
    Object.freeze({ id: 'voucher-rejected-console', stage: STAGES[4], event: 'console', method: 'POST', pathname: '/checkout/quote', status: 422, errorPattern: '^Failed to load resource: the server responded with a status of 422 \\(Unprocessable (?:Content|Entity)\\)$', min: 0, max: 1 }),
]);
function faultMonitor(origin) {
    const armed = new Set();
    const counts = Object.fromEntries(EXPECTED_FAULTS.map(fault => [fault.id, 0]));
    return {
        options: {
            expected: EXPECTED_FAULTS,
            expectedNetworkFailure({ requestURL, errorText, method, event, stage, status }) {
                let url;
                try { url = new URL(requestURL); } catch { return false; }
                if (url.origin !== origin || url.search || url.hash) return false;
                const fault = EXPECTED_FAULTS.find(entry => armed.has(entry.id) && entry.event === event && entry.stage === stage
                    && entry.method === method && entry.pathname === url.pathname && (entry.status === undefined || entry.status === status)
                    && new RegExp(entry.errorPattern).test(errorText) && counts[entry.id] < entry.max);
                if (!fault) return false;
                counts[fault.id] += 1;
                return true;
            },
        },
        arm: (...ids) => ids.forEach(id => armed.add(id)),
        disarm: (...ids) => ids.forEach(id => armed.delete(id)),
        verify() { for (const fault of EXPECTED_FAULTS) assert(counts[fault.id] >= fault.min && counts[fault.id] <= fault.max); },
        counts: () => ({ ...counts }),
    };
}
const isSnapshot = request => request.headers()['x-zafira-dashboard'] === 'snapshot';
const pathOf = value => new URL(value).pathname;
async function ready(page) { await page.locator('#app[data-react-boot="ready"]').waitFor({ timeout: TIMEOUT }); }
async function idle(page) {
    await page.waitForFunction(() => [...document.querySelectorAll('[data-admin-table]')].every(el => el.getAttribute('aria-busy') === 'false'), null, { timeout: TIMEOUT });
}
async function documentSubmit(page, selector) {
    await Promise.all([page.waitForEvent('domcontentloaded', { timeout: TIMEOUT }), page.locator(selector).click()]);
    await ready(page);
}
async function address(form) {
    const values = { label: 'Synthetic acceptance', recipient_name: 'Synthetic Client', phone: '0800000000', line1: 'Synthetic test address', city: 'Jakarta', province: 'DKI Jakarta', postal_code: '12345' };
    for (const [name, value] of Object.entries(values)) await form.locator(`[name="${name}"]`).fill(value);
}
async function filter(page, key, value) {
    const form = page.locator(`[data-admin-table-form="${key}"]`);
    await form.locator('input[type="search"]').fill(value);
    await form.locator('button[type="submit"]').click();
    await idle(page);
}
async function fingerprints(page) {
    return page.locator('[data-admin-table]').evaluateAll(elements => elements.map(el => el.querySelector('tbody')?.textContent ?? el.querySelector('.admin-empty')?.textContent ?? ''));
}
async function snapshot(page, origin) {
    const result = await page.request.get(origin + '/admin', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Zafira-Dashboard': 'snapshot' }, timeout: TIMEOUT });
    assert(result.status() === 200);
    const data = await result.json();
    assert(Number.isInteger(data.stats?.orders));
    return data;
}

async function completedOrderReview({ customer, native, origin, fixture }) {
    fixture.verify('review-before');
    await native(customer, '/belanja');
    const review = customer.locator('#order-list form:has([name="rating"])');
    await review.waitFor({ timeout: TIMEOUT });
    assert.equal(await review.count(), 1);
    await review.locator('[name="rating"]').selectOption('5');
    await review.locator('[name="body"]').fill('Synthetic acceptance review');
    let writes = 0;
    const count = request => { if (request.url() === origin + '/reviews' && request.method() === 'POST') writes += 1; };
    customer.on('request', count);
    try {
        const posted = customer.waitForResponse(response => response.url() === origin + '/reviews'
            && response.request().method() === 'POST', { timeout: TIMEOUT });
        await review.locator('button').click();
        const response = await posted;
        assert.equal(response.status(), 201);
        const payload = response.request().postDataJSON();
        assert(payload.order_id === fixture.order_id && payload.product_id === fixture.product_id
            && payload.rating === 5 && payload.body === 'Synthetic acceptance review');
        const saved = (await response.json()).review;
        assert(saved?.user_id === fixture.customer_id && saved.order_id === fixture.order_id
            && saved.product_id === fixture.product_id && saved.rating === 5 && saved.body === payload.body);
        await review.waitFor({ state: 'detached', timeout: TIMEOUT });
        assert.equal(writes, 1);
        fixture.verify('review-after');
    } finally { customer.off('request', count); }
}

module.exports = async function completeFlows({ pageFor, native, origin, credentials, fixture, start, done, monitor, diagnose = () => {} }) {
    const unavailable = [];
    const faults = faultMonitor(origin);
    async function login(page, role) {
        await native(page, role === 'admin' ? '/admin/login' : '/login');
        await page.locator('#email').fill(credentials[role].email);
        await page.locator('#password').fill(credentials[role].password);
        await documentSubmit(page, 'form[data-auth-form] button[type="submit"]');
    }
    const guest = await pageFor({ viewport: { width: 375, height: 812 }, serviceWorkers: 'block', reducedMotion: 'reduce' });
    monitor(guest, faults.options);
    guest.setDefaultTimeout(TIMEOUT);
    start(STAGES[0]);
    await native(guest, '/');
    const menu = guest.locator('.mobile-menu-button');
    await menu.focus();
    await guest.keyboard.press('Enter');
    await guest.locator('[role="dialog"] #mobile-menu').waitFor();
    assert(await guest.locator('#main-content').evaluate(el => Boolean(el.inert || el.closest('[inert]'))));
    await guest.keyboard.press('Shift+Tab');
    assert(await guest.locator('[role="dialog"]').evaluate(el => el.contains(document.activeElement)));
    await guest.keyboard.press('Escape');
    await guest.locator('[role="dialog"]').waitFor({ state: 'detached' });
    assert(await menu.evaluate(el => el === document.activeElement));
    for (const mode of ['dark', 'light']) {
        await guest.locator('.footer-preferences-bar .zafira-theme-select').selectOption(mode);
        await guest.waitForFunction(value => document.documentElement.dataset.theme === value, mode, { timeout: TIMEOUT });
        assert(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    }
    await guest.setViewportSize({ width: 1440, height: 1000 });
    assert(await guest.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    done();

    start(STAGES[1]);
    await guest.locator('#home-catalog-query').fill('DEMO');
    await Promise.all([guest.waitForURL(url => url.pathname === '/koleksi' && url.searchParams.get('q') === 'DEMO', { timeout: TIMEOUT }), guest.locator('#home-catalog-search button').click()]);
    await ready(guest);
    await native(guest, '/');
    const category = guest.locator('.category-card').first();
    const categoryHref = new URL(await category.getAttribute('href'), origin);
    assert(categoryHref.origin === origin && categoryHref.searchParams.has('kategori'));
    await Promise.all([guest.waitForURL(url => url.pathname === categoryHref.pathname && url.search === categoryHref.search, { timeout: TIMEOUT }), category.click()]);
    await ready(guest);
    await native(guest, '/');
    const localeResponse = guest.waitForResponse(response => pathOf(response.url()) === '/locale' && response.request().method() === 'POST', { timeout: TIMEOUT });
    await guest.locator('.footer-preferences-bar .zafira-lang-select').selectOption('en');
    assert((await localeResponse).status() === 204);
    await guest.waitForFunction(() => document.documentElement.lang === 'en', null, { timeout: TIMEOUT });
    await ready(guest);
    done();

    start(STAGES[2]);
    await native(guest, '/produk/amadis-demo-stock-37');
    let cartWrites = 0;
    const countCart = request => { if (pathOf(request.url()).startsWith('/cart') && request.method() !== 'GET') cartWrites += 1; };
    guest.on('request', countCart);
    const buy = guest.locator('.product-detail-actions > button').first();
    await guest.locator('#pdp-monogram-input').fill('ABC');
    await buy.click();
    await guest.locator('.concierge-modal').waitFor();
    await guest.locator('#sf-concierge-notes').fill('Synthetic inquiry draft');
    await guest.keyboard.press('Escape');
    assert((await guest.locator('#pdp-monogram-input').inputValue()) === 'ABC');
    await buy.click();
    assert((await guest.locator('#sf-concierge-notes').inputValue()) === 'Synthetic inquiry draft');
    assert(cartWrites === 0);
    await guest.keyboard.press('Escape');
    await guest.locator('#pdp-monogram-input').fill('');
    await buy.click();
    await guest.locator('.cart-item').waitFor();
    const line = guest.locator('.cart-item').first();
    const initial = Number(await line.locator('.quantity-control span').textContent());
    await line.locator('[data-action="plus"]').click();
    await guest.waitForFunction(quantity => Number(document.querySelector('.cart-item .quantity-control span')?.textContent) === quantity + 1, initial, { timeout: TIMEOUT });
    await line.locator('[data-action="remove"]').click();
    await guest.locator('.cart-item').waitFor({ state: 'detached' });
    await guest.locator('.cart-drawer .toast button').click();
    await guest.waitForFunction(quantity => Number(document.querySelector('.cart-item .quantity-control span')?.textContent) === quantity + 1, initial, { timeout: TIMEOUT });
    guest.off('request', countCart);
    await guest.keyboard.press('Escape');
    done();

    const admin = await pageFor({ serviceWorkers: 'block' });
    monitor(admin);
    await login(admin, 'admin');
    const beforeOrders = (await snapshot(admin, origin)).stats.orders;
    start(STAGES[3]);
    await native(guest, '/belanja');
    const guestForm = guest.locator('#guest-checkout-form');
    await address(guestForm);
    for (const [name, value] of Object.entries({ customer_name: 'Synthetic Guest', customer_email: 'synthetic-guest@example.test', customer_phone: '0800000000' })) await guestForm.locator(`[name="${name}"]`).fill(value);
    await guest.waitForFunction(() => !document.querySelector('#guest-checkout-button').disabled, null, { timeout: TIMEOUT });
    let firstPayload, firstOrder, firstKey, attempts = 0, replayEqual = false, replayOrder = false;
    let resolveDropped;
    const dropped = new Promise(resolve => { resolveDropped = resolve; });
    const checkoutRoute = async route => {
        const request = route.request();
        if (request.method() !== 'POST' || pathOf(request.url()) !== '/checkout') return route.fallback();
        attempts += 1;
        const body = request.postDataJSON();
        if (attempts === 1) {
            firstPayload = request.postData(); firstKey = body.idempotency_key;
            const response = await route.fetch({ timeout: TIMEOUT, maxRetries: 0 });
            assert(response.status() === 201 || response.status() === 200);
            firstOrder = (await response.json()).order.id;
            faults.arm('checkout-abort-requestfailed', 'checkout-abort-console');
            await route.abort('failed');
            resolveDropped();
        } else {
            replayEqual = request.postData() === firstPayload && body.idempotency_key === firstKey;
            const response = await route.fetch({ timeout: TIMEOUT, maxRetries: 0 });
            replayOrder = (await response.json()).order.id === firstOrder;
            await route.fulfill({ response });
        }
    };
    await guest.route('**/*', checkoutRoute);
    try {
        await guest.locator('#guest-checkout-button').click();
        await Promise.race([dropped, new Promise((_, reject) => setTimeout(() => reject(new Error('checkout_drop_deadline')), TIMEOUT))]);
        await guest.waitForFunction(() => !document.querySelector('#guest-checkout-button').disabled && document.querySelector('#feedback').getAttribute('role') === 'alert', null, { timeout: TIMEOUT });
        faults.disarm('checkout-abort-requestfailed', 'checkout-abort-console');
        await guestForm.locator('[name="customer_name"]').fill('Edited after uncertain write');
        await guest.locator('#guest-checkout-button').click();
        await guest.locator('#guest-order-confirmation h3').waitFor();
        assert(attempts === 2 && replayEqual && replayOrder);
        assert((await snapshot(admin, origin)).stats.orders === beforeOrders + 1);
    } finally {
        faults.disarm('checkout-abort-requestfailed', 'checkout-abort-console');
        await guest.unroute('**/*', checkoutRoute);
        firstPayload = firstKey = firstOrder = null;
    }
    done();

    start(STAGES[4]);
    const customer = await pageFor({ serviceWorkers: 'block' });
    monitor(customer, faults.options);
    await login(customer, 'customer');
    await native(customer, '/produk/amadis-demo-stock-37');
    await customer.locator('.product-detail-actions > button').first().click();
    await customer.locator('.cart-item').waitFor();
    await customer.keyboard.press('Escape');
    // The available related native product exposes the real favorites mutation.
    const favorite = customer.locator('.wishlist-button').first();
    await favorite.waitFor();
    assert(await favorite.count() === 1);
    const favoriteAdded = customer.waitForResponse(response => pathOf(response.url()) === '/favorites' && response.request().method() === 'POST', { timeout: TIMEOUT });
    await favorite.click();
    assert((await favoriteAdded).ok());
    await native(customer, '/belanja');
    await address(customer.locator('#address-form'));
    const addressSaved = customer.waitForResponse(response => pathOf(response.url()) === '/addresses' && response.request().method() === 'POST', { timeout: TIMEOUT });
    await customer.locator('#address-form button').click();
    assert((await addressSaved).status() === 201);
    await customer.locator('#address-list .item').first().waitFor();
    await customer.locator('#checkout-form [name="voucher_code"]').fill('SYNTHETIC-NOT-ISSUED');
    const quote = customer.waitForResponse(response => pathOf(response.url()).endsWith('/checkout/quote'), { timeout: TIMEOUT });
    faults.arm('voucher-rejected-console');
    await customer.locator('#checkout-form [name="voucher_code"]').blur();
    assert((await quote).status() === 422);
    await customer.locator('#feedback[role="alert"]').waitFor();
    faults.disarm('voucher-rejected-console');
    await customer.locator('#checkout-form [name="voucher_code"]').fill('');
    await customer.locator('#checkout-form [name="voucher_code"]').blur();
    await customer.waitForFunction(() => !document.querySelector('#checkout-button').disabled, null, { timeout: TIMEOUT });
    const purchased = customer.waitForResponse(response => pathOf(response.url()) === '/checkout' && response.request().method() === 'POST', { timeout: TIMEOUT });
    await customer.locator('#checkout-button').click();
    assert((await purchased).ok());
    await customer.locator('#order-list article').first().waitFor();
    assert((await snapshot(admin, origin)).stats.orders === beforeOrders + 2);
    await customer.locator('#favorite-list .item').first().waitFor();
    assert(await customer.locator('#favorite-list .item').count() === 1);
    const removedFavorite = customer.waitForResponse(response => pathOf(response.url()).startsWith('/favorites/') && response.request().method() === 'DELETE', { timeout: TIMEOUT });
    await customer.locator('#favorite-list .item button').click();
    assert((await removedFavorite).ok());
    await customer.locator('#favorite-list .item').waitFor({ state: 'detached' });
    done();

    start(STAGES[5]);
    await completedOrderReview({ customer, native, origin, fixture });
    done();

    start(STAGES[6]);
    await native(admin, '/admin');
    assert.equal(await admin.locator('[data-admin-table]').count(), 7);
    await admin.evaluate(() => { window.__completeDashboardDocument = true; });
    for (const key of ['kategori', 'produk', 'pesanan', 'user', 'audit', 'concierge', 'subscriber']) {
        const response = admin.waitForResponse(result => isSnapshot(result.request()), { timeout: TIMEOUT });
        await filter(admin, key, '');
        assert((await response).ok());
        assert(await admin.evaluate(() => window.__completeDashboardDocument === true));
        assert(await admin.locator(`[data-admin-table="${key}"]`).count() === 1);
    }
    await admin.locator('#cat_title').fill('External unsent draft');
    const before = await fingerprints(admin);
    let release, entered;
    const held = new Promise(resolve => { entered = resolve; });
    const gate = new Promise(resolve => { release = resolve; });
    const holdSnapshot = async route => {
        if (!isSnapshot(route.request())) return route.fallback();
        const response = await route.fetch({ timeout: TIMEOUT });
        entered();
        await gate;
        await route.fulfill({ response });
    };
    await admin.route('**/*', holdSnapshot);
    try {
        await admin.locator('#filter-pq').fill('amadis-no-match');
        await admin.locator('[data-admin-table-form="produk"] button[type="submit"]').click();
        await Promise.race([held, new Promise((_, reject) => setTimeout(() => reject(new Error('snapshot_deadline')), TIMEOUT))]);
        assert(JSON.stringify(await fingerprints(admin)) === JSON.stringify(before));
        release();
        await idle(admin);
        assert(await admin.locator('[data-admin-table="produk"] .admin-empty').count() === 1);
        assert((await admin.locator('#cat_title').inputValue()) === 'External unsent draft');
    } finally { release(); await admin.unroute('**/*', holdSnapshot); }
    await filter(admin, 'produk', '');
    done();

    start(STAGES[7]);
    const inline = admin.locator('[data-admin-table="kategori"] [name="cat_title"]').first();
    const original = await inline.inputValue();
    await inline.fill('Inline unsent draft');
    admin.once('dialog', dialog => dialog.dismiss());
    await filter(admin, 'produk', 'cancelled-filter');
    assert(!new URL(admin.url()).searchParams.has('pq'));
    assert((await inline.inputValue()) === 'Inline unsent draft');
    admin.once('dialog', dialog => dialog.accept());
    await filter(admin, 'produk', 'amadis');
    assert((await inline.inputValue()) === original);
    let releaseLate, enteredLate;
    const lateGate = new Promise(resolve => { releaseLate = resolve; });
    const lateStarted = new Promise(resolve => { enteredLate = resolve; });
    const late = async route => {
        if (!isSnapshot(route.request())) return route.fallback();
        const response = await route.fetch({ timeout: TIMEOUT }); enteredLate(); await lateGate; await route.fulfill({ response });
    };
    await admin.route('**/*', late);
    try {
        await admin.locator('#filter-pq').fill('late-filter');
        await admin.locator('[data-admin-table-form="produk"] button[type="submit"]').click();
        await Promise.race([lateStarted, new Promise((_, reject) => setTimeout(() => reject(new Error('late_snapshot_deadline')), TIMEOUT))]);
        await inline.fill('Late unsent draft');
        admin.once('dialog', dialog => dialog.dismiss());
        releaseLate();
        await idle(admin);
        assert(new URL(admin.url()).searchParams.get('pq') === 'amadis');
        assert((await inline.inputValue()) === 'Late unsent draft');
    } finally { releaseLate(); await admin.unroute('**/*', late); }
    await inline.fill(original);
    done();

    start(STAGES[8]);
    await filter(admin, 'produk', 'amadis-no-match');
    const committed = await fingerprints(admin);
    await admin.evaluate(() => {
        window.__dashboardRootBeforeBack = document.querySelector('.native-admin-dashboard');
        window.__dashboardTraversalProbe = { bubble: 0, capture: 0, historyNavigate: 0, clientNavigate: 0 };
        window.addEventListener('popstate', () => { window.__dashboardTraversalProbe.capture += 1; }, true);
        window.addEventListener('popstate', () => { window.__dashboardTraversalProbe.bubble += 1; });
        document.addEventListener('inertia:navigate', event => {
            if (event.detail.type === 'history') window.__dashboardTraversalProbe.historyNavigate += 1;
            else window.__dashboardTraversalProbe.clientNavigate += 1;
        });
    });
    let injectedSnapshots = 0;
    const failSnapshot = async route => {
        if (!isSnapshot(route.request())) return route.fallback();
        await route.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
        injectedSnapshots += 1;
    };
    await admin.route('**/*', failSnapshot);
    try {
        const failedRequest = admin.waitForRequest(isSnapshot, { timeout: TIMEOUT });
        const failedResponse = admin.waitForResponse(result => isSnapshot(result.request()), { timeout: TIMEOUT });
        await admin.goBack({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });
        assert(new URL((await failedRequest).url()).searchParams.get('pq') === 'amadis');
        const failed = await failedResponse;
        // CDP may release response bodies after history traversal. Bind the oracle to
        // the completed literal-body injection, then require the UI rejection/recovery below.
        diagnose({ snapshotStatus: failed.status(), injectedSnapshots });
        assert(failed.status() === 200 && injectedSnapshots === 1);
        await admin.locator('.native-admin-dashboard .auth-errors[role="alert"]').waitFor();
        await idle(admin);
        await admin.waitForURL(url => url.searchParams.get('pq') === 'amadis-no-match', { timeout: TIMEOUT });
        assert(JSON.stringify(await fingerprints(admin)) === JSON.stringify(committed));
    } catch (error) {
        diagnose(await admin.evaluate(() => ({
            sameDashboardDocument: window.__completeDashboardDocument === true,
            sameDashboardRoot: window.__dashboardRootBeforeBack === document.querySelector('.native-admin-dashboard'),
            ...window.__dashboardTraversalProbe,
            nativeDashboardCount: document.querySelectorAll('.native-admin-dashboard').length,
            dashboardAlertCount: document.querySelectorAll('.native-admin-dashboard .auth-errors[role="alert"]').length,
            privateHidden: document.documentElement.style.visibility === 'hidden',
            historyEncrypted: history.state?.page instanceof ArrayBuffer,
            expectedFilter: new URL(location.href).searchParams.get('pq') === 'amadis-no-match',
        })));
        throw error;
    } finally { await admin.unroute('**/*', failSnapshot); }
    const retryRequest = admin.waitForRequest(isSnapshot, { timeout: TIMEOUT });
    const retryResponse = admin.waitForResponse(result => isSnapshot(result.request()), { timeout: TIMEOUT });
    await admin.locator('.native-admin-dashboard .auth-errors button').click();
    const retried = await retryRequest;
    assert(new URL(retried.url()).searchParams.get('pq') === 'amadis-no-match');
    const retryResult = await retryResponse;
    assert(retryResult.ok());
    const retryData = await retryResult.json();
    assert(retryData.contract === 'admin-dashboard.v1' && retryData.filters.pq === 'amadis-no-match');
    await idle(admin);
    await admin.locator('.native-admin-dashboard .auth-errors[role="alert"]').waitFor({ state: 'detached' });
    assert(new URL(admin.url()).searchParams.get('pq') === 'amadis-no-match');
    assert(retryData.tables.produk.rows.length === 0 && await admin.locator('[data-admin-table="produk"] .admin-empty').count() === 1);
    assert(JSON.stringify(await fingerprints(admin)) === JSON.stringify(committed));
    await filter(admin, 'produk', '');
    done();

    start(STAGES[9]);
    const orderForm = admin.locator('[data-admin-table="pesanan"] form:has(select[name="status"])').first();
    assert(await orderForm.count() === 1);
    const action = await orderForm.getAttribute('action');
    const target = 'cancelled';
    assert(await orderForm.locator('select option[value="cancelled"]').count() === 1);
    let writes = 0;
    const countWrite = request => { if (request.method() === 'POST' && request.url() === new URL(action, origin).href) writes += 1; };
    admin.on('request', countWrite);
    try {
        await orderForm.locator('select').selectOption(target);
        const saved = admin.waitForResponse(response => response.request().method() === 'POST' && response.url() === new URL(action, origin).href, { timeout: TIMEOUT });
        const refreshed = admin.waitForResponse(response => isSnapshot(response.request()), { timeout: TIMEOUT });
        admin.once('dialog', dialog => dialog.accept());
        await orderForm.locator('button').click();
        const result = await saved;
        assert(result.ok());
        const updated = (await result.json()).order;
        assert(updated.status === target);
        const refreshResult = await refreshed;
        assert(refreshResult.ok());
        const refreshedData = await refreshResult.json();
        assert(refreshedData.tables.pesanan.rows.some(row => row.id === updated.id && row.status === target));
        await idle(admin);
        const updatedRow = admin.locator('[data-admin-table="pesanan"] tbody tr').filter({ has: admin.locator(`a[href="${refreshedData.tables.pesanan.rows.find(row => row.id === updated.id).invoice}"]`) });
        await updatedRow.locator('.badge-cancelled').waitFor();
        await ready(admin);
        assert(await admin.evaluate(() => window.__completeDashboardDocument === true));
        assert(await updatedRow.locator('select[name="status"]').count() === 0);
        assert(writes === 1);
        await admin.reload({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });
        await ready(admin);
        const persisted = await snapshot(admin, origin);
        assert(persisted.tables.pesanan.rows.some(row => row.id === updated.id && row.status === target));
        assert(writes === 1);
    } finally { admin.off('request', countWrite); }
    done();

    start(STAGES[10]);
    await admin.waitForFunction(() => history.state?.page instanceof ArrayBuffer, null, { timeout: TIMEOUT });
    await documentSubmit(admin, 'form[action$="/admin/logout"] button');
    await admin.goBack({ waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await admin.waitForURL(url => url.pathname === '/admin/login', { timeout: TIMEOUT });
    assert(await admin.locator('.native-admin-dashboard').count() === 0);
    done();
    faults.verify();
    return { unavailable, expectedFaultCounts: faults.counts() };
};
module.exports.completedOrderReview = completedOrderReview;
module.exports.STAGES = STAGES;
module.exports.OPTIONAL_STAGES = OPTIONAL_STAGES;
module.exports.EXPECTED_FAULTS = EXPECTED_FAULTS;
module.exports.MANDATORY_ORDER = Object.freeze([
    'catalog-native', 'catalog-spa-pagination-history', 'catalog-repeated-filters', 'catalog-locale-head',
    'catalog-dialog-cart', 'catalog-mobile', 'auth-native-post-errors', 'auth-other-pages',
    'catalog-delayed-chunk-draft', 'entry-failure-fallback', 'no-js-and-explicit-rollback', 'admin-private-history',
    ...STAGES,
    ...require('./acceptance_react_security.cjs').STAGES,
    'console-clean',
]);
