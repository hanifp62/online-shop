import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const root = dirname(fileURLToPath(import.meta.url));
const rows = [];
const ratio = (fg,bg) => { const l = a => { const [r,g,b] = a.map(v => { v/=255; return v<=.04045 ? v/12.92 : ((v+.055)/1.055)**2.4; }); return .2126*r+.7152*g+.0722*b; }; const [a,b]=[l(fg),l(bg)]; return +((Math.max(a,b)+.05)/(Math.min(a,b)+.05)).toFixed(2); };
const rgb = s => s.match(/[\d.]+/g).slice(0,3).map(Number);
for (const [surface,width,height] of [['/',390,844],['/',1366,768],['/prototype/',390,844],['/prototype/',1366,768]]) {
  const page = await browser.newPage({ viewport:{width,height}, reducedMotion:'reduce' });
  await page.goto('http://127.0.0.1:8015'+surface,{waitUntil:'networkidle'});
  const items = await page.evaluate(() => {
    const cases = surface => surface === '/' ? [
      ['#welcome-monogram-counter','#d9ccba'], ['#monogram-hint','#ddd2c3'], ['.newsletter-section small','#263a5d'], ['#live-catalog-search-input::placeholder','#fefefd'],
    ] : [
      ['.sf-footer input[type="email"]::placeholder','#1c232f'], ['.diptych-origin','#f8f6f1'], ['.product-cat','#fffdfa'], ['.hero-eyebrow','#f8f6f1'],
    ];
    const out=[];
    for(const [selector,bgHex] of cases(location.pathname.includes('prototype')?'/prototype/':'/')) {
      const pseudo=selector.includes('::'); const base=selector.replace(/::.*$/,''); const el=document.querySelector(base); if(!el) {out.push({selector,missing:true});continue;}
      const s=getComputedStyle(el,pseudo?'::placeholder':null); const fg=s.color; const bg=getComputedStyle(el).backgroundColor;
      out.push({selector,text:(el.textContent||el.placeholder||'').trim().slice(0,60),color:fg,background:bg,declaredBackground:bgHex,fontSize:s.fontSize,fontWeight:s.fontWeight,ratio:0});
    }
    return out;
  });
  for (const item of items) if(!item.missing) item.ratio=ratio(rgb(item.color),rgb(item.declaredBackground.startsWith('#')?`rgb(${parseInt(item.declaredBackground.slice(1,3),16)}, ${parseInt(item.declaredBackground.slice(3,5),16)}, ${parseInt(item.declaredBackground.slice(5,7),16)})`:item.declaredBackground));
  rows.push({surface,width,items}); await page.close();
}
await browser.close();
writeFileSync(join(root,'contrast-final2.json'),JSON.stringify(rows,null,2)+'\n');
console.log(JSON.stringify(rows,null,2));
