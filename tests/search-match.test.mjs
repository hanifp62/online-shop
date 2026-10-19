import { test } from 'node:test';
import assert from 'node:assert/strict';
import { searchMatches } from '../resources/js/search-match.js';

test('searchMatches gives substring priority over typo matches', () => {
    const items = [
        { id: '1', haystack: 'tas kulit' },
        { id: '2', haystack: 'tas sutra' },
    ];
    const results = searchMatches(items, 'sutr');
    assert.deepEqual(results.map((i) => i.id), ['2']);
});

test('searchMatches fallback works for single-word typo with edit distance 1', () => {
    const items = [
        { id: '1', haystack: 'sutra sutera halus' },
        { id: '2', haystack: 'gaun malam sutra' },
        { id: '3', haystack: 'jaket kulit' },
    ];
    // single character replacement
    assert.deepEqual(searchMatches(items, 'sutrx').map((i) => i.id), ['1', '2']);
    // single character insertion in query
    assert.deepEqual(searchMatches(items, 'sutraa').map((i) => i.id), ['1', '2']);
    // single character deletion in query (candidate sutera -> query stera)
    assert.deepEqual(searchMatches(items, 'stera').map((i) => i.id), ['1']);
});

test('searchMatches fallback works for adjacent transposition', () => {
    const items = [
        { id: '1', haystack: 'sutra sutera halus' },
        { id: '2', haystack: 'gaun malam sutra' },
        { id: '3', haystack: 'jaket kulit' },
    ];
    assert.deepEqual(searchMatches(items, 'stura').map((i) => i.id), ['1', '2']);
});

test('searchMatches does not trigger typo fallback for word length < 4 or multi-word queries', () => {
    const items = [
        { id: '1', haystack: 'tas kulit' },
        { id: '2', haystack: 'sutra sutera halus' },
    ];
    // word length 3 with 1 typo
    assert.deepEqual(searchMatches(items, 'tax').map((i) => i.id), []);
    // multi-word query with 1 typo in a word
    assert.deepEqual(searchMatches(items, 'sutrx halus').map((i) => i.id), []);
});

test('searchMatches respects limit parameter', () => {
    const items = [
        { id: '1', haystack: 'sutra satu' },
        { id: '2', haystack: 'sutra dua' },
        { id: '3', haystack: 'sutra tiga' },
        { id: '4', haystack: 'sutra empat' },
        { id: '5', haystack: 'sutra lima' },
    ];
    assert.equal(searchMatches(items, 'sutra', 4).length, 4);
});
