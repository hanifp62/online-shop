import './bootstrap';

const select = (selector, scope = document) => scope.querySelector(selector);
const selectAll = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
const smoothScrollTo = (element) => {
    if (!element) return;
    element.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
};

const STORAGE_VERSION = 'v1';
const MONOGRAM_SANITIZE_RE = /[^a-zA-Z]/g; // js-hoist-regexp: jangan buat RegExp per keystroke
const scheduleIdleWork = window.requestIdleCallback?.bind(window) ?? ((cb) => setTimeout(cb, 1)); // js-request-idle-callback

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
                    try { localStorage.setItem(namespaced, raw); } catch { /* abaikan */ }
                    try { localStorage.removeItem(key); } catch { /* abaikan */ }
                }
            }
            const value = raw === null ? fallback : (JSON.parse(raw) ?? fallback);
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
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
        if (e.key) storage._mem.delete(e.key);
    });
}

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const storefront = select('.storefront');

if (storefront) {
    const overlay = select('[data-overlay]');
    const cartDrawer = select('.cart-drawer');
    const searchPanel = select('.search-panel');
    const quickViewModal = select('.quick-view-modal');
    const conciergeModal = select('.concierge-modal');
    const mobileMenu = select('.mobile-menu');
    const productCards = selectAll('[data-product-card]');
    const productMap = new Map(productCards.map((card) => [card.dataset.id, readProduct(card)]));
    // js-cache-function-results + js-index-maps: haystack search dihitung sekali,
    // bukan string-template per produk per keystroke.
    const productHaystack = new Map(
        [...productMap.entries()].map(([id, p]) => [id, `${p.name} ${p.category} ${p.color} ${p.description}`.toLowerCase()]),
    );
    // js-cache-property-access: cache referensi DOM yang dipakai di hot path.
    const cartCountEls = selectAll('.cart-count');
    const wishlistCountEls = selectAll('.wishlist-count');
    const cartCountTextEls = selectAll('.cart-count-text');
    let cart = storage.get('zafira-cart', []);
    let wishlist = storage.get('zafira-wishlist', []);
    // js-set-map-lookups: O(1) untuk includes() yang dipanggil di loop render/filter.
    let wishlistSet = new Set(wishlist);
    let currentQuickProduct = null;
    let toastTimer;
    let lastFocusedElement = null;
    let checkoutMode = false;

    function readProduct(card) {
        return {
            id: card.dataset.id,
            name: card.dataset.name,
            category: card.dataset.category,
            price: Number(card.dataset.price),
            priceLabel: card.dataset.priceLabel,
            image: card.dataset.image,
            description: card.dataset.description,
            color: card.dataset.color,
            material: card.dataset.material || '',
            origin: card.dataset.origin || '',
        };
    }

    function rupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value);
    }

    function showToast(message, action = null) {
        const toast = select('.toast');
        if (!toast) return;
        const msg = select('[data-toast-message]', toast);
        if (msg) msg.textContent = message;
        let actionBtn = select('[data-toast-action]', toast);
        if (action) {
            if (!actionBtn) {
                actionBtn = document.createElement('button');
                actionBtn.type = 'button';
                actionBtn.dataset.toastAction = '';
                actionBtn.style.cssText = 'margin-left:8px;color:#c7a665;background:transparent;cursor:pointer;font-size:12px;text-decoration:underline;';
                toast.appendChild(actionBtn);
            }
            actionBtn.textContent = action.label;
            actionBtn.hidden = false;
            actionBtn.onclick = () => { action.onClick(); toast.classList.remove('show'); };
        } else if (actionBtn) {
            actionBtn.hidden = true;
            actionBtn.onclick = null;
        }
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), action ? 6000 : 3500);
    }

    function syncOverlay() {
        const somethingOpen = [cartDrawer, searchPanel, quickViewModal, conciergeModal]
            .some((element) => element?.classList.contains('open'));
        overlay?.classList.toggle('open', somethingOpen);
        document.body.classList.toggle('no-scroll', somethingOpen || mobileMenu?.classList.contains('open'));
    }

    function openElement(element) {
        if (!element) return;
        lastFocusedElement = document.activeElement;
        element.classList.add('open');
        element.setAttribute('aria-hidden', 'false');
        syncOverlay();
        setTimeout(() => {
            const preferredFocus = element === searchPanel
                ? select('[data-search-input]', element)
                : select('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])', element);
            preferredFocus?.focus();
        }, 320);
    }

    function closeElement(element) {
        if (!element) return;
        const wasOpen = element.classList.contains('open');
        element.classList.remove('open');
        element.setAttribute('aria-hidden', 'true');
        syncOverlay();
        if (wasOpen && !select('.open[role="dialog"], .mobile-menu.open')) {
            lastFocusedElement?.focus?.();
        }
    }

    function closeAll() {
        [cartDrawer, searchPanel, quickViewModal, conciergeModal].forEach((element) => closeElement(element));
    }

    function updateCounts() {
        // js-combine-iterations analog: satu reduce untuk total, lalu tulis ke
        // daftar elemen yang sudah di-cache (tanpa querySelector per update).
        const quantity = cart.reduce((sum, item) => sum + item.quantity, 0);
        for (const count of cartCountEls) {
            count.textContent = quantity;
            count.classList.toggle('has-items', quantity > 0);
        }
        for (const count of wishlistCountEls) {
            count.textContent = wishlist.length;
            count.classList.toggle('has-items', wishlist.length > 0);
        }
        for (const count of cartCountTextEls) {
            count.textContent = `(${quantity})`;
        }
    }

    function syncWishlistButtons() {
        for (const button of selectAll('[data-toggle-wishlist]')) {
            const active = wishlistSet.has(button.closest('[data-product-card]').dataset.id);
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        }
        updateCounts();
    }

    function toggleWishlist(card) {
        const product = readProduct(card);
        // js-early-exit: keluar cepat setelah ketemu cabang yang cocok.
        if (wishlistSet.has(product.id)) {
            wishlistSet.delete(product.id);
            wishlist = wishlist.filter((id) => id !== product.id);
            showToast(`${product.name} dihapus dari favorit.`);
        } else {
            wishlistSet.add(product.id);
            wishlist.push(product.id);
            showToast(`${product.name} disimpan dalam favorit.`);
        }
        storage.set('zafira-wishlist', wishlist);
        syncWishlistButtons();
    }

    function setConciergeMode(asCheckout) {
        const modal = select('.concierge-modal');
        if (!modal) return;
        checkoutMode = Boolean(asCheckout);
        modal.classList.toggle('is-checkout', checkoutMode);

        const summary = select('[data-checkout-summary]', modal);
        const title = select('#concierge-title, #concierge-title-pdp', modal);
        const intro = select('.concierge-intro > p', modal);
        const service = select('#concierge-service, #concierge-service-pdp, [name="service"]', modal);
        const submit = select('.concierge-form [type="submit"]', modal);
        const orderItemsInput = select('[data-order-items]', modal);

        if (orderItemsInput) {
            orderItemsInput.value = checkoutMode ? JSON.stringify(cart) : '';
        }

        if (!modal.dataset.defaultSubmit && submit) {
            modal.dataset.defaultSubmit = submit.innerHTML;
        }
        if (!modal.dataset.defaultTitle && title) {
            modal.dataset.defaultTitle = title.innerHTML;
        }
        if (!modal.dataset.defaultIntro && intro?.textContent) {
            modal.dataset.defaultIntro = intro.textContent.replace(/\s+/g, ' ').trim();
        }

        if (summary) summary.hidden = !checkoutMode;
        if (title) title.innerHTML = checkoutMode
            ? 'Lanjutkan<br>pesanan Anda.'
            : modal.dataset.defaultTitle;
        if (intro && service && submit) {
            if (checkoutMode) {
                service.value = 'virtual-consultation';
                intro.textContent = 'Client Advisor kami akan menghubungi Anda untuk mengonfirmasi pesanan, mengatur pembayaran, dan pengiriman.';
                submit.innerHTML = 'Kirim permintaan pesanan <svg><use href="#icon-arrow"/></svg>';
            } else {
                intro.textContent = modal.dataset.defaultIntro;
                submit.innerHTML = modal.dataset.defaultSubmit;
            }
        }

        if (checkoutMode && summary) {
            const lines = select('[data-checkout-lines]', summary);
            const total = select('[data-checkout-total]', summary);
            if (lines) lines.innerHTML = cart.map((item) => `
                <li>${escapeHtml(item.name)}${item.size ? ` · Ukuran: ${escapeHtml(item.size)}` : ''}${item.monogram ? ` · Monogram: ${escapeHtml(item.monogram)}` : ''} <span>× ${escapeHtml(item.quantity)}</span></li>
            `).join('');
            if (total) total.textContent = rupiah(cart.reduce((sum, item) => sum + (item.price * item.quantity), 0));
        }
    }

    function addToCart(product) {
        // client-localstorage-schema: simpan snapshot minimal, bukan seluruh
        // dataset DOM (hindari description panjang + duplikasi priceLabel).
        const snapshot = {
            id: product.id,
            name: product.name,
            category: product.category,
            price: product.price,
            priceLabel: product.priceLabel,
            image: product.image,
            color: product.color,
            size: product.size || '',
            monogram: product.monogram || '',
        };
        const existing = cart.find((item) => item.id === snapshot.id
            && (item.size || '') === snapshot.size && (item.monogram || '') === snapshot.monogram);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({ ...snapshot, quantity: 1 });
        }
        persistCart();
        showToast(`${snapshot.name} ditambahkan ke tas Anda.`);
    }

    function persistCart() {
        storage.set('zafira-cart', cart);
        renderCart();
        updateCounts();
    }

    function renderCart() {
        const container = select('[data-cart-items]');
        const empty = select('[data-cart-empty]');
        const footer = select('[data-cart-footer]');
        if (!container || !empty || !footer) return;

        container.innerHTML = '';
        empty.hidden = cart.length > 0;
        footer.hidden = cart.length === 0;

        cart.forEach((item) => {
            const element = document.createElement('article');
            element.className = 'cart-item';
            element.dataset.cartId = `${item.id}-${item.size || ''}-${item.monogram || ''}`;
            element.innerHTML = `
                <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" width="100" height="125" loading="lazy">
                <div class="cart-item-info">
                    <span>${escapeHtml(item.category)}</span>
                    <h3><a href="/produk/${encodeURIComponent(item.id)}">${escapeHtml(item.name)}</a></h3>
                    <p translate="no">${escapeHtml(item.priceLabel)}</p>
                    ${item.size ? `<p>Ukuran: ${escapeHtml(item.size)}</p>` : ''}
                    ${item.monogram ? `<p>Monogram: ${escapeHtml(item.monogram)}</p>` : ''}
                    <div class="quantity-control" aria-label="Jumlah ${escapeHtml(item.name)}">
                        <button type="button" data-quantity="minus" aria-label="Kurangi jumlah"><svg><use href="#icon-minus"/></svg></button>
                        <span>${escapeHtml(item.quantity)}</span>
                        <button type="button" data-quantity="plus" aria-label="Tambah jumlah"><svg><use href="#icon-plus"/></svg></button>
                    </div>
                </div>
                <button class="cart-remove" type="button" data-remove-cart aria-label="Hapus ${escapeHtml(item.name)}"><svg><use href="#icon-close"/></svg></button>
            `;
            container.appendChild(element);
        });

        const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const totalElement = select('[data-cart-total]');
        if (totalElement) totalElement.textContent = rupiah(total);
    }

    function changeQuantity(cartKey, direction) {
        const item = cart.find((entry) => `${entry.id}-${entry.size || ''}-${entry.monogram || ''}` === cartKey);
        if (!item) return;
        item.quantity += direction === 'plus' ? 1 : -1;
        if (item.quantity < 1) cart = cart.filter((entry) => `${entry.id}-${entry.size || ''}-${entry.monogram || ''}` !== cartKey);
        persistCart();
    }

    function showQuickView(card) {
        const product = readProduct(card);
        currentQuickProduct = product;
        select('[data-quick-image]').src = product.image;
        select('[data-quick-image]').alt = product.name;
        select('[data-quick-category]').textContent = product.category;
        select('[data-quick-name]').textContent = product.name;
        select('[data-quick-price]').textContent = product.priceLabel;
        select('[data-quick-description]').textContent = product.description;
        select('[data-quick-color]').textContent = product.color;
        const materialRow = select('[data-quick-material-row]', quickViewModal);
        const originRow = select('[data-quick-origin-row]', quickViewModal);
        if (materialRow) {
            materialRow.hidden = !product.material;
            select('[data-quick-material]', quickViewModal).textContent = product.material;
        }
        if (originRow) {
            originRow.hidden = !product.origin;
            select('[data-quick-origin]', quickViewModal).textContent = product.origin;
        }
        openElement(quickViewModal);
    }

    function applyFilter(category, wishlistOnly = false, { syncUrl = true } = {}) {
        // js-early-exit: wishlist kosong → langsung sembunyikan semua tanpa loop cocok.
        if (wishlistOnly && wishlistSet.size === 0) {
            for (const card of productCards) card.hidden = true;
            const c = select('[data-product-count]');
            if (c) c.textContent = '0';
            const e = select('.empty-products');
            if (e) e.hidden = false;
            if (syncUrl) {
                const u = new URL(window.location.href);
                u.searchParams.set('tampilkan', 'favorit');
                u.searchParams.delete('kategori');
                history.replaceState(null, '', u);
            }
            return;
        }
        let visible = 0;
        const showAll = !wishlistOnly && category === 'Semua';
        for (const card of productCards) {
            const matches = wishlistOnly
                ? wishlistSet.has(card.dataset.id)
                : showAll || card.dataset.category === category;
            card.hidden = !matches;
            if (matches) visible += 1;
        }
        const count = select('[data-product-count]');
        if (count) count.textContent = visible;
        const empty = select('.empty-products');
        if (empty) empty.hidden = visible > 0;
        if (syncUrl) {
            const url = new URL(window.location.href);
            if (wishlistOnly) {
                url.searchParams.set('tampilkan', 'favorit');
                url.searchParams.delete('kategori');
            } else if (category && category !== 'Semua') {
                url.searchParams.set('kategori', category);
                url.searchParams.delete('tampilkan');
            } else {
                url.searchParams.delete('kategori');
                url.searchParams.delete('tampilkan');
            }
            history.replaceState(null, '', url);
        }
    }

    function setActiveFilter(category) {
        selectAll('[data-filter]').forEach((button) => {
            button.classList.toggle('active', button.dataset.filter === category);
        });
    }

    function searchProducts(query) {
        // js-early-exit + js-length-check-first: cek murah dulu sebelum
        // menyentuh Map / DOM / URL (hindari kerja saat query < 2 char).
        const resultContainers = selectAll('[data-search-results]');
        if (!resultContainers.length) return;
        const trimmed = query.trim();
        if (trimmed.length < 2) {
            resultContainers.forEach((el) => { el.innerHTML = ''; });
            const url = new URL(window.location.href);
            url.searchParams.delete('cari');
            history.replaceState(null, '', url);
            return;
        }
        const normalized = trimmed.toLowerCase();
        // js-flatmap-filter analog: satu pass tanpa array perantara .filter().map().
        const results = [];
        for (const [id, haystack] of productHaystack) {
            if (haystack.includes(normalized)) {
                results.push(productMap.get(id));
                if (results.length >= 4) break; // cukup untuk 4 slot UI
            }
        }
        const html = results.length
            ? results.map((product) => `
                <button class="search-result" type="button" data-search-product="${escapeHtml(product.id)}">
                    <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" width="55" height="68" loading="lazy">
                    <span><strong>${escapeHtml(product.name)}</strong><span translate="no">${escapeHtml(product.priceLabel)}</span></span>
                </button>
            `).join('')
            : '<p>Tidak ada kreasi yang ditemukan.</p>';
        resultContainers.forEach((el) => { el.innerHTML = html; });
        const url = new URL(window.location.href);
        url.searchParams.set('cari', trimmed);
        history.replaceState(null, '', url);
    }

    selectAll('[data-open-cart]').forEach((button) => button.addEventListener('click', () => openElement(cartDrawer)));
    selectAll('[data-close-cart]').forEach((button) => button.addEventListener('click', () => closeElement(cartDrawer)));
    selectAll('[data-open-search]').forEach((button) => button.addEventListener('click', () => {
        openElement(searchPanel);
        setTimeout(() => select('[data-search-input]')?.focus(), 300);
    }));
    selectAll('[data-close-search]').forEach((button) => button.addEventListener('click', () => closeElement(searchPanel)));
    selectAll('[data-open-concierge]').forEach((button) => button.addEventListener('click', () => {
        setConciergeMode(false);
        closeElement(quickViewModal);
        closeElement(mobileMenu);
        openElement(conciergeModal);
    }));
    selectAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeElement(button.closest('.modal'))));
    overlay?.addEventListener('click', closeAll);

    selectAll('[data-quick-view]').forEach((button) => {
        button.addEventListener('click', () => showQuickView(button.closest('[data-product-card]')));
    });
    selectAll('[data-toggle-wishlist]').forEach((button) => {
        button.addEventListener('click', () => toggleWishlist(button.closest('[data-product-card]')));
    });
    selectAll('[data-card-add]').forEach((button) => {
        button.addEventListener('click', () => {
            addToCart(readProduct(button.closest('[data-product-card]')));
            if (!prefersReducedMotion() && button.animate) {
                button.animate(
                    [{ transform: 'scale(1)' }, { transform: 'scale(.88)' }, { transform: 'scale(1)' }],
                    { duration: 260, easing: 'ease-out' },
                );
            }
        });
    });
    selectAll('[data-quick-add]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!currentQuickProduct) return;
            addToCart(currentQuickProduct);
            closeElement(quickViewModal);
            setTimeout(() => openElement(cartDrawer), 180);
        });
    });

    selectAll('[data-cart-items]').forEach((container) => {
        container.addEventListener('click', (event) => {
            const item = event.target.closest('[data-cart-id]');
            if (!item) return;
            if (event.target.closest('[data-remove-cart]')) {
                const removedIndex = cart.findIndex((entry) => `${entry.id}-${entry.size || ''}-${entry.monogram || ''}` === item.dataset.cartId);
                if (removedIndex === -1) return;
                const [removed] = cart.splice(removedIndex, 1);
                persistCart();
                showToast('Kreasi dihapus dari tas Anda.', {
                    label: 'Urungkan',
                    onClick: () => {
                        cart.splice(Math.min(removedIndex, cart.length), 0, removed);
                        persistCart();
                    },
                });
            }
            const quantityButton = event.target.closest('[data-quantity]');
            if (quantityButton) changeQuantity(item.dataset.cartId, quantityButton.dataset.quantity);
        });
    });

    selectAll('[data-checkout]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!cart.length) return;
            setConciergeMode(true);
            closeElement(cartDrawer);
            setTimeout(() => openElement(conciergeModal), 180);
        });
    });

    selectAll('[data-product-add]').forEach((button) => {
        button.addEventListener('click', () => {
            addToCart({
                id: button.dataset.id,
                name: button.dataset.name,
                category: button.dataset.category,
                price: Number(button.dataset.price),
                priceLabel: button.dataset.priceLabel,
                image: button.dataset.image,
                description: button.dataset.description,
                color: button.dataset.color,
                size: select('#pdp-size')?.value || '',
                monogram: select('#pdp-monogram-input')?.value.replace(MONOGRAM_SANITIZE_RE, '').slice(0, 3).toUpperCase() || '',
            });
            setTimeout(() => openElement(cartDrawer), 180);
        });
    });

    selectAll('[data-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            setActiveFilter(button.dataset.filter);
            applyFilter(button.dataset.filter);
        });
    });

    selectAll('[data-category-link]').forEach((link) => {
        link.addEventListener('click', () => {
            const category = link.dataset.categoryLink;
            setActiveFilter(category);
            applyFilter(category);
        });
    });

    selectAll('[data-show-wishlist]').forEach((button) => button.addEventListener('click', () => {
        selectAll('[data-filter]').forEach((filter) => filter.classList.remove('active'));
        applyFilter('', true);
        closeElement(mobileMenu);
        menuButton?.setAttribute('aria-expanded', 'false');
        smoothScrollTo(select('#new-arrivals'));
        showToast(wishlist.length ? 'Menampilkan kreasi favorit Anda.' : 'Belum ada kreasi dalam favorit Anda.');
    }));

    selectAll('[data-search-input]').forEach((input) => {
        input.addEventListener('input', () => searchProducts(input.value));
    });
    selectAll('[data-clear-search]').forEach((button) => {
        button.addEventListener('click', () => {
            selectAll('[data-search-input]').forEach((input) => { input.value = ''; });
            searchProducts('');
            selectAll('[data-search-input]')[0]?.focus();
        });
    });
    selectAll('[data-search-term]').forEach((button) => {
        button.addEventListener('click', () => {
            selectAll('[data-search-input]').forEach((input) => { input.value = button.dataset.searchTerm; });
            searchProducts(button.dataset.searchTerm);
        });
    });
    selectAll('[data-search-results]').forEach((container) => {
        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-search-product]');
            if (!button) return;
            const productId = button.dataset.searchProduct;
            const card = productCards.find((entry) => entry.dataset.id === productId);
            closeElement(searchPanel);
            if (card) {
                setTimeout(() => showQuickView(card), 180);
            } else {
                window.location.href = `/produk/${encodeURIComponent(productId)}`;
            }
        });
    });

    const menuButton = select('.mobile-menu-button');
    menuButton?.addEventListener('click', () => {
        openElement(mobileMenu);
        menuButton.setAttribute('aria-expanded', 'true');
    });
    select('[data-close-menu]')?.addEventListener('click', () => {
        closeElement(mobileMenu);
        menuButton?.setAttribute('aria-expanded', 'false');
    });
    selectAll('.mobile-menu a').forEach((link) => link.addEventListener('click', () => {
        closeElement(mobileMenu);
        menuButton?.setAttribute('aria-expanded', 'false');
    }));

    const accountButton = select('.account-button');
    accountButton?.addEventListener('click', () => {
        const menu = select('.account-menu');
        const open = menu.classList.toggle('open');
        accountButton.setAttribute('aria-expanded', String(open));
    });

    const monogramInput = select('#monogram-input');
    monogramInput?.addEventListener('input', () => {
        monogramInput.value = monogramInput.value.replace(MONOGRAM_SANITIZE_RE, '').slice(0, 3).toUpperCase();
        select('[data-monogram-preview]').textContent = monogramInput.value || 'Z';
    });
    selectAll('[data-foil]').forEach((button) => {
        button.addEventListener('click', () => {
            selectAll('[data-foil]').forEach((foil) => {
                foil.classList.remove('active');
                foil.setAttribute('aria-pressed', 'false');
            });
            button.classList.add('active');
            button.setAttribute('aria-pressed', 'true');
            select('[data-monogram-preview]').style.color = button.dataset.foil;
        });
    });

    selectAll('[data-ajax-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (checkoutMode) {
                const orderItemsInput = select('[data-order-items]', form);
                if (orderItemsInput) {
                    orderItemsInput.value = JSON.stringify(cart);
                }
            }
            selectAll('.form-error', form).forEach((error) => error.remove());
            const submitButton = select('[type="submit"]', form);
            const originalText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.innerHTML = '<span aria-hidden="true" class="spinner"></span> Mengirim…';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': select('meta[name="csrf-token"]').content,
                    },
                    body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) {
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([fieldName, messages]) => {
                            const field = select(`[name="${fieldName}"]`, form);
                            if (!field) return;
                            const error = document.createElement('p');
                            error.className = 'form-error';
                            error.setAttribute('role', 'alert');
                            error.textContent = messages[0];
                            field.closest('.field')?.appendChild(error) || field.parentElement.appendChild(error);
                            field.setAttribute('aria-invalid', 'true');
                        });
                        const firstInvalid = select('[aria-invalid="true"]', form);
                        firstInvalid?.focus();
                    }
                    throw new Error(data.message || 'Periksa kembali informasi Anda.');
                }
                if (form.hasAttribute('data-success-reset')) form.reset();
                closeElement(form.closest('.modal'));
                if (checkoutMode) {
                    cart = [];
                    persistCart();
                    setConciergeMode(false);
                }
                showToast(data.message);
            } catch (error) {
                showToast(error.message || 'Terjadi kendala. Silakan coba kembali.');
            } finally {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-busy');
                submitButton.innerHTML = originalText;
            }
        });
    });

    // Restore filter/search state from URL (deep-linking)
    // js-request-idle-callback: non-kritis, jangan blokir first paint.
    scheduleIdleWork(() => {
        try {
            const params = new URLSearchParams(window.location.search);
            const initialCategory = params.get('kategori');
            const showFav = params.get('tampilkan') === 'favorit';
            const initialSearch = params.get('cari');
            if (showFav) {
                applyFilter('', true, { syncUrl: false });
            } else if (initialCategory) {
                setActiveFilter(initialCategory);
                applyFilter(initialCategory, false, { syncUrl: false });
            }
            if (initialSearch && searchInput) {
                searchInput.value = initialSearch;
                searchProducts(initialSearch);
            }
        } catch {
            // URL sync is progressive enhancement.
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
            closeElement(mobileMenu);
            select('.account-menu')?.classList.remove('open');
        }

        if (event.key === 'Tab') {
            const activeLayer = [conciergeModal, quickViewModal, searchPanel, cartDrawer, mobileMenu]
                .find((element) => element?.classList.contains('open'));
            if (!activeLayer) return;
            const focusable = selectAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', activeLayer)
                .filter((element) => element.offsetParent !== null);
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

    const header = select('#site-header');
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const position = window.scrollY;
        header?.classList.toggle('is-fixed', position > 110);
        header?.classList.toggle('is-hidden', position > 450 && position > lastScroll + 8);
        if (position < lastScroll) header?.classList.remove('is-hidden');
        lastScroll = position;
    }, { passive: true });

    // rendering-content-visibility analog di sisi JS: tunda observasi reveal
    // sampai browser idle agar tidak bersaing dengan first paint.
    scheduleIdleWork(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
        selectAll('.reveal:not(.is-visible)').forEach((element) => observer.observe(element));
    });

    renderCart();
    syncWishlistButtons();

    // Flash Laravel (redirect with('success')) ke toast agar terasa di UI —
    // halaman auth/akun/admin tanpa komponen toast tetap aman (select null-safe).
    const flashToast = select('[data-flash]');
    if (flashToast?.dataset.flash) {
        showToast(flashToast.dataset.flash);
    }
}

selectAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.closest('.password-wrap').querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Sembunyikan' : 'Lihat';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
});

// Auth forms: spinner + focus first server error (progressive enhancement)
selectAll('[data-auth-form]').forEach((form) => {
    const errBox = select('.auth-errors', form);
    if (errBox) {
        if (!errBox.hasAttribute('tabindex')) errBox.setAttribute('tabindex', '-1');
        errBox.focus({ preventScroll: false });
    }
    form.addEventListener('submit', () => {
        const btn = select('[type="submit"]', form);
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.textContent = 'Mengirim…';
    });
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
