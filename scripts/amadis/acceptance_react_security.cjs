// Synthetic accounts only, supplied by the guarded fixture. Never log credentials, OTPs or recovery codes.
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');

function otp(secret) {
    assert(/^[A-Z2-7]+$/.test(secret));
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

async function privateMaterialAbsent(page, material) {
    await page.waitForFunction(() => history.state?.page instanceof ArrayBuffer, null, { timeout: 15000 });
    // Installed core format: JSON raw-key bytes in historyKey; 12-byte IV prefix, then AES-GCM ciphertext.
    // Only a boolean crosses back to Node; neither key nor decrypted page is exported or logged.
    assert(await page.evaluate(async values => {
        try {
            const bytes = JSON.parse(sessionStorage.getItem('historyKey') || 'null');
            if (!Array.isArray(bytes) || bytes.length !== 32 || !values.length || values.some(value => !value)) return false;
            const entry = new Uint8Array(history.state.page);
            if (entry.length <= 28) return false;
            const key = await crypto.subtle.importKey('raw', new Uint8Array(bytes), { name: 'AES-GCM', length: 256 }, false, ['decrypt']);
            const clear = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: entry.subarray(0, 12) }, key, entry.subarray(12));
            const text = new TextDecoder().decode(clear);
            const restored = JSON.parse(text);
            if (typeof restored.component !== 'string' || !restored.props || restored.encryptHistory !== true) return false;
            const stored = text + Object.values(localStorage).join('') + Object.values(sessionStorage).join('');
            return !text.includes('totpMaterial') && values.every(value => !stored.includes(value));
        } catch { return false; }
    }, material));
    assert.equal(await page.locator('script[data-page="app"]').count(), 0);
}

