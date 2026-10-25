'use strict';
// UI-LOCAL-20261025: independent of acceptance_react_complete's 31 mandatory checks.
// Contract: docs/04-implementation/ADMIN_PROFILE_THEME_STOCK_2026-10-25.md.
// Skills: accessibility, diagnosing-bugs, browser-testing-with-devtools (Playwright adapter),
// incremental-implementation, git-workflow-and-versioning. Browser execution belongs to main.
// No UI design changes; antislop's design/install workflow is out of scope.
// No browser/fixture side effects on import. Only the explicit browser CLI runs the application.
const fs = require('node:fs');
const path = require('node:path');

const TASK = 'UI-LOCAL-20261025';
const REQUIREMENTS = ['FR-ADM-20261025', 'FR-PROFILE-20261025', 'NFR-THEME-20261025', 'NFR-SAFE-20261025'];
const RENDERERS = ['native', 'blade'];
const MODES = ['dark', 'light', 'system'];
const WIDTHS = [320, 390, 1440];
const TIMEOUT = 6000;
const BUDGET_MS = 260000; // acceptance_server.py owns the final 300s deadline and group cleanup.
const THEME_SELECT = 'select[data-theme-select], select.zafira-theme-select';
const PRODUCT = '/produk/amadis-demo-stock-37';
const AUTH_TEXT = ['.auth-card h1', '.field label', 'form[data-auth-form] button[type="submit"]'];
const PAGES = [
    { id: 'home', route: '/', role: 'guest', ready: '#home-catalog-search', text: ['.products-heading h2', '.product-info h3', '.product-info p'], placeholder: '#home-catalog-query', wishlist: true, controls: ['#home-catalog-query', '.wishlist-button', '.footer-preferences-bar .zafira-theme-select'] },
    { id: 'catalog', route: '/koleksi', role: 'guest', ready: '.catalog-card', text: ['.catalog-title', '.catalog-lead', '.catalog-card-price', '.catalog-card-attrs'], placeholder: '#filter-q', controls: ['#filter-q', '[data-quick-view]'] },
    { id: 'pdp', route: PRODUCT, role: 'guest', ready: '.product-detail-info', text: ['.product-detail-info h1', '.product-detail-price', '.product-detail-description'], placeholder: '#pdp-monogram-input', wishlist: true, controls: ['#pdp-monogram-input', '.product-detail-actions > button', '.wishlist-button'] },
    { id: 'shop', route: '/belanja', role: 'guest', ready: '#guest-checkout-form', text: ['.intro h1', '#tas h2', '#cart-list p'], input: '#guest-checkout-form [name="customer_name"]', controls: ['#guest-checkout-form [name="customer_name"]', '#guest-checkout-form [name="customer_email"]'] },
    { id: 'login', route: '/login', role: 'guest', ready: 'form[data-auth-form]', text: AUTH_TEXT, input: '#email', controls: ['#email', '#password', '.password-toggle'] },
    { id: 'register', route: '/register', role: 'guest', ready: '#password_confirmation', text: AUTH_TEXT, input: '#email', controls: ['#email', '#password'] },
    { id: 'forgot', route: '/lupa-kata-sandi', role: 'guest', ready: '#email', text: AUTH_TEXT, input: '#email', controls: ['#email', 'form[data-auth-form] button[type="submit"]'] },
    { id: 'admin-login', route: '/admin/login', role: 'guest', ready: '#email', text: AUTH_TEXT, input: '#email', controls: ['#email', '#password'] },
    { id: 'account', route: '/akun', role: 'customer', ready: '#account-details', text: ['#account-details h2', '.profile-note', '#account-security p'], placeholder: '#mobile', input: '#name', controls: ['#account-theme', '#name', '.profile-section-nav a'] },
    { id: 'dashboard', route: '/admin', role: 'admin', ready: '[data-admin-table]', text: ['.admin-page-heading h1', '.admin-work-operator', '.stat-card dt', '#panel-pesanan th:nth-child(2)'], placeholder: '#cat_title', controls: ['#admin-theme', '.admin-profile-link'] },
    { id: 'admin-profile', route: '/admin/profil', role: 'admin', ready: '#profile-identity', text: ['#profile-identity h2', '.profile-note', '#profile-security p'], input: '#current_password', controls: ['#profile-theme', '#current_password', '.profile-section-nav a'] },
    { id: 'auth-events', route: '/admin/auth-events', role: 'admin', ready: '.auth-events-table-wrap', text: ['.admin-page-heading h1', '.admin-page-intro', '.auth-events-table-wrap th', '.auth-events-table-wrap td'], placeholder: '#auth-request-filter', controls: ['#admin-theme', '#auth-request-filter'] },
    { id: 'reports', route: '/admin/reports/operations-summary', role: 'admin', ready: '[data-ops-report][data-as-of]:not([data-as-of=""])', text: ['#ops-filter-heading', '#ops-window-help', '.ops-table th', '.ops-detail'], controls: ['#admin-theme', '#ops-period', '#ops-from'] },
];
const LIMITATIONS = [
    'Sampel CSS solid-surface, bukan sertifikasi WCAG atau audit seluruh teks/piksel.',
    'Teks memakai ambang 4.5 tanpa pengecualian teks besar; ikon wishlist memakai 3.',
    'Gradient/gambar/overlay/filter/blend dan warna non-sRGB tidak ditebak; sampel wajib yang tidak terukur menggagalkan gate.',
    'Screenshot viewport, bukan full-page/golden diff; hanya fixture sintetis. Field rahasia dan code/pre dimask.',
    'Kontras diukur pada 390px (light/dark); reflow semua halaman/renderer/tema pada 320/390/1440px.',
    'Fokus diuji lewat Shift+Tab/Tab dan outline luar >=2px dengan kontras solid-surface >=3 pada kontrol sampel; bukan audit seluruh urutan Tab/fokus atau pembaca layar.',
    'Band fokus memakai rounded rectangle axis-aligned dan bounding box media, bukan raster/occlusion compositor; transform, clipping, dan outline multi-fragment belum dimodelkan.',
    'frontend=blade bersifat per-request: link diuji ke tujuan native default, lalu GET eksplisit Blade menguji section navigation fallback.',
    'Network eksternal diblok: font remote dapat fallback; screenshot bukan bukti tipografi font remote.',
    'Tidak menguji checkout/write profil/password/TOTP/export, stok DB pengguna, 8000/8001, atau suite 31-mandatory.',
    'Build lokal yang sudah ada dipakai apa adanya; runner tidak menjalankan build atau memperbaiki CSS.',
    'Flag admin_ops_summary wajib aktif pada fixture; tidak ada skip atau pengaktifan flag tersembunyi.',
];

