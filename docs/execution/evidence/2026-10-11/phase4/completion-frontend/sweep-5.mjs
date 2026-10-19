import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const root = dirname(fileURLToPath(import.meta.url));
const out = join(root, 'shots-sweep-5'); mkdirSync(out, { recursive: true });
const base = 'http://127.0.0.1:8015';
const dimensions = [[320,568],[390,844],[768,1024],[1024,768],[1366,768],[1440,900],[1650,927]];
const results = [], failures = [];
for (const surface of ['/', '/prototype/']) for (const lang of ['id','en','fr','it']) for (const [width,height] of dimensions) {
  const ctx = await browser.newContext({ viewport: {width,height}, reducedMotion: 'reduce' });
  await ctx.addInitScript(l => { if (!sessionStorage.getItem('audit-seeded')) { localStorage.setItem('zafira_lang', l); sessionStorage.setItem('audit-seeded','1'); } window.__shifts=[]; window.__lcp=null; new PerformanceObserver(list => { const e=list.getEntries().at(-1); if(e) window.__lcp=e.startTime; }).observe({type:'largest-contentful-paint',buffered:true}); new PerformanceObserver(list => { for (const e of list.getEntries()) if(!e.hadRecentInput) window.__shifts.push(e.value); }).observe({type:'layout-shift',buffered:true}); },lang);
  const page = await ctx.newPage(); const errors=[];
  page.on('pageerror', e => errors.push(e.message)); page.on('console', m => {if(m.type()==='error')errors.push(m.text());}); page.on('requestfailed',r=>errors.push(r.url()+': '+r.failure()?.errorText));
  try {
    const response=await page.goto(base+surface,{waitUntil:'networkidle',timeout:20000});
    await page.waitForTimeout(250);
    const state=await page.evaluate(() => {
      const visible=e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none';};
      const res=performance.getEntriesByType('resource'),nav=performance.getEntriesByType('navigation')[0];
      const controls=[...document.querySelectorAll('a,button,input,select,[role="button"]')].filter(visible).map(e=>{const r=e.getBoundingClientRect();return {label:(e.getAttribute('aria-label')||e.textContent||e.name||'').trim().slice(0,40),w:+r.width.toFixed(1),h:+r.height.toFixed(1),tag:e.tagName};});
      const translations=[...document.querySelectorAll('[data-i18n]')].filter(visible).map(e=>({key:e.dataset.i18n,text:e.textContent.trim().slice(0,80)}));
      return {lang:document.documentElement.lang,stored:localStorage.getItem('zafira_lang'),overflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,overflowers:[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.right>document.documentElement.clientWidth+1&&!e.closest('.marquee-track,[aria-hidden="true"]')&&getComputedStyle(e).position!=='fixed';}).slice(0,5).map(e=>e.tagName+'.'+e.className),translations,smallTargets:controls.filter(x=>x.w<44||x.h<44).slice(0,30),controlCount:controls.length,resourceCount:res.length,transferBytes:res.reduce((n,r)=>n+r.transferSize,0),encodedBytes:res.reduce((n,r)=>n+r.encodedBodySize,0),timing:{responseStart:nav?.responseStart,domContentLoaded:nav?.domContentLoadedEventEnd,load:nav?.loadEventEnd,lcp:window.__lcp},cls:window.__shifts.reduce((a,b)=>a+b,0)};
    });
    let switched=null;
    if(width===390&&lang==='id') { if(surface==='/') await page.locator('.mobile-menu-button').click(); else await page.locator('#btn-mobile-nav-toggle').click(); const b=page.locator('[data-set-lang="fr"]').last(); if(await b.count()){await b.click(); switched=await page.evaluate(()=>({lang:document.documentElement.lang,stored:localStorage.getItem('zafira_lang')})); await page.reload({waitUntil:'networkidle'}); switched.afterRefresh=await page.evaluate(()=>({lang:document.documentElement.lang,stored:localStorage.getItem('zafira_lang')}));} }
    if(width===390||width===1440) await page.screenshot({path:join(out,`${surface==='/'?'store':'prototype'}-${lang}-${width}.png`),fullPage:true});
    const row={surface,lang,viewport:`${width}x${height}`,status:response?.status(),...state,switched,errors}; results.push(row);
    if(row.status!==200||state.lang!==lang||state.stored!==lang||state.overflow>1||errors.length||switched&&(switched.afterRefresh.lang!=='fr'||switched.afterRefresh.stored!=='fr'))failures.push(row);
  } catch(e){failures.push({surface,lang,viewport:`${width}x${height}`,error:e.message});} finally{await ctx.close();}
}
await browser.close();
writeFileSync(join(root,'sweep-5-results.json'),JSON.stringify({results,failures},null,2)+'\n');
writeFileSync(join(root,'sweep-5-summary.json'),JSON.stringify({cases:results.length,failures:failures.length,maxCls:Math.max(...results.map(x=>x.cls)),maxLcp:Math.max(...results.map(x=>x.timing.lcp||0)),maxOverflow:Math.max(...results.map(x=>x.overflow))},null,2)+'\n');
writeFileSync(join(root,'sweep-5-exitcode'),String(failures.length?1:0)+'\n');
console.log(JSON.stringify({cases:results.length,failures:failures.map(x=>({surface:x.surface,lang:x.lang,viewport:x.viewport,overflow:x.overflow,errors:x.errors,error:x.error,switched:x.switched})),screenshots:32}));
process.exitCode=failures.length?1:0;