module.exports = async function securityFlows({ pageFor, native, origin, credentials, start, done, monitor }) {
    const unavailable = ['signed-unsubscribe-native-confirm-complete'];
    const customer = await pageFor();
    let exporting = false, exportAbortCount = 0;
    monitor(customer, { expectedNetworkFailure: failure => {
        // Chromium reports a navigation handed to its download manager as ERR_ABORTED.
        // This allowance exists only around the verified account-export download below.
        if (!exporting || exportAbortCount !== 0 || failure.stage !== 'account-native-update-export-logout'
            || failure.event !== 'requestfailed' || failure.method !== 'GET'
            || failure.requestURL !== origin + '/akun/ekspor' || failure.errorText !== 'net::ERR_ABORTED') return false;
        exportAbortCount += 1;
        return true;
    } });
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
    const unsubscribe = customer.locator('a[href*="/pengingat-keranjang/berhenti/"]').first();
    if (await unsubscribe.count()) {
        const target = new URL(await unsubscribe.getAttribute('href'), origin);
        assert(target.origin === origin && target.searchParams.has('signature'));
        start('signed-unsubscribe-native-confirm-complete');
        await native(customer, target.pathname + target.search);
        await customer.locator('form[data-auth-form] button[type="submit"]').waitFor();
        await Promise.all([
            customer.waitForEvent('domcontentloaded', { timeout: 15000 }),
            customer.locator('form[data-auth-form] button[type="submit"]').click(),
        ]);
        await ready(customer);
        assert.equal(await customer.locator('main form').count(), 0);
        await customer.locator('main [role="status"]').waitFor();
        done();
        unavailable.splice(unavailable.indexOf('signed-unsubscribe-native-confirm-complete'), 1);
        start('account-native-update-export-logout');
        await native(customer, '/akun');
    }
    await customer.locator('#first_name').fill('Native fixture');
    await submit(customer, 'form[data-auth-form] button[type="submit"]');
    await ready(customer);
    assert.equal(await customer.locator('#auth-error-summary').count(), 0);
    // A fresh GET must agree; old input after a rejected write is not persistence evidence.
    await native(customer, '/akun');
    assert.equal(await customer.locator('#first_name').inputValue(), 'Native fixture');
    exporting = true;
    const downloadEvent = customer.waitForEvent('download');
    await customer.locator('a[href$="/akun/ekspor"]').click();
    const download = await downloadEvent;
    assert.equal(download.suggestedFilename(), 'zafira-data-pribadi.json');
    await download.delete();
    exporting = false;
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

    start('customer-totp-native-enrol-recovery');
    await login(customer, 'customer');
    await native(customer, '/akun/verifikasi-dua-langkah');
    const secret = (await customer.locator('.auth-card code').first().textContent()).trim();
    await privateMaterialAbsent(customer, [secret]);
    await customer.locator('#code').fill(otp(secret));
    await submit(customer, 'form[data-auth-form] button[type="submit"]');
    await customer.waitForURL(url => url.pathname.endsWith('/kode-cadangan'));
    await ready(customer);
    const recoveryCodes = (await customer.locator('.auth-card ul li').allTextContents()).map(value => value.trim());
    assert(recoveryCodes.length > 0 && recoveryCodes.every(Boolean));
    const recovery = recoveryCodes[0];
    await privateMaterialAbsent(customer, [secret, ...recoveryCodes]);
    await Promise.all([customer.waitForEvent('domcontentloaded', { timeout: 15000 }), customer.locator('.auth-card a[href$="/akun"]').click()]);
    await ready(customer);
    await customer.goBack({ waitUntil: 'domcontentloaded', timeout: 15000 });
    await ready(customer);
    await privateMaterialAbsent(customer, [secret, ...recoveryCodes]);
    assert(await customer.locator('body').evaluate((element, values) => values.every(value => !element.textContent.includes(value)), [secret, ...recoveryCodes]));
    await customer.reload({ waitUntil: 'domcontentloaded', timeout: 15000 });
    await customer.waitForURL(url => url.pathname === '/akun', { timeout: 15000 });
    await ready(customer);
    assert(await customer.locator('body').evaluate((element, material) => !material.some(value => element.textContent.includes(value)), [secret, ...recoveryCodes]));
    done();
    start('customer-totp-native-challenge');
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

    start('admin-totp-native-enrol-recovery');
    const admin = await pageFor();
    monitor(admin);
    await login(admin, 'admin');
    await native(admin, '/admin/totp/enrol');
    const adminSecret = await admin.locator('#setup_key').inputValue();
    await privateMaterialAbsent(admin, [adminSecret]);
    await admin.locator('#code').fill(otp(adminSecret));
    await submit(admin, 'form[action$="/admin/totp/enrol"] button[type="submit"]');
    await admin.waitForURL(url => url.pathname === '/admin/totp/enrol/recovery');
    await ready(admin);
    const adminRecoveryCodes = (await admin.locator('.auth-card li code').allTextContents()).map(value => value.trim());
    assert(adminRecoveryCodes.length > 0 && adminRecoveryCodes.every(Boolean));
    const adminRecovery = adminRecoveryCodes[0];
    await privateMaterialAbsent(admin, [adminSecret, ...adminRecoveryCodes]);
    await Promise.all([admin.waitForEvent('domcontentloaded', { timeout: 15000 }), admin.locator('.auth-card a[href$="/admin"]').click()]);
    await ready(admin);
    await admin.goBack({ waitUntil: 'domcontentloaded', timeout: 15000 });
    await ready(admin);
    await privateMaterialAbsent(admin, [adminSecret, ...adminRecoveryCodes]);
    assert(await admin.locator('body').evaluate((element, values) => values.every(value => !element.textContent.includes(value)), [adminSecret, ...adminRecoveryCodes]));
    await admin.reload({ waitUntil: 'domcontentloaded', timeout: 15000 });
    await admin.waitForURL(url => url.pathname === '/admin', { timeout: 15000 });
    await ready(admin);
    assert(await admin.locator('body').evaluate((element, material) => !material.some(value => element.textContent.includes(value)), [adminSecret, ...adminRecoveryCodes]));
    done();
    start('admin-totp-native-challenge');
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
    return { unavailable, expectedFaultCounts: { 'account-export-download': exportAbortCount } };
};
module.exports.STAGES = Object.freeze([
    'account-native-update-export-logout', 'reset-native-flash-validation',
    'customer-totp-native-enrol-recovery', 'customer-totp-native-challenge',
    'admin-totp-native-enrol-recovery', 'admin-totp-native-challenge',
]);
module.exports.OPTIONAL_STAGES = Object.freeze(['signed-unsubscribe-native-confirm-complete']);
