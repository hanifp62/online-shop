/**
 * ZAFIRA (La Maison de Lumière) — Flagship Digital Engine (v3.0)
 * Haute Couture Interaction, Editorial Pacing, Product Studio, and Real-Time Concierge
 */

// 1. AUTHENTIC LUXURY REPOSITORY
const INITIAL_CATEGORIES = [
  { id: 'all', title: 'Semua Koleksi' },
  { id: 8, title: 'Warisan Nusantara' },
  { id: 9, title: 'Kapsul Adibusana' },
  { id: 1, title: 'Busana Wanita' },
  { id: 2, title: 'Mantel Pria' },
  { id: 3, title: 'Tas Malam' },
  { id: 4, title: 'Sepatu Kulit' },
  { id: 5, title: 'Syal Sutra' }
];

const INITIAL_PRODUCTS = [
  {
    id: 9,
    cat_id: 8,
    title: 'Kebaya Lumière',
    slug: 'kebaya-lumiere',
    price: 14900000,
    badge: 'Warisan Nusantara',
    color: 'Ivoire & Or',
    color_hex: '#dfcaa0',
    hero_quote: 'Bordir tambal-tangan 60 jam oleh empu kriya Kebayoran — bertutur dalam kemurnian brokat Prancis.',
    desc: 'Kebaya kontemporer dengan siluet Parisian dan bordir tangan pengrajin Kebayoran. Menjembatani keanggunan keraton dengan modernitas salon haute-couture Paris.',
    material: 'Brokat Prancis 60% viskosa 40% poliamida, dilapisi sutra organza Lyon murni, payet kristal kaca Venesia.',
    origin: 'Brokat: Calais, Prancis · Bordir Tangan: Kebayoran Baru, Jakarta · Jahit: Atelier Utama ZAFIRA',
    craft: 'Bordir tambal-tangan 60 jam per helai oleh 3 pengrajin senior. Kutu baru diselesaikan dengan kelim tangan rolled-hem mikro 1.2mm.',
    care: 'Dry clean khusus spesialis adibusana dan bordir tangan. Simpan digantung pada hanger berbalut katun organik ZAFIRA.',
    measurements: { 'FR 36': 'Dada 84cm · Pinggang 66cm · Pinggul 92cm', 'FR 38': 'Dada 88cm · Pinggang 70cm · Pinggul 96cm', 'FR 40': 'Dada 92cm · Pinggang 74cm · Pinggul 100cm', 'FR 42': 'Dada 96cm · Pinggang 78cm · Pinggul 104cm' },
    images: [
      'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=1400&q=90',
      'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1200&q=90',
      'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d?auto=format&fit=crop&w=1200&q=90'
    ]
  },
  {
    id: 10,
    cat_id: 8,
    title: 'Chemise Rivière',
    slug: 'chemise-riviere',
    price: 6850000,
    badge: 'Warisan Nusantara',
    color: 'Indigo Solo',
    color_hex: '#1b2e50',
    hero_quote: 'Canting malam 9 celupan indigo alam Laweyan disatukan kerah kemeja arsitektural Italia.',
    desc: 'Kemeja batik tulis Solo dalam potongan chemise Prancis kontemporer. Harmonisasi canting malam tradisional dengan ketelitian garis jahit Neapolitan.',
    material: 'Katun primisima berserat panjang, pewarnaan indigo pasta alami daun Tom (Indigofera tinctoria).',
    origin: 'Batik Tulis: Kampung Laweyan, Solo · Pola & Potongan: Milan · Jahit: Atelier Jakarta',
    craft: 'Satu helai kain batik ditulis tangan selama 3 minggu. Seluruh sambungan motif dipadukan dengan jahitan French seam presisi tinggi.',
    care: 'Cuci tangan dingin dengan sari buah lerak alami atau sampo sutra lembut. Jemur di tempat teduh terbalik.',
    measurements: { 'FR 38': 'Leher 38cm · Dada 96cm · Panjang 74cm', 'FR 40': 'Leher 40cm · Dada 102cm · Panjang 76cm', 'FR 42': 'Leher 42cm · Dada 108cm · Panjang 78cm' },
    images: [
      'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=1400&q=90',
      'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1200&q=90'
    ]
  },
  {
    id: 3,
    cat_id: 1,
    title: 'Robe Élan',
    slug: 'elan-dress',
    price: 15900000,
    badge: 'Kapsul Adibusana',
    color: 'Sapphire Night',
    color_hex: '#0f1c33',
    hero_quote: 'Sutra cair potongan bias mengalir anggun dari aperitivo senja hingga soirée malam.',
    desc: 'Gaun sutra crepe de chine dengan drapery bias-cut satu panel utuh. Didesain untuk menciptakan ilusi siluet memanjang yang bergerak mengikuti ritme tubuh.',
    material: '100% Sutra Murbei Lyon 22 momme murni, lapisan sutra habotai.',
    origin: 'Sutra: Lyon, Prancis · Pemotongan Bias: Paris · Jahit: Atelier Jakarta',
    craft: 'Dipotongan pada sudut 45 derajat (true bias), kelim bawah digulung tangan rolled-hem.',
    care: 'Dry clean khusus spesialis sutra. Jangan diperas atau disetrika uap langsung.',
    measurements: { 'FR 36': 'Dada 82cm · Pinggang 64cm · Panjang 138cm', 'FR 38': 'Dada 86cm · Pinggang 68cm · Panjang 140cm', 'FR 40': 'Dada 90cm · Pinggang 72cm · Panjang 142cm' },
    images: [
      'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=1400&q=90',
      'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=1200&q=90'
    ]
  },
  {
    id: 5,
    cat_id: 3,
    title: 'Minaudière Saphir',
    slug: 'saphir-clutch',
    price: 22500000,
    badge: 'Edisi Terbatas',
    color: 'Bleu Zafira',
    color_hex: '#17263f',
    hero_quote: 'Bingkai kuningan dipahat tangan 12 jam, bertahta kristal potong faset dan satin safir Lyon.',
    desc: 'Minaudière malam berlapis satin biru safir dengan bingkai kuningan pahatan tangan empu logam. Setiap unit memiliki nomor seri edisi terbatas yang diukir di bagian dalam.',
    material: 'Satin sutra Lyon, bingkai kuningan lapis emas 24k satin finish, 40 kristal faset tangan.',
    origin: 'Satin: Prancis · Pahat Logam: Kotagede & Jakarta · Perakitan: Atelier ZAFIRA',
    craft: 'Bingkai dipahat tangan selama 12 jam per unit. Kunci faset batu safir sintetis presisi tinggi.',
    care: 'Simpan dalam kotak berlapis beludru khusus ZAFIRA. Bersihkan dengan kain mikrofiber kering.',
    measurements: { 'One Size': 'Panjang 19cm · Tinggi 11cm · Kedalaman 4.5cm' },
    images: [
      'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=1400&q=90',
      'https://images.unsplash.com/photo-1583743814966-8936f5b7fdf1?auto=format&fit=crop&w=1200&q=90'
    ]
  },
  {
    id: 6,
    cat_id: 2,
    title: 'Manteau Firenze',
    slug: 'firenze-coat',
    price: 19800000,
    badge: 'Baru',
    color: 'Tuscan Tobacco',
    color_hex: '#8a7868',
    hero_quote: 'Wol merino Biella dan kasmir murni dalam konstruksi full-canvas terapung klasik.',
    desc: 'Mantel pria dengan bahu arsitektural dan siluet panjang yang berwibawa. Dijahit dengan konstruksi full-canvas wol kuda yang menyesuaikan bentuk tubuh pemakainya seiring waktu.',
    material: '90% Wol Merino Biella Italia, 10% Kasmir Mongolia; kancing tanduk kerbau asli.',
    origin: 'Kain: Biella, Italia · Pola: Firenze · Jahit: Atelier Jakarta',
    craft: 'Full-floating canvas dada, lapel roll dijahit tangan tanpa lem sintetis.',
    care: 'Angin-anginkan setelah dipakai. Dry clean profesional maksimal 1x per musim.',
    measurements: { 'FR 48': 'Bahu 46cm · Dada 104cm · Panjang 112cm', 'FR 50': 'Bahu 48cm · Dada 108cm · Panjang 114cm', 'FR 52': 'Bahu 50cm · Dada 112cm · Panjang 116cm' },
    images: [
      'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1400&q=90',
      'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1200&q=90'
    ]
  },
  {
    id: 11,
    cat_id: 9,
    title: 'Écharpe Éclat',
    slug: 'echarpe-eclat',
    price: 8900000,
    badge: 'Kapsul Adibusana',
    color: 'Or Palembang',
    color_hex: '#b89858',
    hero_quote: 'Songket Palembang berbenang emas berpadu panel sutra twill Lyon dalam dialog dua sungai mode.',
    desc: 'Selendang adibusana hasil kolaborasi maestro tenun Palembang dan sutra Lyon Prancis. Representasi kemewahan wastra Nusantara di panggung dunia.',
    material: 'Sutra 70%, Benang emas metalik 30%, Panel sutra twill Lyon 100%.',
    origin: 'Songket: Palembang, Sumatra Selatan · Sutra: Lyon, Prancis · Jahit: Jakarta',
    craft: 'Ditenun menggunakan alat tenun bukan mesin (ATBM) selama 6 minggu per lembar.',
    care: 'Dry clean khusus wastra emas. Gulung pada tabung karton bebas asam, jangan dilipat.',
    measurements: { 'One Size': 'Panjang 200cm · Lebar 65cm' },
    images: [
      'https://images.unsplash.com/photo-1583743814966-8936f5b7fdf1?auto=format&fit=crop&w=1400&q=90'
    ]
  },
  {
    id: 12,
    cat_id: 9,
    title: 'Robe Aurore',
    slug: 'robe-aurore',
    price: 17200000,
    badge: 'Kapsul Adibusana',
    color: 'Terracotta Ikat',
    color_hex: '#b45309',
    hero_quote: 'Geometri tenun ikat Flores dalam konstruksi gaun malam couture Riviera.',
    desc: 'Gaun malam dengan perpaduan tenun ikat Maumere pewarna alam dan crepe wol Italia. Mengangkat wastra Flores ke ranah keanggunan soirée internasional.',
    material: 'Tenun ikat katun pewarna alam 100%, Crepe wol Biella 100%.',
    origin: 'Tenun Ikat: Maumere, Flores · Kain Penunjang: Italia · Jahit: Atelier Jakarta',
    craft: 'Penyelarasan motif ikat simetris di badan depan dengan potongan bias couture.',
    care: 'Dry clean spesialis tenun tangan. Simpan digantung dalam garment bag kain.',
    measurements: { 'FR 38': 'Dada 86cm · Pinggang 68cm · Panjang 142cm', 'FR 40': 'Dada 90cm · Pinggang 72cm · Panjang 144cm' },
    images: [
      'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1400&q=90'
    ]
  },
  {
    id: 4,
    cat_id: 4,
    title: 'Mocassin Riva',
    slug: 'riva-loafer',
    price: 9850000,
    badge: 'Signature',
    color: 'Espresso',
    color_hex: '#3b2f2f',
    hero_quote: 'Kulit napa Marche Italia dengan konstruksi Blake-stitched dan ornamen emas ZAFIRA.',
    desc: 'Loafer kulit napa dengan proporsi ramping dan ornamen logo Z berlapis emas pucat. Nyaman untuk mobilitas tinggi tanpa mengorbankan formalitas adibusana.',
    material: '100% Kulit Napa Anak Sapi, sol kulit sol nabati dengan tapak karet tersembunyi.',
    origin: 'Penyamakan Kulit: Marche, Italia · Perakitan: Atelier Sepatu ZAFIRA Jakarta',
    craft: 'Konstruksi jahitan Blake agar dapat disol ulang seumur hidup. Apron dijahit tangan.',
    care: 'Gunakan shoe-tree kayu cedar saat tidak dipakai. Bersihkan dengan krim kulit netral.',
    measurements: { 'EU 40': '25.5cm', 'EU 41': '26.2cm', 'EU 42': '27.0cm', 'EU 43': '27.8cm' },
    images: [
      'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1400&q=90'
    ]
  }
];

