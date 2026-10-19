import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm',['root','-g'],{encoding:'utf8'}).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser=await chromium.launch({executablePath:process.env.HOME+'/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell'});
const out=join(fileURLToPath(import.meta.url).replace(/inspect-viewport\.mjs$/,''),'shots-sweep-4');
for(const [name,url,width,height,lang,drawer] of [
 ['prototype-fr-mobile','/prototype/',390,844,'fr',true],['store-fr-mobile','/',390,844,'fr',true],['store-fr-desktop','/',1440,900,'fr',false],['prototype-it-desktop','/prototype/',1440,900,'it',false]
]){const ctx=await browser.newContext({viewport:{width,height},reducedMotion:'reduce'});await ctx.addInitScript(l=>localStorage.setItem('zafira_lang',l),lang);const p=await ctx.newPage();await p.goto('http://127.0.0.1:8015'+url,{waitUntil:'networkidle'});if(drawer){const selector=url==='/'?'.mobile-menu-button':'#btn-mobile-nav-toggle';if(await p.locator(selector).count())await p.locator(selector).click();await p.waitForTimeout(400);}await p.screenshot({path:join(out,name+'.png')});await ctx.close();}
await browser.close();
