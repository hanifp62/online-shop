/**
 * ZAFIRA (La Maison de Lumière) — Interactive Prototype Logic & State Engine
 * Drives Storefront & Atelier Admin Workspace with real simulated flows
 */

// 1. MOCK DATABASE (Berdasarkan data otentik ZAFIRA seeder & SQL dump)
const INITIAL_CATEGORIES = [
  { id: 1, title: 'Busana Wanita' },
  { id: 2, title: 'Mantel Pria' },
  { id: 3, title: 'Tas Pesta' },
  { id: 4, title: 'Sepatu Kulit' },
  { id: 5, title: 'Syal Sutra' },
  { id: 6, title: 'Arloji' },
  { id: 8, title: 'Warisan Nusantara' },
  { id: 9, title: 'Koleksi Kapsul' }
];

const INITIAL_PRODUCTS = [
  {
    id: 3,
    cat_id: 1,
    title: 'Robe Élan',
    slug: 'elan-dress',
    price: 15900000,
    image: 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=1200&q=88',
    badge: 'Eksklusif',
    color: 'Sapphire Night',
    color_hex: '#17263f',
    desc: 'Gaun sutra dengan drapery cair, dibuat untuk bergerak anggun dari aperitivo hingga soirée.',
    material: 'Sutra crepe de chine (100% sutra murbei 22 momme)',
    origin: 'Kain: Lyon, Prancis · Jahit: Atelier Jakarta',
    craft: 'Drapery bias-cut satu panel, kelim tangan rolled-hem, resleting tersembunyi buatan tangan.',
    care: 'Dry clean dengan spesialis sutra. Simpan dalam garment bag kain.'
  },
  {
    id: 9,
    cat_id: 8,
    title: 'Kebaya Lumière',
    slug: 'kebaya-lumiere',
    price: 14900000,
    image: 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=1200&q=88',
    badge: 'Warisan Nusantara',
    color: 'Ivoire & Or',
    color_hex: '#dfcaa0',
    desc: 'Kebaya kontemporer dengan siluet Parisian dan bordir tangan pengrajin Kebayoran — warisan Indonesia yang berbicara Prancis.',
    material: 'Brokat 60% viskosa 40% poliamida, lapis sutra organza, payet kaca',
    origin: 'Brokat: Calais, Prancis · Bordir tangan: Kebayoran, Jakarta · Jahit: Atelier Jakarta',
    craft: 'Bordir tambal-tangan 60 jam per helai oleh 3 pengrajin, kutu baru dijahit tangan.',
    care: 'Dry clean spesialis bordir. Simpan digantung dengan bantalan bahu katun.'
  },
  {
    id: 10,
    cat_id: 8,
    title: 'Chemise Rivière',
    slug: 'chemise-riviere',
    price: 6850000,
    image: 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=1200&q=88',
    badge: 'Warisan Nusantara',
    color: 'Indigo Solo',
    color_hex: '#263a5d',
    desc: 'Kemeja batik tulis Solo dalam potongan chemise Prancis — canting malam bertemu kerapian kerah Italia.',
    material: 'Katun primisima batik tulis pewarnaan indigo alam 9 celupan',
    origin: 'Batik tulis: Laweyan, Solo · Jahit: Atelier Jakarta',
    craft: 'Satu helai batik ditulis 3 minggu oleh pembatik senior, jahitan French seam presisi.',
    care: 'Cuci tangan dengan lerak atau sampo lembut, jemur teduh terbalik.'
  },
  {
    id: 5,
    cat_id: 3,
    title: 'Minaudière Saphir',
    slug: 'saphir-clutch',
    price: 22500000,
    image: 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=1200&q=88',
    badge: 'Edisi Terbatas',
    color: 'Bleu Zafira',
    color_hex: '#1e304d',
    desc: 'Minaudière malam berlapis satin biru safir dengan bingkai pahatan tangan dari atelier kami.',
    material: 'Satin 100% sutra, bingkai kuningan berlapis emas, 40 kristal potong tangan',
    origin: 'Satin: Lyon, Prancis · Pahat: Atelier Jakarta',
    craft: 'Bingkai dipahat tangan 12 jam per buah, nomor edisi diukir di dalam.',
    care: 'Simpan dalam kotak berlapis beludru. Bersihkan dengan kain mikrofiber kering.'
  },
  {
    id: 6,
    cat_id: 2,
    title: 'Manteau Firenze',
    slug: 'firenze-coat',
    price: 19800000,
    image: 'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1200&q=88',
    badge: 'Baru',
    color: 'Tuscan Tobacco',
    color_hex: '#927f6b',
    desc: 'Mantel wol-kasmir dengan bahu arsitektural dan potongan panjang, dijahit oleh tangan ahli.',
    material: '90% wol merino, 10% kasmir; kancing tanduk asli',
    origin: 'Kain: Biella, Italia · Jahit: Atelier Jakarta',
    craft: 'Lapel digulung tangan (hand-rolled), canvas dada full-floating fungsional.',
    care: 'Angin-anginkan tiap selesai pakai. Dry clean maksimal 1x per musim.'
  },
  {
    id: 11,
    cat_id: 9,
    title: 'Écharpe Éclat',
    slug: 'echarpe-eclat',
    price: 8900000,
    image: 'https://images.unsplash.com/photo-1583743814966-8936f5b7fdf1?auto=format&fit=crop&w=1200&q=88',
    badge: 'Koleksi Kapsul',
    color: 'Or Palembang',
    color_hex: '#b69a64',
    desc: 'Selendang songket Palembang berbenang emas, disandingkan sutra Lyon — kapsul pertemuan dua sungai mode.',
    material: 'Sutra 70%, benang emas metalik 30%, panel sutra Lyon 100%',
    origin: 'Songket: Palembang, Indonesia · Panel: Lyon, Prancis · Jahit: Jakarta',
    craft: 'Ditenun di alat tenun kayu 6 minggu per helai, sambungan panel dijahit tangan.',
    care: 'Dry clean saja. Gulung, jangan lipat, untuk menjaga benang emas.'
  },
  {
    id: 12,
    cat_id: 9,
    title: 'Robe Aurore',
    slug: 'robe-aurore',
    price: 17200000,
    image: 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1200&q=88',
    badge: 'Koleksi Kapsul',
    color: 'Terracotta Ikat',
    color_hex: '#b45309',
    desc: 'Gaun tenun ikat Flores dalam konstruksi couture — geometri ikat bertemu drapery lembut ala Riviera.',
    material: 'Tenun ikat katun 100% pewarna alam, crepe wol 100% untuk drapery',
    origin: 'Tenun ikat: Maumere, Flores · Crepe: Biella, Italia · Jahit: Jakarta',
    craft: 'Kain ikat dipilih motif per helai agar simetris di badan, drapery bias-cut.',
    care: 'Dry clean. Simpan digantung dalam garment bag kain.'
  },
  {
    id: 4,
    cat_id: 4,
    title: 'Mocassin Riva',
    slug: 'riva-loafer',
    price: 9850000,
    image: 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1200&q=88',
    badge: 'Signature',
    color: 'Espresso',
    color_hex: '#3b2f2f',
    desc: 'Loafer kulit napa dengan proporsi ramping dan ornamen Z berlapis emas pucat.',
    material: 'Upper 100% kulit napa, sol kulit dengan sisipan karet anti-selip',
    origin: 'Kulit: Marche, Italia · Jahit: Atelier Jakarta',
    craft: 'Konstruksi Blake-stitched agar bisa disol ulang, apron dijahit tangan.',
    care: 'Semir krim netral tiap 8–10 kali pakai. Gunakan shoe tree cedar.'
  },
  {
    id: 7,
    cat_id: 5,
    title: 'Carré Lumière',
    slug: 'lumiere-scarf',
    price: 4950000,
    image: 'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d?auto=format&fit=crop&w=1200&q=88',
    badge: 'Hadiah Pilihan',
    color: 'Sapphire & Sand',
    color_hex: '#263a5d',
    desc: 'Syal twill sutra dengan ilustrasi cahaya yang terurai melalui faset batu safir.',
    material: '100% sutra twill 14 momme, sablon tangan 12 warna',
    origin: 'Sutra: Lyon, Prancis · Sablon: Pekalongan, Indonesia',
    craft: 'Motif digambar eksklusif untuk Maison, tepi hand-rolled.',
    care: 'Cuci tangan air dingin dengan sabun sutra, jemur teduh terbalik.'
  },
  {
    id: 8,
    cat_id: 6,
    title: 'Montre Nocturne',
    slug: 'nocturne-watch',
    price: 28900000,
    image: 'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?auto=format&fit=crop&w=1200&q=88',
    badge: 'Signature',
    color: 'Midnight Blue',
    color_hex: '#0f172a',
    desc: 'Arloji berprofil tipis dengan dial biru matahari, penanda emas, dan strap kulit buatan tangan.',
    material: 'Case baja 316L, kristal safir anti-gores, movement otomatis 24 jewel',
    origin: 'Movement: Besançon, Prancis · Perakitan: Atelier Jakarta',
    craft: 'Dial sunburst dilapisi 7 lapis lak, penanda emas dipasang tangan.',
    care: 'Servis tiap 4–5 tahun. Hindari medan magnet dan air panas.'
  }
];