const INITIAL_CONCIERGE = [
  {
    id: 101,
    name: 'Madame Aurelia Tan',
    email: 'aurelia@tanholdings.id',
    phone: '+62 811 8899 771',
    service: 'Private Fitting Jakarta',
    status: 'confirmed',
    date: '2026-10-15 14:00',
    notes: '[Pesanan Adibusana]\n• Kebaya Lumière (1x) FR 38 · Monogram: AT\n• Kemasan Kado: Kotak Safir & Pita Emas\nTotal: Rp14.900.000\n\nCatatan: Fitting privat di Salon Kebayoran Baru.'
  },
  {
    id: 102,
    name: 'Bapak Hendra Wijaya',
    email: 'hendra.w@capital.com',
    phone: '+62 812 3456 7890',
    service: 'Bespoke Consultation',
    status: 'contacted',
    date: '2026-10-16 16:30',
    notes: '[Pesanan Adibusana]\n• Manteau Firenze (1x) FR 50\n• Chemise Rivière (2x) FR 40\nTotal: Rp33.500.000'
  },
  {
    id: 103,
    name: 'Lady Stephanie Iskandar',
    email: 'stephanie@iskandar.org',
    phone: '+62 878 9900 1122',
    service: 'Virtual Styling Monaco',
    status: 'new',
    date: '2026-10-18 11:00',
    notes: 'Konsultasi gaun malam gala dinner di Monte Carlo untuk bulan November.'
  },
  {
    id: 104,
    name: 'Dato’ Sri Dr. Farhan Azman',
    email: 'farhan@kualalumpur.my',
    phone: '+60 12 345 6789',
    service: 'Private Gifting Delivery',
    status: 'completed',
    date: '2026-10-09 10:00',
    notes: 'Pengiriman Écharpe Éclat kado ulang tahun diplomatik. Selesai diantar via courier diplomatik.'
  }
];

