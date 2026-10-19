// Read-only audit: touch targets (<44px) + color contrast on / and /prototype/.
// Usage: BASE=http://127.0.0.1:8015 node a11y-targets-contrast.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require(execSync('npm root -g').toString().trim() + '/@playwright/cli/node_modules/playwright-core');
const exe = process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const base = process.env.BASE;
if (!base || !/^http:\/\/127\.0\.0\.1:\d+$/.test(base) || /:(8000|8001)$/.test(base)) { console.error('bad BASE'); process.exit(2); }

const out = dirname(fileURLToPath(import.meta.url));
const surfaces = [['storefront', '/'], ['prototype', '/prototype/']];
const vps = [[390, 844], [1366, 768]];
const browser = await chromium.launch({ executablePath: exe });
const results = [];

for (const [name, path] of surfaces) for (const [w, h] of vps) {
  const page = await browser.newPage({ viewport: { width: w, height: h } });
  await page.goto(base + path, { waitUntil: 'networkidle' });
  // scroll through to trigger lazy/reveal content, then back to top
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } scrollTo(0, 0); });
  await page.waitForTimeout(400);
  const data = await page.evaluate(() => {
    const vis = el => { const r = el.getBoundingClientRect(); const s = getComputedStyle(el); for (let e = el; e; e = e.parentElement) if (+getComputedStyle(e).opacity === 0) return false; return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && s.clip !== 'rect(0px, 0px, 0px, 0px)' && !el.closest('[hidden],[inert],[aria-hidden="true"]'); };
    const sel = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.classList.length ? '.' + [...el.classList].slice(0, 3).join('.') : '') + (el.type && el.tagName === 'INPUT' ? `[type=${el.type}]` : '');
    const pseudo = (el, p) => { const s = getComputedStyle(el, p); if (s.content === 'none' || s.content === 'normal') return null; return { pos: s.position, inset: [s.top, s.right, s.bottom, s.left].join(' '), w: s.width, h: s.height }; };
    const controls = [...document.querySelectorAll('a[href],button,input:not([type=hidden]),select,textarea,[role=button],summary,label[for]')].filter(vis).map(el => {
      const r = el.getBoundingClientRect(); const s = getComputedStyle(el);
      let label = null;
      if (el.tagName === 'INPUT' && (el.type === 'radio' || el.type === 'checkbox')) { const l = el.closest('label') || (el.id && document.querySelector(`label[for="${el.id}"]`)); if (l) { const lr = l.getBoundingClientRect(); label = [Math.round(lr.width), Math.round(lr.height)]; } }
      return { sel: (el.id || el.classList.length ? sel(el) : sel(el.parentElement) + ' > ' + sel(el)), classes: el.className, text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 30), w: +r.width.toFixed(1), h: +r.height.toFixed(1), pad: s.padding, minW: s.minWidth, minH: s.minHeight, display: s.display, before: pseudo(el, '::before'), after: pseudo(el, '::after'), label, inline: s.display === 'inline' && !!el.closest('p,li') };
    });
    // contrast
    const parse = c => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) { const cv = document.createElement('canvas'); cv.width = cv.height = 1; const cx = cv.getContext('2d', { willReadFrequently: true }); cx.fillStyle = c; cx.fillRect(0, 0, 1, 1); const d = cx.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; } const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p[3] ?? 1]; };
    const bgOf = el => { let layers = []; let uncertain = null; for (let e = el; e; e = e.parentElement) { const s = getComputedStyle(e); if (s.backgroundImage !== 'none') { uncertain = 'bg-image/gradient on ' + sel(e); } const c = parse(s.backgroundColor); if (c && c[3] > 0) { layers.push(c); if (c[3] >= 1) break; } if (+s.opacity < 1) uncertain = uncertain || 'opacity<1 on ' + sel(e); if (uncertain && !layers.length) break; } let base = [255, 255, 255]; for (const c of layers.reverse()) base = base.map((v, i) => v * (1 - c[3]) + c[i] * c[3]); return { bg: base, uncertain }; };
    const texts = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const seen = new Set();
    while (walker.nextNode()) { const t = walker.currentNode; if (!t.textContent.trim()) continue; const el = t.parentElement; if (!el || seen.has(el) || !vis(el) || ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes(el.tagName)) continue; seen.add(el); const s = getComputedStyle(el); const fg = parse(s.color); const { bg, uncertain } = bgOf(el); const fgc = fg[3] < 1 ? bg.map((v, i) => v * (1 - fg[3]) + fg[i] * fg[3]) : fg.slice(0, 3); texts.push({ sel: sel(el), ctx: sel(el.closest('header,footer,nav,section,main,aside,form,[role=dialog]') || document.body), text: t.textContent.trim().slice(0, 40), fg: fgc.map(Math.round), bg: bg.map(Math.round), size: parseFloat(s.fontSize), weight: +s.fontWeight, uncertain, ownOpacity: s.opacity }); }
    // placeholders
    const ph = [...document.querySelectorAll('input[placeholder],textarea[placeholder]')].filter(vis).map(el => { const s = getComputedStyle(el, '::placeholder'); const fg = parse(s.color); const { bg, uncertain } = bgOf(el); const fgc = fg[3] < 1 ? bg.map((v, i) => v * (1 - fg[3]) + fg[i] * fg[3]) : fg.slice(0, 3); return { sel: sel(el) + '::placeholder', ctx: 'input', text: el.placeholder.slice(0, 40), fg: fgc.map(Math.round), bg: bg.map(Math.round), size: parseFloat(s.fontSize), weight: 400, uncertain }; });
    // non-text: input borders, focus not measured
    const nt = [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(vis).map(el => { const s = getComputedStyle(el); const fg = parse(s.borderBottomColor); const { bg } = bgOf(el.parentElement); return { sel: sel(el) + ' border', fg: fg.slice(0, 3).map(Math.round), fgA: fg[3], bg: bg.map(Math.round), bw: s.borderBottomWidth }; });
    return { controls, texts: texts.concat(ph), nonText: nt };
  });
  results.push({ surface: name, vw: w, ...data });
  await page.close();
}
await browser.close();
writeFileSync(join(out, 'a11y-targets-contrast-raw.json'), JSON.stringify(results, null, 1));
console.log('ok', results.map(r => `${r.surface}@${r.vw}: ${r.controls.length} controls, ${r.texts.length} texts`).join('; '));
