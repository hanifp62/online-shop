import "./bootstrap";
import { initAdminTables } from "./admin-table";
import { searchMatches } from "./search-match";

initAdminTables();

const select = (selector, scope = document) => scope.querySelector(selector);
const selectAll = (selector, scope = document) => [
    ...scope.querySelectorAll(selector),
];

let appCopy = {};
try {
    const blob =
        typeof document !== "undefined"
            ? document.getElementById("zafira-app-copy")
            : null;
    if (blob?.textContent) appCopy = JSON.parse(blob.textContent);
} catch {
    // Keep exact Indonesian fallbacks when blob is not present or malformed.
}
const getAppCopy = (key, fallback) =>
    typeof appCopy?.[key] === "string" ? appCopy[key] : fallback;
const prefersReducedMotion = () =>
    window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
const smoothScrollTo = (element) => {
    if (!element) return;
    element.scrollIntoView({
        behavior: prefersReducedMotion() ? "auto" : "smooth",
    });
};

const STORAGE_VERSION = "v1";
const MONOGRAM_SANITIZE_RE = /[^a-zA-Z]/g; // js-hoist-regexp: jangan buat RegExp per keystroke
const scheduleIdleWork =
    window.requestIdleCallback?.bind(window) ?? ((cb) => setTimeout(cb, 1)); // js-request-idle-callback