// 2. STATE STORE ENGINE
class PrototypeStore {
  constructor() {
    this.categories = this.load('zafira_v3_categories', INITIAL_CATEGORIES);
    this.products = this.load('zafira_v3_products', INITIAL_PRODUCTS);
    this.concierge = this.load('zafira_v3_concierge', INITIAL_CONCIERGE);
    this.cart = this.load('zafira_v3_cart', [
      { productId: 9, qty: 1, size: 'FR 38', monogram: 'AT', packaging: 'gift-box' }
    ]);

    this.currentView = 'storefront'; // 'storefront' | 'studio' | 'account' | 'admin'
    this.viewMode = 'editorial'; // 'editorial' (living magazine) | 'gallery' (museum grid)
    this.currentCategory = 'all';
    this.searchQuery = '';
    this.activeStudioProduct = this.products[0];
    this.selectedStudioSize = 'FR 38';
    this.selectedStudioTab = 'story';
    
    // Packaging Simulator State
    this.packagingState = {
      ribbon: 'gold', // 'gold' | 'sapphire'
      message: 'Pour Madame Aurelia, avec toute notre admiration.',
      isSealed: true
    };

    // Rendez-vous Appointment State
    this.appointmentState = {
      service: 'Private Fitting Jakarta',
      date: '2026-10-16',
      slot: '14:00'
    };
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

  addToCart(productId, size = 'FR 38', monogram = '', packaging = 'gift-box') {
    const existing = this.cart.find(c => c.productId === productId && c.size === size && c.monogram === monogram);
    if (existing) {
      existing.qty += 1;
    } else {
      this.cart.push({ productId, qty: 1, size, monogram, packaging });
    }
    this.save('zafira_v3_cart', this.cart);
  }

  updateCartQty(index, delta) {
    if (this.cart[index]) {
      this.cart[index].qty += delta;
      if (this.cart[index].qty <= 0) {
        this.cart.splice(index, 1);
      }
      this.save('zafira_v3_cart', this.cart);
    }
  }

  removeFromCart(index) {
    this.cart.splice(index, 1);
    this.save('zafira_v3_cart', this.cart);
  }

  clearCart() {
    this.cart = [];
    this.save('zafira_v3_cart', this.cart);
  }

  getCartTotal() {
    return this.cart.reduce((total, item) => {
      const p = this.products.find(prod => prod.id === item.productId);
      return total + (p ? p.price * item.qty : 0);
    }, 0);
  }

  addConciergeOrder(order) {
    const newOrder = {
      id: Math.floor(Math.random() * 900) + 105,
      ...order,
      status: 'new',
      date: new Date().toISOString().replace('T', ' ').substring(0, 16)
    };
    this.concierge.unshift(newOrder);
    this.save('zafira_v3_concierge', this.concierge);
    return newOrder;
  }

  updateOrderStatus(id, newStatus) {
    const item = this.concierge.find(c => c.id === id);
    if (item) {
      item.status = newStatus;
      this.save('zafira_v3_concierge', this.concierge);
    }
  }
}

// 3. SINGLETON CONTROLLER
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
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dfcaa0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
    </svg>
    <span>${message}</span>
  `;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ==========================================================================
// RENDERERS (EDITORIAL VS GALLERY)
// ==========================================================================

function renderCatalog() {
  const container = document.getElementById('catalog-content-area');
  if (!container) return;

  const filtered = store.getFilteredProducts();

  if (store.viewMode === 'editorial') {
    // 1. EDITORIAL LIVING MAGAZINE SHOWCASE
    container.innerHTML = `
      <div class="editorial-flow">
        
