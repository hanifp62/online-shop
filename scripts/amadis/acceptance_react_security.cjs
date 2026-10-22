// Synthetic accounts only, supplied by the guarded fixture. Never log credentials, OTPs or recovery codes.
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');

function otp(secret) {
    assert.match(secret, /^[A-Z2-7]+$/);
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bits = [...secret].map(char => alphabet.indexOf(char).toString(2).padStart(5, '0')).join('');
    const key = Buffer.from((bits.match(/.{8}/g) ?? []).map(byte => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = createHmac('sha1', key).update(counter).digest();
    const offset = digest[19] & 15;
    return String((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).padStart(6, '0');
}
async function submit(page, selector) {
    const button = page.locator(selector);
    const action = await button.evaluate(element => element.form.action);
    // Inertia's asynchronous replaceState can resolve waitForNavigation with null.
    // Require real document responses and a new DOMContentLoaded, not a history event.
    const trace = [];
    const record = response => {
        const request = response.request();
        if (request.resourceType() !== 'document' || trace.length >= 10) return;
        trace.push({ method: ['GET', 'POST', 'PATCH', 'DELETE'].includes(request.method()) ? request.method() : 'OTHER',
            status: response.status(), navigation: request.isNavigationRequest(), action: request.url() === action,
            mainFrame: request.frame() === page.mainFrame(), fromWorker: response.fromServiceWorker(),
            hasLocation: Boolean(response.headers().location), redirected: Boolean(request.redirectedFrom()),
            inertia: request.headers()['x-inertia'] === 'true' });
    };
    page.on('response', record);
    try {
        const [, response, posted] = await Promise.all([
            page.waitForEvent('domcontentloaded'),
            page.waitForResponse(response => response.request().isNavigationRequest()
                && response.request().frame() === page.mainFrame()
                && (response.request().method() === 'GET' || (response.request().method() === 'POST'
                    && response.request().url() === action && response.fromServiceWorker()))
                && (response.status() < 300 || response.status() >= 400)),
            page.waitForResponse(response => response.request().isNavigationRequest()
                && response.request().method() === 'POST' && response.request().url() === action),
            button.click(),
        ]);
        // A controlling worker may follow the redirect internally and expose the final 200
        // as the POST's response. Accept only that observed worker path, never arbitrary POST 200.
        assert(posted.status() === 302 || (posted.fromServiceWorker() && posted.status() === 200));
        assert.equal(response.status(), 200);
    } catch (error) {
        error.migrationNavigation = trace;
        throw error;
    } finally {
        page.off('response', record);
    }
}

module.exports = async function securityFlows({ pageFor, native, origin, credentials, start, done, monitor }) {
    const customer = await pageFor();
    monitor(customer);
    async function login(page, role) {
        await native(page, role === 'admin' ? '/admin/login' : '/login');
        await page.locator('#email').fill(credentials[role].email);
        await page.locator('#password').fill(credentials[role].password);
        await submit(page, 'form[data-auth-form] button[type="submit"]');
    }
    async function ready(page) { await page.locator('#app[data-react-boot="ready"]').waitFor(); }
    async function customerLogout() {
        await native(customer, '/akun');
        await submit(customer, 'form[action$="/logout"] button');
    }

    start('account-native-update-export-logout');
    await login(customer, 'customer');
    await native(customer, '/akun');
    await customer.locator('#first_name').fill('Native fixture');
    await submit(customer, 'form[data-auth-form] button[type="submit"]');
    await ready(customer);
    assert.equal(await customer.locator('#auth-error-summary').count(), 0);
    // A fresh GET must agree; old input after a rejected write is not persistence evidence.
    await native(customer, '/akun');
    assert.equal(await customer.locator('#first_name').inputValue(), 'Native fixture');
    const downloadEvent = customer.waitForEvent('download');
    await customer.locator('a[href$="/akun/ekspor"]').click();
    const download = await downloadEvent;
    assert.equal(download.suggestedFilename(), 'zafira-data-pribadi.json');
    await download.delete();
    await customer.locator('details:has(#delete_password) summary').click();
    await customer.locator('#delete_password').fill(credentials.customer.password);
    let deletedRequests = 0;
    customer.on('request', request => {
        if (new URL(request.url()).pathname === '/akun' && request.method() === 'POST' && request.postData()?.includes('_method=DELETE')) deletedRequests += 1;
    });
    customer.once('dialog', dialog => dialog.dismiss());
    await customer.locator('form:has(#delete_password) button[type="submit"]').click();
    assert.equal(deletedRequests, 0);
    await submit(customer, 'form[action$="/logout"] button');
    await customer.goBack();
    await customer.waitForURL(url => url.pathname === '/login');
    assert.equal(await customer.locator('#first_name').count(), 0);
    done();

    start('reset-native-flash-validation');
    const reset = await pageFor();
    monitor(reset);
    await native(reset, '/reset-kata-sandi/synthetic-reset-token?email=reset-fixture%40example.test&locale=en');
    assert.equal(await reset.locator('[name="token"]').inputValue(), 'synthetic-reset-token');
    await reset.locator('#password').fill('Synthetic-password-not-issued1!');
    await reset.locator('#password_confirmation').fill('Different-synthetic-password2!');
    await submit(reset, 'form[data-auth-form] button[type="submit"]');
    await ready(reset);
    await reset.locator('#auth-error-summary').waitFor();
    assert.equal(await reset.locator('#password').getAttribute('aria-invalid'), 'true');
    assert.equal(await reset.locator('#password').getAttribute('aria-describedby'), 'password-error');
    assert.match(await reset.locator('#password-error').textContent(), /confirmation.*match/i);
    assert.equal(await reset.locator('#email').getAttribute('aria-invalid'), null);
    assert.equal(await reset.locator(':focus').getAttribute('id'), 'auth-error-summary');
    assert.equal(await reset.locator('#password').inputValue(), '');
    assert.equal(await reset.locator('[name="token"]').inputValue(), 'synthetic-reset-token');
    await reset.waitForFunction(() => history.state?.page instanceof ArrayBuffer);
    done();

    start('customer-totp-native-challenge');
    await login(customer, 'customer');
    await customer.goto(origin + '/akun/verifikasi-dua-langkah');
    // Enrol/recovery remain the established Blade flow in this slice, not claimed as native.
    const secret = (await customer.locator('.auth-card code').first().textContent()).trim();
    await customer.locator('#code').fill(otp(secret));
    await submit(customer, 'form[data-auth-form] button[type="submit"]');
    await customer.waitForURL(url => url.pathname.endsWith('/kode-cadangan'));
    const recovery = (await customer.locator('.auth-card ul li').first().textContent()).trim();
    assert(recovery.length > 0);
    await customerLogout();
    await login(customer, 'customer');
    await customer.waitForURL(url => url.pathname === '/login/verifikasi');
    await ready(customer);
    assert.equal(await customer.locator(':focus').getAttribute('id'), 'code');
    assert.equal(await customer.locator('#code').getAttribute('pattern'), '\\d{6}');
    await customer.locator('details summary').click();
    await customer.locator('#recovery_code').fill(recovery);
    await submit(customer, 'form:has(#recovery_code) button[type="submit"]');
    assert.equal(new URL(customer.url()).pathname, '/');
    // The very same code must not authenticate a second time.
    await customerLogout();
    await login(customer, 'customer');
    await ready(customer);
    await customer.locator('details summary').click();
    await customer.locator('#recovery_code').fill(recovery);
    await submit(customer, 'form:has(#recovery_code) button[type="submit"]');
    await ready(customer);
    await customer.locator('#auth-error-summary').waitFor();
    assert.equal(new URL(customer.url()).pathname, '/login/verifikasi');
    done();

    start('admin-totp-native-challenge');
    const admin = await pageFor();
    monitor(admin);
    await login(admin, 'admin');
    await admin.goto(origin + '/admin/totp/enrol');
    const adminSecret = await admin.locator('#setup_key').inputValue();
    await admin.locator('#code').fill(otp(adminSecret));
    await submit(admin, 'form[action$="/admin/totp/enrol"] button[type="submit"]');
    await admin.waitForURL(url => url.pathname === '/admin/totp/enrol/recovery');
    const adminRecovery = (await admin.locator('.auth-card li code').first().textContent()).trim();
    assert(adminRecovery.length > 0);
    await admin.goto(origin + '/admin');
    await submit(admin, 'form[action$="/admin/logout"] button');
    await login(admin, 'admin');
    await admin.waitForURL(url => url.pathname === '/admin/totp');
    await ready(admin);
    assert.equal(await admin.locator(':focus').getAttribute('id'), 'code');
    await admin.locator('details summary').click();
    await admin.locator('#recovery_code').fill(adminRecovery);
    await submit(admin, 'form:has(#recovery_code) button[type="submit"]');
    assert.equal(new URL(admin.url()).pathname, '/admin');
    done();
};
