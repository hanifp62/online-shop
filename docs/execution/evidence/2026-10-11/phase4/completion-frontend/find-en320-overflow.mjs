// Bisection: hide each subtree and find the deepest element whose removal ends root horizontal scroll.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const [lang = 'en', width = '320', path = '/'] = process.argv.slice(2);
const ctx = await browser.newContext({ viewport: { width: Number(width), height: 568 }, reducedMotion: 'reduce' });
await ctx.addInitScript(l => localStorage.setItem('zafira_lang', l), lang);
const page = await ctx.newPage();
await page.goto('http://127.0.0.1:8015' + path, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
console.log(JSON.stringify(await page.evaluate(() => {
  const de = document.documentElement, over = () => de.scrollWidth - de.clientWidth;
  const start = over(), chain = [];
  let node = document.body;
  while (node) {
    let culprit = null;
    for (const child of node.children) {
      const old = child.style.display; child.style.display = 'none';
      const fixed = over() <= 0; child.style.display = old;
      if (fixed) { culprit = child; break; }
    }
    if (!culprit) break;
    const r = culprit.getBoundingClientRect(), s = getComputedStyle(culprit);
    chain.push({ tag: culprit.tagName, id: culprit.id, cls: String(culprit.className).slice(0, 70), left: +r.left.toFixed(1), right: +r.right.toFixed(1), width: +r.width.toFixed(1), whiteSpace: s.whiteSpace, minWidth: s.minWidth, text: culprit.textContent.trim().replace(/\s+/g, ' ').slice(0, 70) });
    node = culprit;
  }
  return { start, chain };
}), null, 2));
await browser.close();