        <!-- Diptych 1: Kebaya Lumiere & Savoir-Faire -->
        <article class="diptych-row">
          <div class="diptych-media" onclick="openProductStudio(9)" style="cursor:pointer;">
            <img src="${filtered[0]?.images[0] || INITIAL_PRODUCTS[0].images[0]}" alt="${filtered[0]?.title}">
            <span class="diptych-caption-tag">Warisan Adibusana · 60h Broderie</span>
          </div>
          <div class="diptych-content">
            <span class="diptych-origin">Atelier Paris · Bordir Tangan Kebayoran</span>
            <h3 class="diptych-title">${filtered[0]?.title || 'Kebaya Lumière'}</h3>
            <p class="diptych-quote">“${filtered[0]?.hero_quote || 'Bordir tambal-tangan 60 jam oleh empu kriya Kebayoran.'}”</p>
            <p style="font-size:13.5px; color:var(--muted); line-height:1.8; margin-bottom:20px;">
              ${filtered[0]?.desc}
            </p>
            <div class="diptych-metrics">
              <div class="metric-col">
                <strong>60+ Jam</strong>
                <span>Bordir Tangan</span>
              </div>
              <div class="metric-col">
                <strong>22 Momme</strong>
                <span>Sutra Organza Lyon</span>
              </div>
              <div class="metric-col">
                <strong>${formatRupiah(filtered[0]?.price || 14900000)}</strong>
                <span>Nilai Kreasi</span>
              </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
              <button class="btn-gold" onclick="openProductStudio(${filtered[0]?.id || 9})">Buka Studio Kriya ⟶</button>
              <button class="btn-outline" onclick="quickAddToCart(${filtered[0]?.id || 9})">+ Tas Belanja</button>
            </div>
          </div>
        </article>

        <!-- Diptych 2 (Reverse): Chemise Riviere & Canting Solo -->
        ${filtered[1] ? `
        <article class="diptych-row reverse">
          <div class="diptych-content">
            <span class="diptych-origin">Batik Tulis Laweyan · Kerah Neapolitan</span>
            <h3 class="diptych-title">${filtered[1].title}</h3>
            <p class="diptych-quote">“${filtered[1].hero_quote}”</p>
            <p style="font-size:13.5px; color:var(--muted); line-height:1.8; margin-bottom:20px;">
              ${filtered[1].desc}
            </p>
            <div class="diptych-metrics">
              <div class="metric-col">
                <strong>3 Minggu</strong>
                <span>Goresan Canting</span>
              </div>
              <div class="metric-col">
                <strong>9 Kali</strong>
                <span>Celupan Indigo Alam</span>
              </div>
              <div class="metric-col">
                <strong>${formatRupiah(filtered[1].price)}</strong>
                <span>Nilai Kreasi</span>
              </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
              <button class="btn-gold" onclick="openProductStudio(${filtered[1].id})">Buka Studio Kriya ⟶</button>
              <button class="btn-outline" onclick="quickAddToCart(${filtered[1].id})">+ Tas Belanja</button>
            </div>
          </div>
          <div class="diptych-media" onclick="openProductStudio(${filtered[1].id})" style="cursor:pointer;">
            <img src="${filtered[1].images[0]}" alt="${filtered[1].title}">
            <span class="diptych-caption-tag">Pewarna Alami · Canting Solo</span>
          </div>
        </article>
        ` : ''}

      </div>

