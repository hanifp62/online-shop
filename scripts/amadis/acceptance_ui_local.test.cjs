'use strict';
// Pure Node/unit + source-contract tests. No launcher, PHP, database, browser or build.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const { PAGES, MODES, WIDTHS, RENDERERS, REQUIREMENTS, parseColor, over, contrast, measurePaint, inspectPaint, validOrigin, routeFor, safeError, expectedCases, expectedScreenshots, finalize } = require('./acceptance_ui_local.cjs');
const runner = fs.readFileSync(path.join(__dirname, 'acceptance_ui_local.cjs'), 'utf8');
const wrapper = fs.readFileSync(path.join(__dirname, 'acceptance_ui_local.sh'), 'utf8');
const solid = (color, opacity = 1, extra = {}) => ({ color, opacity, complex: false, effect: false, ...extra });
const paint = (color, layers, paintOpacity = 1) => ({ paint: color, layers, paintOpacity });
const near = (a, b) => assert(Math.abs(a - b) < 1e-9, `${a} != ${b}`);

test('import is inert: no Playwright, fixture Python or application is loaded', () => {
    assert(!Object.keys(require.cache).some(name => /playwright|acceptance_react_fixture|acceptance_react_monitor/.test(name)));
});
test('computed sRGB formats include comma, modern space, percentages and alpha', () => {
    assert.deepEqual(parseColor('rgb(255, 0, 127.5)'), [1, 0, 0.5, 1]);
    assert.deepEqual(parseColor('rgba(255, 0, 0, 0.5)'), [1, 0, 0, 0.5]);
    assert.deepEqual(parseColor('rgb(100% 0% 50% / 25%)'), [1, 0, 0.5, 0.25]);
    assert.deepEqual(parseColor('color(srgb 1 0.25 0 / .4)'), [1, 0.25, 0, 0.4]);
    assert.deepEqual(parseColor('#ffffff80'), [1, 1, 1, 128 / 255]);
    assert.deepEqual(parseColor('transparent'), [0, 0, 0, 0]);
});
test('unsupported colors are not silently treated as black/white', () => {
    for (const value of ['oklch(50% 0.1 20)', 'color(display-p3 1 0 0)', 'rgb(1 2)', 'rgb(999 2 3)', 'rgba(0 0 0 / NaN)', 'currentColor', '', null]) assert.equal(parseColor(value), null);
});
test('source-over accounts for background alpha and zero-alpha inputs', () => {
    assert.deepEqual(over([1, 0, 0, 0.5], [0, 0, 1, 0.5]), [2 / 3, 0, 1 / 3, 0.75]);
    assert.deepEqual(over([1, 0, 0, 0], [0, 1, 0, 0]), [0, 0, 0, 0]);
});
test('WCAG luminance reference: black/white 21 and identical colors 1', () => {
    near(contrast([0, 0, 0], [1, 1, 1]), 21);
    near(contrast([0.4, 0.4, 0.4], [0.4, 0.4, 0.4]), 1);
});
test('text threshold is 4.5, not the icon threshold or rounded-to-pass', () => {
    const failed = measurePaint(paint('#777777', [solid('#ffffff')]), 4.5);
    assert.equal(failed.status, 'FAIL');
    near(failed.ratio, 4.478089453577214);
    assert.equal(measurePaint(paint('#767676', [solid('#ffffff')]), 4.5).status, 'PASS');
});
test('wishlist icons require 3:1', () => {
    assert.equal(measurePaint(paint('#999999', [solid('#ffffff')]), 3).status, 'FAIL');
    assert.equal(measurePaint(paint('#888888', [solid('#ffffff')]), 3).status, 'PASS');
});
test('placeholder color alpha and pseudo opacity multiply before compositing', () => {
    const result = measurePaint(paint('rgba(0,0,0,0.5)', [solid('#ffffff')], 0.5), 4.5);
    assert.deepEqual(result.foreground, [191.25, 191.25, 191.25]);
    assert.equal(result.status, 'FAIL');
});
test('nested translucent solid backgrounds are composed in paint order', () => {
    const result = measurePaint(paint('#000000', [solid('rgba(255,255,255,.5)'), solid('#000000')]), 4.5);
    assert.deepEqual(result.background, [127.5, 127.5, 127.5]);
    assert.deepEqual(result.foreground, [0, 0, 0]);
    near(result.ratio, contrast([0, 0, 0], [0.5, 0.5, 0.5]));
});
test('element opacity applies to its background as a group, not only text', () => {
    const result = measurePaint(paint('#ffffff', [solid('#000000', 0.5), solid('#ffffff')]), 4.5);
    assert.deepEqual(result.background, [127.5, 127.5, 127.5]);
    assert.deepEqual(result.foreground, [255, 255, 255]);
    near(result.ratio, contrast([1, 1, 1], [0.5, 0.5, 0.5]));
});
test('nested group opacity is not flattened into foreground alpha', () => {
    const result = measurePaint(paint('#ffffff', [solid('#000000', 0.5), solid('#ff0000', 0.5), solid('#0000ff')]), 4.5);
    assert.deepEqual(result.foreground, [127.5, 63.75, 191.25]);
    assert.deepEqual(result.background, [63.75, 0, 127.5]);
});
test('opaque wishlist surface hides an image, translucent surface does not', () => {
    const backdrop = solid('#ffffff', 1, { complex: true });
    assert.equal(measurePaint(paint('#ffffff', [solid('#000000'), backdrop]), 3).status, 'PASS');
    assert.equal(measurePaint(paint('#ffffff', [solid('rgba(0,0,0,.9)'), backdrop]), 3).reason, 'non_solid_backdrop');
    assert.equal(measurePaint(paint('#ffffff', [solid('#000000', 0.9), backdrop]), 3).reason, 'non_solid_backdrop');
});
test('unresolved canvas, filters and unknown paints never report PASS', () => {
    assert.equal(measurePaint(paint('#ffffff', [solid('transparent')]), 4.5).reason, 'unresolved_canvas');
    assert.equal(measurePaint(paint('#ffffff', [solid('#000000', 1, { effect: true })]), 4.5).reason, 'paint_effect');
    assert.equal(measurePaint(paint('oklch(80% 0 0)', [solid('#000000')]), 4.5).reason, 'unsupported_paint');
    assert.equal(measurePaint(paint('#ffffff', [solid('color(display-p3 0 0 0)')]), 4.5).reason, 'unsupported_surface');
    assert.equal(measurePaint(paint('#ffffff', [solid('#000000')], NaN), 4.5).status, 'UNMEASURED');
});
test('DOM sampler uses the actual text leaf, ignores transparent target-expansion pseudos', t => {
    const defaults = { color: 'rgb(0, 0, 0)', backgroundColor: 'rgba(0, 0, 0, 0)', opacity: '1', backgroundImage: 'none', filter: 'none', mixBlendMode: 'normal', backdropFilter: 'none', visibility: 'visible', display: 'block', clipPath: 'none', clip: 'auto', content: 'none', position: 'static', boxShadow: 'none' };
    const surface = { children: [], parentElement: null, css: { backgroundColor: 'rgb(255, 255, 255)' } };
    const heading = { children: [], parentElement: surface, css: { color: 'rgb(0, 0, 0)' } };
    const button = { children: [], parentElement: heading, css: { color: 'rgb(119, 119, 119)' }, pseudo: { content: '\"\"', position: 'absolute' } };
    surface.children.push(heading); heading.children.push(button);
    for (const el of [surface, heading, button]) {
        el.getBoundingClientRect = () => ({ left: 0, top: 0, right: 100, bottom: 20 });
        el.contains = other => el === other || el.children.some(child => child.contains(other));
        el.matches = () => false;
    }
    const previous = { document: global.document, NodeFilter: global.NodeFilter, getComputedStyle: global.getComputedStyle };
    t.after(() => { for (const [key, value] of Object.entries(previous)) { if (value === undefined) delete global[key]; else global[key] = value; } });
    global.document = { createTreeWalker: () => { let once = true; return { nextNode: () => { if (!once) return null; once = false; return { textContent: 'synthetic unit text', parentElement: button }; } }; } };
    global.NodeFilter = { SHOW_TEXT: 4 };
    global.getComputedStyle = (el, pseudo) => ({ ...defaults, ...(pseudo ? el.pseudo : el.css) });
    const sample = inspectPaint(heading, 'text');
    assert.equal(sample.paint, 'rgb(119, 119, 119)');
    assert(!sample.layers.some(layer => layer.effect));
    assert.equal(measurePaint(sample, 4.5).status, 'FAIL');
    button.pseudo.backgroundColor = 'rgb(0, 0, 0)';
    assert.equal(measurePaint(inspectPaint(heading, 'text'), 4.5).reason, 'paint_effect');
});
test('DOM placeholder opacity and outside focus paint use the correct surfaces', t => {
    const parent = { parentElement: null, children: [], css: { backgroundColor: 'rgb(255,255,255)' } };
    const input = { parentElement: parent, children: [], css: { backgroundColor: 'rgb(255,255,255)', opacity: '0.8' } };
    parent.children.push(input);
    const defaults = { color: 'rgb(0,0,0)', opacity: '1', backgroundColor: 'rgba(0,0,0,0)', backgroundImage: 'none', mixBlendMode: 'normal', filter: 'none', backdropFilter: 'none', content: 'none', position: 'static', boxShadow: 'none', outlineWidth: '3px', outlineOffset: '3px', borderTopLeftRadius: '0px', borderTopRightRadius: '0px', borderBottomRightRadius: '0px', borderBottomLeftRadius: '0px' };
    const previous = global.getComputedStyle;
    t.after(() => { if (previous === undefined) delete global.getComputedStyle; else global.getComputedStyle = previous; });
    global.getComputedStyle = (el, pseudo) => ({ ...defaults, ...(pseudo === '::placeholder' ? { color: 'rgba(0,0,0,.5)', opacity: '0.5' } : pseudo ? {} : el.css) });
    input.getBoundingClientRect = () => ({ left: 0, top: 0, right: 100, bottom: 20 });
    for (const el of [input, parent]) { el.contains = other => other === input; el.matches = () => false; }
    const result = measurePaint(inspectPaint(input, 'placeholder'), 4.5);
    assert.deepEqual(result.foreground, [204, 204, 204]);
    assert.equal(result.status, 'FAIL');
    input.css = { backgroundColor: 'rgb(0,0,0)', outlineColor: 'rgb(255,255,255)', opacity: '1' };
    const outline = measurePaint(inspectPaint(input, 'focus'), 3);
    near(outline.ratio, 1); // White outline on the white parent, not on the black input itself.
    assert.equal(outline.status, 'FAIL');
});
// R2 geometry repro: use the serialized DOM sampler, as locator.evaluate does, without a browser.
function focusScene(t, { width = 44, height = 44, radius = '0px' } = {}) {
    const defaults = { color: 'rgb(0,0,0)', backgroundColor: 'transparent', opacity: '1', backgroundImage: 'none', filter: 'none', mixBlendMode: 'normal', backdropFilter: 'none', content: 'none', position: 'static', boxShadow: 'none', outlineColor: 'rgb(0,0,0)', outlineWidth: '3px', outlineOffset: '3px' };
    for (const corner of ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft']) defaults[`border${corner}Radius`] = radius;
    function append(parent, tag, rect, css = {}) {
        const el = { tag, rect, css, parentElement: parent, children: [] };
        el.getBoundingClientRect = () => { const [left, top, right, bottom] = el.rect; return { left, top, right, bottom, width: right - left, height: bottom - top }; };
        el.contains = other => el === other || el.children.some(child => child.contains(other));
        el.matches = selector => selector.split(',').some(name => name.trim() === tag);
        parent?.children.push(el);
        return el;
    }
    const surface = append(null, 'div', [-100, -100, 500, 500], { backgroundColor: 'rgb(255,255,255)' });
    const control = append(surface, 'button', [0, 0, width, height]);
    const getStyle = (el, pseudo) => ({ ...defaults, ...(pseudo ? el.pseudo : el.css) });
    const inspect = vm.runInNewContext(`(${inspectPaint.toString()})`, { getComputedStyle: getStyle });
    return { surface, control, append, measure: (kind = 'focus') => measurePaint(inspect(control, kind), kind === 'focus' ? 3 : 4.5) };
}
test('R2 header: a centered child SVG is not behind the rounded outside outline', t => {
    const scene = focusScene(t, { radius: '50%' });
    scene.append(scene.control, 'svg', [12.5, 12.5, 31.5, 31.5]); // 19px glyph inside a 44px button.
    assert.equal(scene.measure().status, 'PASS');
    near(scene.measure().ratio, 21);
    scene.control.css.outlineColor = 'rgba(0,0,0,.4)';
    assert.equal(scene.measure().status, 'FAIL'); // Geometry must not bypass contrast/alpha.
});
test('R2 search: moving the same sibling SVG from input interior into the ring rejects it', t => {
    const scene = focusScene(t, { width: 260, radius: '100px' });
    const icon = scene.append(scene.surface, 'svg', [12, 13, 26, 27]);
    assert.equal(scene.measure().status, 'PASS');
    icon.rect = [-5, 20, -4, 24];
    assert.equal(scene.measure().reason, 'non_solid_backdrop');
    icon.rect = [12, 13, 26, 27];
    assert.equal(scene.measure().status, 'PASS');
});
for (const [label, rect, intersects] of [
    ['interior', [12, 12, 31, 31], false],
    ['offset gap', [-3, 12, -1, 31], false],
    ['left ring only', [-5, 12, -4, 31], true],
    ['right ring only', [48, 12, 49, 31], true],
    ['top ring only', [12, -5, 31, -4], true],
    ['bottom ring only', [12, 48, 31, 49], true],
    ['fractional inner boundary', [-3.1, 12, -2.9, 31], true],
    ['outside touching outer boundary', [-7, 12, -6, 31], false],
    ['spanning control and ring', [-5, 12, 5, 31], true],
    ['covering the whole ring', [-20, -20, 64, 64], true],
]) test(`focus-band image mutation: ${label}`, t => {
    const scene = focusScene(t);
    scene.append(scene.surface, 'img', rect);
    const result = scene.measure();
    if (intersects) assert.equal(result.reason, 'non_solid_backdrop');
    else { assert.equal(result.status, 'PASS'); near(result.ratio, 21); }
});
for (const radius of ['50%', '999px']) {
    test(`rounded ${radius} ring can cross a media rect inside the control's bounding box`, t => {
        const scene = focusScene(t, { radius });
        scene.append(scene.control, 'svg', [2, 2, 3, 3]); // Distance 26.9..28.3 from center; ring radii 25..28.
        assert.equal(scene.measure().reason, 'non_solid_backdrop');
    });
    test(`rounded ${radius} offset gap is not part of the ring`, t => {
        const scene = focusScene(t, { radius });
        scene.append(scene.control, 'svg', [4.5, 4.5, 5.5, 5.5]); // Inside inner radius 25, outside button radius 22.
        assert.equal(scene.measure().status, 'PASS');
    });
    test(`rounded ${radius} exterior corner is not part of the outer bounding box band`, t => {
        const scene = focusScene(t, { radius });
        scene.append(scene.surface, 'img', [-5, -5, -4, -4]);
        assert.equal(scene.measure().status, 'PASS');
    });
}
test('focus geometry uses the computed outline width and offset, not hardcoded 3px', t => {
    const scene = focusScene(t);
    const image = scene.append(scene.surface, 'img', [-8, 12, -7, 31]);
    assert.equal(scene.measure().status, 'PASS');
    scene.control.css.outlineWidth = '5px'; scene.control.css.outlineOffset = '4px';
    assert.equal(scene.measure().reason, 'non_solid_backdrop');
    image.rect = [-3, 12, -2, 31];
    assert.equal(scene.measure().status, 'PASS');
});
test('elliptical percentage radii keep an interior glyph out of the outline measurement', t => {
    const scene = focusScene(t, { width: 88, radius: '50% 50%' });
    scene.append(scene.control, 'svg', [10, 7, 11, 8]);
    assert.equal(scene.measure().status, 'PASS');
});
test('focus still rejects real image surfaces, painted overlays and group effects', t => {
    const scene = focusScene(t);
    scene.surface.css.backgroundImage = 'linear-gradient(black, white)';
    assert.equal(scene.measure().reason, 'non_solid_backdrop');
    scene.surface.css.backgroundImage = 'none';
    scene.surface.pseudo = { content: '""', position: 'absolute', backgroundColor: 'rgba(0,0,0,.4)' };
    assert.equal(scene.measure().reason, 'paint_effect');
    delete scene.surface.pseudo;
    for (const [property, value] of [['filter', 'blur(1px)'], ['backdropFilter', 'blur(1px)'], ['mixBlendMode', 'multiply']]) {
        scene.surface.css[property] = value;
        assert.equal(scene.measure().reason, 'paint_effect');
        delete scene.surface.css[property];
    }
});
test('placeholder media intersection remains the control interior, not the focus band', t => {
    const scene = focusScene(t);
    const image = scene.append(scene.surface, 'img', [-5, 12, -4, 31]);
    assert.equal(scene.measure('placeholder').status, 'PASS');
    image.rect = [12, 12, 31, 31];
    assert.equal(scene.measure('placeholder').reason, 'non_solid_backdrop');
});
test('loopback-only origin excludes live/reserved ports, auth, queries and remote hosts', () => {
    assert(validOrigin('http://127.0.0.1:8044'));
    for (const value of ['http://localhost:8044', 'http://127.0.0.1:8000', 'http://127.0.0.1:8001', 'http://127.0.0.1:65536', 'http://127.0.0.1:80', 'http://127.0.0.1:08044', 'http://127.0.0.1:8044/', 'http://user:pw@127.0.0.1:8044', 'https://example.test', undefined]) assert(!validOrigin(value));
});
test('matrix includes every required page and reports is mandatory', () => {
    assert.deepEqual(WIDTHS, [320, 390, 1440]);
    assert.deepEqual(MODES, ['dark', 'light', 'system']);
    assert.deepEqual(RENDERERS, ['native', 'blade']);
    for (const route of ['/', '/koleksi', '/produk/amadis-demo-stock-37', '/belanja', '/login', '/register', '/lupa-kata-sandi', '/admin/login', '/akun', '/admin', '/admin/profil', '/admin/auth-events', '/admin/reports/operations-summary']) assert(PAGES.some(p => p.route === route));
    assert.equal(PAGES.find(p => p.id === 'reports').optional, undefined);
    assert.equal(new Set(expectedCases()).size, 78);
    assert.equal(REQUIREMENTS.length, 4);
    for (const page of PAGES) { assert(page.text.length >= 3); assert(page.controls.length > 0); }
});
test('native and explicit Blade URLs are separate, no monkeypatching the renderer', () => {
    assert.equal(routeFor('/akun', 'native'), '/akun');
    assert.equal(routeFor('/akun', 'blade'), '/akun?frontend=blade');
    assert.throws(() => routeFor('/akun', 'unknown'));
});
test('errors cannot leak messages, call logs, stack, credentials or assertion values', () => {
    const error = new Error('secret-fill-argument');
    error.actual = 'private-value'; error.expected = 'private-value'; error.code = 'attacker-supplied';
    assert.deepEqual(safeError(error), { code: 'operation_failed' });
    error.name = 'TimeoutError';
    assert.deepEqual(safeError(error), { code: 'timeout' });
});
test('fixture/auth flow reuses guarded provision/channel and real login forms', () => {
    assert.match(wrapper, /launch\.sh provision-react "\$PORT"/);
    assert.match(runner, /require\('\.\/acceptance_react_fixture\.cjs'\)/);
    assert.match(runner, /fixtureChannel\(origin\)/);
    assert.match(runner, /credentials\[role\]\.password/);
    assert.doesNotMatch(runner, /addCookies|storageState:|setExtraHTTPHeaders|INSERT INTO|UPDATE users|tinker/);
    assert.match(wrapper, /unset AMADIS_ROOT/);
});
test('owned cleanup and bounded runtime reuse the supervisor without changing the old harness', () => {
    assert.match(wrapper, /from scripts\.amadis\.acceptance_server import wait_command, stop_owned/);
    assert.match(wrapper, /wait_command\(process, 120\)/);
    assert.match(wrapper, /acceptance_server\.py "\$PORT" "\$OUT\/serve.log" node scripts\/amadis\/acceptance_ui_local.cjs/);
    assert.match(runner, /BUDGET_MS = 260000/);
    assert.doesNotMatch(wrapper.replace(/^\s*#.*$/gm, ''), /npm |npx |migrate:fresh|db:wipe|killall|pkill/);
    assert.doesNotMatch(runner, /require\('\.\/acceptance_react_(?:complete|security)\.cjs'\)/);
});
test('evidence is bounded and private, without HTML/trace/HAR or browser console payloads', () => {
    assert.match(wrapper, /umask 077/);
    assert.match(runner, /fullPage: false/);
    assert.match(runner, /mask: \[page\.locator/);
    assert.match(runner, /report\.events\.length < 20/);
    assert.match(runner, /mode: 0o600/);
    assert.doesNotMatch(runner, /message\.text\(|page\.content\(|tracing\.start|recordHar|recordVideo|console\.log\(credentials/);
});

function publicationFixture(t, change = () => {}) {
    const out = fs.mkdtempSync(path.join(os.tmpdir(), 'ui-local-unit-'));
    t.after(() => fs.rmSync(out, { recursive: true, force: true }));
    const report = { schema: 1, task: 'UI-LOCAL-20261025', browserStatus: 'PASS', checks: [{ id: 'unit-only', status: 'PASS' }], cases: expectedCases().map(id => ({ id, status: 'PASS' })), eventCount: 0, screenshots: expectedScreenshots() };
    for (const name of report.screenshots) fs.writeFileSync(path.join(out, name), Buffer.from([137, 80, 78, 71]));
    change(report);
    fs.writeFileSync(path.join(out, 'ui-local-browser.json'), JSON.stringify(report));
    return out;
}
function publishQuietly(...args) {
    const original = console.log;
    try { console.log = () => {}; return finalize(...args); }
    finally { console.log = original; }
}
test('browser PASS cannot publish before supervisor cleanup succeeds', t => {
    const out = publicationFixture(t);
    assert.equal(publishQuietly(out, 'browser', 1), 1);
    const result = JSON.parse(fs.readFileSync(path.join(out, 'ui-local-flow.json')));
    assert.equal(result.status, 'FAIL');
    assert.equal(result.supervisor.cleanup, 'unconfirmed');
});
test('partial matrix or missing screenshot makes final publication fail', t => {
    const out = publicationFixture(t, report => { report.cases.pop(); report.screenshots.push('blade-dark-missing.png'); });
    assert.equal(publishQuietly(out, 'complete', 0), 1);
});
test('a nominal PASS with only one image cannot pass the complete evidence gate', t => {
    const out = publicationFixture(t, report => { report.screenshots = report.screenshots.slice(0, 1); });
    assert.equal(publishQuietly(out, 'complete', 0), 1);
});
test('passing report is exclusive and cannot overwrite previous evidence', t => {
    const out = publicationFixture(t);
    assert.equal(publishQuietly(out, 'complete', 0), 0);
    assert.equal(JSON.parse(fs.readFileSync(path.join(out, 'ui-local-flow.json'))).status, 'PASS');
    assert.throws(() => publishQuietly(out, 'complete', 0));
});
test('setup failure still emits a safe NOT_RUN report', t => {
    const out = fs.mkdtempSync(path.join(os.tmpdir(), 'ui-local-unit-'));
    t.after(() => fs.rmSync(out, { recursive: true, force: true }));
    assert.equal(publishQuietly(out, 'provision', 1), 1);
    const result = JSON.parse(fs.readFileSync(path.join(out, 'ui-local-flow.json')));
    assert.equal(result.browserStatus, 'NOT_RUN');
    assert.equal(result.status, 'FAIL');
    assert.equal(result.summary.visitedCases, 0);
});