class GateError extends Error {
    constructor(code) { super(code); this.code = code; }
}
function need(condition, code) { if (!condition) throw new GateError(code); }
function safeError(error) {
    // Never persist Playwright's call log: fill/assertion diagnostics can contain credentials or DOM text.
    return { code: error instanceof GateError ? error.code : error?.name === 'TimeoutError' ? 'timeout' : 'operation_failed' };
}
function validOrigin(value) {
    const match = /^http:\/\/127\.0\.0\.1:([1-9][0-9]{3,4})$/.exec(value ?? '');
    return Boolean(match && Number(match[1]) >= 1024 && Number(match[1]) <= 65535 && ![8000, 8001].includes(Number(match[1])));
}
function routeFor(route, renderer) {
    need(RENDERERS.includes(renderer), 'renderer_invalid');
    return route + (renderer === 'blade' ? '?frontend=blade' : '');
}
function parseColor(value) {
    if (typeof value !== 'string') return null;
    value = value.trim().toLowerCase();
    if (value === 'transparent') return [0, 0, 0, 0];
    const hex = /^#([a-f0-9]{6})([a-f0-9]{2})?$/.exec(value);
    if (hex) return [0, 2, 4].map(i => parseInt(hex[1].slice(i, i + 2), 16) / 255).concat(hex[2] ? parseInt(hex[2], 16) / 255 : 1);
    const rgb = /^rgba?\(([^()]+)\)$/.exec(value);
    const srgb = /^color\(srgb\s+([^()]+)\)$/.exec(value);
    if (!rgb && !srgb) return null;
    const tokens = (rgb || srgb)[1].replace(/[,/]/g, ' ').trim().split(/\s+/);
    if (tokens.length < 3 || tokens.length > 4 || tokens.some(t => !/^[+-]?(?:\d+\.?\d*|\.\d+)%?$/.test(t))) return null;
    const values = tokens.map((token, i) => Number.parseFloat(token) / (token.endsWith('%') ? 100 : i < 3 && rgb ? 255 : 1));
    if (values.some(n => !Number.isFinite(n) || n < 0 || n > 1)) return null;
    return values.length === 3 ? [...values, 1] : values;
}
function over(front, back) {
    const alpha = front[3] + back[3] * (1 - front[3]);
    if (alpha === 0) return [0, 0, 0, 0];
    return [0, 1, 2].map(i => (front[i] * front[3] + back[i] * back[3] * (1 - front[3])) / alpha).concat(alpha);
}
function luminance(color) {
    return color.slice(0, 3).map(c => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
        .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
}
function contrast(a, b) {
    const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (values[0] + 0.05) / (values[1] + 0.05);
}
function measurePaint(sample, threshold) {
    let foreground = parseColor(sample.paint);
    let background = [0, 0, 0, 0];
    if (!foreground || !Number.isFinite(sample.paintOpacity) || sample.paintOpacity < 0 || sample.paintOpacity > 1) return { status: 'UNMEASURED', reason: 'unsupported_paint', threshold };
    foreground[3] *= sample.paintOpacity;
    for (const layer of sample.layers) {
        const color = parseColor(layer.color);
        if (!color || !Number.isFinite(layer.opacity) || layer.opacity < 0 || layer.opacity > 1) return { status: 'UNMEASURED', reason: 'unsupported_surface', threshold };
        if (layer.effect) return { status: 'UNMEASURED', reason: 'paint_effect', threshold };
        // An opaque button hides the image beneath it. A translucent button does not.
        if (layer.complex && (foreground[3] < 1 || background[3] < 1)) return { status: 'UNMEASURED', reason: 'non_solid_backdrop', threshold };
        foreground = over(foreground, color);
        background = over(background, color);
        // CSS opacity composites the whole group, including its background. Multiplying just
        // the text alpha here would give the wrong answer on translucent nested surfaces.
        foreground[3] *= layer.opacity;
        background[3] *= layer.opacity;
    }
    if (foreground[3] < 0.999999 || background[3] < 0.999999) return { status: 'UNMEASURED', reason: 'unresolved_canvas', threshold };
    const ratio = contrast(foreground, background);
    const channels = color => color.slice(0, 3).map(c => Math.round(c * 25500) / 100);
    return { status: ratio >= threshold ? 'PASS' : 'FAIL', ratio, threshold, foreground: channels(foreground), background: channels(background) };
}

// Executed only by Playwright against fixed source selectors. DOM is data, never instructions.
function inspectPaint(element, kind) {
    if (kind === 'text') {
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            if (!node.textContent.trim()) continue;
            const parent = node.parentElement;
            const s = getComputedStyle(parent);
            if (s.visibility !== 'hidden' && s.display !== 'none' && s.clipPath === 'none' && ['auto', ''].includes(s.clip)) { element = parent; break; }
        }
        if (!node) return { paint: null, paintOpacity: 1, layers: [] };
    }
    if (kind === 'icon') element = element.querySelector('use, path, polygon, circle, line, rect') || element;
    const style = getComputedStyle(element);
    const pseudo = kind === 'placeholder' ? getComputedStyle(element, '::placeholder') : style;
    let paint = pseudo.color;
    let paintOpacity = kind === 'placeholder' ? Number(pseudo.opacity) : 1;
    if (kind === 'icon') {
        const stroke = style.stroke;
        const fill = style.fill;
        paint = stroke !== 'none' && Number.parseFloat(style.strokeWidth) > 0 ? stroke : fill;
        paintOpacity = Number(stroke !== 'none' && Number.parseFloat(style.strokeWidth) > 0 ? style.strokeOpacity : style.fillOpacity);
    }

    if (kind === 'focus') paint = style.outlineColor;
    const rect = element.getBoundingClientRect();
    const intersects = (a, b) => a.right > b.left && a.left < b.right && a.bottom > b.top && a.top < b.bottom;
    let overlaps = other => intersects(rect, other.getBoundingClientRect());
    if (kind === 'focus') {
        const width = rect.right - rect.left, height = rect.bottom - rect.top;
        const offset = Number.parseFloat(style.outlineOffset), stroke = Number.parseFloat(style.outlineWidth);
        const length = (value, size) => /^\d*\.?\d+(?:px|%)$/.test(value) ? Number.parseFloat(value) * (value.endsWith('%') ? size / 100 : 1) : NaN;
        const radii = ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'].map(corner => {
            const parts = (style[`border${corner}Radius`] || '').trim().split(/\s+/);
            return parts.length > 2 ? [NaN, NaN] : [length(parts[0], width), length(parts[1] || parts[0], height)];
        });
        if (![width, height, offset, stroke, ...radii.flat()].every(Number.isFinite) || width <= 0 || height <= 0 || offset < 0 || stroke <= 0) {
            return { paint: null, paintOpacity, layers: [] }; // Unsupported geometry must not produce a guessed PASS.
        }
        for (const radius of radii) if (radius.includes(0)) radius.fill(0);
        // CSS scales oversized/percentage corner radii before the outline expands them.
        const scale = Math.min(1, width / (radii[0][0] + radii[1][0]), width / (radii[3][0] + radii[2][0]),
            height / (radii[0][1] + radii[3][1]), height / (radii[1][1] + radii[2][1]));
        const expand = distance => {
            const box = { left: rect.left - distance, right: rect.right + distance, top: rect.top - distance, bottom: rect.bottom + distance };
            const corners = radii.map(([x, y], index) => {
                const rx = x ? x * scale + distance : 0, ry = y ? y * scale + distance : 0;
                const sx = index === 0 || index === 3 ? -1 : 1, sy = index < 2 ? -1 : 1;
                return { rx, ry, sx, sy, x: sx < 0 ? box.left + rx : box.right - rx, y: sy < 0 ? box.top + ry : box.bottom - ry };
            });
            return { ...box, corners };
        };
        const inner = expand(offset), outer = expand(offset + stroke);
        const inCorner = (x, y, c) => c.rx > 0 && c.ry > 0 && (x - c.x) * c.sx > 0 && (y - c.y) * c.sy > 0;
        const ellipse = (x, y, c) => ((x - c.x) / c.rx) ** 2 + ((y - c.y) / c.ry) ** 2;
        const inside = (x, y, box) => x >= box.left && x <= box.right && y >= box.top && y <= box.bottom
            && box.corners.every(c => !inCorner(x, y, c) || ellipse(x, y, c) <= 1);
        overlaps = other => {
            const r = other.getBoundingClientRect();
            const clip = { left: Math.max(r.left, outer.left), right: Math.min(r.right, outer.right), top: Math.max(r.top, outer.top), bottom: Math.min(r.bottom, outer.bottom) };
            if (clip.right <= clip.left || clip.bottom <= clip.top) return false;
            const touchesOuter = outer.corners.every(c => {
                const x = Math.max(clip.left, Math.min(c.x, clip.right)), y = Math.max(clip.top, Math.min(c.y, clip.bottom));
                return !inCorner(x, y, c) || ellipse(x, y, c) < 1;
            });
            // A media rect inside the inner rounded contour cannot back the outline.
            // Testing all four corners is sufficient because that contour is convex.
            const insideInner = [[r.left, r.top], [r.right, r.top], [r.right, r.bottom], [r.left, r.bottom]]
                .every(([x, y]) => inside(x, y, inner));
            return touchesOuter && !insideInner;
        };
    }
    const layers = [];
    for (let el = element; el; el = el.parentElement) {
        const css = getComputedStyle(el);
        const pseudoOverlay = ['::before', '::after'].some(selector => {
            const p = getComputedStyle(el, selector);
            const colored = value => value && value !== 'transparent' && !/^rgba\([^)]*,\s*0\)$/.test(value);
            const ink = !['none', 'normal', '', '\"\"', "''"].includes(p.content);
            const border = ['Top', 'Right', 'Bottom', 'Left'].some(side => Number.parseFloat(p[`border${side}Width`]) > 0 && colored(p[`border${side}Color`]));
            const painted = ink || colored(p.backgroundColor) || p.backgroundImage !== 'none' || border || p.boxShadow !== 'none';
            return !['none', 'normal', ''].includes(p.content) && ['absolute', 'fixed'].includes(p.position) && Number(p.opacity) > 0 && painted;
        });
        const mediaBehind = [...el.children].some(child => child !== element && !child.contains(element)
            && child.matches('img, video, canvas, picture, svg') && overlaps(child));
        // Positive-offset outlines are outside the control's own painted background.
        layers.push({ color: kind === 'focus' && el === element ? 'transparent' : css.backgroundColor, opacity: Number(css.opacity),
            complex: css.backgroundImage !== 'none' || mediaBehind,
            effect: css.mixBlendMode !== 'normal' || css.filter !== 'none' || (css.backdropFilter && css.backdropFilter !== 'none') || pseudoOverlay });
    }
    return { paint, paintOpacity, layers };
}
function expectedCases() { return RENDERERS.flatMap(renderer => MODES.flatMap(mode => PAGES.map(p => `${renderer}/${mode}/${p.id}`))); }
function expectedScreenshots() {
    return RENDERERS.flatMap(renderer => MODES.flatMap(mode => PAGES.flatMap(p => {
        const prefix = `${renderer}-${mode}-${p.id}`;
        if (mode === 'system') return [prefix + '-1440.png'];
        return [...WIDTHS.map(width => `${prefix}-${width}.png`), prefix + '-390-focus.png',
            ...(p.role === 'admin' ? [prefix + '-320-navigation-open.png'] : [])];
    }))).sort();
}
function newReport() {
    return { schema: 1, task: TASK, requirements: REQUIREMENTS, startedAt: new Date().toISOString(), browserStatus: 'RUNNING',
        cases: [], checks: [], screenshots: [], events: [], eventCount: 0, externalRequests: 0, limitations: LIMITATIONS };
}
function readCredentials(root) {
    // fixtureChannel has already validated the guarded root and its private-runtime directory.
    const file = path.join(root, 'private-runtime/credentials.json');
    let fd;
    try {
        fd = fs.openSync(file, fs.constants.O_RDONLY | fs.constants.O_NOFOLLOW | fs.constants.O_NONBLOCK);
        const stat = fs.fstatSync(fd);
        need(stat.isFile() && stat.nlink === 1 && stat.uid === process.getuid() && !(stat.mode & 0o077) && stat.size <= 4096, 'credentials_rejected');
        const value = JSON.parse(fs.readFileSync(fd, 'utf8'));
        need(Object.keys(value).sort().join(',') === 'admin,customer', 'credentials_rejected');
        for (const role of ['admin', 'customer']) {
            need(Object.keys(value[role]).sort().join(',') === 'email,password'
                && value[role].email === `amadis-${role}@example.test`
                && typeof value[role].password === 'string' && value[role].password.length >= 24, 'credentials_rejected');
        }
        return value;
    } catch { throw new GateError('credentials_rejected'); }
    finally { if (fd !== undefined) fs.closeSync(fd); }
}
function writeReport(file, report, exclusive = false) {
    fs.writeFileSync(file, JSON.stringify(report, null, 2) + '\n', { flag: exclusive ? 'wx' : 'w', mode: 0o600 });
}

