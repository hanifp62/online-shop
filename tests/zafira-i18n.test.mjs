import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const core = readFileSync(new URL('../public/js/zafira-core.js', import.meta.url), 'utf8');
const context = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
runInNewContext(core, context);
const dictionaries = context.window.Zafira.I18N;
const welcome = readFileSync(new URL('../resources/views/welcome.blade.php', import.meta.url), 'utf8');
const usedKeys = [...new Set([...welcome.matchAll(/data-i18n(?:-placeholder|-title)?="([^"]+)"/g)].map(([, key]) => key))];

test('exported dictionaries cover storefront translation attributes', () => {
    assert.deepEqual(Object.keys(dictionaries), ['zh', 'id', 'en', 'fr', 'it']);
    for (const [language, dictionary] of Object.entries(dictionaries)) {
        const missing = usedKeys.filter(key => typeof dictionary[key] !== 'string' || !dictionary[key].trim());
        assert.deepEqual(missing, [], `${language}: missing storefront translations`);
    }
});