const INITIAL_CONCIERGE = [
  {
    id: 101,
    name: 'Madame Aurelia Tan',
    email: 'aurelia@tanholdings.id',
    phone: '+62 811 8899 771',
    service: 'private-styling',
    status: 'confirmed',
    date: '2026-10-14 14:00',
    notes: '[Pesanan Checkout]\n- Kebaya Lumière (1x) Rp14.900.000 (Monogram: AT)\nTotal: Rp14.900.000\n\nCatatan: Mohon siapkan ukuran S untuk fitting atelier.'
  },
  {
    id: 102,
    name: 'Bapak Hendra Wijaya',
    email: 'hendra.w@capital.com',
    phone: '+62 812 3456 7890',
    service: 'atelier',
    status: 'contacted',
    date: '2026-10-15 16:30',
    notes: '[Pesanan Checkout]\n- Manteau Firenze (1x) Rp19.800.000\n- Chemise Rivière (2x) Rp13.700.000\nTotal: Rp33.500.000'
  },
  {
    id: 103,
    name: 'Lady Stephanie Iskandar',
    email: 'stephanie@iskandar.org',
    phone: '+62 878 9900 1122',
    service: 'virtual-consultation',
    status: 'new',
    date: '2026-10-16 11:00',
    notes: 'Konsultasi gaun malam gala dinner di Monte Carlo.'
  }
];