async function runBrowser() {
    const origin = process.env.AM_ACCEPTANCE_URL;
    const output = process.env.AM_ACCEPTANCE_OUTPUT;
    need(validOrigin(origin), 'origin_rejected');
    need(path.isAbsolute(output ?? '') && path.basename(output) === 'ui-local-browser.json', 'output_rejected');
    const out = path.dirname(output);
    const outStat = fs.lstatSync(out);
    need(outStat.isDirectory() && !outStat.isSymbolicLink() && !(outStat.mode & 0o077) && outStat.uid === process.getuid(), 'output_rejected');
    const report = newReport();
    writeReport(output, report, true);
    let stage = 'fixture-preflight', browser, interrupted = false;
    const finishMonitors = [];
    const record = (id, status, detail = {}) => { report.checks.push({ ...detail, id, status }); };
    const check = async (id, action) => {
        if (interrupted) throw new GateError('runtime_budget');
        stage = id;
        const before = report.checks.length;
        try {
            await action();
            const passed = !report.checks.slice(before).some(row => row.status === 'FAIL');
            record(id, passed ? 'PASS' : 'FAIL');
            return passed;
        } catch (error) { record(id, 'FAIL', { ...safeError(error), stage }); return false; }
    };
    const watchdog = setTimeout(() => {
        interrupted = true;
        record('runtime-budget', 'FAIL', { code: 'runtime_budget' });
        report.browserStatus = 'FAIL';
        report.finishedAt = new Date().toISOString();
        writeReport(output, report);
        // Do not detach or kill arbitrary Chromium processes. The supervisor drains our owned group.
        process.exit(1);
    }, BUDGET_MS);
    async function pageFor() {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light', reducedMotion: 'reduce', serviceWorkers: 'block', acceptDownloads: false });
        await context.route('**/*', async route => {
            const request = route.request();
            if (new URL(request.url()).origin === origin) return route.continue();
            report.externalRequests += 1;
            if (request.isNavigationRequest()) {
                record('network-boundary', 'FAIL', { code: 'external_navigation_blocked' });
                return route.abort();
            }
            return route.fulfill({ status: 200, body: '', contentType: 'text/css' });
        });
        const page = await context.newPage();
        page.setDefaultTimeout(TIMEOUT);
        page.setDefaultNavigationTimeout(12000);
        finishMonitors.push(require('./acceptance_react_monitor.cjs')(page, { stage: () => stage, unexpected: event => {
            report.eventCount += 1;
            if (report.events.length < 20) report.events.push(event); // helper emits only fixed categories, stage, numeric status
        } }));
        page.on('response', response => {
            if (response.status() >= 400 && new URL(response.url()).origin === origin) {
                report.eventCount += 1;
                if (report.events.length < 20) report.events.push({ event: 'http', reason: 'unexpected_status', stage, status: response.status() });
            }
        });
        page.on('dialog', dialog => { record('unexpected-dialog', 'FAIL', { code: 'dialog_dismissed' }); void dialog.dismiss().catch(() => {}); });
        return page;
    }
    async function ready(page, renderer) {
        if (renderer === 'native') {
            await page.locator('#app[data-react-boot="ready"]').waitFor();
            need(await page.locator('script[data-page="app"]').count() === 0, 'legacy_app_script');
        } else need(await page.locator('#app').count() === 0, 'blade_not_selected');
    }
    async function goto(page, descriptor, renderer) {
        const response = await page.goto(origin + routeFor(descriptor.route, renderer), { waitUntil: 'domcontentloaded' });
        need(response?.status() === 200, descriptor.id === 'reports' ? 'reports_flag_or_response_unavailable' : 'http_not_200');
        need(new URL(page.url()).origin === origin && new URL(page.url()).pathname === descriptor.route, 'unexpected_route');
        if (descriptor.role !== 'guest') need(/no-store/i.test(response.headers()['cache-control'] ?? ''), 'private_cache_policy');
        await ready(page, renderer);
        await page.locator(descriptor.ready).first().waitFor();
        if (descriptor.id === 'shop') await page.waitForFunction(() => document.querySelector('#cart-list p') && document.querySelector('main[aria-busy="true"]') === null);
        if (descriptor.id === 'dashboard') need(await page.locator('[data-admin-table]').count() === 7, 'dashboard_regions');
        await page.evaluate(async () => { await document.fonts.ready; });
    }
    async function login(page, role, credentials, renderer) {
        const loginPage = PAGES.find(p => p.id === (role === 'admin' ? 'admin-login' : 'login'));
        await goto(page, loginPage, renderer);
        await page.locator('#email').fill(credentials[role].email);
        await page.locator('#password').fill(credentials[role].password);
        await Promise.all([page.waitForEvent('domcontentloaded'), page.locator('form[data-auth-form] button[type="submit"]').click()]);
        // Auth is the real native form, never injected cookies/users/session storage. No screenshot here.
        await goto(page, PAGES.find(p => p.id === (role === 'admin' ? 'dashboard' : 'account')), renderer);
    }
    async function themeState(page, mode, resolved) {
        await page.waitForFunction(({ mode, resolved }) => document.documentElement.dataset.theme === resolved
            && document.documentElement.dataset.themePreference === mode, { mode, resolved });
        const state = await page.evaluate(selector => ({ stored: localStorage.getItem('zafira_theme'),
            scheme: getComputedStyle(document.documentElement).colorScheme,
            controls: [...document.querySelectorAll(selector)].every(el => el.value === document.documentElement.dataset.themePreference) }), THEME_SELECT);
        need(state.stored === mode && state.scheme === resolved && state.controls, 'theme_not_persisted_or_synced');
    }
    async function setTheme(page, mode) {
        await page.locator(THEME_SELECT).filter({ visible: true }).first().selectOption(mode);
        await themeState(page, mode, mode === 'system' ? 'light' : mode);
    }
    async function reflow(page, id) {
        const size = await page.evaluate(() => ({ viewport: innerWidth, root: document.documentElement.scrollWidth, body: document.body.scrollWidth, client: document.documentElement.clientWidth }));
        record(id, size.root <= size.viewport + 1 && size.body <= size.viewport + 1 ? 'PASS' : 'FAIL', size);
    }
    async function screenshot(page, id) {
        need(new URL(page.url()).origin === origin && PAGES.some(p => p.route === new URL(page.url()).pathname), 'screenshot_route_rejected');
        const filename = id.replaceAll('/', '-') + '.png';
        await page.screenshot({ path: path.join(out, filename), fullPage: false, animations: 'disabled', timeout: TIMEOUT,
            mask: [page.locator('input[type="password"], input[autocomplete="one-time-code"], input[name="token"], input[name="current_password"], input[name="password"], input[name="password_confirmation"], pre, code')] });
        report.screenshots.push(filename);
    }
    async function sample(page, selector, kind, id) {
        const locator = page.locator(selector).filter({ visible: true }).first();
        need(await locator.count() === 1, 'sample_missing');
        await locator.scrollIntoViewIfNeeded();
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        if (kind === 'placeholder') need(await locator.evaluate(el => el.matches(':placeholder-shown') && Boolean(el.getAttribute('placeholder'))), 'placeholder_not_displayed');
        const measured = measurePaint(await locator.evaluate(inspectPaint, kind), kind === 'icon' ? 3 : 4.5);
        record(id, measured.status === 'PASS' ? 'PASS' : 'FAIL', { selector, kind, ...measured, measurement: measured.status });
    }
    async function focusControl(page, selector, id, capture = false) {
        const control = page.locator(selector).filter({ visible: true }).first();
        need(await control.count() === 1, 'control_missing');
        await control.focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        const focus = await control.evaluate(el => { const s = getComputedStyle(el); return { reached: document.activeElement === el, visible: el.matches(':focus-visible'), width: Number.parseFloat(s.outlineWidth), offset: Number.parseFloat(s.outlineOffset), color: s.outlineColor, styled: !['none', 'hidden'].includes(s.outlineStyle) }; });
        need(focus.reached && focus.visible && focus.styled && focus.width >= 2 && focus.offset >= 0 && parseColor(focus.color)?.[3] > 0, 'keyboard_focus_missing');
        const measured = measurePaint(await control.evaluate(inspectPaint, 'focus'), 3);
        record(id + '/focus-contrast', measured.status === 'PASS' ? 'PASS' : 'FAIL', { selector, kind: 'focus', ...measured, measurement: measured.status });
        if (capture) await screenshot(page, id + '/focus');
    }
    async function targetSizes(page, selector, id) {
        const sizes = await page.locator(selector).filter({ visible: true }).evaluateAll(elements => elements.slice(0, 12).map(el => { const r = el.getBoundingClientRect(); return { width: r.width, height: r.height }; }));
        record(id, sizes.length > 0 && sizes.every(r => r.width >= 43.99 && r.height >= 43.99) ? 'PASS' : 'FAIL', { selector, minimum: 44, sizes });
    }
    async function adminMobile(page, id) {
        const disclosure = page.locator('[data-admin-navigation]');
        const summary = disclosure.locator('summary');
        need(!(await disclosure.evaluate(el => el.open)), 'admin_mobile_initially_open');
        await focusControl(page, '[data-admin-navigation] > summary', id);
        await page.keyboard.press('Enter');
        need(await disclosure.evaluate(el => el.open), 'admin_mobile_not_open');
        await reflow(page, id + '/open-reflow');
        await targetSizes(page, '[data-admin-navigation] > summary', id + '/summary-size');
        await screenshot(page, id + '/open');
        await page.keyboard.press('Tab');
        need(await disclosure.evaluate(el => el.querySelector('nav').contains(document.activeElement)), 'admin_mobile_tab');
        await page.keyboard.press('Escape');
        need(!(await disclosure.evaluate(el => el.open)) && await summary.evaluate(el => el === document.activeElement), 'admin_mobile_escape');
    }
    async function sectionNav(page, sections) {
        for (const target of sections) {
            const selector = `.profile-section-nav a[href="#${target}"]`;
            await focusControl(page, selector, 'section-nav');
            await page.keyboard.press('Enter');
            await page.waitForURL(url => url.hash === '#' + target);
            const visible = await page.locator('#' + target).evaluate(el => { const r = el.getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight; });
            need(visible, 'section_not_in_view');
        }
    }
    async function interactions(pages, renderer) {
        const prefix = `${renderer}/interactions`;
        const guest = pages.guest;
        await check(prefix + '/theme-buttons-and-escape', async () => {
            await goto(guest, PAGES.find(p => p.id === 'home'), renderer);
            const trigger = renderer === 'native' ? '.header-actions button[aria-haspopup="dialog"]' : '[data-open-profile]';
            await focusControl(guest, trigger, prefix);
            await guest.keyboard.press('Enter');
            stage = prefix + '/profile-drawer-open';
            await guest.locator('.profile-drawer').filter({ visible: true }).waitFor();
            for (const [index, mode] of ['light', 'dark', 'system'].entries()) {
                const button = guest.locator('.profile-drawer .theme-seg-btn').nth(index);
                // The drawer shell can be visible one frame before its transitioning children.
                await button.waitFor({ state: 'visible' });
                await button.focus();
                need(await button.evaluate(el => el === document.activeElement), 'theme_button_not_focused');
                await guest.keyboard.press('Enter');
                stage = prefix + '/profile-theme-' + mode;
                await themeState(guest, mode, mode === 'system' ? 'light' : mode);
                need(await button.getAttribute(renderer === 'blade' ? 'aria-checked' : 'aria-pressed') === 'true', 'theme_button_state');
            }
            await guest.keyboard.press('Escape');
            stage = prefix + '/profile-drawer-closed';
            await guest.waitForFunction(() => { const el = document.querySelector('.profile-drawer'); return !el || el.getAttribute('aria-hidden') === 'true'; });
            need(await guest.locator(trigger).first().evaluate(el => document.activeElement === el), 'profile_drawer_restore_focus');
        });
        await check(prefix + '/catalog-dialog-escape', async () => {
            await goto(guest, PAGES.find(p => p.id === 'catalog'), renderer);
            const trigger = guest.locator('[data-quick-view]').first();
            await focusControl(guest, '[data-quick-view]', prefix);
            await guest.keyboard.press('Enter');
            await guest.waitForFunction(() => document.querySelector('#catalog-quick-dialog')?.open);
            need(await guest.locator('#dialog-close-btn').evaluate(el => document.activeElement === el), 'dialog_initial_focus');
            await guest.keyboard.press('Escape');
            await guest.waitForFunction(() => !document.querySelector('#catalog-quick-dialog').open);
            need(await trigger.evaluate(el => document.activeElement === el), 'dialog_restore_focus');
        });
        await check(prefix + '/auth-password-toggle', async () => {
            await goto(guest, PAGES.find(p => p.id === 'login'), renderer);
            await focusControl(guest, '.password-toggle', prefix);
            await guest.keyboard.press('Enter');
            need(await guest.locator('#password').getAttribute('type') === 'text', 'password_toggle_open');
            await guest.keyboard.press('Enter');
            need(await guest.locator('#password').getAttribute('type') === 'password', 'password_toggle_close');
        });
        if (pages.admin) await check(prefix + '/admin-profile-link-sections', async () => {
            const page = pages.admin;
            await goto(page, PAGES.find(p => p.id === 'dashboard'), renderer);
            await focusControl(page, '.admin-profile-link', prefix);
            await page.keyboard.press('Enter');
            await page.waitForURL(url => url.pathname === '/admin/profil');
            await ready(page, 'native');
            // FrontendPage's explicit rollback is per-request, not a cookie or global switch.
            if (renderer === 'blade') await goto(page, PAGES.find(p => p.id === 'admin-profile'), renderer);
            await sectionNav(page, ['profile-identity', 'profile-security', 'profile-password']);
        });
        if (pages.customer) await check(prefix + '/customer-profile-link-sections', async () => {
            const page = pages.customer;
            await goto(page, PAGES.find(p => p.id === 'home'), renderer);
            const trigger = renderer === 'native' ? '.header-actions button[aria-haspopup="dialog"]' : '[data-open-profile]';
            await focusControl(page, trigger, prefix);
            await page.keyboard.press('Enter');
            const drawer = page.locator('.profile-drawer').filter({ visible: true });
            stage = prefix + '/customer-drawer-open';
            await drawer.waitFor();
            const link = drawer.locator('a[href$="/akun"]').first();
            need(await link.count() === 1, 'customer_profile_link_missing');
            await link.waitFor({ state: 'visible' });
            await link.focus();
            need(await link.evaluate(el => el === document.activeElement), 'profile_link_not_focused');
            await page.keyboard.press('Enter');
            stage = prefix + '/customer-account-navigation';
            await page.waitForURL(url => url.pathname === '/akun');
            await ready(page, 'native');
            if (renderer === 'blade') await goto(page, PAGES.find(p => p.id === 'account'), renderer);
            await sectionNav(page, ['account-details', 'account-security', 'account-privacy', 'account-history']);
        });
    }
    try {
        const fixtureChannel = require('./acceptance_react_fixture.cjs');
        fixtureChannel(origin); // Uses the existing private channel + read-only oracle, never exports its signed URL.
        const credentials = readCredentials(process.env.AMADIS_ROOT);
        const { chromium } = require(process.env.AM_PLAYWRIGHT);
        browser = await chromium.launch({ headless: true, executablePath: process.env.AM_CHROMIUM, timeout: 15000 });
        record(stage, 'PASS');
        for (const renderer of RENDERERS) {
            const pages = { guest: await pageFor() };
            for (const role of ['customer', 'admin']) {
                const page = await pageFor();
                const loggedIn = await check(`${renderer}/login/${role}`, () => login(page, role, credentials, renderer));
                if (loggedIn) pages[role] = page;
                else await page.context().close();
            }
            for (const mode of MODES) {
                for (const [role, page] of Object.entries(pages)) {
                    await check(`${renderer}/${mode}/${role}/theme-control`, async () => {
                        await page.emulateMedia({ colorScheme: 'light' });
                        await goto(page, PAGES.find(p => p.id === (role === 'admin' ? 'dashboard' : role === 'customer' ? 'account' : 'home')), renderer);
                        await setTheme(page, mode);
                        if (mode !== 'system') {
                            await page.emulateMedia({ colorScheme: mode === 'dark' ? 'light' : 'dark' });
                            await page.reload({ waitUntil: 'domcontentloaded' });
                            await ready(page, renderer);
                            await themeState(page, mode, mode);
                            await page.emulateMedia({ colorScheme: 'light' });
                        }
                        if (role !== 'guest') {
                            await goto(page, PAGES.find(p => p.id === 'home'), renderer);
                            await themeState(page, mode, mode === 'system' ? 'light' : mode);
                        }
                    });
                }
                for (const descriptor of PAGES) {
                    const id = `${renderer}/${mode}/${descriptor.id}`;
                    const page = pages[descriptor.role];
                    if (!page) { record(id + '/load', 'FAIL', { code: 'login_dependency_failed' }); report.cases.push({ id, status: 'FAIL' }); continue; }
                    const loaded = await check(id + '/load', () => goto(page, descriptor, renderer));
                    if (!loaded) { report.cases.push({ id, status: 'FAIL' }); writeReport(output, report); continue; }
                    await check(id + '/theme-route-persistence', () => themeState(page, mode, mode === 'system' ? 'light' : mode));
                    if (mode === 'system') await check(id + '/system-media-live-reload', async () => {
                        await page.emulateMedia({ colorScheme: 'dark' });
                        await themeState(page, 'system', 'dark');
                        await page.reload({ waitUntil: 'domcontentloaded' });
                        await ready(page, renderer);
                        await themeState(page, 'system', 'dark');
                        await page.emulateMedia({ colorScheme: 'light' });
                        await themeState(page, 'system', 'light');
                    });
                    for (const width of WIDTHS) {
                        const cell = id + '/' + width;
                        await page.setViewportSize({ width, height: width === 1440 ? 1000 : 844 });
                        await page.evaluate(async () => {
                            scrollTo({ top: 0, left: 0, behavior: 'instant' });
                            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                        });
                        await reflow(page, cell + '/reflow');
                        if (mode !== 'system' || width === 1440) await check(cell + '/screenshot', () => screenshot(page, cell));
                        if (mode === 'system') continue;
                        if (descriptor.id === 'catalog') await check(cell + '/filter-disclosure', async () => {
                            const details = page.locator('#catalog-filters');
                            if (!(await details.evaluate(el => el.open))) await details.locator('summary').click();
                            need(await details.evaluate(el => el.open), 'filters_not_open');
                            await reflow(page, cell + '/filters-open-reflow');
                        });
                        for (const [index, selector] of descriptor.controls.entries()) await check(cell + '/control-' + index, () => targetSizes(page, selector, cell + '/size-' + index));
                        if (descriptor.role === 'admin' && width === 320) await check(cell + '/admin-mobile-keyboard', () => adminMobile(page, cell + '/navigation'));
                        if (width !== 390) continue;
                        for (const [index, selector] of descriptor.text.entries()) await check(cell + '/text-' + index, () => sample(page, selector, 'text', cell + '/contrast-text-' + index));
                        for (const kind of ['input', 'placeholder']) if (descriptor[kind]) await check(cell + '/' + kind, () => sample(page, descriptor[kind], kind, cell + '/contrast-' + kind));
                        if (descriptor.wishlist) await check(cell + '/wishlist-icon', async () => {
                            await sample(page, '.wishlist-button svg', 'icon', cell + '/contrast-wishlist');
                            const button = page.locator('.wishlist-button').first();
                            await button.click(); // Guest preference only: no new customer/account/auth machinery.
                            await page.waitForFunction(() => document.querySelector('.wishlist-button')?.getAttribute('aria-pressed') === 'true');
                            await sample(page, '.wishlist-button svg', 'icon', cell + '/contrast-wishlist-selected');
                            await button.click();
                            await page.waitForFunction(() => document.querySelector('.wishlist-button')?.getAttribute('aria-pressed') === 'false');
                        });
                        if (descriptor.id === 'home') await check(cell + '/add-icon-hover', async () => {
                            const add = page.locator('.product-add:not(:disabled)').first();
                            need(await add.count() === 1, 'available_add_fixture_missing');
                            await add.hover();
                            await sample(page, '.product-add:not(:disabled) svg', 'icon', cell + '/contrast-add-hover');
                            await page.mouse.move(0, 0);
                        });
                        await check(cell + '/keyboard-focus', () => focusControl(page, descriptor.controls[0], cell, true));
                        await reflow(page, cell + '/after-interaction-reflow');
                    }
                    report.cases.push({ id, status: report.checks.some(c => c.id.startsWith(id + '/') && c.status === 'FAIL') ? 'FAIL' : 'PASS' });
                    writeReport(output, report);
                }
            }
            await interactions(pages, renderer);
            for (const page of Object.values(pages)) await page.context().close();
        }
    } catch (error) { record(stage, 'FAIL', safeError(error)); }
    finally {
        try { await browser?.close(); }
        catch { record('browser-close', 'FAIL', { code: 'browser_cleanup_failed' }); }
        finishMonitors.forEach(finish => finish());
        record('console-network-clean', report.eventCount === 0 ? 'PASS' : 'FAIL', { count: report.eventCount });
        record('matrix-complete', JSON.stringify(report.cases.map(c => c.id)) === JSON.stringify(expectedCases()) ? 'PASS' : 'FAIL');
        report.browserStatus = report.checks.some(c => c.status === 'FAIL') ? 'FAIL' : 'PASS';
        report.finishedAt = new Date().toISOString();
        writeReport(output, report);
        clearTimeout(watchdog);
    }
    process.exitCode = report.browserStatus === 'PASS' ? 0 : 1;
}