      <!-- SAVOIR-FAIRE IMMERSIVE FULL-WIDTH INTERMEZZO -->
      <section class="savoir-faire-banner">
        <div class="savoir-faire-inner">
          <span class="sf-eyebrow">L'Âme de la Maison ZAFIRA</span>
          <h2 class="sf-quote">
            “Setiap jahitan adalah dialog abadi antara benang emas Palembang, sutra Lyon Prancis, dan kesabaran puluhan jam para empu kriya Nusantara.”
          </h2>
          <div class="sf-metrics-grid">
            <div>
              <span class="sf-stat-value">60+ Jam</span>
              <span class="sf-stat-label">Bordir Tangan / Helai Adibusana</span>
            </div>
            <div>
              <span class="sf-stat-value">100%</span>
              <span class="sf-stat-label">Serat Alami & Pewarna Nabati</span>
            </div>
            <div>
              <span class="sf-stat-value">Paris · Solo</span>
              <span class="sf-stat-label">Sinergi Atelier Dua Benua</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Grid Triptych: Other Creations -->
      <div class="editorial-flow">
        <div style="text-align:center; margin-bottom:36px;">
          <span style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:var(--gold); font-weight:700;">Les Autres Pièces</span>
          <h3 style="font-family:var(--serif); font-size:32px; color:var(--sapphire-midnight);">Koleksi Kriya Pilihan</h3>
        </div>
        <div class="gallery-grid">
          ${filtered.slice(2).map(p => renderProductCardHtml(p)).join('')}
        </div>
      </div>
    `;
  } else {
    // 2. CLEAN GALLERY MODE (MUSEUM CATALOG GRID)
    container.innerHTML = `
      <div class="gallery-flow">
        <div class="gallery-grid">
          ${filtered.map(p => renderProductCardHtml(p)).join('')}
        </div>
      </div>
    `;
  }
}

function renderProductCardHtml(p) {
  const cat = store.categories.find(c => c.id == p.cat_id)?.title || 'Koleksi';
  return `
    <article class="product-card" data-id="${p.id}">
      <div class="product-media" onclick="openProductStudio(${p.id})" style="cursor:pointer;">
        <img src="${p.images[0]}" alt="${p.title}" loading="lazy">
        ${p.badge ? `<span class="badge-creations">${p.badge}</span>` : ''}
        <div class="product-overlay-actions" onclick="event.stopPropagation()">
          <button class="btn-quick" onclick="openProductStudio(${p.id})">Studio Kriya</button>
          <button class="btn-quick" style="background:var(--sapphire-midnight); color:#fff; border-color:var(--sapphire-midnight);" onclick="quickAddToCart(${p.id})">+ Ke Tas</button>
        </div>
      </div>
      <div class="product-info">
        <span class="product-cat">${cat}</span>
        <h4 class="product-title" onclick="openProductStudio(${p.id})" style="cursor:pointer;">${p.title}</h4>
        <p class="product-desc-snippet">${p.desc.substring(0, 80)}...</p>
        <div class="product-meta">
          <span class="product-price">${formatRupiah(p.price)}</span>
          ${p.color_hex ? `<span class="color-dot" style="background:${p.color_hex}" title="${p.color}"></span>` : ''}
        </div>
      </div>
    </article>
  `;
}

// ==========================================================================
// DEDICATED PRODUCT STUDIO (PDP SPLIT SCREEN)
// ==========================================================================

function openProductStudio(productId) {
  const p = store.products.find(prod => prod.id === productId);
  if (!p) return;
  store.activeStudioProduct = p;
  store.selectedStudioSize = Object.keys(p.measurements || {})[0] || 'FR 38';
  store.selectedStudioTab = 'story';

  // Render Studio DOM
  const studioView = document.getElementById('product-studio-view');
  if (!studioView) return;

  const cat = store.categories.find(c => c.id == p.cat_id)?.title || 'Koleksi Adibusana';
  const sizeKeys = Object.keys(p.measurements || { 'FR 36': '', 'FR 38': '', 'FR 40': '', 'FR 42': '' });

  studioView.innerHTML = `
    <!-- Top Back Navigation -->
    <div class="studio-top-nav">
      <button class="proto-btn" style="background:transparent; color:var(--ink); border:1px solid var(--line);" onclick="switchView('storefront')">
        ← Kembali ke Penjelajahan Maison
      </button>
      <div style="font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:var(--taupe); font-weight:700;">
        ${cat} · Création No. ${p.id}
      </div>
      <button class="action-btn" onclick="openCartDrawer()">
        <span>Tas Belanja</span>
        <span class="badge-count cart-count-badge">${store.cart.reduce((s, i) => s + i.qty, 0)}</span>
      </button>
    </div>

    <!-- Split Screen Studio -->
    <div class="studio-layout">
      
      <!-- Left: High-Resolution Vertical Image Gallery -->
      <div class="studio-gallery-pane">
        ${p.images.map((imgUrl, i) => `
          <div class="studio-img-card">
            <img src="${imgUrl}" alt="${p.title} Angle ${i+1}">
          </div>
        `).join('')}
      </div>

      <!-- Right: Sticky Purchase & Savoir-Faire Panel -->
      <div class="studio-panel-pane">
        <span class="studio-category-label">${cat}</span>
        <h1 class="studio-product-title">${p.title}</h1>
        <div class="studio-price">${formatRupiah(p.price)}</div>

