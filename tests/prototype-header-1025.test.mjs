import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// Run13: /prototype/ header overflowed 20-127px at 1025-1300px (all langs) because compact
// header rules lived only in max-width:1024. The 1025-1400 block must carry them too.
const css = readFileSync(process.env.PROTOTYPE_CSS || 'prototype/app.css', 'utf8');

test('prototype header keeps compact sizing at 1025-1400px (no horizontal overflow)', () => {
  const start = css.indexOf('@media (min-width: 1025px) and (max-width: 1400px)');
  assert.ok(start >= 0, 'missing 1025-1400 media block');
  const body = css.slice(start, css.indexOf('@media', start + 1));
  assert.match(body, /\.sf-header\s*\{\s*padding:\s*12px 20px;/);
  assert.match(body, /\.sf-nav\s*\{\s*gap:\s*4px;/);
  assert.match(body, /\.sf-nav a\s*\{\s*font-size:\s*10px;\s*letter-spacing:\s*0\.04em;/);
  assert.match(body, /\.sf-brand-title\s*\{\s*font-size:\s*22px;/);
  assert.match(body, /\.search-input\s*\{\s*width:\s*100px;/);
  // Fix by spacing only: no element may be hidden in this range.
  assert.doesNotMatch(body, /display:\s*none/);
});
