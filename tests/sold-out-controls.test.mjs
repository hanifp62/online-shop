import test from 'node:test';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const catalog = readFileSync('resources/views/catalog/index.blade.php', 'utf8');
const pdp = readFileSync('resources/views/products/show.blade.php', 'utf8');
const labels = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require "resources/views/catalog/labels.php");'], { encoding: 'utf8' }));

test('FR-SOLDOUT-01: translated unavailable label exists for every catalog locale', () => {
    for (const [locale, row] of Object.entries(labels)) assert.ok(row.sold_out?.length, `${locale}: sold_out`);
});

test('FR-SOLDOUT-01: catalog card and quick view prevent adding stock-zero products', () => {
    assert.match(catalog, /\$product->stock\s*<=\s*0[\s\S]*?disabled[\s\S]*?\$t\['sold_out'\]/);
    assert.match(catalog, /data-stock="\{\{\s*max\(0,\s*\(int\)\s*\$product->stock\)\s*\}\}"/);
    assert.match(catalog, /dialog-cart-form[\s\S]*?dialog-add-button/);
    assert.match(catalog, /var soldOut = Number\(btn\.getAttribute\('data-stock'\)\) <= 0;/);
    assert.match(catalog, /dialogAddButton\.disabled = soldOut;/);
    assert.match(catalog, /dialogQtyInput\.disabled = soldOut;/);
    assert.match(catalog, /var textSoldOut = @json\(\$t\['sold_out'\]\)/);
    assert.match(catalog, /dialogAddButton\.textContent = soldOut \? textSoldOut : textSetQuantity;/);
    // Same catalog card button: disabled only at stock <= 0, sold-out label vs original in-stock label.
    assert.match(catalog, /<button type="submit" class="catalog-btn"[^\n]*@if \(\$product->stock <= 0\) disabled @endif>\{\{ \$product->stock <= 0 \? \$t\['sold_out'\] : \$t\['set_one'\] \}\}<\/button>/);
});

test('FR-SOLDOUT-01: PDP disables purchase when live product stock is zero', () => {
    assert.match(pdp, /data-product-add[\s\S]*?\$product->stock\s*<=\s*0[\s\S]*?disabled[\s\S]*?Stok habis/);
});

const home = readFileSync('resources/views/welcome.blade.php', 'utf8');
const footer = readFileSync('resources/views/partials/product-footer.blade.php', 'utf8');
const appJs = readFileSync('resources/js/app.js', 'utf8');
const storefront = readFileSync('app/Http/Controllers/StorefrontController.php', 'utf8');
const invalidation = readFileSync('app/Http/Controllers/AdminCategoryController.php', 'utf8');

test('FR-SOLDOUT-01: homepage and related cards expose stock and disable sold-out add', () => {
    assert.match(home, /data-stock="\{\{\s*max\(0,\s*\(int\)\s*\$product\['stock'\]\)\s*\}\}"/);
    assert.match(home, /data-card-add @if \(\$product\['stock'\] <= 0\) disabled @endif/);
    assert.match(pdp, /data-stock="\{\{\s*max\(0,\s*\(int\)\s*\$item->stock\)\s*\}\}"/);
    assert.match(pdp, /data-card-add @if \(\$item->stock <= 0\) disabled @endif/);
    // In-stock path keeps the original label.
    assert.match(home, /\$product\['stock'\] <= 0 \? 'Stok habis' : 'Tambah'/);
    assert.match(pdp, /\$item->stock <= 0 \? 'Stok habis' : 'Tambah'/);
});

test('FR-SOLDOUT-01: shared quick view and card handler refuse sold-out products', () => {
    assert.match(appJs, /stock: Number\(card\.dataset\.stock\)/);
    assert.match(appJs, /const soldOut = product\.stock <= 0;[\s\S]*?button\.disabled = soldOut;/);
    assert.match(appJs, /if \(product\.stock <= 0\) return;/);
    assert.match(appJs, /if \(!currentQuickProduct \|\| currentQuickProduct\.stock <= 0\) return;/);
    for (const view of [home, footer]) assert.match(view, /data-quick-availability/);
});

test('FR-SOLDOUT-01: shared quick view resets sold-out state when opening an in-stock card', () => {
    const readProduct = appJs.slice(appJs.indexOf('    function readProduct('), appJs.indexOf('    function rupiah('));
    const showQuickView = appJs.slice(appJs.indexOf('    function showQuickView('), appJs.indexOf('    // Satu-satunya'));
    const button = { disabled: false, firstChild: { textContent: '' } };
    const availability = { lastChild: { textContent: '' } };
    const context = {
        quickViewModal: {}, currentQuickProduct: null,
        select: (selector) => selector === '[data-quick-availability]' ? availability : {},
        selectAll: () => [button], openElement: () => {},
    };
    vm.createContext(context);
    vm.runInContext(readProduct + showQuickView, context);
    for (const stock of [0, 3, 0]) {
        context.showQuickView({ dataset: { stock: String(stock) } });
        assert.equal(button.disabled, stock === 0);
        assert.equal(button.firstChild.textContent.trim(), stock === 0 ? 'Stok habis' : 'Tambahkan ke tas');
        assert.equal(availability.lastChild.textContent, stock === 0 ? 'Stok habis' : 'Tersedia');
    }
});

test('FR-SOLDOUT-01: home/related payload selects stock under v3 cache keys with matching invalidation', () => {
    assert.match(storefront, /'stock' => max\(0,\s*\(int\)\s*\$product->stock\)/);
    assert.equal((storefront.match(/'product_price', 'stock'/g) || []).length, 3);
    assert.match(storefront, /'storefront:products:v3'/);
    assert.match(storefront, /storefront:related:\{\$product->product_cat\}:\{\$product->product_id\}:v3/);
    assert.match(invalidation, /'storefront:products:v3'/);
    assert.match(invalidation, /storefront:related:\{\$product->product_cat\}:\{\$product->product_id\}:v3/);
    assert.doesNotMatch(storefront + invalidation, /storefront:(products|related)[^'"]*:v2/);
});