        <!-- 4-Tab Craftsmanship Navigation -->
        <div class="qv-tabs" role="tablist">
          <button class="qv-tab-btn ${store.selectedStudioTab === 'story' ? 'active' : ''}" onclick="switchStudioTab('story')">Siluet</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'material' ? 'active' : ''}" onclick="switchStudioTab('material')">Material</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'craft' ? 'active' : ''}" onclick="switchStudioTab('craft')">Savoir-Faire</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'care' ? 'active' : ''}" onclick="switchStudioTab('care')">Perawatan</button>
        </div>

        <!-- Tab 1: Siluet & Narasi -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'story' ? 'active' : ''}" id="studio-pane-story">
          <p style="margin-bottom:12px;">${p.desc}</p>
          <p style="font-size:12px; color:var(--muted); font-style:italic; border-left:2px solid var(--gold); padding-left:12px;">
            “${p.hero_quote}”
          </p>
        </div>

        <!-- Tab 2: Material & Asal Bahan -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'material' ? 'active' : ''}" id="studio-pane-material">
          <div style="background:var(--ivory); padding:14px; border-radius:var(--radius-xs); border-left:3px solid var(--sapphire-midnight); margin-bottom:8px;">
            <p><strong>Komposisi:</strong> ${p.material}</p>
            <p style="margin-top:6px;"><strong>Provenance:</strong> ${p.origin}</p>
          </div>
          <small style="color:var(--muted); font-size:11px;">Tersertifikasi serat alami bebas pewarna sintetis berat.</small>
        </div>

        <!-- Tab 3: Savoir-Faire Kriya -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'craft' ? 'active' : ''}" id="studio-pane-craft">
          <div style="background:var(--ivory); padding:14px; border-radius:var(--radius-xs); border-left:3px solid var(--gold); margin-bottom:8px;">
            <p><strong>Keahlian Tangan:</strong> ${p.craft}</p>
          </div>
          <small style="color:var(--muted); font-size:11px;">Dikerjakan di bawah supervisi Direktur Kreatif ZAFIRA.</small>
        </div>

        <!-- Tab 4: Panduan Perawatan -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'care' ? 'active' : ''}" id="studio-pane-care">
          <div style="background:var(--ivory); padding:14px; border-radius:var(--radius-xs); border-left:3px solid #64748b; margin-bottom:8px;">
            <p>${p.care}</p>
          </div>
        </div>

        <!-- Size Selection (Atelier Fit) -->
        <div class="size-selector-wrap">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <label class="form-label">Pilihan Ukuran Atelier</label>
            <span style="font-size:11px; color:var(--taupe); cursor:pointer; text-decoration:underline;" onclick="alert('Panduan Ukuran: Standard French Couture sizing (FR). Konsultasi ukuran khusus tersedia via Client Advisor.')">Panduan Ukuran</span>
          </div>
          <div class="size-pills">
            ${sizeKeys.map(sizeKey => `
              <button class="size-pill ${store.selectedStudioSize === sizeKey ? 'active' : ''}" onclick="selectStudioSize('${sizeKey}')">
                ${sizeKey.replace('FR ', '')}
              </button>
            `).join('')}
          </div>
          <div style="font-size:11.5px; color:var(--muted); margin-top:8px;" id="size-measurement-feedback">
            ${p.measurements ? p.measurements[store.selectedStudioSize] || '' : ''}
          </div>
        </div>

        <!-- Live Monogram Studio -->
        <div class="monogram-studio-card">
          <div class="monogram-stamp-badge" id="studio-monogram-stamp">AT</div>
          <div style="flex-grow:1;">
            <label class="form-label" style="margin-bottom:3px;">Personalisasi Monogram Emas</label>
            <input type="text" id="studio-monogram-input" class="form-input" maxlength="3" value="AT" placeholder="Misal: AT"
              style="padding:6px 12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;"
              oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, ''); updateStudioMonogramBadge(this.value)">
            <small style="font-size:10px; color:var(--taupe); display:block; margin-top:3px;">Dibordir tangan dengan benang metalik emas 18 karat di atelier.</small>
          </div>
        </div>

        <!-- Packaging Selection Toggle -->
        <div style="margin-bottom:24px; padding:12px; background:var(--ivory); border-radius:var(--radius-xs); border:1px solid var(--line);">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <strong style="font-size:12px; color:var(--sapphire-midnight);">Kemasan Kado Signature & Wax Seal</strong>
              <p style="font-size:11px; color:var(--muted);">Kotak kaku safir, pita sutra Lyon, dan stempel segel lilin merah.</p>
            </div>
            <span style="font-size:10px; font-weight:700; color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:10px;">Complimentary</span>
          </div>
        </div>

        <!-- Purchase Actions -->
        <div style="display:flex; flex-direction:column; gap:12px;">
          <button class="btn-gold" style="width:100%; justify-content:center; padding:15px;" onclick="addStudioToCart()">
            Tambah ke Tas Belanja ⟶
          </button>
          <button class="btn-outline" style="width:100%; justify-content:center; padding:13px;" onclick="openRendezvousModal()">
            Jadwalkan Fitting Privat di Atelier
          </button>
        </div>

      </div>
    </div>
  `;

  switchView('studio');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function switchStudioTab(tabKey) {
  store.selectedStudioTab = tabKey;
  document.querySelectorAll('.qv-tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.qv-tab-pane').forEach(pane => pane.classList.remove('active'));
  event.currentTarget.classList.add('active');
  document.getElementById(`studio-pane-${tabKey}`)?.classList.add('active');
}

function selectStudioSize(sizeVal) {
  store.selectedStudioSize = sizeVal;
  document.querySelectorAll('.size-pill').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.trim() === sizeVal.replace('FR ', ''));
  });
  const p = store.activeStudioProduct;
  const feedback = document.getElementById('size-measurement-feedback');
  if (feedback && p.measurements) {
    feedback.textContent = p.measurements[sizeVal] || '';
  }
}

function updateStudioMonogramBadge(val) {
  const badge = document.getElementById('studio-monogram-stamp');
  if (badge) {
    badge.textContent = val.trim() ? val.toUpperCase() : 'ZFL';
  }
}

function addStudioToCart() {
  const mono = document.getElementById('studio-monogram-input')?.value.trim() || '';
  store.addToCart(store.activeStudioProduct.id, store.selectedStudioSize, mono, 'gift-box');
  renderCartDrawer();
  openCartDrawer();
  showToast(`Kreasi "${store.activeStudioProduct.title}" (${store.selectedStudioSize}) berhasil ditambahkan ke Tas.`);
}

function quickAddToCart(productId) {
  store.addToCart(productId, 'FR 38', '', 'gift-box');
  renderCartDrawer();
  openCartDrawer();
  const p = store.products.find(prod => prod.id === productId);
  showToast(`"${p?.title}" telah ditambahkan ke tas belanja.`);
}

// ==========================================================================
// CART DRAWER & CHECKOUT
// ==========================================================================

function openCartDrawer() {
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

function closeCartDrawer() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('drawer-backdrop')?.classList.remove('open');
}

function renderCartDrawer() {
  const container = document.getElementById('cart-items-container');
  const subtotalEl = document.getElementById('cart-subtotal');
  const badgeCounts = document.querySelectorAll('.cart-count-badge');

  const totalCount = store.cart.reduce((sum, item) => sum + item.qty, 0);
  badgeCounts.forEach(el => el.textContent = totalCount);

  if (!container) return;

  if (store.cart.length === 0) {
    container.innerHTML = '<div style="text-align:center; padding:60px 20px; color:var(--muted);">Tas belanja Maison Anda saat ini kosong.</div>';
    if (subtotalEl) subtotalEl.textContent = 'Rp0';
    return;
  }

  container.innerHTML = store.cart.map((item, idx) => {
    const p = store.products.find(prod => prod.id === item.productId);
    if (!p) return '';
    return `
      <div class="cart-item">
        <img src="${p.images[0]}" alt="${p.title}">
        <div class="cart-item-detail">
          <h4 class="cart-item-title">${p.title}</h4>
          <div class="cart-item-price">${formatRupiah(p.price)}</div>
          <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px;">
            ${item.size ? `<span style="font-size:10px; background:#f1f5f9; color:var(--sapphire-midnight); font-weight:700; padding:2px 7px; border-radius:2px; border:1px solid #e2e8f0;">Taille: ${item.size}</span>` : ''}
            ${item.monogram ? `<span style="font-size:10px; background:rgba(184,152,88,0.15); color:#876831; font-weight:700; padding:2px 7px; border-radius:2px; border:1px solid var(--gold);">✦ Monogram: ${item.monogram}</span>` : ''}
            <span style="font-size:10px; background:#f8fafc; color:#15803d; font-weight:600; padding:2px 7px; border-radius:2px;">Signature Box & Seal</span>
          </div>
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

  if (subtotalEl) subtotalEl.textContent = formatRupiah(store.getCartTotal());
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