// 2. STATE REPOSITORY (localStorage sync for persistent prototype interaction)
class PrototypeStore {
  constructor() {
    this.categories = this.load('zafira_proto_categories', INITIAL_CATEGORIES);
    this.products = this.load('zafira_proto_products', INITIAL_PRODUCTS);
    this.concierge = this.load('zafira_proto_concierge', INITIAL_CONCIERGE);
    this.cart = this.load('zafira_proto_cart', [
      { productId: 9, qty: 1, monogram: 'ZFL' }
    ]);
    this.currentCategory = 'all';
    this.searchQuery = '';
    this.currentView = 'storefront'; // 'storefront' | 'admin' | 'account'
  }

  load(key, fallback) {
    try {
      const data = localStorage.getItem(key);
      return data ? JSON.parse(data) : fallback;
    } catch (e) {
      return fallback;
    }
  }

  save(key, data) {
    try {
      localStorage.setItem(key, JSON.stringify(data));
    } catch (e) {}
  }

  getFilteredProducts() {
    return this.products.filter(p => {
      const matchesCat = this.currentCategory === 'all' || p.cat_id == this.currentCategory;
      const q = this.searchQuery.toLowerCase();
      const matchesSearch = !q || p.title.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q) || (p.color && p.color.toLowerCase().includes(q));
      return matchesCat && matchesSearch;
    });
  }

  addToCart(productId, monogram = '') {
    const existing = this.cart.find(c => c.productId === productId && c.monogram === monogram);
    if (existing) {
      existing.qty += 1;
    } else {
      this.cart.push({ productId, qty: 1, monogram });
    }
    this.save('zafira_proto_cart', this.cart);
  }

  updateCartQty(index, delta) {
    if (this.cart[index]) {
      this.cart[index].qty += delta;
      if (this.cart[index].qty <= 0) {
        this.cart.splice(index, 1);
      }
      this.save('zafira_proto_cart', this.cart);
    }
  }

  removeFromCart(index) {
    this.cart.splice(index, 1);
    this.save('zafira_proto_cart', this.cart);
  }

  clearCart() {
    this.cart = [];
    this.save('zafira_proto_cart', this.cart);
  }

  getCartTotal() {
    return this.cart.reduce((total, item) => {
      const p = this.products.find(prod => prod.id === item.productId);
      return total + (p ? p.price * item.qty : 0);
    }, 0);
  }

  addConciergeOrder(order) {
    const newOrder = {
      id: Math.floor(Math.random() * 900) + 104,
      ...order,
      status: 'new',
      date: new Date().toISOString().replace('T', ' ').substring(0, 16)
    };
    this.concierge.unshift(newOrder);
    this.save('zafira_proto_concierge', this.concierge);
    return newOrder;
  }

  updateConciergeStatus(id, newStatus) {
    const item = this.concierge.find(c => c.id === id);
    if (item) {
      item.status = newStatus;
      this.save('zafira_proto_concierge', this.concierge);
    }
  }

  addProduct(productData) {
    const newId = Math.max(...this.products.map(p => p.id), 0) + 1;
    const slug = productData.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const product = {
      id: newId,
      slug: slug || `product-${newId}`,
      ...productData
    };
    this.products.unshift(product);
    this.save('zafira_proto_products', this.products);
    return product;
  }

  deleteProduct(id) {
    this.products = this.products.filter(p => p.id !== id);
    this.save('zafira_proto_products', this.products);
  }

  addCategory(title) {
    const newId = Math.max(...this.categories.map(c => c.id), 0) + 1;
    const cat = { id: newId, title };
    this.categories.push(cat);
    this.save('zafira_proto_categories', this.categories);
    return cat;
  }

  deleteCategory(catId) {
    const isUsed = this.products.some(p => p.cat_id == catId);
    if (isUsed) {
      return { success: false, message: 'Kategori sedang digunakan oleh produk, tidak dapat dihapus.' };
    }
    this.categories = this.categories.filter(c => c.id != catId);
    this.save('zafira_proto_categories', this.categories);
    return { success: true };
  }
}

