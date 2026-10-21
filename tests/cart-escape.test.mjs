import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";

// Pins storefront cart drawer escaping: product titles reach innerHTML only via escapeHtml.
const app = readFileSync(
    new URL("../resources/js/app.js", import.meta.url),
    "utf8",
);
const slice = (from, to) => {
    const start = app.indexOf(from);
    const end = app.indexOf(to, start);
    assert.ok(start >= 0 && end > start, `source marker missing: ${from}`);
    return app.slice(start, end);
};
const escapeSource = slice("const escapeHtml", "const storefront");
const renderCartSource = slice(
    "    function renderCart()",
    "    function changeQuantity",
);

function run(cart, translations = {}) {
    const container = {
        children: [],
        replaceChildren() {
            this.children = [];
        },
        appendChild(child) {
            this.children.push(child);
        },
    };
    const elements = {
        "[data-cart-items]": container,
        "[data-cart-empty]": {},
        "[data-cart-footer]": {},
        "[data-cart-total]": {},
    };
    const context = {
        cart,
        cartSubtotal: 0,
        cartDrawer: null,
        rupiah: (n) => `Rp${n}`,
        select: (selector) => elements[selector] ?? null,
        document: {
            activeElement: null,
            createElement: () => ({ dataset: {} }),
        },
        getAppCopy: (key, fallback) => translations[key] ?? fallback,
    };
    vm.createContext(context);
    vm.runInContext(
        `${escapeSource}\n${renderCartSource}\nrenderCart(); globalThis.escapeHtml = escapeHtml;`,
        context,
    );
    return {
        html: container.children.map((child) => child.innerHTML).join(""),
        escapeHtml: context.escapeHtml,
    };
}

test("escapeHtml encodes & < > \" '", () => {
    const { escapeHtml } = run([]);
    assert.equal(escapeHtml(`&<>"'`), "&amp;&lt;&gt;&quot;&#039;");
    assert.equal(escapeHtml(null), "");
});

test("renderCart escapes hostile product_title in text and aria-label attributes", () => {
    const { html } = run([
        {
            product_id: 1,
            product_title: '<b>x</b>" onmouseover="alert(1)',
            unit_price: 10,
            quantity: "<i>2</i>",
            available: true,
        },
    ]);
    assert.ok(
        html.includes("&lt;b&gt;x&lt;/b&gt;&quot; onmouseover=&quot;alert(1)"),
    );
    assert.ok(!html.includes("<b>x</b>"));
    assert.ok(!html.includes('" onmouseover="'));
    assert.ok(!html.includes("<i>2</i>"));
});

test("renderCart escapes hostile translation text in availability message", () => {
    const hostileTranslation = '<script>alert("xss")</script>';
    const { html } = run(
        [
            {
                product_id: 1,
                product_title: "Kreasi Test",
                unit_price: 10,
                quantity: 1,
                available: false,
            },
        ],
        { unavailable: hostileTranslation },
    );
    assert.ok(
        html.includes("&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"),
    );
    assert.ok(!html.includes("<script>"));
});

test("every ${...} interpolation in the renderCart template passes through escapeHtml", () => {
    const template = renderCartSource.slice(
        renderCartSource.indexOf("element.innerHTML = `"),
        renderCartSource.indexOf(
            "`;",
            renderCartSource.indexOf("element.innerHTML = `"),
        ),
    );
    const interpolations = [...template.matchAll(/\$\{([^}]*)\}/g)].map((m) =>
        m[1].trim(),
    );
    assert.ok(interpolations.length > 0);
    // Only escapeHtml(...) calls or the ternary containing escapeHtml(copy('unavailable', ...)) are allowed.
    for (const expr of interpolations) {
        const isStandardEscaped = expr.startsWith("escapeHtml(");
        const isEscapedAvailability =
            expr ===
            'item.available ? "" : "<p>" + escapeHtml(copy("unavailable", "Stok tidak tersedia. Hapus kreasi ini sebelum checkout.")) + "</p>"';
        assert.ok(
            isStandardEscaped || isEscapedAvailability,
            `unescaped interpolation: ${expr}`,
        );
    }
});
