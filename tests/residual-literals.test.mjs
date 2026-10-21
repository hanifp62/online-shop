import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('PDP residual labels use the shop_ui catalog', () => {
    const view = readFileSync(new URL('../resources/views/products/show.blade.php', import.meta.url), 'utf8');
    for (const key of ['size_guide_eyebrow', 'signature_wrapping', 'care_for_life', 'discover']) {
        assert.ok(view.includes(`__('shop_ui.pdp.${key}')`), key);
    }
    assert.doesNotMatch(view, />Guide des Tailles<|>Signature wrapping<|>Care for Life<|>À Découvrir</);
});

test('Residual JS literals: app.js and admin-table.js read translated copy blobs with fallback', () => {
    const appJs = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
    const adminTableJs = readFileSync(new URL('../resources/js/admin-table.js', import.meta.url), 'utf8');

    // app.js must read a copy helper / blob element
    assert.match(appJs, /zafira-app-copy|getAppCopy|appCopy/);

    // admin-table.js must read a copy helper / blob element
    assert.match(adminTableJs, /zafira-admin-table-copy|getAdminTableCopy|tableCopy/);

    // Hardcoded status strings in admin-table.js should be replaced or read via copy lookup
    assert.doesNotMatch(adminTableJs, /announce\("Menyimpan status pesanan…"\)/);
    assert.doesNotMatch(adminTableJs, /announce\("Status pesanan berhasil diperbarui\."\)/);
    assert.doesNotMatch(adminTableJs, /announce\("Memuat data tabel…"\)/);
    assert.doesNotMatch(adminTableJs, /win\.confirm\("Batalkan pesanan ini secara permanen\? Tindakan ini tidak dapat diurungkan\."\)/);
});
