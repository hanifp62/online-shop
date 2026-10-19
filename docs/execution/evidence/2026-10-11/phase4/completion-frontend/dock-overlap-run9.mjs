import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync, existsSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const label = process.argv[2];
if (!['before', 'after', 'verified'].includes(label)) throw Error('Use before, after or verified');
const output = new URL(`dock-run9-${label}.json`, import.meta.url);
if (existsSync(output)) throw Error('Evidence already exists');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const rows = [];
for (const surface of ['/', '/prototype/']) for (const [width, height] of [[320,568],[390,844],[768,1024],[1024,768],[1366,768],[1440,900],[1650,927]]) {
  const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
  await page.goto('http://127.0.0.1:8015' + surface, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  for (const scroll of [0, 50, 150]) {
    await page.evaluate(y => window.scrollTo(0, y), scroll);
    await page.waitForTimeout(450);
    rows.push({ surface, width, height, scroll, ...await page.evaluate(() => {
      const dock = document.querySelector('.floating-luxury-dock');
      const d = dock?.getBoundingClientRect();
      // Every element whose text box intersects the fixed dock band counts as overlap.
      let worst = { overlap: 0, selector: null };
      const heroRoot = document.querySelector('[data-i18n="hero_subtitle"]')?.closest('section');
      if (d && heroRoot) for (const node of heroRoot.querySelectorAll('p, h1, h2, h3, h4, span, a, button, li, small, label, input, select, div')) {
        if (node.children.length && node.tagName === 'DIV') continue;
        const style = getComputedStyle(node);
        if (style.visibility === 'hidden' || style.display === 'none' || style.position === 'fixed') continue;
        if (node.closest('.floating-luxury-dock')) continue;
        if (!node.textContent.trim() && node.tagName !== 'INPUT' && node.tagName !== 'SELECT') continue;
        const r = node.getBoundingClientRect();
        if (!r.width || !r.height) continue;
        if (Math.min(r.right, d.right) <= Math.max(r.left, d.left)) continue;
        const overlap = Math.max(0, Math.min(r.bottom, d.bottom, innerHeight) - Math.max(r.top, d.top, 0));
        if (overlap > worst.overlap) worst = { overlap, selector: node.tagName.toLowerCase() + (node.className && typeof node.className === 'string' ? '.' + node.className.trim().split(/\s+/).join('.') : '') };
      }
      const paragraph = document.querySelector('[data-i18n="hero_subtitle"]');
      const p = paragraph?.getBoundingClientRect();
      const heroOverlap = p && d && Math.min(p.right,d.right)>Math.max(p.left,d.left) ? Math.max(0, Math.min(p.bottom,d.bottom,innerHeight)-Math.max(p.top,d.top,0)) : 0;
      return { heroOverlap, worstOverlap: worst.overlap, worstSelector: worst.selector, dockPosition: dock ? getComputedStyle(dock).position : 'absent', overflow: document.documentElement.scrollWidth - innerWidth };
    }) });
  }
  await page.close();
}
await browser.close();
const failures = rows.filter(r => (r.scroll === 0 && (r.heroOverlap > 0.5 || r.worstOverlap > 0.5)) || r.overflow > 1);
writeFileSync(output, JSON.stringify({ rows, failures }, null, 2)+'\n');
console.log(JSON.stringify({ label, failures: failures.length, rows: rows.filter(r => (r.scroll === 0 && (r.heroOverlap > 0.5 || r.worstOverlap > 0.5)) || r.overflow > 1) }, null, 2));
process.exitCode = failures.length ? 1 : 0;