// ==========================================================================
// UNBOXING SIMULATOR & RENDEZ-VOUS MODAL
// ==========================================================================

function updatePackagingRibbon(ribbonColor) {
  store.packagingState.ribbon = ribbonColor;
  const ribbonEl = document.getElementById('preview-ribbon-band');
  if (ribbonEl) {
    if (ribbonColor === 'gold') {
      ribbonEl.style.background = 'linear-gradient(to bottom, #d4ba7d, #b89858)';
    } else {
      ribbonEl.style.background = 'linear-gradient(to bottom, #2a436f, #0f1c33)';
    }
  }
  showToast(`Pita kemasan diganti: ${ribbonColor === 'gold' ? 'Or Champagne' : 'Bleu Saphir'}`);
}

function stampWaxSeal() {
  const badge = document.getElementById('interactive-wax-seal');
  if (badge) {
    badge.style.transform = 'scale(1.25) rotate(12deg)';
    setTimeout(() => {
      badge.style.transform = 'scale(1) rotate(0deg)';
      showToast('✦ Stempel lilin emas resmi ZAFIRA telah dibubuhkan.');
    }, 250);
  }
}

function selectTimeSlot(slot) {
  store.appointmentState.slot = slot;
  document.querySelectorAll('.time-slot-pill').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.trim() === slot);
  });
}