const storage = {
    // js-cache-storage: cache baca di memori, tulis tetap sync ke localStorage.
    // client-localstorage-schema: kunci berversi + migrasi + simpan field minimal.
    _mem: new Map(),
    _key(key) {
        return `${key}:${STORAGE_VERSION}`;
    },
    get(key, fallback) {
        const namespaced = this._key(key);
        if (this._mem.has(namespaced)) return this._mem.get(namespaced);
        try {
            let raw = localStorage.getItem(namespaced);
            if (raw === null) {
                // Migrasi sekali dari kunci lama tanpa versi.
                raw = localStorage.getItem(key);
                if (raw !== null) {
                    try {
                        localStorage.setItem(namespaced, raw);
                    } catch {
                        /* abaikan */
                    }
                    try {
                        localStorage.removeItem(key);
                    } catch {
                        /* abaikan */
                    }
                }
            }
            const value =
                raw === null ? fallback : (JSON.parse(raw) ?? fallback);
            this._mem.set(namespaced, value);
            return value;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        const namespaced = this._key(key);
        this._mem.set(namespaced, value);
        try {
            localStorage.setItem(namespaced, JSON.stringify(value));
        } catch {
            // The storefront still works if private browsing blocks storage.
        }
    },
    invalidate(key) {
        this._mem.delete(this._key(key));
    },
};
if (typeof window !== "undefined") {
    window.addEventListener("storage", (e) => {
        if (e.key) storage._mem.delete(e.key);
    });
}

const escapeHtml = (value) =>
    String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

const storefront = select(".storefront");

if (storefront) {
    const overlay = select("[data-overlay]");
    const cartDrawer = select(".cart-drawer");
    const searchPanel = select(".search-panel");
    const quickViewModal = select(".quick-view-modal");
    const conciergeModal = select(".concierge-modal");
    const profileDrawer = select("#welcome-profile-drawer");
    const sizeGuideDrawer = select("[data-size-guide]");
    const shortcutsModal = select("#welcome-shortcuts-backdrop");
    const mobileMenu = select(".mobile-menu");
    [
        cartDrawer,
        searchPanel,
        quickViewModal,
        conciergeModal,
        profileDrawer,
        sizeGuideDrawer,
        shortcutsModal,
    ].forEach((element) => {
        if (!element) return;
        element.inert = true;
        element.setAttribute("aria-hidden", "true");
    });
    const productCards = selectAll("[data-product-card]");
    const productMap = new Map(
        productCards.map((card) => [card.dataset.id, readProduct(card)]),
    );
    // js-cache-function-results + js-index-maps: haystack search dihitung sekali,
    // bukan string-template per produk per keystroke.
    const productHaystack = new Map(
        [...productMap.entries()].map(([id, p]) => [
            id,
            `${p.name} ${p.category} ${p.color} ${p.description} ${p.material} ${p.origin}`.toLowerCase(),
        ]),
    );
    // js-cache-property-access: cache referensi DOM yang dipakai di hot path.
    const cartCountEls = selectAll(".cart-count");
    const wishlistCountEls = selectAll(".wishlist-count");
    const cartCountTextEls = selectAll(".cart-count-text");
    let cart = [];
    let cartSubtotal = 0;
    let cartRequests = Promise.resolve();
    let inquiryItems = [];
    // Legacy data is read-only: never migrate, rewrite, or automatically merge it.
    for (const key of ["zafira-cart:v1", "zafira-cart"]) {
        try {
            const legacy = JSON.parse(localStorage.getItem(key) || "[]");
            if (!Array.isArray(legacy) || !legacy.length) continue;
            const panel = select("[data-legacy-cart]");
            if (panel) panel.hidden = false;
            const list = select("[data-legacy-lines]");
            legacy.forEach((item) => {
                if (!item || typeof item !== "object" || !list) return;
                const line = document.createElement("li");
                line.textContent = `${item.name || item.id || getAppCopy("legacy_item", "Kreasi")} × ${item.quantity || 1}${item.size ? ` · ${getAppCopy("size_label", "Ukuran")}: ${item.size}` : ""}${item.monogram ? ` · ${getAppCopy("monogram_label", "Monogram")}: ${item.monogram}` : ""}`;
                list.appendChild(line);
            });
        } catch {
            /* Leave unreadable legacy data untouched. */
        }
    }
    const catalogSearch = select("#live-catalog-search-input");
    let activeCategory = "Semua";
    let activeWishlistOnly = false;
    let wishlist = storage.get("zafira-wishlist", []);
    // js-set-map-lookups: O(1) untuk includes() yang dipanggil di loop render/filter.
    let wishlistSet = new Set(wishlist);
    let currentQuickProduct = null;
    let toastTimer;
    let lastFocusedElement = null;

    function readProduct(card) {
        return {
            id: card.dataset.id,
            productId: Number(card.dataset.productId),
            name: card.dataset.name,
            category: card.dataset.category,
            price: Number(card.dataset.price),
            stock: Number(card.dataset.stock),
            priceLabel: card.dataset.priceLabel,
            image: card.dataset.image,
            alt: card.dataset.alt,
            description: card.dataset.description,
            color: card.dataset.color,
            material: card.dataset.material || "",
            origin: card.dataset.origin || "",
        };
    }

    function rupiah(value) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(value);
    }

    let dismissToast = () => {};

    function showToast(message, action = null) {
        const toast = select(".toast");
        if (!toast) return;
        const home = toast._home || toast.parentNode;
        toast._home = home;
        const actionBtnBefore = select("[data-toast-action]", toast);
        if (document.activeElement === actionBtnBefore) {
            select("[data-close-cart]", cartDrawer)?.focus();
        }
        if (action && cartDrawer?.classList.contains("open"))
            cartDrawer.appendChild(toast);
        else if (toast.parentNode !== home) home.appendChild(toast);
        const msg = select("[data-toast-message]", toast);
        if (msg) msg.textContent = message;
        let actionBtn = select("[data-toast-action]", toast);
        if (action) {
            if (!actionBtn) {
                actionBtn = document.createElement("button");
                actionBtn.type = "button";
                actionBtn.dataset.toastAction = "";
                actionBtn.style.cssText =
                    "margin-left:8px;color:#c7a665;background:transparent;cursor:pointer;font-size:12px;text-decoration:underline;";
                toast.appendChild(actionBtn);
            }
            actionBtn.textContent = action.label;
            actionBtn.hidden = false;
            actionBtn.onclick = () => {
                dismissToast();
                action.onClick();
            };
        } else if (actionBtn) {
            actionBtn.hidden = true;
            actionBtn.onclick = null;
        }
        toast.classList.add("show");
        clearTimeout(toastTimer);
        dismissToast = () => {
            clearTimeout(toastTimer);
            toast.classList.remove("show");
            if (actionBtn) {
                actionBtn.hidden = true;
                actionBtn.onclick = null;
            }
            if (document.activeElement === actionBtn)
                select("[data-close-cart]", cartDrawer)?.focus();
            if (toast.parentNode !== home) home.appendChild(toast);
        };
        const expire = () => {
            if (action && toast.contains(document.activeElement)) {
                toastTimer = setTimeout(expire, 6000);
                return;
            }
            dismissToast();
        };
        toastTimer = setTimeout(expire, action ? 6000 : 3500);
    }

    function syncOverlay() {
        const somethingOpen = [
            cartDrawer,
            searchPanel,
            quickViewModal,
            conciergeModal,
            profileDrawer,
            sizeGuideDrawer,
            shortcutsModal,
        ].some((element) => element?.classList.contains("open"));
        overlay?.classList.toggle("open", Boolean(somethingOpen));
        document.body.classList.toggle(
            "no-scroll",
            Boolean(somethingOpen || mobileMenu?.classList.contains("open")),
        );
    }

    function openElement(element, trigger = document.activeElement) {
        if (!element) return;
        const parentDialog = [
            cartDrawer,
            searchPanel,
            quickViewModal,
            conciergeModal,
            profileDrawer,
            sizeGuideDrawer,
            shortcutsModal,
            mobileMenu,
        ].find((layer) => layer !== element && layer?.contains?.(trigger));
        element._opener = parentDialog?._opener || trigger;
        lastFocusedElement = element._opener;
        if (element === shortcutsModal) {
            element.style.display = "flex";
            select(".modal-window", element)?.classList.add("open");
        }
        element.inert = false;
        element.classList.add("open");
        element.setAttribute("aria-hidden", "false");
        syncOverlay();
        const preferredFocus =
            element === searchPanel
                ? select("[data-search-input]", element)
                : select(
                      'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
                      element,
                  );
        // Retry while opening transitions keep the target unfocusable.
        const focusInside = (attempt = 0) => {
            if (
                !element.classList.contains("open") ||
                !preferredFocus ||
                element.contains(document.activeElement)
            )
                return;
            preferredFocus.focus();
            if (document.activeElement !== preferredFocus && attempt < 10) {
                setTimeout(() => focusInside(attempt + 1), 50);
            }
        };
        setTimeout(focusInside, 50);
    }

    let cartDrawerEpoch = 0;

    function closeElement(element, restoreFocus = true) {
        if (!element) return;
        const wasOpen = element.classList.contains("open");
        if (element === cartDrawer) {
            cartDrawerEpoch++;
            if (wasOpen && select(".toast")?.parentNode === cartDrawer)
                dismissToast();
        }
        element.classList.remove("open");
        element.setAttribute("aria-hidden", "true");
        element.inert = true;
        if (element === shortcutsModal) {
            select(".modal-window", element)?.classList.remove("open");
            element.style.display = "none";
        }
        syncOverlay();
        const opener = element._opener || lastFocusedElement;
        if (
            wasOpen &&
            restoreFocus &&
            !select('.open[role="dialog"], .mobile-menu.open')
        )
            opener?.focus?.();
    }

    function closeShortcuts() {
        closeElement(shortcutsModal);
    }

    function openShortcuts(trigger = document.activeElement) {
        openElement(shortcutsModal, trigger);
    }

    window.closeWelcomeShortcutsModal = closeShortcuts;
    window.openWelcomeShortcutsModal = openShortcuts;

    function closeAll() {
        const layers = [
            cartDrawer,
            searchPanel,
            quickViewModal,
            conciergeModal,
            profileDrawer,
            sizeGuideDrawer,
            shortcutsModal,
        ];
        const active = layers.find((element) =>
            element?.classList.contains("open"),
        );
        const opener = active?._opener;
        layers.forEach((element) => closeElement(element, false));
        opener?.focus?.();
    }

    function updateCounts() {
        const quantity = cart.reduce((sum, item) => sum + item.quantity, 0);
        for (const count of cartCountEls) {
            count.textContent = quantity;
            count.classList.toggle("has-items", quantity > 0);
        }
        for (const count of wishlistCountEls) {
            count.textContent = wishlist.length;
            count.classList.toggle("has-items", wishlist.length > 0);
        }
        for (const count of cartCountTextEls) {
            count.textContent = `(${quantity})`;
        }
    }

    function syncWishlistButtons() {
        for (const button of selectAll("[data-toggle-wishlist]")) {
            const active = wishlistSet.has(
                button.closest("[data-product-card]").dataset.id,
            );
            button.classList.toggle("active", active);
            button.setAttribute("aria-pressed", String(active));
        }
        updateCounts();
    }

    function toggleWishlist(card) {
        const product = readProduct(card);
        // js-early-exit: keluar cepat setelah ketemu cabang yang cocok.
        if (wishlistSet.has(product.id)) {
            wishlistSet.delete(product.id);
            wishlist = wishlist.filter((id) => id !== product.id);
            showToast(
                getAppCopy(
                    "wish_removed",
                    ":name dihapus dari favorit.",
                ).replace(":name", product.name),
            );
        } else {
            wishlistSet.add(product.id);
            wishlist.push(product.id);
            showToast(
                getAppCopy(
                    "wish_saved",
                    ":name disimpan dalam favorit.",
                ).replace(":name", product.name),
            );
        }
        storage.set("zafira-wishlist", wishlist);
        syncWishlistButtons();
    }

    function setConciergeMode() {
        const modal = select(".concierge-modal");
        if (!modal) return;
        modal.classList.remove("is-checkout");
        const summary = select("[data-checkout-summary]", modal);
        const orderItemsInput = select("[data-order-items]", modal);
        const lines = summary && select("[data-checkout-lines]", summary);
        if (orderItemsInput) orderItemsInput.value = "";
        if (summary) summary.hidden = !inquiryItems.length;
        if (lines) {
            lines.replaceChildren();
            inquiryItems.forEach((item) => {
                const line = document.createElement("li");
                line.textContent = `${item.name}${item.size ? ` · ${getAppCopy("size_label", "Ukuran")}: ${item.size}` : ""}${item.monogram ? ` · ${getAppCopy("monogram_label", "Monogram")}: ${item.monogram}` : ""}`;
                lines.appendChild(line);
            });
        }
        const title = select("[data-checkout-total]", modal);
        if (title)
            title.textContent = inquiryItems.length
                ? getAppCopy(
                      "inquiry_total",
                      "Permintaan personalisasi — harga dikonfirmasi advisor",
                  )
                : "";
    }

    /** Ukuran dan monogram tidak pernah masuk tas server; keduanya jadi permintaan concierge eksplisit. */
    function openInquiry(product, personalization, trigger) {
        inquiryItems = [
            {
                name: product.name,
                size: personalization.size,
                monogram: personalization.monogram,
            },
        ];
        setConciergeMode(false);
        const notes = select('.concierge-modal [name="notes"]');
        if (notes) {
            notes.value =
                getAppCopy(
                    "inquiry_notes",
                    "Permintaan personalisasi :name",
                ).replace(":name", product.name) +
                (personalization.size
                    ? ` · ${getAppCopy("size_label", "Ukuran")}: ${personalization.size}`
                    : "") +
                (personalization.monogram
                    ? ` · ${getAppCopy("monogram_label", "Monogram")}: ${personalization.monogram}`
                    : "");
        }
        const service = select('.concierge-modal [name="service"]');
        if (service) service.value = "atelier";
        openElement(conciergeModal, trigger);
        showToast(
            getAppCopy(
                "inquiry_toast",
                "Ukuran dan monogram dikirim sebagai permintaan concierge, bukan pembelian katalog.",
            ),
        );
    }

    function cartRequest(url, method = "GET", body = null) {
        const copy =
            typeof getAppCopy === "function"
                ? getAppCopy
                : (_key, fallback) => fallback;
        const request = cartRequests
            .catch(() => null)
            .then(async () => {
                const status = select("[data-cart-status]");
                if (status)
                    status.textContent = copy("processing", "Memproses…");
                try {
                    const fetchWithTimeout = async (requestUrl, options) => {
                        // No AbortController (old runtime): send without a timeout rather than fail.
                        if (typeof AbortController !== "function")
                            return fetch(requestUrl, options);
                        const controller = new AbortController();
                        const timeout = setTimeout(
                            () => controller.abort(),
                            10000,
                        );
                        try {
                            return await fetch(requestUrl, {
                                ...options,
                                signal: controller.signal,
                            });
                        } finally {
                            clearTimeout(timeout);
                        }
                    };
                    const parseResponse = async (response) => {
                        try {
                            return await response.json();
                        } catch {
                            throw new Error(
                                copy(
                                    "err_invalid",
                                    "Respons server tidak valid. Coba lagi.",
                                ),
                            );
                        }
                    };
                    const responseError = (response, data) => {
                        if (response.status === 419)
                            return copy(
                                "err_session",
                                "Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.",
                            );
                        if (response.status >= 500)
                            return copy(
                                "err_service",
                                "Layanan sedang bermasalah. Coba lagi sebentar lagi.",
                            );
                        return (
                            data.message ||
                            copy("err_bag", "Tas belum dapat diperbarui.")
                        );
                    };
                    if (typeof url === "function") {
                        const freshResponse = await fetchWithTimeout("/cart", {
                            headers: { Accept: "application/json" },
                        });
                        const freshData = await parseResponse(freshResponse);
                        if (!freshResponse.ok)
                            throw new Error(
                                responseError(freshResponse, freshData),
                            );
                        cart = freshData.items || [];
                        cartSubtotal = freshData.subtotal || 0;
                        renderCart();
                        updateCounts();
                        const intent = await url();
                        if (!intent) {
                            if (status)
                                status.textContent = copy(
                                    "bag_updated",
                                    "Tas belanja diperbarui.",
                                );
                            return null;
                        }
                        [url, method, body = null] = intent;
                    }
                    const response = await fetchWithTimeout(url, {
                        method,
                        headers: {
                            Accept: "application/json",
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN":
                                select('meta[name="csrf-token"]')?.content ||
                                "",
                        },
                        body: body ? JSON.stringify(body) : null,
                    });
                    const data = await parseResponse(response);
                    if (!response.ok)
                        throw new Error(responseError(response, data));
                    cart = data.items || [];
                    cartSubtotal = data.subtotal || 0;
                    renderCart();
                    updateCounts();
                    if (status) {
                        const count = cart.reduce(
                            (sum, entry) => sum + Number(entry.quantity || 0),
                            0,
                        );
                        status.textContent = cart.length
                            ? copy(
                                  "bag_summary",
                                  "Tas belanja diperbarui: :count barang, subtotal :subtotal.",
                              )
                                  .replace(":count", String(count))
                                  .replace(":subtotal", rupiah(cartSubtotal))
                            : copy("bag_empty", "Tas belanja kosong.");
                    }
                    return data;
                } catch (error) {
                    const knownMessages = new Set([
                        copy(
                            "err_invalid",
                            "Respons server tidak valid. Coba lagi.",
                        ),
                        copy(
                            "err_session",
                            "Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.",
                        ),
                        copy(
                            "err_service",
                            "Layanan sedang bermasalah. Coba lagi sebentar lagi.",
                        ),
                        copy("err_bag", "Tas belum dapat diperbarui."),
                    ]);
                    const message =
                        error?.name === "AbortError"
                            ? copy(
                                  "err_timeout",
                                  "Permintaan terlalu lama. Coba lagi.",
                              )
                            : knownMessages.has(error?.message)
                              ? error.message
                              : copy(
                                    "err_network",
                                    "Jaringan bermasalah. Periksa koneksi lalu coba lagi.",
                                );
                    if (status) status.textContent = message;
                    showToast(message);
                    return null;
                }
            });
        cartRequests = request;
        return request;
    }

    // Relative change: the server applies it under its cart lock, so another tab's write is not overwritten.
    const cartDelta = (productId, delta) => [
        `/cart/items/${productId}/delta`,
        "POST",
        { delta },
    ];

    const addToCart = (product) =>
        cartRequest(() => cartDelta(product.productId, 1)).then((data) => {
            if (data)
                showToast(
                    getAppCopy(
                        "added_toast",
                        ":name ditambahkan ke tas Anda.",
                    ).replace(":name", product.name),
                );
            return data;
        });

    function renderCart() {
        const copy =
            typeof getAppCopy === "function"
                ? getAppCopy
                : (_key, fallback) => fallback;
        const container = select("[data-cart-items]");
        const empty = select("[data-cart-empty]");
        const footer = select("[data-cart-footer]");
        if (!container || !empty || !footer) return;

        const active = document.activeElement;
        const focusedCartId =
            active?.closest?.("[data-cart-id]")?.dataset?.cartId;
        const hadFocus = !!focusedCartId;
        const focusedAction =
            active?.dataset?.quantity ??
            (active?.closest?.("[data-remove-cart]") ? "remove" : null);
        container.replaceChildren();
        empty.hidden = cart.length > 0;
        footer.hidden = cart.length === 0;

        cart.forEach((item) => {
            const element = document.createElement("article");
            element.className = "cart-item";
            element.dataset.cartId = String(item.product_id);
            element.innerHTML = `
                <div class="cart-item-info">
                    <h3>${escapeHtml(item.product_title)}</h3>
                    <p translate="no">${escapeHtml(rupiah(item.unit_price))}</p>
                    ${item.available ? "" : "<p>" + escapeHtml(copy("unavailable", "Stok tidak tersedia. Hapus kreasi ini sebelum checkout.")) + "</p>"}
                    <div class="quantity-control" aria-label="${escapeHtml(copy("qty_group", "Jumlah :name").replace(":name", item.product_title))}">
                        <button type="button" data-quantity="minus" aria-label="${escapeHtml(copy("qty_minus", "Kurangi jumlah"))}"><svg><use href="#icon-minus"/></svg></button>
                        <span>${escapeHtml(item.quantity)}</span>
                        <button type="button" data-quantity="plus" aria-label="${escapeHtml(copy("qty_plus", "Tambah jumlah"))}"><svg><use href="#icon-plus"/></svg></button>
                    </div>
                </div>
                <button class="cart-remove" type="button" data-remove-cart aria-label="${escapeHtml(copy("remove_item", "Hapus :name").replace(":name", item.product_title))}"><svg><use href="#icon-close"/></svg></button>
            `;
            container.appendChild(element);
        });

        const totalElement = select("[data-cart-total]");
        if (totalElement) totalElement.textContent = rupiah(cartSubtotal);

        if (hadFocus) {
            const actionSelector =
                focusedAction === "remove"
                    ? "[data-remove-cart]"
                    : `[data-quantity="${focusedAction}"]`;
            // Row gone (deleted): fall back to the drawer close button so keyboard focus is not lost to <body>.
            const target =
                (focusedCartId &&
                    focusedAction &&
                    select(
                        `[data-cart-id="${focusedCartId}"] ${actionSelector}`,
                        container,
                    )) ||
                select("[data-close-cart]", cartDrawer);
            target?.focus?.();
        }
    }

    function changeQuantity(productId, direction) {
        cartRequest(() => {
            const item = cart.find(
                (entry) => String(entry.product_id) === productId,
            );
            if (!item) return null;
            return cartDelta(item.product_id, direction === "plus" ? 1 : -1);
        });
    }

    function showQuickView(card) {
        const copy =
            typeof getAppCopy === "function"
                ? getAppCopy
                : (_key, fallback) => fallback;
        const product = readProduct(card);
        currentQuickProduct = product;
        select("[data-quick-image]").src = product.image;
        select("[data-quick-image]").alt = product.alt;
        select("[data-quick-category]").textContent = product.category;
        select("[data-quick-name]").textContent = product.name;
        select("[data-quick-price]").textContent = product.priceLabel;
        select("[data-quick-description]").textContent = product.description;
        select("[data-quick-color]").textContent = product.color;
        const materialRow = select("[data-quick-material-row]", quickViewModal);
        const originRow = select("[data-quick-origin-row]", quickViewModal);
        if (materialRow) {
            materialRow.hidden = !product.material;
            select("[data-quick-material]", quickViewModal).textContent =
                product.material;
        }
        if (originRow) {
            originRow.hidden = !product.origin;
            select("[data-quick-origin]", quickViewModal).textContent =
                product.origin;
        }
        const soldOut = product.stock <= 0;
        selectAll("[data-quick-add]", quickViewModal).forEach((button) => {
            button.disabled = soldOut;
            button.firstChild.textContent = soldOut
                ? `${copy("sold_out", "Stok habis")} `
                : `${copy("add_to_bag", "Tambahkan ke tas")} `;
        });
        const availability = select(
            "[data-quick-availability]",
            quickViewModal,
        );
        if (availability)
            availability.lastChild.textContent = soldOut
                ? copy("sold_out", "Stok habis")
                : copy("available", "Tersedia");
        openElement(quickViewModal);
    }

    // Satu-satunya mekanisme filter katalog: kategori/favorit ∩ kata kunci, selalu via `hidden`.
    function applyFilter(
        category = activeCategory,
        wishlistOnly = activeWishlistOnly,
        { syncUrl = true } = {},
    ) {
        activeCategory = category || "Semua";
        activeWishlistOnly = Boolean(wishlistOnly);
        const query = (catalogSearch?.value || "").trim().toLowerCase();
        let visible = 0;
        const showAll = !activeWishlistOnly && activeCategory === "Semua";
        let scopedCards = productCards.filter((card) => {
            const inGroup = activeWishlistOnly
                ? wishlistSet.has(card.dataset.id)
                : showAll || card.dataset.category === activeCategory;
            return inGroup;
        });
        let typoFallback = false;
        if (query) {
            const normalizedQuery = query.normalize("NFC");
            const scope = scopedCards.map((card) => ({
                card,
                haystack: productHaystack.get(card.dataset.id),
            }));
            const matched = searchMatches(scope, normalizedQuery);
            const matchedIds = new Set(
                matched.map((entry) => entry.card.dataset.id),
            );
            const hadSubstring = scope.some((entry) =>
                (entry.haystack || "")
                    .normalize("NFC")
                    .toLowerCase()
                    .includes(normalizedQuery),
            );
            typoFallback = !hadSubstring && matched.length > 0;
            scopedCards = scopedCards.filter((card) =>
                matchedIds.has(card.dataset.id),
            );
        }
        for (const card of productCards) {
            const matches = scopedCards.includes(card);
            card.hidden = !matches;
            if (matches) visible += 1;
        }
        const strip = select("#welcome-feedback-strip");
        if (strip) {
            const filtering =
                Boolean(query) ||
                activeWishlistOnly ||
                activeCategory !== "Semua";
            strip.hidden = !filtering;
            if (filtering) {
                const scopeLabel = activeWishlistOnly
                    ? getAppCopy("fav_scope", "Favorit Anda")
                    : activeCategory === "Semua"
                      ? getAppCopy("all_scope", "Semua Kategori")
                      : activeCategory;
                const scope = query
                    ? `${scopeLabel} · ${getAppCopy("query_label", 'kata kunci ":query"').replace(":query", () => query)}${typoFallback ? getAppCopy("similar", " — menampilkan saran mirip") : ""}`
                    : scopeLabel;
                strip.textContent = getAppCopy(
                    "filter_showing",
                    "Menampilkan :count kreasi hasil penyaringan (:scope)",
                )
                    .replace(":count", visible)
                    .replace(":scope", () => scope);
            }
        }
        const count = select("[data-product-count]");
        if (count) count.textContent = visible;
        const empty = select(".empty-products");
        if (empty) empty.hidden = visible > 0;
        if (syncUrl) {
            const url = new URL(window.location.href);
            if (wishlistOnly) {
                url.searchParams.set("tampilkan", "favorit");
                url.searchParams.delete("kategori");
            } else if (category && category !== "Semua") {
                url.searchParams.set("kategori", category);
                url.searchParams.delete("tampilkan");
            } else {
                url.searchParams.delete("kategori");
                url.searchParams.delete("tampilkan");
            }
            const search = (catalogSearch?.value || "").trim();
            if (search) url.searchParams.set("cari", search);
            else url.searchParams.delete("cari");
            history.replaceState(null, "", url);
        }
    }

    function setActiveFilter(category) {
        selectAll("[data-filter]").forEach((button) => {
            const isActive = button.dataset.filter === category;
            button.classList.toggle("active", isActive);
            button.setAttribute("aria-pressed", String(isActive));
        });
    }

    function searchProducts(query, { syncUrl = true } = {}) {
        // js-early-exit + js-length-check-first: cek murah dulu sebelum
        // menyentuh Map / DOM / URL (hindari kerja saat query < 2 char).
        const resultContainers = selectAll("[data-search-results]");
        const statusContainers = selectAll("[data-search-status]");
        if (!resultContainers.length && !statusContainers.length) return;
        const trimmed = (query || "").trim();
        if (trimmed.length < 2) {
            resultContainers.forEach((el) => {
                el.innerHTML = "";
            });
            statusContainers.forEach((el) => {
                el.textContent = "";
            });
            if (syncUrl) {
                const url = new URL(window.location.href);
                url.searchParams.delete("cari");
                history.replaceState(null, "", url);
            }
            return;
        }
        const normalized = trimmed.normalize("NFC").toLowerCase();
        const scope = [...productHaystack].map(([id, haystack]) => ({
            id,
            haystack,
        }));
        const matched = searchMatches(scope, normalized, 4);
        const results = matched.map((entry) => productMap.get(entry.id));
        const hadSubstring = scope.some((entry) =>
            (entry.haystack || "")
                .normalize("NFC")
                .toLowerCase()
                .includes(normalized),
        );
        const typoFallback = !hadSubstring && results.length > 0;
        const html = results.length
            ? results
                  .map(
                      (product) => `
                <button class="search-result" type="button" data-search-product="${escapeHtml(product.id)}">
                    <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" width="55" height="68" loading="lazy">
                    <span><strong>${escapeHtml(product.name)}</strong><span translate="no">${escapeHtml(product.priceLabel)}</span></span>
                </button>
            `,
                  )
                  .join("")
            : `<p>${escapeHtml(getAppCopy("search_none_html", "Tidak ada kreasi yang ditemukan. Coba kata lain atau hapus pencarian untuk mulai kembali."))}</p>`;
        resultContainers.forEach((el) => {
            el.innerHTML = html;
        });
        const statusText = results.length
            ? getAppCopy("search_showing", "Menampilkan :count kreasi").replace(
                  ":count",
                  results.length,
              ) +
              (typoFallback
                  ? getAppCopy("similar", " — menampilkan saran mirip")
                  : "")
            : getAppCopy("search_none", "Tidak ada kreasi yang ditemukan.");
        statusContainers.forEach((el) => {
            el.textContent = statusText;
        });
        if (syncUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set("cari", trimmed);
            history.replaceState(null, "", url);
        }
    }

    catalogSearch?.addEventListener("input", () => applyFilter());
    selectAll("[data-open-size-guide]").forEach((button) =>
        button.addEventListener("click", () =>
            openElement(sizeGuideDrawer, button),
        ),
    );
    selectAll("[data-close-size-guide]").forEach((button) =>
        button.addEventListener("click", () => closeElement(sizeGuideDrawer)),
    );
    selectAll("[data-open-profile]").forEach((button) =>
        button.addEventListener("click", () =>
            openElement(profileDrawer, button),
        ),
    );
    selectAll("[data-close-profile]").forEach((button) =>
        button.addEventListener("click", () => closeElement(profileDrawer)),
    );
    selectAll("[data-open-cart]").forEach((button) =>
        button.addEventListener("click", () => {
            openElement(cartDrawer, button);
            cartRequest("/cart");
        }),
    );
    selectAll("[data-close-cart]").forEach((button) =>
        button.addEventListener("click", () => closeElement(cartDrawer)),
    );
    selectAll("[data-open-search]").forEach((button) =>
        button.addEventListener("click", () =>
            openElement(searchPanel, button),
        ),
    );
    selectAll("[data-close-search]").forEach((button) =>
        button.addEventListener("click", () => closeElement(searchPanel)),
    );
    selectAll("[data-open-shortcuts]").forEach((button) =>
        button.addEventListener("click", () => openShortcuts(button)),
    );
    selectAll("[data-close-shortcuts]").forEach((button) =>
        button.addEventListener("click", () => closeShortcuts()),
    );
    selectAll("[data-open-concierge]").forEach((button) =>
        button.addEventListener("click", () => {
            setConciergeMode(false);
            closeElement(profileDrawer);
            closeElement(quickViewModal);
            closeElement(mobileMenu);
            openElement(conciergeModal, button);
        }),
    );
    selectAll("[data-close-modal]").forEach((button) =>
        button.addEventListener("click", () =>
            closeElement(button.closest(".modal")),
        ),
    );
    overlay?.addEventListener("click", closeAll);

    selectAll("[data-quick-view]").forEach((button) => {
        button.addEventListener("click", () =>
            showQuickView(button.closest("[data-product-card]")),
        );
    });
    selectAll("[data-toggle-wishlist]").forEach((button) => {
        button.addEventListener("click", () =>
            toggleWishlist(button.closest("[data-product-card]")),
        );
    });
    selectAll("[data-card-add]").forEach((button) => {
        button.addEventListener("click", () => {
            const product = readProduct(button.closest("[data-product-card]"));
            if (product.stock <= 0) return;
            if (product.category === "Busana") {
                window.location.href = `/produk/${encodeURIComponent(product.id)}`;
                return;
            }
            addToCart(product);
            if (!prefersReducedMotion() && button.animate) {
                button.animate(
                    [
                        { transform: "scale(1)" },
                        { transform: "scale(.88)" },
                        { transform: "scale(1)" },
                    ],
                    { duration: 260, easing: "ease-out" },
                );
            }
        });
    });
    selectAll("[data-quick-add]").forEach((button) => {
        button.addEventListener("click", () => {
            if (!currentQuickProduct || currentQuickProduct.stock <= 0) return;
            if (currentQuickProduct.category === "Busana") {
                window.location.href = `/produk/${encodeURIComponent(currentQuickProduct.id)}`;
                return;
            }
            addToCart(currentQuickProduct);
            closeElement(quickViewModal);
            setTimeout(() => openElement(cartDrawer), 180);
        });
    });

    selectAll("[data-cart-items]").forEach((container) => {
        container.addEventListener("click", (event) => {
            const item = event.target.closest("[data-cart-id]");
            if (!item) return;
            if (event.target.closest("[data-remove-cart]")) {
                let removed = null;
                const requestEpoch = cartDrawerEpoch;
                cartRequest(() => {
                    // Read the line when the request runs, so undo restores the quantity after earlier queued edits.
                    removed = cart.find(
                        (entry) =>
                            String(entry.product_id) === item.dataset.cartId,
                    );
                    return removed
                        ? [`/cart/items/${removed.product_id}`, "DELETE"]
                        : null;
                }).then((data) => {
                    // removed_quantity 0 means another tab already removed it: a delta of 0 would be rejected.
                    if (!data || !removed || !data.removed_quantity) return;
                    if (requestEpoch !== cartDrawerEpoch) return;
                    showToast(
                        getAppCopy(
                            "removed_toast",
                            "Kreasi dihapus dari tas Anda.",
                        ),
                        {
                            label: getAppCopy("undo", "Urungkan"),
                            onClick: () =>
                                cartRequest(() =>
                                    cartDelta(
                                        removed.product_id,
                                        data.removed_quantity,
                                    ),
                                ).then((restored) => {
                                    // cartRequest already reported the cause. Another tab may have re-added the line, so do not claim it is absent.
                                    const status = select("[data-cart-status]");
                                    if (!restored && status)
                                        status.textContent = getAppCopy(
                                            "undo_failed",
                                            "Penghapusan :name belum berhasil diurungkan. :status",
                                        )
                                            .replace(
                                                ":name",
                                                removed.product_title,
                                            )
                                            .replace(
                                                ":status",
                                                status.textContent,
                                            );
                                }),
                        },
                    );
                });
            }
            const quantityButton = event.target.closest("[data-quantity]");
            if (quantityButton)
                changeQuantity(
                    item.dataset.cartId,
                    quantityButton.dataset.quantity,
                );
        });
    });

    const pdpMonogram = select("#pdp-monogram-input");
    if (pdpMonogram) {
        pdpMonogram.addEventListener("input", () => {
            pdpMonogram.value = pdpMonogram.value
                .replace(MONOGRAM_SANITIZE_RE, "")
                .slice(0, 3)
                .toUpperCase();
            const badge = select("#pdp-monogram-badge");
            const counter = select("#pdp-monogram-counter");
            if (badge) badge.textContent = pdpMonogram.value || "Z";
            if (counter)
                counter.textContent = `${pdpMonogram.value.length} / 3`;
        });
    }

    selectAll("[data-product-add]").forEach((button) => {
        button.addEventListener("click", () => {
            const product = {
                name: button.dataset.name,
                productId: Number(button.dataset.productId),
            };
            const size = select("#pdp-size")?.value || "";
            const monogram =
                select("#pdp-monogram-input")
                    ?.value.replace(MONOGRAM_SANITIZE_RE, "")
                    .slice(0, 3)
                    .toUpperCase() || "";
            // Personalisasi tidak pernah masuk tas server; jadi inquiry concierge eksplisit.
            if (size || monogram) {
                openInquiry(product, { size, monogram }, button);
                return;
            }
            addToCart(product).then((data) => {
                if (data)
                    setTimeout(() => openElement(cartDrawer, button), 180);
            });
        });
    });

    selectAll("[data-share-product]").forEach((button) => {
        button.addEventListener("click", async () => {
            const { shareTitle: title, shareUrl: url } = button.dataset;
            try {
                if (navigator.share) {
                    await navigator.share({ title, url });
                    return;
                }
                await navigator.clipboard.writeText(url);
                showToast(getAppCopy("link_copied", "Tautan kreasi disalin."));
            } catch (error) {
                // Batal dari lembar berbagi bukan galat.
                if (error?.name !== "AbortError")
                    showToast(
                        getAppCopy(
                            "link_failed",
                            "Tautan tidak dapat dibagikan. Salin dari bilah alamat.",
                        ),
                    );
            }
        });
    });

    selectAll("[data-filter]").forEach((button) => {
        button.addEventListener("click", () => {
            setActiveFilter(button.dataset.filter);
            applyFilter(button.dataset.filter, false);
        });
    });

    selectAll("[data-category-link]").forEach((link) => {
        link.addEventListener("click", () => {
            const category = link.dataset.categoryLink;
            setActiveFilter(category);
            applyFilter(category, false);
        });
    });

    selectAll("[data-show-wishlist]").forEach((button) =>
        button.addEventListener("click", () => {
            selectAll("[data-filter]").forEach((filter) =>
                filter.classList.remove("active"),
            );
            applyFilter("", true);
            closeElement(mobileMenu);
            menuButton?.setAttribute("aria-expanded", "false");
            smoothScrollTo(select("#new-arrivals"));
            showToast(
                wishlist.length
                    ? getAppCopy(
                          "fav_showing",
                          "Menampilkan kreasi favorit Anda.",
                      )
                    : getAppCopy(
                          "fav_none",
                          "Belum ada kreasi dalam favorit Anda.",
                      ),
            );
        }),
    );

    selectAll("[data-search-input]").forEach((input) => {
        input.addEventListener("input", () => searchProducts(input.value));
    });
    selectAll("[data-clear-catalog-search]").forEach((button) => {
        button.addEventListener("click", () => {
            if (!catalogSearch) return;
            catalogSearch.value = "";
            applyFilter();
            catalogSearch.focus();
        });
    });
    selectAll("[data-clear-search]").forEach((button) => {
        button.addEventListener("click", () => {
            selectAll("[data-search-input]").forEach((input) => {
                input.value = "";
            });
            searchProducts("");
            selectAll("[data-search-input]")[0]?.focus();
        });
    });
    selectAll("[data-search-term]").forEach((button) => {
        button.addEventListener("click", () => {
            selectAll("[data-search-input]").forEach((input) => {
                input.value = button.dataset.searchTerm;
            });
            searchProducts(button.dataset.searchTerm);
        });
    });
    selectAll("[data-search-results]").forEach((container) => {
        container.addEventListener("click", (event) => {
            const button = event.target.closest("[data-search-product]");
            if (!button) return;
            const productId = button.dataset.searchProduct;
            const card = productCards.find(
                (entry) => entry.dataset.id === productId,
            );
            closeElement(searchPanel);
            if (card) {
                setTimeout(() => showQuickView(card), 180);
            } else {
                window.location.href = `/produk/${encodeURIComponent(productId)}`;
            }
        });
    });

    const menuButton = select(".mobile-menu-button");
    menuButton?.addEventListener("click", () => {
        openElement(mobileMenu);
        menuButton.setAttribute("aria-expanded", "true");
    });
    select("[data-close-menu]")?.addEventListener("click", () => {
        closeElement(mobileMenu);
        menuButton?.setAttribute("aria-expanded", "false");
    });
    selectAll(".mobile-menu a").forEach((link) =>
        link.addEventListener("click", () => {
            closeElement(mobileMenu);
            menuButton?.setAttribute("aria-expanded", "false");
        }),
    );

    const accountButton = select(".account-button");
    accountButton?.addEventListener("click", () => {
        const menu = select(".account-menu");
        const open = menu.classList.toggle("open");
        accountButton.setAttribute("aria-expanded", String(open));
    });

    const monogramInput = select("#monogram-input");
    monogramInput?.addEventListener("input", () => {
        monogramInput.value = monogramInput.value
            .replace(MONOGRAM_SANITIZE_RE, "")
            .slice(0, 3)
            .toUpperCase();
        select("[data-monogram-preview]").textContent =
            monogramInput.value || "Z";
    });
    selectAll("[data-foil]").forEach((button) => {
        button.addEventListener("click", () => {
            selectAll("[data-foil]").forEach((foil) => {
                foil.classList.remove("active");
                foil.setAttribute("aria-pressed", "false");
            });
            button.classList.add("active");
            button.setAttribute("aria-pressed", "true");
            select("[data-monogram-preview]").style.color = button.dataset.foil;
        });
    });

    selectAll("[data-ajax-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            selectAll(".form-error", form).forEach((error) => error.remove());
            const submitButton = select('[type="submit"]', form);
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.setAttribute("aria-busy", "true");
            submitButton.innerHTML =
                '<span aria-hidden="true" class="spinner"></span> Mengirim…';

            try {
                const response = await fetch(form.action, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "X-CSRF-TOKEN": select('meta[name="csrf-token"]')
                            .content,
                    },
                    body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) {
                    if (data.errors) {
                        Object.entries(data.errors).forEach(
                            ([fieldName, messages]) => {
                                const field = select(
                                    `[name="${fieldName}"]`,
                                    form,
                                );
                                if (!field) return;
                                const error = document.createElement("p");
                                error.className = "form-error";
                                error.setAttribute("role", "alert");
                                error.textContent = messages[0];
                                field.closest(".field")?.appendChild(error) ||
                                    field.parentElement.appendChild(error);
                                field.setAttribute("aria-invalid", "true");
                            },
                        );
                        const firstInvalid = select(
                            '[aria-invalid="true"]',
                            form,
                        );
                        firstInvalid?.focus();
                    }
                    throw new Error(
                        data.message ||
                            getAppCopy(
                                "check_info",
                                "Periksa kembali informasi Anda.",
                            ),
                    );
                }
                if (form.hasAttribute("data-success-reset")) form.reset();
                closeElement(form.closest(".modal"));
                setConciergeMode(false);
                inquiryItems = [];
                showToast(data.message);
            } catch (error) {
                showToast(
                    error.message ||
                        getAppCopy(
                            "generic_error",
                            "Terjadi kendala. Silakan coba kembali.",
                        ),
                );
            } finally {
                submitButton.disabled = false;
                submitButton.removeAttribute("aria-busy");
                submitButton.innerHTML = originalText;
            }
        });
    });

    // Restore filter/search state from URL (deep-linking)
    // js-request-idle-callback: non-kritis, jangan blokir first paint.
    scheduleIdleWork(() => {
        try {
            const params = new URLSearchParams(window.location.search);
            const initialCategory = params.get("kategori");
            const showFav = params.get("tampilkan") === "favorit";
            const initialSearch = params.get("cari");
            if (showFav) {
                applyFilter("", true, { syncUrl: false });
            } else if (initialCategory) {
                setActiveFilter(initialCategory);
                applyFilter(initialCategory, false, { syncUrl: false });
            }
            if (initialSearch) {
                if (catalogSearch) catalogSearch.value = initialSearch;
                selectAll("[data-search-input]").forEach((input) => {
                    input.value = initialSearch;
                });
                applyFilter(undefined, undefined, { syncUrl: false });
                searchProducts(initialSearch, { syncUrl: false });
            }
        } catch {
            // URL sync is progressive enhancement.
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeAll();
            closeElement(mobileMenu);
            menuButton?.setAttribute("aria-expanded", "false");
            select(".account-menu")?.classList.remove("open");
        }

        if (event.key === "Tab") {
            const activeLayer = [
                shortcutsModal,
                sizeGuideDrawer,
                conciergeModal,
                quickViewModal,
                searchPanel,
                cartDrawer,
                profileDrawer,
                mobileMenu,
            ].find((element) => element?.classList.contains("open"));
            if (!activeLayer) return;
            const focusable = selectAll(
                'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                activeLayer,
            ).filter((element) => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    const header = select("#site-header");
    let lastScroll = 0;
    window.addEventListener(
        "scroll",
        () => {
            const position = window.scrollY;
            header?.classList.toggle("is-fixed", position > 110);
            header?.classList.toggle(
                "is-hidden",
                position > 450 && position > lastScroll + 8,
            );
            if (position < lastScroll) header?.classList.remove("is-hidden");
            lastScroll = position;
        },
        { passive: true },
    );

    // rendering-content-visibility analog di sisi JS: tunda observasi reveal
    // sampai browser idle agar tidak bersaing dengan first paint.
    scheduleIdleWork(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: "0px 0px -40px" },
        );
        selectAll(".reveal:not(.is-visible)").forEach((element) =>
            observer.observe(element),
        );
    });

    renderCart();
    syncWishlistButtons();
    cartRequest("/cart");
    // Refresh on return to the tab so changes from other tabs/devices show up; no polling.
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") cartRequest("/cart");
    });

    // Flash Laravel (redirect with('success')) ke toast agar terasa di UI —
    // halaman auth/akun/admin tanpa komponen toast tetap aman (select null-safe).
    const flashToast = select("[data-flash]");
    if (flashToast?.dataset.flash) {
        showToast(flashToast.dataset.flash);
    }
}

selectAll("[data-password-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
        const input = button.closest(".password-wrap").querySelector("input");
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        button.textContent = show
            ? button.dataset.i18nHide
            : button.dataset.i18nShow;
        button.setAttribute("aria-pressed", String(show));
        button.setAttribute(
            "aria-label",
            show ? button.dataset.i18nAriaHide : button.dataset.i18nAriaShow,
        );
    });
});

// Auth forms: spinner + focus first server error (progressive enhancement)
selectAll("[data-auth-form]").forEach((form) => {
    const errBox = select(".auth-errors", form);
    if (errBox) {
        if (!errBox.hasAttribute("tabindex"))
            errBox.setAttribute("tabindex", "-1");
        errBox.focus({ preventScroll: false });
    }
    form.addEventListener("submit", () => {
        const btn = select('[type="submit"]', form);
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.setAttribute("aria-busy", "true");
        btn.textContent = "Mengirim…";
    });
});

if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker.register("/sw.js").catch(() => {});
    });
}
