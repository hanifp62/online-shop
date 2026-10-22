import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const app = readFileSync(new URL('../prototype/app.js', import.meta.url), 'utf8');
function fn(name) {
  const start = app.indexOf(`function ${name}(`);
  const end = app.indexOf('\n}', start);
  assert.ok(start >= 0 && end > start, `function ${name} must exist`);
  return app.slice(start, end + 2);
}
// Load the real manager too: renderer tests must not stub away overlay integration.
const overlayManager = app.slice(app.indexOf('let drawerOpener = null;'), app.indexOf('\nfunction openDrawer('));
assert.ok(overlayManager.includes('function openOverlay('));

function dom(ids = []) {
  const nodes = new Map();
  const document = { getElementById: id => nodes.get(id), querySelectorAll: () => [] };
  const encode = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  function element(tag = 'div') {
    const classes = new Set();
    const attrs = new Map();
    return {
      tagName: tag.toUpperCase(), children: [], parentNode: null, inert: false, style: {},
      classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
      setAttribute: (name, value) => attrs.set(name, value),
      hasAttribute: name => attrs.has(name),
      get innerHTML() {
        return (this.html || '') + this.children.map(child => `<${child.tagName.toLowerCase()} class="${child.classes()}">${child.innerHTML}</${child.tagName.toLowerCase()}>`).join('');
      },
      set innerHTML(value) { this.html = value; this.children = []; },
      set textContent(value) { this.html = encode(value); this.text = String(value); this.children = []; },
      get textContent() { return this.text || ''; },
      classes: () => [...classes].join(' '),
      appendChild(child) { child.remove(); child.parentNode = this; this.children.push(child); return child; },
      remove() {
        if (this.parentNode) this.parentNode.children.splice(this.parentNode.children.indexOf(this), 1);
        this.parentNode = null; this.removed = true;
      },
      contains(node) { return node === this || this.children.some(child => child.contains(node)); },
      querySelector(selector) {
        for (const child of this.children) {
          if ((selector === '.toast-action-btn' && child.classList.contains('toast-action-btn')) ||
              (selector.includes('button') && child.tagName === 'BUTTON')) return child;
          const found = child.querySelector(selector);
          if (found) return found;
        }
        return null;
      },
      addEventListener(type, callback) { (this.listeners ??= {})[type] = callback; },
      focus() { document.activeElement = this; },
      blur() { if (document.activeElement === this) document.activeElement = document.body; },
      scrollTo() {},
    };
  }
  document.createElement = element;
  document.body = element('body');
  document.activeElement = document.body;
  for (const id of ids) {
    const node = element();
    nodes.set(id, node);
    document.body.appendChild(node);
  }
  return { document, nodes };
}