function openRendezvousModal() {
  const modal = document.getElementById('rendezvous-modal');
  modal?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

function submitRendezvousBooking(e) {
  e.preventDefault();
  const name = document.getElementById('rv-name').value;
  const email = document.getElementById('rv-email').value;
  const phone = document.getElementById('rv-phone').value;
  const service = document.getElementById('rv-service').value;
  const date = document.getElementById('rv-date').value;
  const notes = document.getElementById('rv-notes').value;

  const cartSummary = store.cart.map(item => {
    const p = store.products.find(prod => prod.id === item.productId);
    return `• ${p?.title} (${item.qty}x) ${item.size ? `[${item.size}]` : ''} ${item.monogram ? `[Monogram: ${item.monogram}]` : ''}`;
  }).join('\n');

  store.addConciergeOrder({
    name,
    email,
    phone,
    service,
    date: `${date} ${store.appointmentState.slot}`,
    notes: (cartSummary ? `[Kreasi di Tas Belanja]\n${cartSummary}\n\n` : '') + `Catatan: ${notes}`
  });

  store.clearCart();
  renderCartDrawer();
  renderKanbanBoard();
  renderAccountPortal();
  closeAllModals();
  showToast('✦ Janji temu privat berhasil didaftarkan di Atelier Kebayoran Baru.');
}

// ==========================================================================
// ATELIER ADMIN WORKSPACE (KANBAN ORDER PIPELINE)
// ==========================================================================

function renderKanbanBoard() {
  const board = document.getElementById('kanban-board-container');
  if (!board) return;

  const cols = [
    { key: 'new', title: 'Baru (New)', color: '#0369a1' },
    { key: 'contacted', title: 'Dihubungi (Contacted)', color: '#b45309' },
    { key: 'confirmed', title: 'Terkonfirmasi (Confirmed)', color: '#15803d' },
    { key: 'completed', title: 'Selesai (Completed)', color: '#7e22ce' }
  ];

  board.innerHTML = cols.map(col => {
    const items = store.concierge.filter(c => c.status === col.key);
    return `
      <div class="kanban-col">
        <div class="kanban-col-header" style="border-bottom-color:${col.color}">
          <span>${col.title}</span>
          <span class="badge-count" style="background:${col.color}">${items.length}</span>
        </div>
        <div style="flex-grow:1; display:flex; flex-direction:column; gap:12px;">
          ${items.length === 0 ? '<p style="font-size:11px; color:var(--muted); text-align:center; padding:30px 0;">Kosong</p>' : ''}
          ${items.map(item => `
            <div class="kanban-card">
              <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                <span class="kanban-card-title">#${item.id} · ${item.name}</span>
              </div>
              <div class="kanban-card-meta">
                ${item.phone} · ${item.service}<br>
                <small style="color:var(--taupe);">${item.date}</small>
              </div>
              <div class="kanban-card-notes">${item.notes}</div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; border-top:1px solid #f1f5f9; padding-top:8px;">
                <span style="font-size:10px; color:var(--muted); font-weight:700;">Ubah Status:</span>
                <select class="form-select" style="width:auto; padding:2px 8px; font-size:11px;" onchange="adminUpdateOrderStatus(${item.id}, this.value)">
                  <option value="new" ${item.status === 'new' ? 'selected' : ''}>New</option>
                  <option value="contacted" ${item.status === 'contacted' ? 'selected' : ''}>Contacted</option>
                  <option value="confirmed" ${item.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                  <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>Completed</option>
                </select>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }).join('');

  // Update KPI counters
  const kpiProducts = document.getElementById('admin-kpi-products');
  const kpiCategories = document.getElementById('admin-kpi-categories');
  const kpiOrders = document.getElementById('admin-kpi-orders');
  const kpiUsers = document.getElementById('admin-kpi-users');

  if (kpiProducts) kpiProducts.textContent = store.products.length;
  if (kpiCategories) kpiCategories.textContent = store.categories.length;
  if (kpiOrders) kpiOrders.textContent = store.concierge.length;
  if (kpiUsers) kpiUsers.textContent = '8';
}

function adminUpdateOrderStatus(id, newStatus) {
  store.updateOrderStatus(id, newStatus);
  renderKanbanBoard();
  renderAccountPortal();
  showToast(`Pesanan #${id} diperbarui: ${newStatus.toUpperCase()}`);
}

// ==========================================================================
// VIP CLIENT ACCOUNT PORTAL (/akun)
// ==========================================================================

function renderAccountPortal() {
  const container = document.getElementById('account-history-list');
  if (!container) return;

  container.innerHTML = store.concierge.map(c => `
    <div style="background:#fff; border:1px solid var(--line); padding:20px; border-radius:var(--radius-xs); margin-bottom:14px; box-shadow:var(--shadow-sm);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <strong style="font-family:var(--serif); font-size:17px; color:var(--sapphire-midnight);">Pesanan & Janji Temu #${c.id}</strong>
        <span class="badge-status badge-${c.status}">${c.status}</span>
      </div>
      <p style="font-size:12px; color:var(--muted); margin-bottom:10px;">Layanan: <strong>${c.service}</strong> · Jadwal: <strong>${c.date}</strong></p>
      <div style="font-size:12px; background:#f8fafc; padding:12px 16px; border-radius:var(--radius-xs); border-left:3px solid var(--gold); white-space:pre-wrap;">${c.notes}</div>
    </div>
  `).join('');
}

// ==========================================================================
// VIEW SWITCHER & NAVIGATION
// ==========================================================================

function switchView(viewName) {
  store.currentView = viewName;
  document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.proto-btn').forEach(el => el.classList.remove('active'));

  if (viewName === 'storefront') {
    document.getElementById('storefront-view')?.classList.add('active');
    document.getElementById('btn-view-storefront')?.classList.add('active');
    renderCatalog();
  } else if (viewName === 'studio') {
    document.getElementById('product-studio-view')?.classList.add('active');
    document.getElementById('btn-view-studio')?.classList.add('active');
  } else if (viewName === 'admin') {
    document.getElementById('admin-view')?.classList.add('active');
    document.getElementById('btn-view-admin')?.classList.add('active');
    renderKanbanBoard();
  } else if (viewName === 'account') {
    document.getElementById('account-view')?.classList.add('active');
    document.getElementById('btn-view-account')?.classList.add('active');
    renderAccountPortal();
  }
}

function switchViewMode(mode) {
  store.viewMode = mode;
  document.querySelectorAll('.view-mode-btn').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('data-mode') === mode);
  });
  renderCatalog();
}

function filterCategory(catId) {
  store.currentCategory = catId;
  document.querySelectorAll('.cat-pill').forEach(pill => {
    pill.classList.toggle('active', pill.getAttribute('data-cat') == catId);
  });
  renderCatalog();
}

function closeAllModals() {
  document.querySelectorAll('.modal-window').forEach(m => m.classList.remove('open'));
  closeCartDrawer();
}

// ==========================================================================
// INITIALIZATION
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
  renderCatalog();
  renderCartDrawer();
  renderKanbanBoard();
  renderAccountPortal();

  // Search input handler
  const searchInput = document.getElementById('sf-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      store.searchQuery = e.target.value;
      renderCatalog();
    });
  }

  // Keyboard accessibility
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
      closeAllModals();
    }
  });

  // Sticky Smart Bar scroll observer
  window.addEventListener('scroll', () => {
    const smartBar = document.getElementById('sticky-smart-bar');
    if (!smartBar) return;
    if (window.scrollY > 550) {
      smartBar.classList.add('visible');
    } else {
      smartBar.classList.remove('visible');
    }
  });
});
