// Customer logout in a real browser against an isolated temporary SQLite app only.
// Usage: BASE=http://127.0.0.1:8013 QA_PASSWORD_FILE=<0600 file> node logout-e2e.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const base = process.env.BASE || 'http://127.0.0.1:8013';
const password = readFileSync(process.env.QA_PASSWORD_FILE, 'utf8').trim();
const dir = dirname(fileURLToPath(import.meta.url));
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const results = [];
let failures = 0;
const route = page => new URL(page.url()).pathname;
function check(label, actual, expected) {
  const pass = actual === expected;
  results.push({ label, actual, expected, pass });
  if (!pass) failures++;
}
try {
  for (const [label, viewport] of [['desktop', { width: 1440, height: 900 }], ['mobile', { width: 320, height: 568 }]]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.goto(base + '/akun'); check(label + ' anonymous account redirect', route(page), '/login');
    await page.locator('input[name=email]').fill('qa.logout@example.test');
    await page.locator('input[name=password]').fill(password);
    await page.locator('button[type=submit]').click();
    await page.goto(base + '/akun'); check(label + ' authenticated account', route(page), '/akun');
    const form = page.locator('form[action$="/logout"]');
    check(label + ' logout form present', await form.count() > 0, true);
    check(label + ' csrf token present', await form.locator('input[name=_token]').count() > 0, true);
    const before = await page.locator('input[name=_token]').first().inputValue();
    await form.locator('button[type=submit]').click();
    await page.waitForLoadState();
    check(label + ' logout leaves account page', route(page) === '/akun', false);
    await page.goto(base + '/akun'); check(label + ' session revoked after logout', route(page), '/login');
    await page.goto(base + '/login');
    const after = await page.locator('input[name=_token]').first().inputValue();
    check(label + ' session token rotated', after !== before, true);
    await page.screenshot({ path: join(dir, `logout-${label}-redacted.png`), fullPage: true, mask: [page.locator('input:not([type=hidden]), .auth-card h1')], maskColor: '#17263f' });
    await context.close();
  }
} finally {
  await browser.close();
  writeFileSync(join(dir, 'logout-results.json'), JSON.stringify({ base, failures, results }, null, 2));
}
console.log(`${results.length - failures}/${results.length} logout assertions passed`);
process.exit(failures ? 2 : 0);
