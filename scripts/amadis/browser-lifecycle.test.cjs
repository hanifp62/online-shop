'use strict';
// Unit tests for scripts/amadis/browser-lifecycle.cjs cleanup helpers.
// Fakes child/process injection; no server, browser, launcher, credentials or app run.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {stopOwned, cleanup, isLoginContext} = require('./browser-lifecycle.cjs');

// Fake child exposing the subset stopOwned reads: pid + exitCode/signalCode.
function fakeChild({pid = 4242, exitCode = null, signalCode = null} = {}) {
  return {pid, exitCode, signalCode};
}

test('EPERM is not treated as stopped', async () => {
  const log = [];
  const kill = () => { const e = new Error('op not permitted'); e.code = 'EPERM'; throw e; };
  await assert.rejects(
    () => stopOwned(fakeChild(), {kill, records: log, groupPolls: 1, pollMs: 0}),
    e => e.code === 'EPERM',
  );
  assert.deepEqual(log, []);
});

test('ESRCH means group already gone: returns without waiting', async () => {
  const log = [];
  const kill = (pid, sig) => { const e = new Error('no such process'); e.code = 'ESRCH'; throw e; };
  await stopOwned(fakeChild(), {kill, records: log});
  assert.deepEqual(log, [{command: ['stop-owned-group', '4242'], exit: 0}]);
});

test('group that ignores SIGTERM exhausts polls and fails', async () => {
  const kill = (pid, sig) => { if (sig === 0) return; /* SIGTERM accepted, group lingers */ };
  await assert.rejects(
    () => stopOwned(fakeChild(), {kill, groupPolls: 3, pollMs: 0}),
    /did not stop/,
  );
});

test('group exits during polling: reports stop-owned-group', async () => {
  let checks = 0;
  const kill = (pid, sig) => {
    if (sig === 0 && ++checks >= 2) { const e = new Error('no such process'); e.code = 'ESRCH'; throw e; }
  };
  const log = [];
  await stopOwned(fakeChild(), {kill, records: log, pollMs: 0});
  assert.deepEqual(log, [{command: ['stop-owned-group', '4242'], exit: 0}]);
});

test('cleanup runs every step even when an earlier one rejects', async () => {
  const calls = [];
  const failure = await cleanup([
    ['a', async () => { calls.push('a'); throw new Error('boom'); }],
    ['b', async () => { calls.push('b'); }],
    ['c', async () => { calls.push('c'); throw new Error('also'); }],
  ]);
  assert.deepEqual(calls, ['a', 'b', 'c']);
  assert.deepEqual(failure, {step: 'a', error_type: 'Error'});
});

test('cleanup returns null on total success', async () => {
  const calls = [];
  const failure = await cleanup([['ok', async () => { calls.push('ok'); }]]);
  assert.equal(failure, null);
  assert.deepEqual(calls, ['ok']);
});

test('cleanup bounds a hung step', async () => {
  const failure = await cleanup([['hang', () => new Promise(() => {})]], 50);
  assert.deepEqual(failure, {step: 'hang', error_type: 'TimeoutError'});
});

test('isLoginContext guards login pages and phases', () => {
  assert.equal(isLoginContext('/login', 'S1-login'), true);
  assert.equal(isLoginContext('/admin/login', 'S5-persistence'), true);
  assert.equal(isLoginContext('/belanja', 'S1-login'), true);
  assert.equal(isLoginContext('/belanja', 'S2-checkout'), false);
  assert.equal(isLoginContext('', 'setup'), false);
});

test('finalResult gates PASS on cleanup error', () => {
  const {finalResult} = require('./browser-lifecycle.cjs');
  const passing = {status: 'PASS', S0: 'PASS', S1: 'PASS', S2: 'PASS', S3: 'PASS', S4: 'PASS', S5: 'PASS'};
  const blocked = finalResult(passing, {step: 'browser.close', error_type: 'TimeoutError'});
  assert.equal(blocked.status, 'BLOCKED');
  assert.equal(blocked.phase, 'cleanup');
  assert.equal(blocked.step, 'browser.close');

  const cleanPass = finalResult(passing, null);
  assert.equal(cleanPass.status, 'PASS');

  const missingOutcome = finalResult(undefined, null);
  assert.equal(missingOutcome.status, 'BLOCKED');
});
