// Dark-theme contrast (final2). Seeds the key the app reads (`zafira-theme`), composites translucent ancestor layers,
// and for text over images/gradients screenshots the element with text hidden and samples real pixels.
// Exempt (documented, not counted): aria-hidden/hidden/inert content, decorative separators (.lang-divider, .marquee-bullet),
// product illustration preview (.monogram-preview, .bag-lock), and image stamps (.story-image > span).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require(execFileSync('npm', ['root', '-g'], { encoding: 'utf8' }).trim() + '/@playwright/cli/node_modules/playwright-core');
const browser = await chromium.launch({ executablePath: process.env.HOME + '/Library/Caches/ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-mac-arm64/chrome-headless-shell' });
const EXEMPT = '.lang-divider,.marquee-bullet,.monogram-preview,.bag-lock,.story-image > span';
const states = {
  '/': [['base', null], ['search', '[data-open-search]'], ['cart', '[data-open-cart]'], ['concierge', '[data-open-concierge]']],
  // prototype route shares main theme rules; separately exercises main page and its package/gallery inline headings.
  '/prototype/': [['base', null]],
};
const rows = [];
for (const [surface, list] of Object.entries(states)) for (const width of [390, 1366]) for (const [state, opener] of list) {
  const page = await browser.newPage({ viewport: { width, height: 844 }, reducedMotion: 'reduce', colorScheme: 'dark' });
  // Inline bootstrap reads hyphen; shared theme engine reads underscore.
  await page.addInitScript(() => { localStorage.setItem('zafira-theme', 'dark'); localStorage.setItem('zafira_theme', 'dark'); });
  await page.goto('http://127.0.0.1:8015' + surface, { waitUntil: 'networkidle' });
  const themeAttr = await page.evaluate(() => document.documentElement.dataset.theme + '/' + document.documentElement.dataset.themeSetting);
  // trigger reveal-on-scroll content
  const h = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < h; y += 600) { await page.evaluate(v => scrollTo(0, v), y); await page.waitForTimeout(40); }
  await page.evaluate(() => scrollTo(0, 0));
  if (opener) { const o = page.locator(opener).first(); if (await o.isVisible().catch(() => false)) { await o.click(); await page.waitForTimeout(500); } else { rows.push({ surface, width, state, skipped: 'opener hidden' }); await page.close(); continue; } }
  const cands = await page.evaluate((EXEMPT) => {
    const rgba = s => { const m = s.match(/[\d.]+/g)?.map(Number) || [0, 0, 0, 0]; return [m[0], m[1], m[2], m[3] ?? 1]; };
    const over = (top, base) => { const a = top[3]; return [0, 1, 2].map(i => top[i] * a + base[i] * (1 - a)).concat(1); };
    const out = []; let idx = 0;
    // closed dialogs keep aria-modal="true"; only treat as active when opened.
    const modal = document.querySelector('dialog[open],.open[role="dialog"],[role="dialog"][aria-hidden="false"]');
    const root = modal || document.body;
    for (const el of root.querySelectorAll('*')) {
      if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) continue;
      const s = getComputedStyle(el), b = el.getBoundingClientRect();
      if (!b.width || !b.height || s.visibility === 'hidden' || s.display === 'none' || +s.opacity === 0) continue;
      if (el.closest('[aria-hidden="true"],[hidden],[inert],dialog:not([open]),.drawer:not(.open),.search-panel:not(.open),.modal:not(.open)')) continue;
      let hidden = false;
      for (let n = el; n; n = n.parentElement) { const c = getComputedStyle(n); if (+c.opacity === 0 || c.visibility === 'hidden' || c.display === 'none') { hidden = true; break; } }
      if (hidden) continue;
      // offscreen translate check only when no modal active
      if (!modal) { const vw = innerWidth; if (b.right <= 0 || b.left >= vw) continue; }
      const layers = []; let image = false, opaque = null;
      for (let n = el; n; n = n.parentElement) {
        const c = getComputedStyle(n);
        if (c.backgroundImage !== 'none' || (n !== el && n.querySelector(':scope > img,:scope > picture,:scope > video'))) { image = true; break; }
        const v = rgba(c.backgroundColor);
        if (v[3] > 0) layers.push(v);
        if (v[3] >= .99) { opaque = v; break; }
      }
      const exempt = !!el.closest(EXEMPT);
      let bg = null;
      if (!image) { bg = (opaque || [255, 255, 255, 1]); for (const l of layers.filter(l => l !== opaque).reverse()) bg = over(l, bg); }
      const fgRaw = rgba(s.color);
      const fs = parseFloat(s.fontSize), large = fs >= 24 || (fs >= 18.66 && +s.fontWeight >= 700);
      el.setAttribute('data-cc', String(idx));
      out.push({ idx: idx++, tag: el.tagName, cls: String(el.className).slice(0, 40), text: el.textContent.trim().replace(/\s+/g, ' ').slice(0, 36), fg: fgRaw, bg, image, large, exempt, rect: { x: b.x, y: b.y, w: b.width, h: b.height } });
    }
    return out;
  }, EXEMPT);
  // sample real pixels for image backgrounds
  const imageCands = cands.filter(c => c.image && !c.exempt);
  if (imageCands.length) {
    await page.addStyleTag({ content: '*{color:transparent!important;text-shadow:none!important;-webkit-text-fill-color:transparent!important} svg{opacity:0!important}' });
    for (const c of imageCands) {
      const el = page.locator(`[data-cc="${c.idx}"]`);
      await el.scrollIntoViewIfNeeded().catch(() => {});
      const r = await el.boundingBox();
      if (!r || r.y < 0 || r.y + r.height > 844 + 1) { c.px = null; continue; }
      const buf = await page.screenshot({ clip: { x: Math.max(0, r.x), y: r.y, width: Math.min(r.width, width - Math.max(0, r.x)), height: r.height } });
      c.px = await page.evaluate(async ([b64, fg]) => {
        const bmp = await createImageBitmap(await (await fetch('data:image/png;base64,' + b64)).blob());
        const cv = new OffscreenCanvas(bmp.width, bmp.height), cx = cv.getContext('2d'); cx.drawImage(bmp, 0, 0);
        const d = cx.getImageData(0, 0, bmp.width, bmp.height).data;
        const lum = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
        const rs = []; const step = Math.max(1, Math.floor(d.length / 4 / 600));
        for (let i = 0; i < d.length; i += 4 * step) {
          const px = [d[i], d[i + 1], d[i + 2]];
          const f = fg[3] < 1 ? fg.slice(0, 3).map((v, k) => v * fg[3] + px[k] * (1 - fg[3])) : fg.slice(0, 3);
          const [x, y] = [lum(f), lum(px)]; rs.push((Math.max(x, y) + .05) / (Math.min(x, y) + .05));
        }
        rs.sort((a, b) => a - b); return +rs[Math.floor(rs.length * .1)].toFixed(2);
      }, [buf.toString('base64'), c.fg]);
    }
  }
  const lum = ([r, g, b]) => [r, g, b].map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((a, v, i) => a + v * [.2126, .7152, .0722][i], 0);
  const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)]; return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  const fails = [], exempts = []; let measured = 0, sampled = 0, unmeasured = 0;
  for (const c of cands) {
    let value;
    if (c.image) { if (c.exempt) { exempts.push({ cls: c.cls, text: c.text, note: 'exempt+image' }); continue; } if (c.px == null) { unmeasured++; continue; } value = c.px; sampled++; }
    else {
      const fg = c.fg[3] < 1 ? [0, 1, 2].map(i => c.fg[i] * c.fg[3] + c.bg[i] * (1 - c.fg[3])) : c.fg;
      value = +ratio(fg, c.bg).toFixed(2); measured++;
    }
    const need = c.large ? 3 : 4.5;
    if (value < need) (c.exempt ? exempts : fails).push({ tag: c.tag, cls: c.cls, text: c.text, ratio: value, need, via: c.image ? 'pixels' : 'css' });
  }
  rows.push({ surface, width, state, theme: themeAttr, measured, sampled, unmeasured, failCount: fails.length, exemptCount: exempts.length, fails: fails.slice(0, 60), exempts: exempts.slice(0, 20) });
  await page.close();
}
await browser.close();
writeFileSync(new URL('dark-contrast-final2.json', import.meta.url), JSON.stringify(rows, null, 2) + '\n');
console.log(JSON.stringify(rows.map(({ surface, width, state, theme, measured, sampled, unmeasured, failCount, exemptCount, skipped }) => ({ surface, width, state, theme, measured, sampled, unmeasured, failCount, exemptCount, skipped }))));
process.exitCode = rows.some(r => r.failCount) ? 1 : 0;