// 3. UI CONTROLLER
const store = new PrototypeStore();

function formatRupiah(num) {
  return 'Rp' + num.toLocaleString('id-ID');
}

function showToast(message, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = 'toast-item';
  toast.innerHTML = `
    <span style="color:var(--gold); font-size:16px;">✦</span>
    <span>${message}</span>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// RENDERERS
function renderPills() {
  const pillsEl = document.getElementById('category-pills');
  if (!pillsEl) return;
  let html = `<button class="cat-pill ${store.currentCategory === 'all' ? 'active' : ''}" onclick="filterCategory('all')">Semua Koleksi</button>`;
  store.categories.forEach(cat => {
    html += `<button class="cat-pill ${store.currentCategory == cat.id ? 'active' : ''}" onclick="filterCategory(${cat.id})">${cat.title}</button>`;
  });
  pillsEl.innerHTML = html;
}

function renderProducts() {
  const grid = document.getElementById('product-grid');
  if (!grid) return;
  const filtered = store.getFilteredProducts();
  if (filtered.length === 0) {
    grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--muted);">Tidak ada kreasi yang cocok dengan kriteria filter.</div>';
    return;
  }

  grid.innerHTML = filtered.map(p => {
    const cat = store.categories.find(c => c.id == p.cat_id)?.title || 'Koleksi';
    return `
      <div class="product-card" data-id="${p.id}">
        <div class="product-media">
          <img src="${p.image}" alt="${p.title}" loading="lazy">
          ${p.badge ? `<span class="badge-creations">${p.badge}</span>` : ''}
          <div class="product-overlay-actions">
            <button class="btn-quick" onclick="openQuickView(${p.id})">Lihat Kriya</button>
            <button class="btn-quick" style="background:var(--sapphire-deep); color:#fff;" onclick="quickAddToCart(${p.id})">+ Ke Tas</button>
          </div>
        </div>
        <div class="product-info">
          <span class="product-cat">${cat}</span>
          <h4 class="product-title">${p.title}</h4>
          <p style="font-size:12px; color:var(--muted); margin-bottom:12px; line-height:1.4;">${p.desc.substring(0, 75)}...</p>
          <div class="product-meta">
            <span class="product-price">${formatRupiah(p.price)}</span>
            ${p.color_hex ? `<span class="color-dot" style="background:${p.color_hex}" title="${p.color}"></span>` : ''}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function renderCartDrawer() {
  const itemsContainer = document.getElementById('cart-items-container');
  const totalEl = document.getElementById('cart-subtotal');
  const countEls = document.querySelectorAll('.cart-count-badge');
  
  const totalCount = store.cart.reduce((sum, item) => sum + item.qty, 0);
  countEls.forEach(el => el.textContent = totalCount);

  if (!itemsContainer) return;

  if (store.cart.length === 0) {
    itemsContainer.innerHTML = '<p style="text-align:center; padding:40px 0; color:var(--muted);">Tas belanja Anda kosong.</p>';
    if (totalEl) totalEl.textContent = 'Rp0';
    return;
  }

  itemsContainer.innerHTML = store.cart.map((item, idx) => {
    const p = store.products.find(prod => prod.id === item.productId);
    if (!p) return '';
    return `
      <div class="cart-item">
        <img src="${p.image}" alt="${p.title}">
        <div class="cart-item-detail">
          <div class="cart-item-title">${p.title}</div>
          <div class="cart-item-price">${formatRupiah(p.price)}</div>
          ${item.monogram ? `<div class="monogram-tag">✦ Monogram: ${item.monogram}</div>` : ''}
          <div class="cart-item-controls">
            <div class="qty-stepper">
              <button class="qty-btn" onclick="stepCartQty(${idx}, -1)">−</button>
              <span class="qty-val">${item.qty}</span>
              <button class="qty-btn" onclick="stepCartQty(${idx}, 1)">+</button>
            </div>
            <button class="btn-remove" onclick="removeCartItem(${idx})">Hapus</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  if (totalEl) totalEl.textContent = formatRupiah(store.getCartTotal());
}

function renderAdminTables() {
  // Render KPI
  const kpiProducts = document.getElementById('admin-kpi-products');
  const kpiCategories = document.getElementById('admin-kpi-categories');
  const kpiOrders = document.getElementById('admin-kpi-orders');
  const kpiUsers = document.getElementById('admin-kpi-users');

  if (kpiProducts) kpiProducts.textContent = store.products.length;
  if (kpiCategories) kpiCategories.textContent = store.categories.length;
  if (kpiOrders) kpiOrders.textContent = store.concierge.length;
  if (kpiUsers) kpiUsers.textContent = '8';

  // Render Admin Product Table
  const prodTbody = document.getElementById('admin-products-tbody');
  if (prodTbody) {
    prodTbody.innerHTML = store.products.map(p => {
      const cat = store.categories.find(c => c.id == p.cat_id)?.title || 'Koleksi';
      return `
        <tr>
          <td style="width:50px"><img src="${p.image}" style="width:40px; height:50px; object-fit:cover; border-radius:2px;"></td>
          <td><strong>${p.title}</strong><br><small style="color:var(--muted)">${p.slug}</small></td>
          <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:11px;">${cat}</span></td>
          <td style="font-weight:600; color:var(--sapphire-deep);">${formatRupiah(p.price)}</td>
          <td>${p.badge || '—'}</td>
          <td>
            <button style="padding:4px 8px; font-size:11px; background:#a83232; color:#fff; border-radius:3px; cursor:pointer;" onclick="adminDeleteProduct(${p.id})">Hapus</button>
          </td>
        </tr>
      `;
    }).join('');
  }

  // Render Admin Concierge Table
  const concTbody = document.getElementById('admin-concierge-tbody');
  if (concTbody) {
    concTbody.innerHTML = store.concierge.map(c => {
      return `
        <tr>
          <td>#${c.id}</td>
          <td><strong>${c.name}</strong><br><small style="color:var(--muted)">${c.phone} · ${c.email}</small></td>
          <td><div style="font-size:11px; max-width:260px; white-space:pre-wrap; background:#f8fafc; padding:4px 8px; border-radius:3px; border-left:2px solid var(--sapphire);">${c.notes}</div></td>
          <td>
            <select class="form-select" style="padding:4px 8px; font-size:11px; width:auto;" onchange="adminUpdateStatus(${c.id}, this.value)">
              <option value="new" ${c.status === 'new' ? 'selected' : ''}>New</option>
              <option value="contacted" ${c.status === 'contacted' ? 'selected' : ''}>Contacted</option>
              <option value="confirmed" ${c.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
              <option value="completed" ${c.status === 'completed' ? 'selected' : ''}>Completed</option>
              <option value="cancelled" ${c.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
            </select>
          </td>
          <td><span class="badge-status badge-${c.status}">${c.status}</span></td>
        </tr>
      `;
    }).join('');
  }

  // Render Admin Categories Table
  const catTbody = document.getElementById('admin-categories-tbody');
  if (catTbody) {
    catTbody.innerHTML = store.categories.map(c => {
      const count = store.products.filter(p => p.cat_id == c.id).length;
      return `
        <tr>
          <td>#${c.id}</td>
          <td><strong>${c.title}</strong></td>
          <td>${count} kreasi</td>
          <td>
            <button style="padding:4px 8px; font-size:11px; background:#a83232; color:#fff; border-radius:3px; cursor:pointer;" onclick="adminDeleteCategory(${c.id})">Hapus</button>
          </td>
        </tr>
      `;
    }).join('');
  }

  // Render Category Select options in Add Product Modal
  const catSelect = document.getElementById('new-product-cat');
  if (catSelect) {
    catSelect.innerHTML = store.categories.map(c => `<option value="${c.id}">${c.title}</option>`).join('');
  }

  // Render Customer Account Portal
  renderAccountPortal();
}

function renderAccountPortal() {
  const historyList = document.getElementById('account-history-list');
  if (!historyList) return;
  historyList.innerHTML = store.concierge.map(c => `
    <div style="background:#fff; border:1px solid var(--line); padding:16px; border-radius:4px; margin-bottom:12px;">
      <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
        <strong>Pesanan / Janji Temu #${c.id}</strong>
        <span class="badge-status badge-${c.status}">${c.status}</span>
      </div>
      <p style="font-size:12px; color:var(--muted); margin-bottom:8px;">Jadwal / Waktu: ${c.date}</p>
      <div style="font-size:12px; background:#f8fafc; padding:8px 12px; border-radius:4px; border-left:3px solid var(--gold); white-space:pre-wrap;">${c.notes}</div>
    </div>
  `).join('');
}

// ACTIONS
function filterCategory(catId) {
  store.currentCategory = catId;
  renderPills();
  renderProducts();
}

function quickAddToCart(productId) {
  store.addToCart(productId);
  renderCartDrawer();
  openCartDrawer();
  const p = store.products.find(prod => prod.id === productId);
  showToast(`Kreasi "${p?.title}" telah ditambahkan ke tas belanja.`);
}

function stepCartQty(index, delta) {
  store.updateCartQty(index, delta);
  renderCartDrawer();
}

function removeCartItem(index) {
  store.removeFromCart(index);
  renderCartDrawer();
  showToast('Kreasi dihapus dari tas belanja.');
}

function openCartDrawer() {
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

function closeCartDrawer() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('drawer-backdrop')?.classList.remove('open');
}

function openQuickView(productId) {
  const p = store.products.find(prod => prod.id === productId);
  if (!p) return;
  const modal = document.getElementById('quickview-modal');
  const content = document.getElementById('quickview-content');
  if (!content || !modal) return;

  const cat = store.categories.find(c => c.id == p.cat_id)?.title || 'Koleksi';

  content.innerHTML = `
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
      <img src="${p.image}" alt="${p.title}" style="width:100%; border-radius:4px; object-fit:cover; max-height:420px;">
      <div>
        <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.15em; color:var(--taupe);">${cat}</span>
        <h2 style="font-family:var(--serif); font-size:28px; margin:4px 0 8px;">${p.title}</h2>
        <div style="font-family:var(--serif); font-size:20px; color:var(--sapphire-deep); font-weight:600; margin-bottom:16px;">${formatRupiah(p.price)}</div>
        <p style="font-size:13px; line-height:1.6; color:var(--ink); margin-bottom:16px;">${p.desc}</p>
        
        <div style="border-top:1px solid var(--line); padding-top:12px; margin-bottom:16px; font-size:12px;">
          <p><strong>✦ Material:</strong> ${p.material || 'Sutra & Serat Alami'}</p>
          <p><strong>✦ Provenance:</strong> ${p.origin || 'Atelier Jakarta'}</p>
          <p><strong>✦ Savoir-Faire:</strong> ${p.craft || 'Jahitan tangan presisi couture'}</p>
        </div>

        <div style="margin-bottom:16px;">
          <label class="form-label">Personalisasi Monogram (Opsional, Maks 3 Huruf)</label>
          <input type="text" id="qv-monogram" class="form-input" maxlength="3" placeholder="Contoh: ZFL" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
        </div>

        <button class="btn-gold" style="width:100%; text-align:center;" onclick="addQuickViewToCart(${p.id})">Tambah ke Tas Belanja</button>
      </div>
    </div>
  `;

  modal.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

function addQuickViewToCart(productId) {
  const mono = document.getElementById('qv-monogram')?.value || '';
  store.addToCart(productId, mono);
  closeAllModals();
  renderCartDrawer();
  openCartDrawer();
  showToast(`Kreasi dengan monogram "${mono || 'Polos'}" ditambahkan.`);
}

function closeAllModals() {
  document.querySelectorAll('.modal-window').forEach(m => m.classList.remove('open'));
  closeCartDrawer();
}

function openConciergeModal() {
  const modal = document.getElementById('concierge-modal');
  const summaryEl = document.getElementById('concierge-order-summary');
  
  if (store.cart.length > 0) {
    const lines = store.cart.map(item => {
      const p = store.products.find(prod => prod.id === item.productId);
      return `• ${p?.title} (${item.qty}x) ${formatRupiah(p ? p.price * item.qty : 0)}${item.monogram ? ` [Monogram: ${item.monogram}]` : ''}`;
    }).join('\n');
    summaryEl.value = `[Pesanan Checkout]\n${lines}\nTotal: ${formatRupiah(store.getCartTotal())}`;
    document.getElementById('concierge-title').textContent = 'Lanjutkan Pesanan Bersama Client Advisor';
  } else {
    summaryEl.value = '';
    document.getElementById('concierge-title').textContent = 'Rendez-vous Privat Atelier ZAFIRA';
  }

  modal?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

function submitConciergeOrder(e) {
  e.preventDefault();
  const name = document.getElementById('c-name').value;
  const email = document.getElementById('c-email').value;
  const phone = document.getElementById('c-phone').value;
  const service = document.getElementById('c-service').value;
  const notes = document.getElementById('concierge-order-summary').value + '\n' + (document.getElementById('c-notes')?.value || '');

  store.addConciergeOrder({
    name,
    email,
    phone,
    service,
    notes: notes.trim()
  });

  store.clearCart();
  renderCartDrawer();
  renderAdminTables();
  closeAllModals();
  showToast('Permintaan pesanan berhasil dikirim ke Client Advisor Maison.');
}

// ADMIN ACTIONS
function adminUpdateStatus(id, newStatus) {
  store.updateConciergeStatus(id, newStatus);
  renderAdminTables();
  showToast(`Status pesanan #${id} diperbarui menjadi: ${newStatus.toUpperCase()}`);
}

function adminDeleteProduct(id) {
  if (confirm('Hapus kreasi ini dari katalog atelier?')) {
    store.deleteProduct(id);
    renderAdminTables();
    renderProducts();
    showToast('Kreasi berhasil dihapus dari katalog.');
  }
}

function adminAddProductSubmit(e) {
  e.preventDefault();
  const title = document.getElementById('new-p-title').value;
  const cat_id = document.getElementById('new-product-cat').value;
  const price = Number(document.getElementById('new-p-price').value);
  const image = document.getElementById('new-p-image').value || 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=1200&q=88';
  const badge = document.getElementById('new-p-badge').value;
  const color = document.getElementById('new-p-color').value;
  const desc = document.getElementById('new-p-desc').value;
  const material = document.getElementById('new-p-mat').value;
  const origin = document.getElementById('new-p-origin').value;

  store.addProduct({
    title,
    cat_id,
    price,
    image,
    badge,
    color,
    desc,
    material,
    origin
  });

  closeAllModals();
  renderAdminTables();
  renderProducts();
  showToast(`Kreasi baru "${title}" berhasil diterbitkan ke katalog.`);
}

function adminDeleteCategory(id) {
  const result = store.deleteCategory(id);
  if (!result.success) {
    alert(result.message);
  } else {
    renderAdminTables();
    renderPills();
    showToast('Kategori berhasil dihapus.');
  }
}

function adminAddCategorySubmit(e) {
  e.preventDefault();
  const title = document.getElementById('new-cat-title').value;
  if (!title) return;
  store.addCategory(title);
  document.getElementById('new-cat-title').value = '';
  renderAdminTables();
  renderPills();
  showToast(`Kategori "${title}" berhasil ditambahkan.`);
}

// VIEW SWITCHER
function switchView(viewName) {
  store.currentView = viewName;
  document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.proto-btn').forEach(el => el.classList.remove('active'));

  if (viewName === 'storefront') {
    document.getElementById('storefront-view')?.classList.add('active');
    document.getElementById('btn-view-storefront')?.classList.add('active');
  } else if (viewName === 'admin') {
    document.getElementById('admin-view')?.classList.add('active');
    document.getElementById('btn-view-admin')?.classList.add('active');
    renderAdminTables();
  } else if (viewName === 'account') {
    document.getElementById('account-view')?.classList.add('active');
    document.getElementById('btn-view-account')?.classList.add('active');
    renderAccountPortal();
  }
}

// INITIALIZATION
document.addEventListener('DOMContentLoaded', () => {
  renderPills();
  renderProducts();
  renderCartDrawer();
  renderAdminTables();

  // Search listener
  const searchInput = document.getElementById('sf-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      store.searchQuery = e.target.value;
      renderProducts();
    });
  }
});
