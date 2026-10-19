#!/usr/bin/env node
/** Isolated browser download: admin orders PDF report. Application commands only run through launch.sh. */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {spawn, spawnSync} = require('node:child_process');

const isMain = require.main === module;
const repo = path.resolve(__dirname, '../..');
const base = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode';
const port = 8016;
const origin = `http://127.0.0.1:${port}`;
const run = 'pdf-download-' + new Date().toISOString().replace(/[-:.]/g, '');
let root = '', out = '';

if (isMain) {
  root = fs.realpathSync(process.env.AMADIS_ROOT || '');
  assert.equal(path.dirname(root), base);
  assert.match(path.basename(root), /^amadis-[a-f0-9]{24}$/);
  const identity = JSON.parse(fs.readFileSync(path.join(root, 'identity.json')));
  assert.equal(identity.root, root);
  assert.equal(identity.database, path.join(root, 'database.sqlite'));
  out = path.join(root, 'evidence', run);
  fs.mkdirSync(out, {mode: 0o700});
}

const records = [];
let server, browser, activePage, phase = 'setup';

function save(name, value) {
  fs.writeFileSync(path.join(out, name), JSON.stringify(value, null, 2) + '\n', {flag: 'wx', mode: 0o600});
}

function command(args) {
  const p = spawnSync('./scripts/amadis/launch.sh', args, {
    cwd: repo, env: {...process.env, AMADIS_ROOT: root}, encoding: 'utf8', timeout: 30000,
  });
  records.push({command: ['launch.sh', ...args], exit: p.status});
  assert.equal(p.status, 0, 'launcher failed');
  return p.stdout;
}

async function start() {
  // Any listener (even one slow to answer) means a stale server; refuse instead of racing it.
  const probe = spawnSync('/usr/sbin/lsof', ['-nP', `-iTCP:${port}`, '-sTCP:LISTEN'], {encoding: 'utf8'});
  if (probe.stdout.trim()) throw new Error('port occupied');
  server = spawn('./scripts/amadis/launch.sh', ['serve', String(port)], {
    cwd: repo, env: {...process.env, AMADIS_ROOT: root}, detached: true, stdio: 'ignore',
  });
  const pid = server.pid;
  records.push({command: ['launch.sh', 'serve', String(port)], pid});
  for (let n = 0; n < 80; n++) {
    assert.equal(server.exitCode, null, 'server exited');
    try { if ((await fetch(origin + '/koleksi', {signal: AbortSignal.timeout(1000)})).ok) return; }
    catch {}
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  throw new Error('server not ready');
}

async function stopOwned(own, {kill = process.kill.bind(process), records: log = [], groupPolls = 100, pollMs = 50} = {}) {
  assert.ok(Number.isInteger(own.pid) && own.pid > 1, 'invalid owned process group');
  try { kill(-own.pid, 'SIGTERM'); } catch (error) { if (error.code !== 'ESRCH') throw error; }
  for (let n = 0; n < groupPolls; n++) {
    try { kill(-own.pid, 0); }
    catch (error) {
      if (error.code !== 'ESRCH') throw error;
      log.push({command: ['stop-owned-group', String(own.pid)], exit: 0});
      return;
    }
    await new Promise(resolve => setTimeout(resolve, pollMs));
  }
  throw new Error('owned process group did not stop');
}

async function stop() {
  if (!server) return;
  const own = server;
  server = undefined;
  await stopOwned(own, {records});
}

async function cleanup(steps, timeoutMs = 6000) {
  let failure = null;
  for (const [step, fn] of steps) {
    let timer;
    try {
      await Promise.race([
        Promise.resolve().then(fn),
        new Promise((_, reject) => { timer = setTimeout(() => reject(Object.assign(new Error('cleanup timeout'), {name: 'TimeoutError'})), timeoutMs); }),
      ]);
    } catch (error) { failure = failure ?? {step, error_type: error?.name ?? 'Error'}; }
    finally { clearTimeout(timer); }
  }
  return failure;
}

function isLoginContext(pathname, currentPhase) {
  return /login/.test(currentPhase || '') || /\/login$/.test(pathname || '');
}

function finalResult(outcome, cleanupError) {
  if (cleanupError) return {status: 'BLOCKED', phase: 'cleanup', ...cleanupError};
  return outcome ?? {status: 'BLOCKED', phase: 'unknown', error_type: 'Error'};
}

async function login(page, role, credentials) {
  await page.goto(origin + (role === 'admin' ? '/admin/login' : '/login'));
  await page.locator('#email').fill(credentials[role].email);
  await page.locator('#password').fill(credentials[role].password);
  await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')),
    page.locator('button[type="submit"]').click()]);
}