function finalize(out, stage, exitCode) {
    need(['init', 'migrate', 'seed', 'provision', 'browser', 'complete'].includes(stage) && Number.isInteger(exitCode) && exitCode >= 0 && exitCode <= 255, 'finalize_arguments');
    let report;
    try { report = JSON.parse(fs.readFileSync(path.join(out, 'ui-local-browser.json'), 'utf8')); }
    catch { report = newReport(); report.browserStatus = 'NOT_RUN'; }
    need(report.schema === 1 && report.task === TASK && Array.isArray(report.checks) && Array.isArray(report.cases) && Array.isArray(report.screenshots), 'report_schema');
    const matrixComplete = JSON.stringify(report.cases.map(c => c.id)) === JSON.stringify(expectedCases());
    const evidencePresent = JSON.stringify([...report.screenshots].sort()) === JSON.stringify(expectedScreenshots()) && report.screenshots.every(name => {
        if (!/^(native|blade)-[a-z0-9-]+\.png$/.test(name)) return false;
        try { const stat = fs.lstatSync(path.join(out, name)); return stat.isFile() && !stat.isSymbolicLink() && stat.size > 0; } catch { return false; }
    });
    const success = stage === 'complete' && exitCode === 0 && report.browserStatus === 'PASS' && matrixComplete && evidencePresent
        && report.checks.length > 0 && report.checks.every(c => c.status === 'PASS') && report.eventCount === 0;
    report.status = success ? 'PASS' : 'FAIL';
    report.supervisor = { stage, exitCode, cleanup: stage === 'complete' && exitCode === 0 ? 'confirmed' : 'unconfirmed' };
    report.summary = { expectedCases: expectedCases().length, visitedCases: report.cases.length,
        checks: report.checks.length, failedChecks: report.checks.filter(c => c.status !== 'PASS').length, screenshots: report.screenshots.length,
        matrixComplete, evidencePresent };
    writeReport(path.join(out, 'ui-local-flow.json'), report, true);
    console.log(JSON.stringify({ task: TASK, status: report.status, ...report.summary, report: 'ui-local-flow.json' }));
    return success ? 0 : 1;
}

module.exports = { PAGES, MODES, WIDTHS, RENDERERS, REQUIREMENTS, parseColor, over, contrast, measurePaint, inspectPaint, validOrigin, routeFor, safeError, expectedCases, expectedScreenshots, finalize };
if (require.main === module) {
    // Debug logging can include Playwright fill arguments; never enable it in this harness.
    delete process.env.DEBUG;
    delete process.env.PWDEBUG;
    delete process.env.NODE_DEBUG;
    if (process.argv[2] === '--finalize') {
        try { process.exitCode = finalize(process.argv[3], process.argv[4], Number(process.argv[5])); }
        catch { console.error('UI_LOCAL_FINALIZE_FAILED'); process.exitCode = 1; }
    } else if (process.argv.length === 2) {
        runBrowser().catch(() => { console.error('UI_LOCAL_PREFLIGHT_FAILED'); process.exitCode = 1; });
    } else { console.error('UI_LOCAL_ARGUMENTS_REJECTED'); process.exitCode = 2; }
}
