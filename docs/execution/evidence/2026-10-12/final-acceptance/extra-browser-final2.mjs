import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const rows = [];
for (const width of [320, 390, 1025, 1366]) for (const theme of ['light', 'dark']) {
 const page = await browser.newPage({viewport:{width,height:844},reducedMotion:'reduce'});
 await page.addInitScript(t=>localStorage.setItem('zafira_theme',t),theme);
 await page.goto('http://127.0.0.1:8015/',{waitUntil:'networkidle'});
 const footer=page.locator('[data-open-shortcuts]').first();
 await footer.scrollIntoViewIfNeeded();
 // Every footer-bottom control must own all four corners of its centred 44px box (no overlap).
 const hit=await footer.evaluate(btn=>[...btn.closest('.footer-bottom').querySelectorAll('a,button')].flatMap(el=>{el.scrollIntoView({block:'center'});const r=el.getBoundingClientRect(),x=r.left+r.width/2,y=r.top+r.height/2;return [[x-21.5,y-21.5],[x+21.5,y-21.5],[x-21.5,y+21.5],[x+21.5,y+21.5]].map(([x,y])=>{const h=document.elementFromPoint(x,y);return h===el||el.contains(h)});}));
 await footer.scrollIntoViewIfNeeded();
 await footer.focus();await page.keyboard.press('Enter');
 const dialog=await page.locator('#welcome-shortcuts-backdrop').evaluate(el=>getComputedStyle(el).display!=='none');
 await page.keyboard.press('Escape');
 const escaped=await page.locator('#welcome-shortcuts-backdrop').evaluate(el=>getComputedStyle(el).display==='none');
 await page.evaluate(()=>scrollTo(0,0));
 const search=page.locator('[data-open-search]').first();await search.click();
 const input=page.locator('#live-catalog-search-input');await input.scrollIntoViewIfNeeded();await input.focus();
 const searchFocus=await page.evaluate(()=>{const h=document.querySelector('.site-header'),vw=document.documentElement.clientWidth;const out=[...h.querySelectorAll('*')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.right>vw+1||r.left<-1)&&!e.closest('[aria-hidden="true"]')}).map(e=>e.tagName+'.'+e.className);const i=document.querySelector('#live-catalog-search-input').getBoundingClientRect();return {headerOverflowers:out,inputInViewport:i.left>=0&&i.right<=vw,inputFocused:document.activeElement.id==='live-catalog-search-input',scrollW:document.documentElement.scrollWidth-vw};});
 await page.evaluate(()=>scrollTo(0,0));
 await page.screenshot({path:new URL(`extra-search-${width}-${theme}.png`,import.meta.url).pathname});
 const state=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,theme:document.documentElement.dataset.theme,focused:document.activeElement?.outerHTML.slice(0,160), search:document.querySelector('.search-panel')?.getBoundingClientRect().toJSON()}));
 const row={width,theme,hit,dialog,escaped,searchFocus,state,pass:hit.every(Boolean)&&dialog&&escaped&&state.overflow<=1&&searchFocus.scrollW<=1&&searchFocus.inputInViewport&&searchFocus.inputFocused&&!searchFocus.headerOverflowers.length};rows.push(row);
 await page.close();
}
await browser.close();
writeFileSync(new URL('extra-browser-final2.json',import.meta.url),JSON.stringify(rows,null,2)+'\n');
console.log(JSON.stringify(rows.map(({width,theme,pass,hit,state})=>({width,theme,pass,hit,overflow:state.overflow,activeTheme:state.theme}))));
process.exitCode=rows.every(r=>r.pass)?0:1;
