import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('mobile hero text pane reserves space above the fixed dock', () => {
  const css = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
  const mobile = css.match(/@media \(max-width: 768px\) \{[\s\S]*?\n\}/)[0];
  assert.match(mobile, /\.hero-text-pane\s*\{\s*padding-top:\s*112px;\s*\}/);
});

test('mobile hero media fills first screen so hero copy starts below the dock band', () => {
  const css = readFileSync(new URL('../prototype/app.css', import.meta.url), 'utf8');
  const mobile = css.match(/@media \(max-width: 768px\) \{[\s\S]*?\n\}/)[0];
  assert.match(mobile, /\.hero-media-pane\s*\{[^}]*min-height:\s*calc\(100svh - 216px\)/);
  assert.match(mobile, /\.hero-media-img\s*\{\s*position:\s*absolute;\s*inset:\s*0;\s*\}/);
});
