import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const root=dirname(fileURLToPath(import.meta.url));
const cases=[];
for(const [path,width,height] of [['/',390,844],['/',1366,768],['/prototype/',390,844],['/prototype/',1366,768]]){
 const page=await browser.newPage({viewport:{width,height},reducedMotion:'reduce'}); const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8015'+path,{waitUntil:'networkidle'});
 const selectors=path==='/'?['.overlay','.drawer','.search-panel','.modal','.toast-container']:['#drawer-backdrop','#mobile-nav-drawer','#cart-drawer','#profile-drawer','.modal-overlay','.toast-container'];
 const initial=await page.evaluate(ss=>ss.map(s=>{const e=document.querySelector(s);if(!e)return {s,absent:true};const c=getComputedStyle(e);return {s,visible:c.display!=='none'&&c.visibility!=='hidden'&&+c.opacity>0,ariaHidden:e.getAttribute('aria-hidden'),inert:e.hasAttribute('inert'),...(s==='.toast-container'?{liveRegion:true,childCount:e.children.length}:{})}}),selectors); // toast-container is a permanent live region: gate on empty

 if(path==='/prototype/'&&width===390){
   const opener=page.locator('#btn-mobile-nav-toggle');
   await opener.click(); const opened=await page.locator('#mobile-nav-drawer').evaluate(e=>e.classList.contains('open')); // prototype openDrawer() toggles .open only
   const trap=await page.evaluate(()=>document.querySelector('#mobile-nav-drawer').contains(document.activeElement));
   await page.keyboard.press('Escape'); const escaped=await page.locator('#mobile-nav-drawer').evaluate(e=>!e.classList.contains('open'));
   const restored=await opener.evaluate(e=>document.activeElement===e);
   cases.push({path,width,initial,opened,trap,escaped,restored,errors});
 }else if(path==='/'&&width===390){
   await page.locator('[data-open-cart]').click();const opened=await page.locator('.cart-drawer').evaluate(e=>e.classList.contains('open'));
   const trap=await page.waitForFunction(()=>document.querySelector('.cart-drawer').contains(document.activeElement),null,{timeout:1000}).then(()=>true,()=>false); // openElement() focuses via setTimeout retries
   await page.keyboard.press('Escape');const escaped=await page.locator('.cart-drawer').evaluate(e=>!e.classList.contains('open'));
   cases.push({path,width,initial,opened,trap,escaped,errors});
 }else cases.push({path,width,initial,errors});
 await page.close();
}
await browser.close();
const failures=cases.filter(c=>c.errors.length||c.initial.some(o=>!o.absent&&(o.liveRegion?o.childCount>0:(o.visible||o.ariaHidden==='false')))||c.opened===false||c.trap===false||c.escaped===false||c.restored===false);
writeFileSync(join(root,'overlay-run10.json'),JSON.stringify({cases,failures},null,2)+'\n');
writeFileSync(join(root,'overlay-run10-exitcode'),String(failures.length?1:0)+'\n');
console.log(JSON.stringify({cases:cases.length,failures},null,2));
process.exitCode=failures.length?1:0;
