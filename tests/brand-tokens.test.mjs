import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';

const source = 'resources/css/app.css';
const css = readFileSync(source, 'utf8');
const root = css.match(/:root\s*\{([^}]+)\}/s)[1];
const colors = new Set([...root.matchAll(/--[\w-]+:\s*(#[\da-f]{6})\s*;/gi)].map((match) => match[1].toLowerCase()));
// Email clients do not reliably support CSS variables; exceptions are file-specific.
const exceptions = new Set([
    'resources/views/mail/cart-reminder.blade.php',
    'resources/views/mail/concierge-requested.blade.php',
    'resources/views/mail/newsletter-welcome.blade.php',
    'resources/views/mail/order-status-changed.blade.php',
]);
// Existing platform/serif fallbacks, technical monospace identifiers, and code fonts stay unchanged.
const fonts = new Set(['bodoni moda', 'manrope', 'serif', 'sans-serif', 'monospace', 'ui-monospace', 'ui-sans-serif', 'system-ui', 'inherit', 'arial', 'times new roman', 'georgia', '-apple-system', 'blinkmacsystemfont', 'sfmono-regular', 'menlo']);

// Font custom properties defined in :root / @theme; only these may be used as var(--x) without fallback.
const fontTokenName = /^--(?:font[\w-]*|serif|sans|mono)$/;
const tokenBlocks = (text) => [...text.matchAll(/(?::root|@theme)\s*\{([^}]+)\}/g)].map((match) => match[1]);
const fontDeclarations = (text) => tokenBlocks(text).flatMap((block) => [...block.matchAll(/(--[\w-]+)\s*:\s*([^;}]+)/g)])
    .filter((match) => fontTokenName.test(match[1]));
const fontTokens = new Set(fontDeclarations(css).map((match) => match[1]));

function familyErrors(value, path, delimiter) {
    const errors = [];
    // An inline style attribute ends at its own quote, before any var() parsing.
    let stack = delimiter ? value.split(delimiter)[0] : value;
    // Unwrap innermost fallbacks first so their quotes are not HTML boundaries.
    const variable = /var\(\s*(--[\w-]+)\s*(?:,([^()]+))?\)/gi;
    while (stack.match(variable)) {
        stack = stack.replace(variable, (_, name, fallback) => {
            if (fallback?.trim()) return fallback;
            if (!fontTokens.has(name)) errors.push(`${path}: undefined font token ${name}`);
            return 'inherit';
        });
    }
    if (!delimiter) stack = stack.split(/"(?=\s+[^\s,]|>)/)[0];
    for (const family of stack.split(',')) {
        const name = family.trim().replace(/^['"]|['"]$/g, '').toLowerCase();
        // Existing counter typography is technical monospace, not a new brand face.
        const counterFont = name === 'jetbrains mono' && [source, 'resources/views/welcome.blade.php'].includes(path);
        if (!fonts.has(name) && !counterFont) errors.push(`${path}: off-brand font ${name}`);
    }
    return errors;
}

// Font tokens declared in :root / @theme are validated at the definition, not only at use sites.
const rootFontErrors = (text, path) => fontDeclarations(text).flatMap((match) => familyErrors(match[2], path));

function violations(text, path) {
    if (exceptions.has(path)) return [];
    let input = text;
    if (path === source) input = input.replace(/:root\s*\{[^}]+\}/s, (block) => block.replace(/--[\w-]+:\s*#[\da-f]{6}\s*;/gi, ''));
    // Browser chrome metadata requires literals; only these existing views are exempt.
    const metadataViews = new Set([
        'resources/views/account/totp-enrol.blade.php',
        'resources/views/account/totp-recovery.blade.php',
        'resources/views/admin/layouts/app.blade.php',
        'resources/views/auth/login.blade.php',
        'resources/views/auth/register.blade.php',
        'resources/views/auth/totp.blade.php',
        'resources/views/products/show.blade.php',
        'resources/views/welcome.blade.php',
    ]);
    if (metadataViews.has(path)) input = input.replace(/<meta name="theme-color" content="#17263f">/g, '');
    const errors = [];
    for (const match of input.matchAll(/(?<!&)#[\da-f]{3,8}\b/gi)) {
        const hex = match[0].toLowerCase();
        const expanded = hex.length === 4 ? '#' + [...hex.slice(1)].map((c) => c + c).join('') : hex;
        if (colors.has(expanded)) errors.push(`${path}: literal brand color ${hex}`);
    }
    errors.push(...rootFontErrors(input, path));
    for (const match of input.matchAll(/font-family\s*:\s*([^;}\r\n]+)/gi)) {
        const lineStart = Math.max(input.lastIndexOf('\n', match.index), input.lastIndexOf('\r', match.index)) + 1;
        // Inside style="…" / style='…' the opening quote terminates the inline style attribute.
        const before = input.slice(lineStart, match.index);
        const attribute = /style\s*=\s*(["'])(?:(?!\1)[^\r\n])*$/i.exec(before);
        errors.push(...familyErrors(match[1], path, attribute?.[1]));
    }
    return errors;
}

function files(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);
        return entry.isDirectory() ? files(path) : [path];
    });
}

test('R-55: brand literals and off-brand fonts stay out of application surfaces', () => {
    assert.ok(colors.size >= 10, 'Existing brand palette must remain discoverable');
    const errors = ['resources/css', 'resources/js', 'resources/views'].flatMap(files)
        .flatMap((path) => violations(readFileSync(path, 'utf8'), path));
    assert.deepEqual(errors, []);
});

test('guard detects Blade/JS colors and mixed font stacks without matching HTML entities', () => {
    assert.equal(violations('color:#B69A64', 'fixture.blade.php').length, 1);
    assert.equal(violations('font-family: Manrope, Comic Sans MS, sans-serif;', 'fixture.css').length, 1);
    assert.equal(violations('font-family: var(--sans); &#039;', 'fixture.js').length, 0);
    assert.equal(violations('font-family: "Comic Sans MS";', 'fixture.css').length, 1);
    assert.equal(violations('font-family: var(--mono, "JetBrains Mono", monospace);', source).length, 0);
    assert.equal(violations('font-family: "JetBrains Mono", monospace;', 'fixture.css').length, 1);
    assert.equal(violations('<meta name="theme-color" content="#17263f">', 'fixture.blade.php').length, 1);
});

test('quoted var() fallbacks are parsed, not bypassed (review R-55 HIGH)', () => {
    // Reviewer examples: a quoted off-brand fallback inside var() must still fail.
    assert.deepEqual(violations('font-family: var(--sans, "Comic Sans MS", sans-serif);', 'fixture.css'), ['fixture.css: off-brand font comic sans ms']);
    assert.deepEqual(violations('font-family: var(--font, "Papyrus");', 'fixture.css'), ['fixture.css: off-brand font papyrus']);
    assert.deepEqual(violations('font-family: var(--sans, "Manrope", sans-serif);', 'fixture.css'), []);
    // Whitespace before the comma must neither hide nor invent a violation.
    assert.deepEqual(violations('font-family: var(--serif, "Bodoni Moda" , serif);', 'fixture.css'), []);
    assert.deepEqual(violations('font-family: var(--font, "Comic Sans MS" , sans-serif);', 'fixture.css'), ['fixture.css: off-brand font comic sans ms']);
    // Nested fallbacks resolve to the innermost family.
    assert.deepEqual(violations('font-family: var(--font, var(--serif, "Bodoni Moda"));', 'fixture.css'), []);
    assert.deepEqual(violations('font-family: var(--font, var(--x, "Papyrus"));', 'fixture.css'), ['fixture.css: off-brand font papyrus']);
    // Blade inline declarations stop at the closing attribute quote.
    assert.deepEqual(violations('<p style="font-family: Manrope" class="x">', 'fixture.blade.php'), []);
    assert.deepEqual(violations('<p style="font-family: Papyrus" class="x">', 'fixture.blade.php'), ['fixture.blade.php: off-brand font papyrus']);
    assert.deepEqual(violations("<p style=\"font-family: 'Bodoni Moda', serif\">", 'fixture.blade.php'), []);
    // JS string declarations are still scanned.
    assert.deepEqual(violations("el.style.cssText = 'font-family: Papyrus';", 'fixture.js'), ['fixture.js: off-brand font papyrus']);
});

test('root font tokens are validated at the definition (review R-55 MEDIUM)', () => {
    assert.deepEqual(violations(css, source), []);
    const mutated = css.replace(/--serif:\s*'Bodoni Moda'/, "--serif: 'Papyrus'");
    assert.notEqual(mutated, css);
    assert.deepEqual(violations(mutated, source), [`${source}: off-brand font papyrus`]);
    const theme = css.replace(/--font-sans:\s*'Manrope'/, "--font-sans: 'Comic Sans MS'");
    assert.deepEqual(violations(theme, source), [`${source}: off-brand font comic sans ms`]);
    assert.deepEqual(violations(':root { --mono: "Papyrus", monospace; }', 'fixture.css'), ['fixture.css: off-brand font papyrus']);
    assert.deepEqual(violations(':root { --brand-mark: 42px; --serif: "Bodoni Moda", serif; }', 'fixture.css'), []);
});

test('var() without fallback must reference a defined font token (review R-55 MEDIUM)', () => {
    assert.deepEqual(violations('font-family: var(--serif);', 'fixture.css'), []);
    assert.deepEqual(violations('font-family: var(--font-sans);', 'fixture.css'), []);
    assert.deepEqual(violations('font-family: var(--custom);', 'fixture.css'), ['fixture.css: undefined font token --custom']);
    assert.deepEqual(violations('font-family: var(--mono);', 'fixture.css'), ['fixture.css: undefined font token --mono']);
    assert.deepEqual(violations('font-family: var(--custom, var(--other));', 'fixture.css'), ['fixture.css: undefined font token --other']);
});

test('var() whitespace and Blade attribute quoting do not cause false results (review R-55 LOW)', () => {
    assert.deepEqual(violations('font-family: var( --sans );', 'fixture.css'), []);
    assert.deepEqual(violations('font-family: var(  --x ,\t"Papyrus" );', 'fixture.css'), ['fixture.css: off-brand font papyrus']);
    assert.deepEqual(violations('font-family: var( --custom );', 'fixture.css'), ['fixture.css: undefined font token --custom']);
    assert.deepEqual(violations("<p style='font-family: \"Bodoni Moda\", serif' class=\"x\">", 'fixture.blade.php'), []);
    assert.deepEqual(violations("<p style='font-family: \"Papyrus\", serif' class=\"x\">", 'fixture.blade.php'), ['fixture.blade.php: off-brand font papyrus']);
    assert.deepEqual(violations("<p style='font-family: var( --sans )'>", 'fixture.blade.php'), []);
    assert.deepEqual(violations('<p style="font-family: var( --sans, Papyrus )" class=\'x\'>', 'fixture.blade.php'), ['fixture.blade.php: off-brand font papyrus']);
});
