// Run only against the isolated restore DB server; never save unmasked authenticated pages.
// Usage: QA_PASSWORD_FILE=<0600-file> node auth-e2e.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const base = 'http://127.0.0.1:8013';
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const password = readFileSync(process.env.QA_PASSWORD_FILE, 'utf8').trim();
const dir = dirname(fileURLToPath(import.meta.url));
const browser = await chromium.launch({ executablePath: exe });
const results = [];
const layout = [];
let failures = 0;
function check(label, actual, expected) {
  const pass = actual === expected;
  results.push({ label, actual, expected, pass });
  if (!pass) failures++;
}
const route = page => new URL(page.url()).pathname;
async function shot(page, name) {
  const sensitive = page.locator('tbody, input:not([type=hidden]), textarea, .admin-header p, .auth-card h1, .account-heading, .account-sidebar');
  layout.push({ shot: name, viewport: page.viewportSize(), overflowX: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) });
  await page.screenshot({ path: join(dir, name + '.png'), fullPage: true, mask: [sensitive], maskColor: '#17263f' });
}
async function login(page, path, email, pass = password) {
  await page.goto(base + path);
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(pass);
  await page.locator('button[type=submit]').click();
}
try {
  for (const [label, viewport] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 320, height: 568 }]]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.goto(base + '/akun'); check(label + ' anonymous account redirect', route(page), '/login');
    await page.goto(base + '/admin'); check(label + ' anonymous admin redirect', route(page), '/admin/login');
    await login(page, '/login', 'qa.synthetic@example.test', 'definitely-incorrect');
    check(label + ' wrong user password stays login', route(page), '/login');
    await page.goto(base + '/akun'); check(label + ' wrong password no user session', route(page), '/login');
    await login(page, '/admin/login', 'qa.admin@example.test', 'definitely-incorrect');
    check(label + ' wrong admin password stays login', route(page), '/admin/login');
    await page.goto(base + '/admin'); check(label + ' wrong password no admin session', route(page), '/admin/login');
    await login(page, '/login', 'qa.synthetic@example.test');
    check(label + ' user login destination', route(page), '/');
    await page.goto(base + '/akun'); check(label + ' user account', route(page), '/akun');
    await shot(page, label + '-account-redacted');
    await page.goto(base + '/admin'); check(label + ' user cannot access admin', route(page), '/admin/login');
    await login(page, '/admin/login', 'qa.admin@example.test');
    check(label + ' admin login destination', route(page), '/admin');
    await page.goto(base + '/admin'); check(label + ' admin dashboard', route(page), '/admin');
    await shot(page, label + '-admin-redacted');
    await page.goto(base + '/akun'); check(label + ' dual guard account survives admin login', route(page), '/akun');
    await page.goto(base + '/admin');
    await page.locator('form[action$="/admin/logout"] button').click();
    check(label + ' admin logout destination', route(page), '/admin/login');
    await page.goto(base + '/admin'); check(label + ' admin logout revoked', route(page), '/admin/login');
    await page.goto(base + '/akun'); check(label + ' user survives admin logout', route(page), '/akun');
    // The missing /akun logout control exhausted the three-attempt budget; keep explicit blocker.
    results.push({ label: label + ' user logout', status: 'NOT RUN', reason: 'No logout form on account page; three runner attempts exhausted.' });
    await context.close();
    const adminOnly = await browser.newContext({ viewport });
    const adminPage = await adminOnly.newPage();
    await login(adminPage, '/admin/login', 'qa.admin@example.test');
    await adminPage.goto(base + '/akun'); check(label + ' admin-only cannot access account', route(adminPage), '/login');
    await adminOnly.close();
  }
} catch (error) {
  failures++;
  results.push({ label: 'runner exception', pass: false, errorType: error.constructor.name, message: String(error.message).split('\n')[0].replaceAll(password, '[redacted]') });
} finally {
  await browser.close();
  writeFileSync(join(dir, 'results.json'), JSON.stringify({ cases: results.length, failures, results, layout }, null, 2));
  console.log(JSON.stringify({ cases: results.length, failures, passed: results.filter(r => r.pass === true).length, blocked: results.filter(r => r.status === 'NOT RUN').length, failedLabels: results.filter(r => r.pass === false).map(r => r.label) }));
}
// Incomplete coverage must not be reported as a fully passing run.
process.exitCode = failures ? 1 : results.some(r => r.status === 'NOT RUN') ? 2 : 0;