const payload = '<img src=x onerror="globalThis.injected=1">';
function setup(record) {
  const { document, nodes } = dom(['toast-container', 'kanban-board-container', 'account-history-list']);
  const context = {
    localStorage: { getItem: () => JSON.stringify([record]) },
    document,
    window: {}, setTimeout() {}, formatRupiah: value => String(value),
  };
  // Execute the real storage loader and renderers, with no browser storage or network.
  const load = app.slice(app.indexOf('  load(key, fallback) {'), app.indexOf('\n  save(key, data) {'));
  vm.runInNewContext(`const loader = { ${load} }; const store = { concierge: loader.load('zafira_v3_5_concierge', []), products: [], categories: [] };\n${overlayManager}\n${['escapeHtml', 'showToast', 'renderKanbanBoard', 'renderAccountPortal'].map(fn).join('\n')}`, context);
  return { context, nodes };
}
for (const [renderer, target, fields] of [
  ['renderKanbanBoard', 'kanban-board-container', ['name', 'phone', 'service', 'date', 'notes']],
  ['renderAccountPortal', 'account-history-list', ['service', 'date', 'notes', 'artisan']],
]) {
  for (const field of fields) test(`${renderer} escapes stored ${field}`, () => {
    const { context, nodes } = setup({ id: 42, status: 'new', [field]: payload });
    vm.runInNewContext(`${renderer}()`, context);
    const html = nodes.get(target).innerHTML;
    assert.ok(!html.includes(payload), 'stored payload reaches raw HTML');
    assert.ok(html.includes('&lt;img src=x onerror=&quot;globalThis.injected=1&quot;&gt;'));
  });
}
for (const [renderer, target, status] of [['renderKanbanBoard', 'kanban-board-container', 'new'], ['renderAccountPortal', 'account-history-list', `x"><b>status</b>`]]) {
  test(`${renderer} keeps stored id and status out of HTML and inline JavaScript`, () => {
    const id = `1');globalThis.injected=1;//<b>`;
    const { context, nodes } = setup({ id, status, notes: 'aman' });
    vm.runInNewContext(`${renderer}()`, context);
    const html = nodes.get(target).innerHTML;
    assert.ok(!html.includes(id) && !html.includes(status === 'new' ? id : status));
    assert.doesNotMatch(html, /on(?:click|change)="[^"]*injected/);
  });
}
test('numeric order controls retain numeric IDs for strict-equality callers', () => {
  const { context, nodes } = setup({ id: 42, status: 'new', name: 'Klien Sintetis', notes: 'aman' });
  vm.runInNewContext('renderKanbanBoard(); renderAccountPortal()', context);
  assert.match(nodes.get('kanban-board-container').innerHTML, /adminAssignArtisan\(42, this.value\)/);
  assert.match(nodes.get('account-history-list').innerHTML, /openWorkOrderModal\(42\)/);
  assert.match(nodes.get('account-history-list').innerHTML, /openAuthenticityModal\(42\)/);
});
test('toast escapes message and action label while retaining SVG and undo callback', () => {
  const { context, nodes } = setup({ notes: payload });
  vm.runInNewContext('showToast(store.concierge[0].notes, "info", store.concierge[0].notes, () => { globalThis.undone = true; })', context);
  const toast = nodes.get('toast-container').children[0];
  const button = toast.querySelector('.toast-action-btn');
  assert.ok(!toast.innerHTML.includes(payload), 'stored payload reaches toast HTML');
  assert.equal(button.textContent, payload, 'action label stays literal text');
  assert.match(button.innerHTML, /&lt;img src=x onerror=&quot;globalThis.injected=1&quot;&gt;/);
  assert.match(toast.innerHTML, /<svg /);
  assert.match(toast.innerHTML, /<button class="toast-action-btn"/);
  assert.equal(toast.parentNode, nodes.get('toast-container'));
  button.listeners.click({ stopPropagation() {} });
  assert.equal(context.undone, true);
  assert.equal(toast.parentNode, null);
  assert.ok(!nodes.get('toast-container').children.includes(toast));
});

test('authenticity certificate escapes stored order fields', () => {
  const { document, nodes } = dom(['authenticity-modal-content', 'authenticity-modal', 'drawer-backdrop']);
  const context = { document, store: { concierge: [{ id: 42, name: payload, service: payload, artisan: payload, notes: payload }] } };
  vm.runInNewContext(`${overlayManager}\n${fn('escapeHtml')}\n${fn('openAuthenticityModal')}`, context);
  vm.runInNewContext('openAuthenticityModal(42)', context);
  assert.ok(!nodes.get('authenticity-modal-content').innerHTML.includes(payload));
  assert.match(nodes.get('authenticity-modal-content').innerHTML, /&lt;img src=x onerror=&quot;globalThis.injected=1&quot;&gt;/);
  assert.equal(nodes.get('authenticity-modal').classList.contains('open'), true);
  assert.equal(context.document.activeElement, nodes.get('authenticity-modal'));
});