let outcome;
if (isMain) (async () => {
  // Verify Playwright version (fast, no server needed)
  const version = spawnSync('/opt/homebrew/bin/playwright-cli', ['--version'], {encoding:'utf8'});
  assert.equal(version.status, 0);
  assert.equal(version.stdout.trim(), '0.1.22');

  // Read credentials with strict checks
  const credentialPath = path.join(root, 'private-runtime/credentials.json');
  const fd = fs.openSync(credentialPath, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW);
  const info = fs.fstatSync(fd);
  assert.equal(info.mode & 0o777, 0o600);
  assert.equal(info.nlink, 1);
  const credentials = JSON.parse(fs.readFileSync(fd, 'utf8'));
  fs.closeSync(fd);

  phase = 'download';
  await start();

  const executablePath = path.join(os.homedir(), 'Library/Caches/ms-playwright/chromium-1228/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing');
  const {chromium} = require('/opt/homebrew/lib/node_modules/@playwright/cli/node_modules/playwright');
  browser = await chromium.launch({executablePath, headless: true});
  const context = await browser.newContext({viewport: {width: 1365, height: 1000}, locale: 'id-ID'});
  await context.route('**/*', route => route.request().url().startsWith(origin + '/') ? route.continue() : route.abort());
  activePage = await context.newPage();
  activePage.setDefaultTimeout(15000);

  // Login as admin
  phase = 'login';
  await login(activePage, 'admin', credentials);
  phase = 'download';

  // Go to dashboard and submit report form
  await activePage.goto(origin + '/admin');
  
  // Wait for the order panel to appear
  const panel = activePage.locator('#panel-pesanan');
  await panel.waitFor();
  
  // Fixture orders sit on 2026-10-13 UTC; use the form's custom range.
  await activePage.locator('#report-period').selectOption('custom');
  await activePage.locator('#report-from').fill('2026-10-13');
  await activePage.locator('#report-to').fill('2026-10-13');

  // Intercept the download triggered by form submit
  const [dl] = await Promise.all([
    activePage.waitForEvent('download'),
    activePage.getByRole('button', {name: 'Unduh PDF', exact: true}).click(),
  ]);
  
  const filePath = path.join(out, 'orders-20261013.pdf');
  await dl.saveAs(filePath);
  await dl.delete();
  fs.chmodSync(filePath, 0o600);

  const bytes = fs.readFileSync(filePath);
  assert.equal(bytes.subarray(0, 5).toString(), '%PDF-');
  const sha256 = crypto.createHash('sha256').update(bytes).digest('hex');
  const meta = {
    period: 'custom', from: '2026-10-13', to: '2026-10-13',
    suggested_filename: dl.suggestedFilename(), file_size: bytes.length, sha256,
    downloaded_at_utc: new Date().toISOString(),
  };
  save("download.json", meta);
  outcome = {status: 'PASS', phase: "download", ...meta};

})().catch(async error => {
  let where = null;
  try {
    where = new URL(activePage.url()).pathname;
    if (!isLoginContext(where, phase)) {
      await activePage.screenshot({path: path.join(out, 'failure.png')});
    }
  } catch {}
  // Login errors may echo filled values, so their message is never recorded.
  const message = isLoginContext(where, phase) ? '[REDACTED: login phase]' : String(error?.message ?? '').slice(0, 500);
  outcome = {status: 'BLOCKED', phase, error_type: error.name, path: where, message};
  process.exitCode = 1;
}).finally(async () => {
  const cleanupError = await cleanup([
    ['browser.close', () => browser?.close()],
    ['stop', stop],
  ]);
  if (cleanupError) process.exitCode = 1;
  const result = finalResult(outcome, cleanupError);
  save('result.json', result);
  save('commands.json', records);
  const hashes = {};
  for (const name of fs.readdirSync(out)) hashes[name] = crypto.createHash('sha256').update(fs.readFileSync(path.join(out,name))).digest('hex');
  save('sha256.json', hashes);
  const exit = process.exitCode || (cleanupError ? 1 : 0);
  console.log(JSON.stringify({evidence: out, exit, cleanupError: cleanupError ?? undefined}));
});

module.exports = {stopOwned, cleanup, isLoginContext, finalResult};
