import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const catalog = readFileSync('resources/views/catalog/index.blade.php', 'utf8');
const pdp = readFileSync('resources/views/products/show.blade.php', 'utf8');
const welcome = readFileSync('resources/views/welcome.blade.php', 'utf8');
const appJs = readFileSync('resources/js/app.js', 'utf8');

test('R54: catalog card image and data-alt use shared imageAlt() fallback', () => {
    assert.match(catalog, /alt="\{\{ \$product->imageAlt\(\) \}\}"/);
    assert.match(catalog, /data-alt="\{\{ \$product->imageAlt\(\) \}\}"/);
    assert.doesNotMatch(catalog, /\$product->product_alt \?: \$product->product_title/);
});

test('R54: PDP main and related images use shared imageAlt() fallback', () => {
    assert.match(pdp, /alt="\{\{ \$product->imageAlt\(\) \}\}"/);
    assert.match(pdp, /alt="\{\{ \$item->imageAlt\(\) \}\}"/);
    assert.doesNotMatch(pdp, /\$product->product_alt \?: \$product->product_title/);
    assert.doesNotMatch(pdp, /\$item->product_alt \?: \$item->product_title/);
});

test('R54: quickview resolves alt from card data-alt, not product name', () => {
    assert.match(appJs, /select\("\[data-quick-image\]"\)\.alt = product\.alt;/);
    assert.match(appJs, /alt: card\.dataset\.alt/);
    // Card must expose the shared alt on the data-alt channel quickview reads from.
    assert.match(welcome, /data-alt="\{\{ \$product\['alt'\] \}\}"/);
    assert.match(pdp, /data-alt="\{\{ \$item->imageAlt\(\) \}\}"/);
});

test('R54: fallback label resolves in every locale (not a raw key)', () => {
    const labels = JSON.parse(execFileSync('php', [
        '-r',
        'echo json_encode(array_combine($l=["id","en","fr","it","zh"], array_map(fn($x)=>require "lang/$x/customer_messages.php", $l)));',
    ], { encoding: 'utf8' }));
    for (const [locale, row] of Object.entries(labels)) {
        const label = row?.storefront?.product_image;
        assert.ok(typeof label === 'string' && label.trim() !== '', `${locale}: product_image present`);
        assert.notEqual(label, 'customer_messages.storefront.product_image', `${locale}: key resolves`);
    }
});