function render(names, call, ids, store, extra = {}) {
  const { document, nodes } = dom(ids);
  const base = { document, store, window: {}, formatRupiah: String, localStorage: { getItem: () => null } };
  const context = { ...base, ...extra, document: { ...base.document, ...(extra.document || {}) } };
  vm.runInNewContext(overlayManager + '\n' + ['escapeHtml', 'safeImageSrc', ...names].map(fn).join('\n') + '\n' + call, context);
  return nodes;
}
test('work order escapes stored order fields', () => {
  const nodes = render(['openWorkOrderModal'], 'openWorkOrderModal(42)', ['work-order-modal-content', 'work-order-modal', 'drawer-backdrop'],
    { concierge: [{ id: 42, name: payload, phone: payload, date: payload, serial: payload, artisan: payload, notes: payload }] });
  assert.ok(!nodes.get('work-order-modal-content').innerHTML.includes(payload));
  assert.match(nodes.get('work-order-modal-content').innerHTML, /&lt;img src=x onerror=&quot;globalThis.injected=1&quot;&gt;/);
  assert.equal(nodes.get('work-order-modal').classList.contains('open'), true);
});
test('cart drawer escapes product, size and monogram', () => {
  const store = { cart: [{ productId: 1, qty: 1, size: payload, monogram: payload }], products: [{ id: 1 }], getProductLocalized: () => ({ title: payload, price: 1, images: [payload] }), getCartTotal: () => 1 };
  const nodes = render(['renderCartDrawer'], 'renderCartDrawer()', ['cart-items-container', 'cart-subtotal'], store, { document: undefined });
  assert.ok(!nodes.get('cart-items-container').innerHTML.includes(payload));
});
test('audit trail and inventory escape stored text', () => {
  const store = { auditLogs: [{ timestamp: payload, actor: payload, role: payload, module: payload, action: payload, hash: payload }], rawMaterials: [{ id: payload, code: payload, name: payload, origin: payload, stock: 1, safetyStock: 1, unit: payload }], wipStations: [{ name: payload, current: 1, limit: 4 }] };
  const nodes = render(['renderAuditTrail', 'renderMsiWipAndInventory'], 'renderAuditTrail(); renderMsiWipAndInventory()', ['msi-audit-tbody', 'msi-inventory-tbody', 'msi-wip-grid-container'], store);
  for (const id of ['msi-audit-tbody', 'msi-inventory-tbody', 'msi-wip-grid-container']) assert.ok(!nodes.get(id).innerHTML.includes(payload), id);
});
test('editorial catalog encodes stored products and rejects active image schemes', () => {
  const p = { id: 1, cat_id: 1, title: payload, badge: payload, origin: payload, hero_quote: payload, desc: payload, price: 1, formattedPrice: 'Rp1', images: ['javascript:alert(1)'] };
  const store = { searchQuery: '', viewMode: 'editorial', getFilteredProducts: () => [p, { ...p, id: 2 }], getProductLocalized: raw => raw };
  const nodes = render(['renderCatalog'], 'renderCatalog()', ['catalog-content-area'], store);
  const html = nodes.get('catalog-content-area').innerHTML;
  assert.ok(!html.includes(payload));
  assert.doesNotMatch(html, /src="javascript:/);
});
test('studio encodes stored fields, measurements and image URLs', () => {
  const p = { id: 1, cat_id: 1, title: payload, desc: payload, hero_quote: payload, material: payload, origin: payload, craft: payload, care: payload, formattedPrice: 'Rp1', images: ['javascript:alert(1)', 'x" onerror="globalThis.injected=1'], measurements: { "FR 38');alert(1);//": payload } };
  const store = { products: [p], cart: [], getProductLocalized: () => p, getCategoryTitle: () => payload };
  const nodes = render(['openProductStudio'], 'openProductStudio(1)', ['product-studio-view'], store, { switchView() {}, scrollTo() {}, window: { scrollTo() {} } });
  const html = nodes.get('product-studio-view').innerHTML;
  assert.ok(!html.includes(payload));
  assert.doesNotMatch(html, /src="javascript:|onerror="globalThis/);
  // Size key stays one quoted JS string literal: no raw quote can close it and append a statement.
  assert.match(html, /onclick="selectStudioSize\(&quot;FR 38&#39;\);alert\(1\);\/\/&quot;\)"/);
});
test('image URL allowlist keeps relative and http(s) sources only', () => {
  const context = {};
  vm.runInNewContext(['escapeHtml', 'safeImageSrc'].map(fn).join('\n') + '\nglobalThis.f = safeImageSrc', context);
  for (const ok of ['assets/hero_couture.jpg', '/storage/a.jpg', 'https://cdn.example.test/a.jpg', 'http://x.test/a.jpg']) assert.equal(context.f(ok), ok);
  for (const bad of ['javascript:alert(1)', ' JavaScript:alert(1)', 'data:image/svg+xml,<svg>', 'vbscript:x', 'java\nscript:alert(1)', 'java\tscript:alert(1)', '//evil.test/a.jpg', '\\\\evil.test/a.jpg', 'a\nb.jpg', 'a\tb.jpg', '', null]) assert.equal(context.f(bad), '', String(bad));
  assert.equal(context.f('a.jpg" onerror="x'), 'a.jpg&quot; onerror=&quot;x');
});
test('empty cart escapes translated message', () => {
  const nodes = render(['renderCartDrawer'], 'renderCartDrawer()', ['cart-items-container', 'cart-subtotal'], { cart: [] }, { window: { Zafira: { getLang: () => 'id', t: () => payload, formatPrice: () => 'Rp0' } } });
  assert.ok(!nodes.get('cart-items-container').innerHTML.includes(payload));
});
test('product card escapes stored product text', () => {
  const p = { id: 1, cat_id: 1, title: payload, badge: payload, desc: payload, formattedPrice: '1', images: [payload], color_hex: 'red" onmouseover="globalThis.injected=1', color: payload };
  const context = { store: { getProductLocalized: () => p, getCategoryTitle: () => payload }, window: {} };
  vm.runInNewContext(['escapeHtml', 'safeImageSrc', 'renderProductCardHtml'].map(fn).join('\n') + '\nglobalThis.out = renderProductCardHtml({})', context);
  assert.ok(!context.out.includes(payload));
  assert.doesNotMatch(context.out, /onmouseover="globalThis/);
});
