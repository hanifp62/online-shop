import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { readFileSync } from 'node:fs';
import monitor from '../scripts/amadis/acceptance_react_monitor.cjs';

for (const consoleFirst of [true, false]) test(`expected abort requires actual request correlation, console first=${consoleFirst}`, () => {
    const page = new EventEmitter(); let errors = 0; const seen = [];
    const finish = monitor(page, { stage: () => 'checkout', unexpected: () => errors++, expectedNetworkFailure: value => {
        seen.push(value); return value.requestURL === 'http://localhost/checkout' && value.method === 'POST'
            && value.stage === 'checkout' && /ERR_FAILED/.test(value.errorText);
    } });
    const request = { url: () => 'http://localhost/checkout', method: () => 'POST', failure: () => ({ errorText: 'net::ERR_FAILED' }) };
    const message = { type: () => 'error', location: () => ({ url: request.url() }), text: () => 'Failed to load resource: net::ERR_FAILED' };
    page.emit('request', request);
    if (consoleFirst) page.emit('console', message);
    page.emit('requestfailed', request);
    if (!consoleFirst) page.emit('console', message);
    finish(); assert.equal(errors, 0); assert.equal(seen.length, 2);
});
test('unrelated console/page errors and uncorrelated matching text cannot be suppressed', () => {
    const page = new EventEmitter(); let errors = 0; let calls = 0;
    const finish = monitor(page, { stage: () => 'checkout', unexpected: () => errors++, expectedNetworkFailure: () => { calls++; return true; } });
    page.emit('console', { type: () => 'error', location: () => ({ url: 'http://localhost/checkout' }), text: () => 'Failed to load resource: net::ERR_FAILED' });
    page.emit('pageerror', new Error('synthetic'));
    finish(); assert.equal(errors, 2); assert.equal(calls, 0);
});
test('negative response status is correlated and a stage change cannot borrow previous failure', () => {
    const page = new EventEmitter(); let errors = 0; let current = 'quote'; const seen = [];
    const finish = monitor(page, { stage: () => current, unexpected: () => errors++, expectedNetworkFailure: value => { seen.push(value); return value.status === 422; } });
    const request = { url: () => 'http://localhost/checkout/quote', method: () => 'POST' };
    page.emit('request', request); page.emit('response', { request: () => request, status: () => 422 });
    const message = { type: () => 'error', location: () => ({ url: request.url() }), text: () => '422' };
    page.emit('console', message); current = 'other'; page.emit('console', message); finish();
    assert.equal(errors, 1); assert.equal(seen.length, 1); assert.equal(seen[0].method, 'POST');
});
test('console cannot borrow a future request, and a request cannot borrow a future stage', () => {
    const page = new EventEmitter(); let errors = 0; let current = 'before'; let accepted = 0;
    const finish = monitor(page, { stage: () => current, unexpected: () => errors++, expectedNetworkFailure: () => { accepted++; return true; } });
    const request = { url: () => 'http://localhost/checkout', method: () => 'POST', failure: () => ({ errorText: 'net::ERR_FAILED' }) };
    page.emit('console', { type: () => 'error', location: () => ({ url: request.url() }), text: () => 'Failed to load resource: net::ERR_FAILED' });
    page.emit('request', request); current = 'checkout'; page.emit('requestfailed', request); finish();
    assert.equal(errors, 2); assert.equal(accepted, 0);
});
test('final drain includes late console and page errors received during close', async () => {
    const page = new EventEmitter(); let errors = 0;
    const finish = monitor(page, { stage: () => 'console-clean', unexpected: () => errors++, expectedNetworkFailure: () => false });
    await Promise.resolve().then(() => {
        page.emit('console', { type: () => 'error', location: () => ({ url: '' }), text: () => 'late' });
        page.emit('pageerror', new Error('late'));
    });
    finish(); assert.equal(errors, 2);
    const source = readFileSync(new URL('../scripts/amadis/acceptance_react.cjs', import.meta.url), 'utf8');
    const closing = source.slice(source.indexOf('await browser?.close()'));
    for (const anchor of ['monitorFinalizers.forEach', "checks.push('console-clean')", 'outcome.consoleErrors = consoleErrors', 'fs.writeFileSync']) assert.ok(closing.includes(anchor));
    assert.ok(closing.indexOf('monitorFinalizers.forEach') < closing.indexOf("checks.push('console-clean')"));
    assert.ok(closing.indexOf('outcome.consoleErrors = consoleErrors') < closing.indexOf('fs.writeFileSync'));
});
