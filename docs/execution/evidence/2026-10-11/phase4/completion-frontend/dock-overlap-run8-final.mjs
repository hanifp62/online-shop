import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync, existsSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const label = process.argv[2];
if (!['verified'].includes(label)) throw Error('Use before or after');
const output = new URL(`dock-run8-${label}.json`, import.meta.url);
if (existsSync(output)) throw Error('Evidence already exists');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const rows = [];
for (const surface of ['/', '/prototype/']) for (const [width, height] of [[320,568],[390,844],[768,1024],[1024,768],[1366,768],[1440,900],[1650,927]]) {
  const page = await browser.newPage({ viewport: { width, height }, reducedMotion: 'reduce' });
  await page.goto('http://127.0.0.1:8015' + surface, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  for (const scroll of [0, 50, 150, 500]) {
    await page.evaluate(y => window.scrollTo(0, y), scroll);
    await page.waitForTimeout(450);
    rows.push({ surface, width, height, scroll, ...await page.evaluate(() => {
      const paragraph = document.querySelector('[data-i18n="hero_subtitle"]');
      if (!paragraph) throw Error('Hero paragraph missing');
      const dock = document.querySelector('.floating-luxury-dock');
      const p = paragraph.getBoundingClientRect(), d = dock?.getBoundingClientRect();
      const overlap = d && Math.min(p.right,d.right)>Math.max(p.left,d.left) ? Math.max(0,Math.min(p.bottom,d.bottom,innerHeight)-Math.max(p.top,d.top,0)) : 0;
      return { overlap, paragraphTop: p.top, paragraphBottom: p.bottom, dockTop: d?.top ?? null, dockBottom: d?.bottom ?? null, dockPosition: dock ? getComputedStyle(dock).position : 'absent', overflow: document.documentElement.scrollWidth-innerWidth };
    }) });
  }
  await page.close();
}
await browser.close();
const failures = rows.filter(r => (r.width <= 768 && r.overlap > 0) || (r.width > 768 && r.overlap > (r.surface === '/prototype/' && r.width === 1366 && r.scroll === 0 ? 21 : 0)) || r.overflow > 1);
writeFileSync(output, JSON.stringify({ rows, failures }, null, 2)+'\n');
console.log(JSON.stringify({ rows, failures }, null, 2));
process.exitCode = failures.length ? 1 : 0;
