import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const results = [];
for (const lang of ['id','en','fr','it']) for (const width of [320,390,768,1024,1025,1030,1100,1150,1200,1201,1250,1300,1366,1440]) {
  const page = await browser.newPage({ viewport: { width, height: 768 } });
  await page.addInitScript(lang => localStorage.setItem('zafira_lang', lang), lang);
  await page.goto('http://127.0.0.1:8015/prototype/', { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const state = await page.evaluate(() => {
    const rect = s => { const r = document.querySelector(s).getBoundingClientRect(); return {left:r.left,right:r.right,width:r.width}; };
    return { overflow:document.documentElement.scrollWidth-innerWidth, nav:rect('.sf-nav'),brand:rect('.sf-brand'),actions:rect('.sf-actions'),grid:getComputedStyle(document.querySelector('.sf-header')).gridTemplateColumns };
  });
  results.push({lang,width,...state,ok:state.overflow===0 && state.actions.right<=width && (!state.nav.width || state.nav.right<=state.brand.left+1) && state.brand.right<=state.actions.left+1});
  await page.close();
}
await browser.close();
const failures = results.filter(r=>!r.ok);
writeFileSync(new URL('header-breakpoint.json',import.meta.url),JSON.stringify({results,failures},null,2)+'\n');
console.log(JSON.stringify({cases:results.length,failures}));
process.exitCode=failures.length?1:0;
