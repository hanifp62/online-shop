import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const root = dirname(fileURLToPath(import.meta.url));
const results = [], failures = [];
for (const [w, h] of [[1024,600],[1024,768],[1025,600],[1366,844],[768,600],[390,844],[320,568]]) {
  const page = await browser.newPage({ viewport: { width: w, height: h } });
  await page.goto('http://127.0.0.1:8015/prototype/', { waitUntil: 'networkidle' });
  const m = await page.evaluate(() => {
    const hero = document.querySelector('.editorial-hero'), img = document.querySelector('.hero-media-img'), txt = document.querySelector('.hero-text-pane'), media = document.querySelector('.hero-media-pane');
    const hr = hero.getBoundingClientRect();
    return { heroH: Math.round(hr.height), grid: getComputedStyle(hero).gridTemplateColumns, imgPos: getComputedStyle(img).position, imgH: Math.round(img.getBoundingClientRect().height), mediaH: Math.round(media.getBoundingClientRect().height), textH: Math.round(txt.getBoundingClientRect().height), overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, heroOverflowX: Math.round(hr.right - document.documentElement.clientWidth), heroOverlapsNext: (hero.nextElementSibling?.getBoundingClientRect().top ?? 1e9) < hr.bottom - 1, textClipped: txt.scrollHeight > txt.clientHeight + 1, imageEscapesPane: img.getBoundingClientRect().bottom > media.getBoundingClientRect().bottom + 1 };
  });
  const bad = m.heroH > 1000 && w >= 1024 && w <= 1366 || m.textClipped || m.imageEscapesPane || m.heroOverflowX > 0 || m.heroOverlapsNext;
  results.push({ viewport: `${w}x${h}`, ...m, ok: !bad }); if (bad) failures.push({ viewport: `${w}x${h}`, ...m });
  await page.close();
}
await browser.close();
writeFileSync(join(root, 'hero-run12.json'), JSON.stringify({ results, failures }, null, 2) + '\n');
writeFileSync(join(root, 'hero-run12-exitcode'), failures.length ? '1\n' : '0\n');
console.log(JSON.stringify(results));
process.exitCode = failures.length ? 1 : 0;
