import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const view = readFileSync('resources/views/catalog/index.blade.php', 'utf8');
const labels = JSON.parse(execFileSync('php', ['-r', 'echo json_encode(require "resources/views/catalog/labels.php");'], { encoding: 'utf8' }));

test('FR-CLOSURE-01: each locale distinguishes SET from additive cart intent', () => {
    for (const [locale, row] of Object.entries(labels)) {
        assert.ok(row.set_one?.length > 0, `${locale}: explicit one-unit action`);
        assert.ok(row.set_quantity?.length > 0, `${locale}: explicit quantity action`);
        assert.ok(row.replace_hint?.length > 0, `${locale}: replacement warning`);
    }
});

test('FR-CLOSURE-01: replacement copy sits beside both controls and live feedback retains cart access', () => {
    assert.match(view, /\$t\['set_one'\]/);
    assert.match(view, /\$t\['set_quantity'\]/);
    assert.equal((view.match(/\$t\['replace_hint'\]/g) || []).length, 2);
    assert.match(view, /id="dialog-qty"[^>]*aria-describedby="dialog-replace-hint"/);
    assert.match(view, /id="catalog-shop-status"[^>]*role="status"[^>]*aria-live="polite"/);
    assert.match(view, /route\('shop.index'\)/);
});
