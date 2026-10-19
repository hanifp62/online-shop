import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require=createRequire(import.meta.url);
const {chromium}=require(execFileSync('npm',['root','-g'],{encoding:'utf8'}).trim()+'/@playwright/cli/node_modules/playwright-core');
const browser=await chromium.launch({executablePath:process.env.HOME+'/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell'});
const root=dirname(fileURLToPath(import.meta.url)), label=process.argv[2]||'before', rows=[];
for(const surface of ['/','/prototype/']) for(const lang of ['id','en','fr','it']) for(const width of (surface==='/'?[320,390,768,1024]:[1024,1366,1440,1650])) {
 const page=await browser.newPage({viewport:{width,height:width===390?844:768},reducedMotion:'reduce'});
 await page.addInitScript(l=>localStorage.setItem('zafira_lang',l),lang);
 await page.goto('http://127.0.0.1:8015'+surface,{waitUntil:'networkidle'}); await page.evaluate(()=>document.fonts.ready);
 const state=await page.evaluate(()=>{
  const rect=e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,right:r.right,bottom:r.bottom}};
  const color=s=>s.match(/[\d.]+/g).map(Number);
  const mix=(f,b)=>f.slice(0,3).map((c,i)=>c*(f[3]??1)+b[i]*(1-(f[3]??1)));
  const lum=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4}).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0);
  function background(e){const chain=[];for(;e;e=e.parentElement)chain.unshift(e);return chain.reduce((b,e)=>mix(color(getComputedStyle(e).backgroundColor),b),[255,255,255]);}
  const footer=[...document.querySelectorAll('footer a,footer p,footer small,footer span,footer button')].filter(e=>e.getBoundingClientRect().width&&e.textContent.trim()).map(e=>{const s=getComputedStyle(e),bg=background(e),fg=mix(color(s.color),bg),a=lum(fg),b=lum(bg);return {selector:e.tagName+'.'+e.className,text:e.textContent.trim().slice(0,100),color:s.color,bg,ratio:+((Math.max(a,b)+.05)/(Math.min(a,b)+.05)).toFixed(2)};});
  const input=document.querySelector('.sf-header input')||document.querySelector('input[data-i18n-placeholder="search_placeholder"]');
  let search=null;if(input){const s=getComputedStyle(input),c=document.createElement('canvas').getContext('2d');c.font=s.font;search={text:input.placeholder,rect:rect(input),available:input.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight),needed:c.measureText(input.placeholder).width};}
  const dock=document.querySelector('.floating-luxury-dock'),dr=dock?.getBoundingClientRect();const overlaps=[];
  if(dr){const walker=document.createTreeWalker(document.querySelector('#view-storefront')||document.body,NodeFilter.SHOW_TEXT);let n;while(n=walker.nextNode()){if(!n.textContent.trim()||n.parentElement.closest('.floating-luxury-dock,script,style'))continue;const range=document.createRange();range.selectNodeContents(n);for(const r of range.getClientRects())if(r.width&&r.height&&r.left<dr.right&&r.right>dr.left&&r.top<dr.bottom&&r.bottom>dr.top)overlaps.push({text:n.textContent.trim(),parent:n.parentElement.className,rect:{x:r.x,y:r.y,w:r.width,h:r.height}});}}
  return {lang:document.documentElement.lang,search,footer,theme:[...document.querySelectorAll('.zafira-theme-select')].map(e=>[...e.options].map(o=>o.text)),images:[...document.querySelectorAll('.product-card img')].map(e=>({alt:e.alt,loading:e.loading,naturalWidth:e.naturalWidth})),dock:dock&&rect(dock),overlaps,overflow:document.documentElement.scrollWidth-innerWidth,overflowers:[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return r.width&&r.right>innerWidth+.1&&r.left<innerWidth&&s.visibility!=='hidden'&&!e.closest('[aria-hidden="true"]')}).map(e=>({tag:e.tagName,cls:e.className,rect:rect(e)})),header:[...document.querySelectorAll('.sf-nav,.sf-nav a,.sf-brand,.sf-brand-sub,.sf-header-right')].map(e=>({cls:e.className,text:e.textContent.trim().slice(0,50),rect:rect(e)}))};
 });
 if(surface==='/') {for(const img of await page.locator('.product-card img').all()){await img.scrollIntoViewIfNeeded();await img.evaluate(e=>e.decode().catch(()=>{}));}state.imagesAfter=await page.locator('.product-card img').evaluateAll(es=>es.map(e=>({alt:e.alt,loading:e.loading,naturalWidth:e.naturalWidth})));}
 if(surface==='/prototype/'&&width===1366)await page.screenshot({path:join(root,`triage-${label}-${lang}-1366-viewport.png`),fullPage:false});
 rows.push({surface,lang,width,...state});await page.close();
}
await browser.close();writeFileSync(join(root,`triage-run10-${label}.json`),JSON.stringify(rows,null,2)+'\n');console.log('measured',rows.length);
