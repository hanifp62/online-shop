// Usage: node shots.mjs <label> [base]  -> screenshots + overlap/overflow report
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const label = process.argv[2] || 'run';
const base = process.argv[3] || 'http://127.0.0.1:8000';
const out = join(dirname(fileURLToPath(import.meta.url)), label);
mkdirSync(out, { recursive: true });
const vps = [[320,568],[390,844],[768,1024],[1024,768],[1366,768],[1440,900],[1650,927]];
const pages = [['home','/'],['login','/login'],['register','/register'],['adminlogin','/admin/login'],['prototype','/prototype/']];
const b = await chromium.launch({ executablePath: exe });
const res = [];
for (const [name, path] of pages) for (const [w,h] of vps) {
  const ctx = await b.newContext({ viewport:{width:w,height:h} });
  const p = await ctx.newPage();
  const errs = []; p.on('pageerror', e => errs.push(String(e).slice(0,120))); p.on('console', m => m.type()==='error' && errs.push(m.text().slice(0,120)));
  await p.goto(base+path, { waitUntil:'networkidle' }).catch(()=>{});
  await p.waitForTimeout(600);
  const info = await p.evaluate(() => {
    const de = document.documentElement;
    // fixed/sticky elements & their rects; overlap between fixed elements
    const fx = [...document.querySelectorAll('body *')].filter(e => { const s=getComputedStyle(e); return (s.position==='fixed'||s.position==='sticky') && s.display!=='none' && s.visibility!=='hidden' && e.getBoundingClientRect().width>0; })
      .map(e => { const r=e.getBoundingClientRect(); return { sel:(e.tagName+'.'+[...e.classList].join('.')+(e.id?'#'+e.id:'')).slice(0,60), x:Math.round(r.x), y:Math.round(r.y), w:Math.round(r.width), h:Math.round(r.height), z:getComputedStyle(e).zIndex }; });
    const ov = [];
    for (let i=0;i<fx.length;i++) for (let j=i+1;j<fx.length;j++){ const a=fx[i],c=fx[j]; if (a.x<c.x+c.w&&c.x<a.x+a.w&&a.y<c.y+c.h&&c.y<a.y+a.h) ov.push([a.sel,c.sel]); }
    return { url:location.href, hOverflow: de.scrollWidth>de.clientWidth+1, fixed:fx, overlaps:ov };
  });
  await p.screenshot({ path: join(out, `${name}-${w}x${h}.png`) });
  res.push({ name, w, h, errs, ...info });
  await ctx.close();
}
await b.close();
writeFileSync(join(out,'report.json'), JSON.stringify(res,null,1));
for (const r of res) console.log(r.name, `${r.w}x${r.h}`, r.url.replace(base,''), 'hOver='+r.hOverflow, 'overlaps='+r.overlaps.length, 'errs='+r.errs.length);
