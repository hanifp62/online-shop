/**
 * ZAFIRA (La Maison de Lumière) - Flagship Digital Engine (v3.3)
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
    hero_quote: 'Bordir tambal-tangan 60 jam oleh empu kriya Kebayoran, bertutur dalam kemurnian brokat Prancis.',
    desc: 'Kebaya kontemporer dengan siluet Parisian dan bordir tangan pengrajin Kebayoran. Menjembatani keanggunan keraton dengan modernitas salon haute-couture Paris.',
    material: 'Brokat Prancis 60% viskosa 40% poliamida, dilapisi sutra organza Lyon murni, payet kristal kaca Venesia.',
    origin: 'Brokat: Calais, Prancis · Bordir Tangan: Kebayoran Baru, Jakarta · Jahit: Atelier Utama ZAFIRA',
    craft: 'Bordir tambal-tangan 60 jam per helai oleh 3 pengrajin senior. Kutu baru diselesaikan dengan kelim tangan rolled-hem mikro 1.2mm.',
    care: 'Dry clean khusus spesialis adibusana dan bordir tangan. Simpan digantung pada hanger berbalut katun organik ZAFIRA.',
    measurements: { 'FR 36': 'Dada 84cm · Pinggang 66cm · Pinggul 92cm', 'FR 38': 'Dada 88cm · Pinggang 70cm · Pinggul 96cm', 'FR 40': 'Dada 92cm · Pinggang 74cm · Pinggul 100cm', 'FR 42': 'Dada 96cm · Pinggang 78cm · Pinggul 104cm' },
    images: [
      'assets/hero_couture.jpg',
      'assets/echarpe_eclat.jpg'
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
      'assets/chemise_riviere.jpg'
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
      'assets/robe_elan.jpg'
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
      'assets/minaudiere_saphir.jpg'
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
      'assets/manteau_firenze.jpg'
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
      'assets/echarpe_eclat.jpg'
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
      'assets/robe_aurore.jpg'
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
      'assets/mocassin_riva.jpg'
    ]
  }
];

const INITIAL_MATERIALS = [
  { id: 'mat-1', code: 'MAT-CALAIS-01', name: 'Brokat Prancis Calais (Noir & Or)', origin: 'Dentelles de Calais (Prancis)', stock: 18.5, unit: 'meter', safetyStock: 10, status: 'safe' },
  { id: 'mat-2', code: 'MAT-LYON-02', name: 'Sutra Murbei Murni Lyon 22 Momme', origin: 'Soieries de Lyon (Prancis)', stock: 8.2, unit: 'meter', safetyStock: 15, status: 'warn' },
  { id: 'mat-3', code: 'MAT-PLB-03', name: 'Benang Emas Songket Palembang (ATBM)', origin: 'Sentra Tenun Ilir, Palembang', stock: 5.5, unit: 'spul', safetyStock: 3, status: 'safe' },
  { id: 'mat-4', code: 'MAT-INDIGO-04', name: 'Pasta Indigofera Alami Celupan Dingin', origin: 'Kampung Batik Laweyan, Solo', stock: 14.0, unit: 'kg', safetyStock: 6, status: 'safe' },
  { id: 'mat-5', code: 'MAT-TUSCAN-05', name: 'Kulit Anak Sapi Sol Nabati Marche', origin: 'Tuscan Leather Guild, Italia', stock: 12.0, unit: 'lembar', safetyStock: 5, status: 'safe' }
];

const INITIAL_WIP_STATIONS = [
  { id: 'wip-1', name: 'Konsultasi & Analisis Pola', current: 1, limit: 5, status: 'safe' },
  { id: 'wip-2', name: 'Pemotongan Presisi & Drape Bias', current: 2, limit: 3, status: 'optimal' },
  { id: 'wip-3', name: 'Bordir Tambal-Tangan & Jahit Kriya', current: 3, limit: 4, status: 'warn' },
  { id: 'wip-4', name: 'Inspeksi QC Ganda & Segel Lilin', current: 1, limit: 2, status: 'safe' }
];

const INITIAL_AUDIT_LOGS = [
  { timestamp: '2026-10-10 03:45:12 WIB', actor: 'Client Advisor (Aurelia)', role: 'advisor', module: 'TPS_INTAKE', action: 'Pesanan #101 Kebaya Lumière diverifikasi', hash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' },
  { timestamp: '2026-10-10 03:12:05 WIB', actor: 'Atelier Master (Hélène)', role: 'artisan', module: 'TPS_WORK_ORDER', action: 'Pemotongan drape bias Robe Élan dimulai', hash: 'a1b2c3d4e5f60718293a4b5c6d7e8f90123456789abcdef0123456789abcdef0' },
  { timestamp: '2026-10-09 18:22:40 WIB', actor: 'Atelier Manager (Budi)', role: 'manager', module: 'MIS_INVENTORY', action: 'Pasokan 10m Sutra Lyon diterima dari Lyon', hash: '9f8e7d6c5b4a3928172635445362718293049586718293049586718293049586' },
  { timestamp: '2026-10-09 14:10:19 WIB', actor: 'Maison Director (Zafira)', role: 'director', module: 'EIS_STRATEGY', action: 'Kapasitas janji temu privat Q4 2026 disetujui', hash: 'bca8210382947192847192847192847192847192847192847192847192847192' }
];

const INITIAL_CONCIERGE = [
  {
    id: 101,
    name: 'Madame Aurelia Tan',
    email: 'aurelia@tanholdings.id',
    phone: '+62 811 8899 771',
    service: 'Private Fitting Jakarta',
    status: 'confirmed',
    artisan: 'Ibu Siti Khodijah',
    serial: 'ZFR-2026-0101',
    totalPrice: 14900000,
    date: '2026-10-15 14:00',
    notes: '[Pesanan Adibusana]\n- Kebaya Lumière (1x) FR 38 · Monogram: AT\n- Kemasan Kado: Kotak Safir & Pita Emas\nTotal: Rp14.900.000\n\nCatatan: Fitting privat di Salon Kebayoran Baru.'
  },
  {
    id: 102,
    name: 'Bapak Hendra Wijaya',
    email: 'hendra.w@capital.com',
    phone: '+62 812 3456 7890',
    service: 'Bespoke Consultation',
    status: 'contacted',
    artisan: 'Madame Hélène',
    serial: 'ZFR-2026-0102',
    totalPrice: 33500000,
    date: '2026-10-16 16:30',
    notes: '[Pesanan Adibusana]\n- Manteau Firenze (1x) FR 50\n- Chemise Rivière (2x) FR 40\nTotal: Rp33.500.000'
  },
  {
    id: 103,
    name: 'Lady Stephanie Iskandar',
    email: 'stephanie@iskandar.org',
    phone: '+62 878 9900 1122',
    service: 'Virtual Styling Monaco',
    status: 'new',
    artisan: 'Belum Ditugaskan',
    serial: 'ZFR-2026-0103',
    totalPrice: 15900000,
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
    artisan: 'Bapak Nyoman Sujana',
    serial: 'ZFR-2026-0104',
    totalPrice: 8900000,
    date: '2026-10-09 10:00',
    notes: 'Pengiriman Écharpe Éclat kado ulang tahun diplomatik. Selesai diantar via courier diplomatik.'
  }
];

// 2. STATE STORE ENGINE
class PrototypeStore {
  constructor() {
    this.categories = this.load('zafira_v3_categories', INITIAL_CATEGORIES);
    this.products = this.load('zafira_v3_5_products', INITIAL_PRODUCTS);
    this.concierge = this.load('zafira_v3_5_concierge', INITIAL_CONCIERGE);
    this.cart = this.load('zafira_v3_5_cart', [
      { productId: 9, qty: 1, size: 'FR 38', monogram: 'AT', packaging: 'gift-box' }
    ]);
    this.currency = this.load('zafira_currency', 'IDR');
    this.soundEnabled = this.load('zafira_sound', true);
    this.isDockCollapsed = false;

    // MSI (Management Information System) States
    this.currentMsiTab = 'tps'; // 'tps' | 'mis' | 'eis' | 'gov'
    this.currentRbacRole = 'director'; // 'director' | 'manager' | 'advisor' | 'artisan'
    this.rawMaterials = this.load('zafira_v3_5_materials', INITIAL_MATERIALS);
    this.wipStations = this.load('zafira_v3_5_wip', INITIAL_WIP_STATIONS);
    this.auditLogs = this.load('zafira_v3_5_audit_logs', INITIAL_AUDIT_LOGS);

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

  getProductLocalized(p, lang) {
    if (!p) return null;
    if (!lang) lang = window.Zafira ? window.Zafira.getLanguage() : 'id';
    const multiDict = window.Zafira && window.Zafira.MULTI_PRODUCTS;
    const multi = multiDict && (multiDict[p.id] || (p.id === 1 ? multiDict[9] : (p.id === 2 ? multiDict[10] : (p.id === 13 ? multiDict[4] : null))));
    const rawBadge = (multi && multi[lang] && multi[lang].badge) || p.badge;
    const localizedBadge = window.Zafira && window.Zafira.getBadgeLocalized ? window.Zafira.getBadgeLocalized(rawBadge, lang) : rawBadge;

    if (multi && multi[lang]) {
      return {
        ...p,
        title: multi[lang].title || p.title,
        badge: localizedBadge,
        color: multi[lang].color || p.color,
        hero_quote: multi[lang].hero_quote || p.hero_quote,
        desc: multi[lang].desc || p.desc,
        material: multi[lang].material || p.material,
        origin: multi[lang].origin || p.origin,
        craft: multi[lang].craft || p.craft,
        care: multi[lang].care || p.care,
        formattedPrice: window.Zafira.formatPrice(p.price, lang)
      };
    }
    return {
      ...p,
      badge: localizedBadge,
      formattedPrice: window.Zafira ? window.Zafira.formatPrice(p.price, lang) : formatRupiah(p.price)
    };
  }

  getCategoryTitle(catId, lang) {
    if (!lang) lang = window.Zafira ? window.Zafira.getLanguage() : 'id';
    const keyMap = {
      'all': 'cat_all',
      8: 'cat_nusantara',
      9: 'cat_capsule',
      1: 'cat_women',
      2: 'cat_men',
      3: 'cat_bags',
      4: 'cat_shoes',
      5: 'cat_scarves'
    };
    const key = keyMap[catId];
    if (key && window.Zafira) {
      return window.Zafira.t(key, 'Koleksi');
    }
    const cat = this.categories.find(c => c.id == catId);
    return cat ? cat.title : 'Koleksi';
  }

  getFilteredProducts() {
    const curLang = window.Zafira ? window.Zafira.getLanguage() : 'id';
    return this.products.filter(rawP => {
      const p = this.getProductLocalized(rawP, curLang);
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
    this.save('zafira_v3_5_cart', this.cart);
  }

  updateCartQty(index, delta) {
    if (this.cart[index]) {
      this.cart[index].qty += delta;
      if (this.cart[index].qty <= 0) {
        this.cart.splice(index, 1);
      }
      this.save('zafira_v3_5_cart', this.cart);
    }
  }

  removeFromCart(index) {
    this.cart.splice(index, 1);
    this.save('zafira_v3_5_cart', this.cart);
  }

  clearCart() {
    this.cart = [];
    this.save('zafira_v3_5_cart', this.cart);
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
    this.save('zafira_v3_5_concierge', this.concierge);
    return newOrder;
  }

  addAuditLog(actor, role, module, action) {
    const now = new Date();
    const timestamp = now.getFullYear() + '-' +
      String(now.getMonth() + 1).padStart(2, '0') + '-' +
      String(now.getDate()).padStart(2, '0') + ' ' +
      String(now.getHours()).padStart(2, '0') + ':' +
      String(now.getMinutes()).padStart(2, '0') + ':' +
      String(now.getSeconds()).padStart(2, '0') + ' WIB';

    const hexChars = '0123456789abcdef';
    let hash = '';
    for (let i = 0; i < 64; i++) hash += hexChars[Math.floor(Math.random() * 16)];

    const entry = { timestamp, actor, role, module, action, hash };
    this.auditLogs.unshift(entry);
    if (this.auditLogs.length > 40) this.auditLogs.pop();
    this.save('zafira_v3_5_audit_logs', this.auditLogs);
    return entry;
  }

  assignArtisan(orderId, artisanName) {
    const item = this.concierge.find(c => c.id === orderId);
    if (item) {
      item.artisan = artisanName;
      this.save('zafira_v3_5_concierge', this.concierge);
      this.addAuditLog('Atelier Manager', this.currentRbacRole, 'TPS_ASSIGNMENT', `Pesanan #${orderId} ditugaskan ke: ${artisanName}`);
    }
  }

  restockMaterial(matId, addQty) {
    const mat = this.rawMaterials.find(m => m.id === matId);
    if (mat) {
      mat.stock += addQty;
      mat.status = mat.stock > mat.safetyStock ? 'safe' : 'warn';
      this.save('zafira_v3_5_materials', this.rawMaterials);
      this.addAuditLog('Supply Logistics', this.currentRbacRole, 'MIS_INVENTORY', `Pasokan diterima: +${addQty} ${mat.unit} ${mat.name}`);
    }
  }

  updateOrderStatus(id, newStatus) {
    const item = this.concierge.find(c => c.id === id);
    if (item) {
      const oldStatus = item.status;
      item.status = newStatus;
      this.save('zafira_v3_5_concierge', this.concierge);
      this.addAuditLog('Client Advisor', this.currentRbacRole, 'TPS_KANBAN', `Status pesanan #${id} (${item.name}) diperbarui: ${oldStatus.toUpperCase()} -> ${newStatus.toUpperCase()}`);
    }
  }
}

// 3. SINGLETON CONTROLLER
const store = new PrototypeStore();

function formatRupiah(num) {
  const curr = store.currency || 'IDR';
  if (curr === 'EUR') {
    const val = Math.round(Number(num || 0) / 17200);
    return '€\u00A0' + val.toLocaleString('de-DE');
  }
  if (curr === 'USD') {
    const val = Math.round(Number(num || 0) / 16200);
    return '$\u00A0' + val.toLocaleString('en-US');
  }
  return 'Rp\u00A0' + Number(num || 0).toLocaleString('id-ID');
}

function setCurrency(curr) {
  store.currency = curr;
  store.save('zafira_currency', curr);
  document.querySelectorAll('.curr-opt').forEach(el => {
    el.classList.toggle('active', el.dataset.curr === curr);
  });
  renderCatalog();
  renderCartDrawer();
  if (store.currentView === 'studio' && store.activeStudioProduct) {
    openProductStudio(store.activeStudioProduct.id);
  }
  if (store.currentView === 'account') {
    renderAccountPortal();
  }
  playAtelierSound('chime');
  showToast(`Mata uang dialihkan ke ${curr} (Konversi real-time Maison ZAFIRA)`);
}

// Web Audio API Synthesis for Subtle Atelier Acoustics
let audioCtx = null;
function getAudioContext() {
  if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
}

function playAtelierSound(type = 'click') {
  if (!store.soundEnabled) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    if (type === 'click' || type === 'tab') {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(800, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(320, ctx.currentTime + 0.04);
      gain.gain.setValueAtTime(0.04, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.04);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.04);
    } else if (type === 'stamp' || type === 'seal') {
      const osc1 = ctx.createOscillator();
      const osc2 = ctx.createOscillator();
      const gain = ctx.createGain();
      osc1.type = 'triangle';
      osc2.type = 'sine';
      osc1.frequency.setValueAtTime(220, ctx.currentTime);
      osc1.frequency.exponentialRampToValueAtTime(110, ctx.currentTime + 0.12);
      osc2.frequency.setValueAtTime(587.33, ctx.currentTime);
      osc2.frequency.exponentialRampToValueAtTime(293.66, ctx.currentTime + 0.18);
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
      osc1.connect(gain);
      osc2.connect(gain);
      gain.connect(ctx.destination);
      osc1.start();
      osc2.start();
      osc1.stop(ctx.currentTime + 0.18);
      osc2.stop(ctx.currentTime + 0.18);
    } else if (type === 'chime') {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(1046.50, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(523.25, ctx.currentTime + 0.25);
      gain.gain.setValueAtTime(0.05, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.25);
    }
  } catch (e) {}
}

function toggleAudioFeedback() {
  store.soundEnabled = !store.soundEnabled;
  store.save('zafira_sound', store.soundEnabled);
  const btn = document.getElementById('sound-toggle-btn');
  const label = document.getElementById('sound-label');
  if (btn && label) {
    label.textContent = store.soundEnabled ? 'Acoustics: On' : 'Acoustics: Mute';
    btn.classList.toggle('muted', !store.soundEnabled);
  }
  const profBtn = document.getElementById('profile-sound-btn');
  const profLabel = document.getElementById('profile-sound-label');
  if (profBtn && profLabel) {
    profLabel.textContent = store.soundEnabled ? (window.Zafira ? window.Zafira.t('sound_on', 'Aktif') : 'Aktif') : (window.Zafira ? window.Zafira.t('sound_mute', 'Senyap') : 'Senyap');
    profBtn.classList.toggle('muted', !store.soundEnabled);
  }
  if (store.soundEnabled) playAtelierSound('chime');
  showToast(store.soundEnabled ? 'Acoustics Atelier diaktifkan' : 'Acoustics Atelier dinonaktifkan');
}

function showToast(message, type = 'info', actionText = null, actionCallback = null) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = 'toast-item';
  toast.innerHTML = `
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dfcaa0" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
      <circle cx="12" cy="12" r="9"></circle>
      <path d="M12 7v5"></path>
      <circle cx="12" cy="16" r="0.75" fill="#dfcaa0"></circle>
    </svg>
    <span style="flex-grow:1;">${message}</span>
    ${actionText ? `<button class="toast-action-btn" type="button">${actionText}</button>` : ''}
  `;
  container.appendChild(toast);

  if (actionText && typeof actionCallback === 'function') {
    const actionBtn = toast.querySelector('.toast-action-btn');
    if (actionBtn) {
      actionBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        actionCallback();
        toast.remove();
      });
    }
  }

  const duration = actionText ? 6500 : 4000;
  setTimeout(() => {
    if (!toast.parentNode) return;
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ==========================================================================
// RENDERERS (EDITORIAL VS GALLERY) - SHNEIDERMAN INFORMATION SEEKING
// ==========================================================================

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
}

function resetSearchAndFilter() {
  store.searchQuery = '';
  store.currentCategory = 'all';
  const searchInput = document.getElementById('sf-search-input');
  if (searchInput) searchInput.value = '';
  document.querySelectorAll('.cat-pill').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.cat === 'all');
  });
  renderCatalog();
  showToast('Menampilkan seluruh koleksi kriya Maison ZAFIRA.');
}

function renderCatalog() {
  const container = document.getElementById('catalog-content-area');
  if (!container) return;

  const filtered = store.getFilteredProducts();

  // Search Feedback Ribbon (Shneiderman Rule 3: Informative Feedback)
  const searchFeedbackHtml = store.searchQuery ? `
    <div class="catalog-search-feedback-bar">
      <span>Menampilkan <strong>${filtered.length}</strong> kreasi untuk pencarian: “<em>${escapeHtml(store.searchQuery)}</em>”</span>
      <button class="proto-btn" style="background:transparent; color:var(--muted); border:1px solid var(--line); padding:4px 10px; font-size:11px;" onclick="resetSearchAndFilter()">Bersihkan Pencarian</button>
    </div>
  ` : '';

  // Empty State (Nielsen Heuristic 9: Help users recognize, diagnose & recover)
  if (filtered.length === 0) {
    container.innerHTML = `
      ${searchFeedbackHtml}
      <div class="catalog-empty-state">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <h3 style="font-family:var(--serif); font-size:24px; color:var(--sapphire-midnight); margin:0 0 8px;">Tiada Kreasi Kriya Ditemukan</h3>
        <p style="color:var(--muted); font-size:13px; max-width:440px; margin:0 auto 20px; line-height:1.6;">
          Pencarian untuk “<strong>${escapeHtml(store.searchQuery)}</strong>” tidak menemukan kreasi yang sesuai. Silakan gunakan kata kunci lain atau bersihkan penyaring.
        </p>
        <button class="btn-gold" onclick="resetSearchAndFilter()">Tampilkan Semua Koleksi Kriya</button>
      </div>
    `;
    return;
  }

  if (store.viewMode === 'editorial') {
    // 1. EDITORIAL LIVING MAGAZINE SHOWCASE
    container.innerHTML = `
      ${searchFeedbackHtml}
      <div class="editorial-flow">

        <!-- Diptych 1: First Creation & Savoir-Faire -->
        <article class="diptych-row">
          <div class="diptych-media" onclick="openProductStudio(${filtered[0].id})" style="cursor:pointer;">
            <img src="${filtered[0].images[0]}" alt="${store.getProductLocalized(filtered[0]).title}">
            <span class="diptych-caption-tag">${store.getProductLocalized(filtered[0]).badge || 'Haute Couture'} · 60h Broderie</span>
          </div>
          <div class="diptych-content">
            <span class="diptych-origin">${store.getProductLocalized(filtered[0]).origin || 'Atelier Paris · Bordir Tangan Kebayoran'}</span>
            <h3 class="diptych-title">${store.getProductLocalized(filtered[0]).title}</h3>
            <p class="diptych-quote">“${store.getProductLocalized(filtered[0]).hero_quote || 'Kemurnian kriya adibusana.'}”</p>
            <p style="font-size:13.5px; color:var(--muted); line-height:1.8; margin-bottom:20px;">
              ${store.getProductLocalized(filtered[0]).desc}
            </p>
            <div class="diptych-metrics">
              <div class="metric-col">
                <strong>60+ Jam</strong>
                <span>${window.Zafira ? window.Zafira.t('studio_tab_craft', 'Savoir-Faire') : 'Bordir Tangan'}</span>
              </div>
              <div class="metric-col">
                <strong>22 Momme</strong>
                <span>${window.Zafira ? window.Zafira.t('studio_tab_material', 'Material') : 'Sutra Organza Lyon'}</span>
              </div>
              <div class="metric-col">
                <strong>${store.getProductLocalized(filtered[0]).formattedPrice}</strong>
                <span>${window.Zafira ? window.Zafira.t('cart_total_label', 'Nilai Kreasi') : 'Nilai Kreasi'}</span>
              </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
              <button class="btn-gold" onclick="openProductStudio(${filtered[0].id})">${window.Zafira ? window.Zafira.t('hero_cta_studio', 'Buka Studio Kriya') : 'Buka Studio Kriya'}</button>
              <button class="btn-outline" onclick="quickAddToCart(${filtered[0].id})">${window.Zafira ? window.Zafira.t('card_add', 'Tambah ke Tas') : 'Tambah ke Tas'}</button>
            </div>
          </div>
        </article>

        <!-- Diptych 2 (Reverse): Second Creation -->
        ${filtered[1] ? `
        <article class="diptych-row reverse">
          <div class="diptych-content">
            <span class="diptych-origin">${store.getProductLocalized(filtered[1]).origin || 'Batik Tulis Laweyan · Kerah Neapolitan'}</span>
            <h3 class="diptych-title">${store.getProductLocalized(filtered[1]).title}</h3>
            <p class="diptych-quote">“${store.getProductLocalized(filtered[1]).hero_quote || 'Kemegahan busana kriya.'}”</p>
            <p style="font-size:13.5px; color:var(--muted); line-height:1.8; margin-bottom:20px;">
              ${store.getProductLocalized(filtered[1]).desc}
            </p>
            <div class="diptych-metrics">
              <div class="metric-col">
                <strong>3 Minggu</strong>
                <span>${window.Zafira ? window.Zafira.t('studio_tab_craft', 'Savoir-Faire') : 'Goresan Canting'}</span>
              </div>
              <div class="metric-col">
                <strong>9 Kali</strong>
                <span>${window.Zafira ? window.Zafira.t('studio_tab_material', 'Material') : 'Celupan Indigo'}</span>
              </div>
              <div class="metric-col">
                <strong>${store.getProductLocalized(filtered[1]).formattedPrice}</strong>
                <span>${window.Zafira ? window.Zafira.t('cart_total_label', 'Nilai Kreasi') : 'Nilai Kreasi'}</span>
              </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
              <button class="btn-gold" onclick="openProductStudio(${filtered[1].id})">${window.Zafira ? window.Zafira.t('hero_cta_studio', 'Buka Studio Kriya') : 'Buka Studio Kriya'}</button>
              <button class="btn-outline" onclick="quickAddToCart(${filtered[1].id})">${window.Zafira ? window.Zafira.t('card_add', 'Tambah ke Tas') : 'Tambah ke Tas'}</button>
            </div>
          </div>
          <div class="diptych-media" onclick="openProductStudio(${filtered[1].id})" style="cursor:pointer;">
            <img src="${filtered[1].images[0]}" alt="${store.getProductLocalized(filtered[1]).title}">
            <span class="diptych-caption-tag">${store.getProductLocalized(filtered[1]).badge || 'Pewarna Alami'} · Canting Solo</span>
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
      ${filtered.length > 2 ? `
      <div class="editorial-flow">
        <div style="text-align:center; margin-bottom:36px;">
          <span style="font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:var(--gold); font-weight:700;">Les Autres Pièces</span>
          <h3 style="font-family:var(--serif); font-size:32px; color:var(--sapphire-midnight);">Koleksi Kriya Pilihan</h3>
        </div>
        <div class="gallery-grid">
          ${filtered.slice(2).map(p => renderProductCardHtml(p)).join('')}
        </div>
      </div>
      ` : ''}
    `;
  } else {
    // 2. CLEAN GALLERY MODE (MUSEUM CATALOG GRID)
    container.innerHTML = `
      ${searchFeedbackHtml}
      <div class="gallery-flow">
        <div class="gallery-grid">
          ${filtered.map(p => renderProductCardHtml(p)).join('')}
        </div>
      </div>
    `;
  }
}

function renderProductCardHtml(rawP) {
  const p = store.getProductLocalized(rawP);
  const cat = store.getCategoryTitle(p.cat_id);
  const studioLabel = window.Zafira ? window.Zafira.t('card_studio_link', 'Studio Z') : 'Studio Z';
  const addLabel = window.Zafira ? window.Zafira.t('card_add', 'Ke Tas') : 'Ke Tas';

  return `
    <article class="product-card" data-id="${p.id}">
      <div class="product-media" onclick="openProductStudio(${p.id})" style="cursor:pointer;">
        <img src="${p.images[0]}" alt="${p.title}">
        ${p.badge ? `<span class="badge-creations">${p.badge}</span>` : ''}
        <div class="product-overlay-actions" onclick="event.stopPropagation()">
          <button class="btn-quick" onclick="openProductStudio(${p.id})">${studioLabel}</button>
          <button class="btn-quick" style="background:var(--sapphire-midnight); color:#fff; border-color:var(--sapphire-midnight);" onclick="quickAddToCart(${p.id})">+ ${addLabel}</button>
        </div>
      </div>
      <div class="product-info">
        <span class="product-cat">${cat}</span>
        <h4 class="product-title" onclick="openProductStudio(${p.id})" style="cursor:pointer;">${p.title}</h4>
        <p class="product-desc-snippet">${p.desc.substring(0, 80)}...</p>
        <div class="product-meta">
          <span class="product-price">${p.formattedPrice}</span>
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
  const baseP = store.products.find(prod => prod.id === productId);
  if (!baseP) return;
  store.activeStudioProduct = baseP;
  const p = store.getProductLocalized(baseP);
  store.selectedStudioSize = Object.keys(baseP.measurements || {})[0] || 'FR 38';
  store.selectedStudioTab = 'story';

  // Render Studio DOM
  const studioView = document.getElementById('product-studio-view');
  if (!studioView) return;

  const cat = store.getCategoryTitle(p.cat_id);
  const sizeKeys = Object.keys(p.measurements || { 'FR 36': '', 'FR 38': '', 'FR 40': '', 'FR 42': '' });

  studioView.innerHTML = `
    <!-- Top Back Navigation -->
    <div class="studio-top-nav">
      <button class="proto-btn" style="background:transparent; color:var(--ink); border:1px solid var(--line); display:inline-flex; align-items:center; gap:6px;" onclick="switchView('storefront')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span data-i18n="studio_back_btn">${window.Zafira ? window.Zafira.t('studio_back_btn', 'Kembali ke Penjelajahan') : 'Kembali ke Penjelajahan'}</span>
      </button>
      <div style="font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:var(--taupe); font-weight:700;">
        ${cat} · Création No. ${p.id}
      </div>
      <div style="display:flex; align-items:center; gap:8px;">
        <button class="action-btn" onclick="openProfileDrawer()" title="Profil &amp; Pilihan Salon">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span data-i18n="nav_profile">${window.Zafira ? window.Zafira.t('nav_profile', 'Profil') : 'Profil'}</span>
        </button>
        <button class="action-btn" onclick="openCartDrawer()">
          <span data-i18n="studio_cart_btn">${window.Zafira ? window.Zafira.t('studio_cart_btn', 'Tas Belanja') : 'Tas Belanja'}</span>
          <span class="badge-count cart-count-badge">${store.cart.reduce((s, i) => s + i.qty, 0)}</span>
        </button>
      </div>
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
        <!-- Steve Krug Trunk Test: Contextual Breadcrumbs -->
        <div class="studio-breadcrumbs">
          <a onclick="switchView('storefront')">Maison ZAFIRA</a>
          <span>/</span>
          <a onclick="switchView('storefront'); filterCategory(${p.cat_id})">${cat}</a>
          <span>/</span>
          <span class="active">${p.title}</span>
        </div>

        <span class="studio-category-label">${cat}</span>
        <h1 class="studio-product-title">${p.title}</h1>
        <div class="studio-price">${p.formattedPrice}</div>

        <!-- 4-Tab Craftsmanship Navigation -->
        <div class="qv-tabs" role="tablist">
          <button class="qv-tab-btn ${store.selectedStudioTab === 'story' ? 'active' : ''}" onclick="switchStudioTab('story')" data-i18n="studio_tab_silhouette">${window.Zafira ? window.Zafira.t('studio_tab_silhouette', 'Siluet') : 'Siluet'}</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'material' ? 'active' : ''}" onclick="switchStudioTab('material')" data-i18n="studio_tab_material">${window.Zafira ? window.Zafira.t('studio_tab_material', 'Material') : 'Material'}</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'craft' ? 'active' : ''}" onclick="switchStudioTab('craft')" data-i18n="studio_tab_savoir_faire">${window.Zafira ? window.Zafira.t('studio_tab_savoir_faire', 'Savoir-Faire') : 'Savoir-Faire'}</button>
          <button class="qv-tab-btn ${store.selectedStudioTab === 'care' ? 'active' : ''}" onclick="switchStudioTab('care')" data-i18n="studio_tab_care">${window.Zafira ? window.Zafira.t('studio_tab_care', 'Perawatan') : 'Perawatan'}</button>
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
            <p><strong data-i18n="studio_label_composition">${window.Zafira ? window.Zafira.t('studio_label_composition', 'Komposisi:') : 'Komposisi:'}</strong> ${p.material}</p>
            <p style="margin-top:6px;"><strong data-i18n="studio_label_provenance">${window.Zafira ? window.Zafira.t('studio_label_provenance', 'Provenance:') : 'Provenance:'}</strong> ${p.origin}</p>
          </div>
          <small style="color:var(--muted); font-size:11px;">Tersertifikasi serat alami bebas pewarna sintetis berat.</small>
        </div>

        <!-- Tab 3: Savoir-Faire Kriya -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'craft' ? 'active' : ''}" id="studio-pane-craft">
          <div style="background:var(--ivory); padding:14px; border-radius:var(--radius-xs); border-left:3px solid var(--gold); margin-bottom:8px;">
            <p><strong data-i18n="studio_label_craft">${window.Zafira ? window.Zafira.t('studio_label_craft', 'Keahlian Tangan:') : 'Keahlian Tangan:'}</strong> ${p.craft}</p>
          </div>
          <small style="color:var(--muted); font-size:11px;">Dikerjakan di bawah supervisi Direktur Kreatif ZAFIRA.</small>
        </div>

        <!-- Tab 4: Panduan Perawatan -->
        <div class="qv-tab-pane ${store.selectedStudioTab === 'care' ? 'active' : ''}" id="studio-pane-care">
          <div style="background:var(--ivory); padding:14px; border-radius:var(--radius-xs); border-left:3px solid #64748b; margin-bottom:8px;">
            <p><strong data-i18n="studio_label_care">${window.Zafira ? window.Zafira.t('studio_label_care', 'Perawatan Busana:') : 'Perawatan Busana:'}</strong> ${p.care}</p>
          </div>
        </div>

        <!-- Size Selection (Atelier Fit) -->
        <div class="size-selector-wrap">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <label class="form-label" data-i18n="studio_size_label">${window.Zafira ? window.Zafira.t('studio_size_label', 'PILIHAN UKURAN ATELIER') : 'PILIHAN UKURAN ATELIER'}</label>
            <span style="font-size:11px; color:var(--taupe); cursor:pointer; text-decoration:underline;" onclick="alert('Panduan Ukuran: Standard French Couture sizing (FR). Konsultasi ukuran khusus tersedia via Client Advisor.')" data-i18n="studio_size_guide">${window.Zafira ? window.Zafira.t('studio_size_guide', 'Panduan Ukuran') : 'Panduan Ukuran'}</span>
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

        <!-- Live Monogram Studio with Norman Affordance & Active Counter -->
        <div class="monogram-studio-card">
          <div class="monogram-stamp-badge" id="studio-monogram-stamp">${store.activeStudioMonogram || 'AT'}</div>
          <div style="flex-grow:1;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <label class="form-label" style="margin-bottom:0;" data-i18n="studio_monogram_title">${window.Zafira ? window.Zafira.t('studio_monogram_title', 'Personalisasi Monogram Emas') : 'Personalisasi Monogram Emas'}</label>
              <span id="monogram-char-counter" class="monogram-counter-badge ${(store.activeStudioMonogram || 'AT').length === 3 ? 'full' : ''}">
                ${(store.activeStudioMonogram || 'AT').length} / 3
              </span>
            </div>
            <input type="text" id="studio-monogram-input" class="form-input" maxlength="3" value="${store.activeStudioMonogram || 'AT'}" placeholder="Misal: AT"
              style="padding:6px 12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase;"
              oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, ''); updateStudioMonogramBadge(this.value)">
            <small style="font-size:10px; color:var(--taupe); display:flex; align-items:center; gap:4px; margin-top:3px;">
              <span class="indicator-dot gold"></span> <span data-i18n="studio_monogram_desc">${window.Zafira ? window.Zafira.t('studio_monogram_desc', 'Dibordir tangan dengan benang metalik emas 18 karat di atelier.') : 'Dibordir tangan dengan benang metalik emas 18 karat di atelier.'}</span>
            </small>
          </div>
        </div>

        <!-- Packaging Selection Toggle -->
        <div style="margin-bottom:24px; padding:12px; background:var(--ivory); border-radius:var(--radius-xs); border:1px solid var(--line);">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <strong style="font-size:12px; color:var(--sapphire-midnight);" data-i18n="packaging_title">Kemasan Kado Signature & Wax Seal</strong>
              <p style="font-size:11px; color:var(--muted);" data-i18n="studio_packaging_desc">${window.Zafira ? window.Zafira.t('studio_packaging_desc', 'Kotak kaku safir, pita sutra Lyon, dan stempel segel lilin merah.') : 'Kotak kaku safir, pita sutra Lyon, dan stempel segel lilin merah.'}</p>
            </div>
            <span style="font-size:10px; font-weight:700; color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:10px;">Complimentary</span>
          </div>
        </div>

        <!-- Provenance & Artisan Verification Card (Loro Piana & Cartier Benchmark) -->
        <div style="margin-bottom:20px; padding:14px; background:var(--ivory); border-radius:var(--radius-xs); border:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <div>
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:2px;">
              <span class="indicator-dot gold" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--gold);"></span>
              <span style="font-size:9.5px; text-transform:uppercase; letter-spacing:0.14em; color:var(--taupe); font-weight:700;">Traçabilité &amp; Kriya Terverifikasi</span>
            </div>
            <strong style="font-size:12px; color:var(--sapphire-midnight); display:block;">84 Jam Kriya Tangan · Sertifikat Keaslian Digital</strong>
            <span style="font-size:11px; color:var(--muted);">Terdaftar dalam Ledger SHA-256 Maison ZAFIRA</span>
          </div>
          <button type="button" class="btn-outline" style="padding:6px 12px; font-size:10.5px; display:inline-flex; align-items:center; gap:5px;" onclick="openAuthenticityModal(${p.id})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            <span>Verifikasi Keaslian</span>
          </button>
        </div>

        <!-- Purchase Actions -->
        <div style="display:flex; flex-direction:column; gap:12px;">
          <button class="btn-gold" style="width:100%; justify-content:center; padding:15px;" onclick="addStudioToCart()" data-i18n="studio_add_bag_btn">
            ${window.Zafira ? window.Zafira.t('studio_add_bag_btn', 'Tambah ke Tas Belanja') : 'Tambah ke Tas Belanja'}
          </button>
          <button class="btn-outline" style="width:100%; justify-content:center; padding:13px;" onclick="openRendezvousModal()" data-i18n="studio_book_fitting_btn">
            ${window.Zafira ? window.Zafira.t('studio_book_fitting_btn', 'Jadwalkan Fitting Privat di Atelier') : 'Jadwalkan Fitting Privat di Atelier'}
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
  playAtelierSound('tab');
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
  playAtelierSound('click');
}

function updateStudioMonogramBadge(val) {
  store.activeStudioMonogram = val;
  const badge = document.getElementById('studio-monogram-stamp');
  const counter = document.getElementById('monogram-char-counter');
  if (badge) {
    badge.textContent = val.trim() ? val.toUpperCase() : 'Z';
    badge.classList.add('pulsing');
    clearTimeout(badge._pulseTimer);
    badge._pulseTimer = setTimeout(() => badge.classList.remove('pulsing'), 900);
  }
  if (counter) {
    counter.textContent = `${val.length} / 3`;
    counter.classList.toggle('full', val.length === 3);
  }
  playAtelierSound('click');
}

function addStudioToCart() {
  const mono = document.getElementById('studio-monogram-input')?.value.trim() || '';
  store.addToCart(store.activeStudioProduct.id, store.selectedStudioSize, mono, 'gift-box');
  renderCartDrawer();
  openCartDrawer();
  playAtelierSound('chime');
  const locProd = store.getProductLocalized(store.activeStudioProduct);
  const tpl = window.Zafira ? window.Zafira.t('toast_cart_added_title', 'Kreasi "{title}" ({size}) berhasil ditambahkan ke Tas.') : 'Kreasi "{title}" ({size}) berhasil ditambahkan ke Tas.';
  const msg = tpl.replace('{title}', locProd ? locProd.title : 'Kreasi').replace('{size}', store.selectedStudioSize);
  showToast(msg);
}

function quickAddToCart(productId) {
  store.addToCart(productId, 'FR 38', '', 'gift-box');
  renderCartDrawer();
  openCartDrawer();
  playAtelierSound('chime');
  const p = store.products.find(prod => prod.id === productId);
  const locProd = store.getProductLocalized(p);
  const tpl = window.Zafira ? window.Zafira.t('toast_cart_added_title', 'Kreasi "{title}" ({size}) berhasil ditambahkan ke Tas.') : 'Kreasi "{title}" ({size}) berhasil ditambahkan ke Tas.';
  const msg = tpl.replace('{title}', locProd ? locProd.title : (p?.title || 'Kreasi')).replace('{size}', 'FR 38');
  showToast(msg);
}

// ==========================================================================
// CART DRAWER & CHECKOUT
// ==========================================================================

function openCartDrawer() {
  document.getElementById('cart-drawer')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
  playAtelierSound('swoosh');
}

function closeCartDrawer() {
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('drawer-backdrop')?.classList.remove('open');
}

function openProfileDrawer() {
  closeAllModals();
  document.getElementById('profile-drawer')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
  playAtelierSound('swoosh');
}

function closeProfileDrawer() {
  document.getElementById('profile-drawer')?.classList.remove('open');
  document.getElementById('drawer-backdrop')?.classList.remove('open');
}

function openMobileNavDrawer() {
  closeAllModals();
  document.getElementById('mobile-nav-drawer')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
  playAtelierSound('swoosh');
}

function closeMobileNavDrawer() {
  document.getElementById('mobile-nav-drawer')?.classList.remove('open');
  document.getElementById('drawer-backdrop')?.classList.remove('open');
}

function renderCartDrawer() {
  const container = document.getElementById('cart-items-container');
  const subtotalEl = document.getElementById('cart-subtotal');
  const badgeCounts = document.querySelectorAll('.cart-count-badge');
  const lang = (window.Zafira && window.Zafira.getLang) ? window.Zafira.getLang() : (localStorage.getItem('zafira-lang') || 'id');

  const totalCount = store.cart.reduce((sum, item) => sum + item.qty, 0);
  badgeCounts.forEach(el => el.textContent = totalCount);

  if (!container) return;

  if (store.cart.length === 0) {
    const emptyMsg = window.Zafira ? window.Zafira.t('cart_empty_title', 'Tas belanja Maison Anda saat ini kosong.') : 'Tas belanja Maison Anda saat ini kosong.';
    container.innerHTML = `<div style="text-align:center; padding:60px 20px; color:var(--muted);">${emptyMsg}</div>`;
    if (subtotalEl) subtotalEl.textContent = window.Zafira ? window.Zafira.formatPrice(0, store.currency) : 'Rp0';
    return;
  }

  const removeLabel = window.Zafira ? window.Zafira.t('cart_remove', 'Hapus') : 'Hapus';
  const sizeLabel = window.Zafira ? window.Zafira.t('cart_size', 'Taille:') : 'Taille:';
  const monogramLabel = window.Zafira ? window.Zafira.t('cart_monogram', 'Monogram:') : 'Monogram:';

  container.innerHTML = store.cart.map((item, idx) => {
    const rawProd = store.products.find(prod => prod.id === item.productId);
    if (!rawProd) return '';
    const p = store.getProductLocalized(rawProd, lang);
    const itemPriceFormatted = window.Zafira ? window.Zafira.formatPrice(p.price, store.currency) : formatRupiah(p.price);
    return `
      <div class="cart-item">
        <img src="${p.images[0]}" alt="${p.title}">
        <div class="cart-item-detail">
          <h4 class="cart-item-title">${p.title}</h4>
          <div class="cart-item-price">${itemPriceFormatted}</div>
          <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px;">
            ${item.size ? `<span style="font-size:10px; background:#f1f5f9; color:var(--sapphire-midnight); font-weight:700; padding:2px 7px; border-radius:2px; border:1px solid #e2e8f0;">${sizeLabel} ${item.size}</span>` : ''}
            ${item.monogram ? `<span style="font-size:10px; background:rgba(184,152,88,0.15); color:#876831; font-weight:700; padding:2px 7px; border-radius:2px; border:1px solid var(--gold); display:inline-flex; align-items:center; gap:4px;"><span class="indicator-dot gold" style="margin-right:2px;"></span> ${monogramLabel} ${item.monogram}</span>` : ''}
            <span style="font-size:10px; background:#f8fafc; color:#15803d; font-weight:600; padding:2px 7px; border-radius:2px;">Signature Box &amp; Seal</span>
          </div>
          <div class="cart-item-controls">
            <div class="qty-stepper">
              <button class="qty-btn" onclick="stepCartQty(${idx}, -1)" aria-label="Kurangi Jumlah"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
              <span class="qty-val">${item.qty}</span>
              <button class="qty-btn" onclick="stepCartQty(${idx}, 1)" aria-label="Tambah Jumlah"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
            </div>
            <button class="btn-remove" onclick="removeCartItem(${idx})">${removeLabel}</button>
          </div>
        </div>
      </div>
    `;
  }).join('');

  if (subtotalEl) {
    subtotalEl.textContent = window.Zafira ? window.Zafira.formatPrice(store.getCartTotal(), store.currency) : formatRupiah(store.getCartTotal());
  }
}

function stepCartQty(index, delta) {
  store.updateCartQty(index, delta);
  renderCartDrawer();
}

function removeCartItem(index) {
  const itemToRestore = store.cart[index];
  if (!itemToRestore) return;
  const product = store.products.find(p => p.id === itemToRestore.productId);
  const locProd = store.getProductLocalized(product);
  const title = locProd?.title || product?.title || 'Kreasi';

  store.removeFromCart(index);
  renderCartDrawer();
  playAtelierSound('click');

  const removedTpl = window.Zafira ? window.Zafira.t('toast_cart_removed', '"{title}" dihapus dari tas belanja.') : '"{title}" dihapus dari tas belanja.';
  const undoBtn = window.Zafira ? window.Zafira.t('toast_cart_undo_btn', 'Urungkan (Undo)') : 'Urungkan (Undo)';
  const restoredTpl = window.Zafira ? window.Zafira.t('toast_cart_restored', 'Kreasi "{title}" dikembalikan ke tas belanja.') : 'Kreasi "{title}" dikembalikan ke tas belanja.';

  showToast(removedTpl.replace('{title}', title), 'info', undoBtn, () => {
    store.cart.splice(index, 0, itemToRestore);
    store.save('zafira_v3_5_cart', store.cart);
    renderCartDrawer();
    playAtelierSound('chime');
    showToast(restoredTpl.replace('{title}', title));
  });
}

// ==========================================================================
// UNBOXING SIMULATOR & RENDEZ-VOUS MODAL
// ==========================================================================

function updatePackagingRibbon(ribbonColor) {
  store.packagingState.ribbon = ribbonColor;
  const ribbonEl = document.getElementById('preview-ribbon-band');
  if (ribbonEl) {
    if (ribbonColor === 'gold') {
      ribbonEl.style.background = '#b89858';
    } else {
      ribbonEl.style.background = '#1b2e50';
    }
  }
  const ribbonName = ribbonColor === 'gold' ? 'Or Champagne' : 'Bleu Saphir';
  const tpl = window.Zafira ? window.Zafira.t('toast_ribbon_updated', 'Pita kemasan diganti: {ribbon}.') : 'Pita kemasan diganti: {ribbon}.';
  showToast(tpl.replace('{ribbon}', ribbonName));
}

function stampWaxSeal() {
  const badge = document.getElementById('interactive-wax-seal');
  if (badge) {
    badge.classList.remove('wax-stamped');
    void badge.offsetWidth; // trigger reflow
    badge.classList.add('wax-stamped');
    playAtelierSound('stamp');
    const tpl = window.Zafira ? window.Zafira.t('toast_wax_sealed', 'Segel lilin resmi Maison ZAFIRA telah dibubuhkan (No. Seri #{sn}).') : 'Segel lilin resmi Maison ZAFIRA telah dibubuhkan (No. Seri #{sn}).';
    showToast(tpl.replace('{sn}', 'ZF-2026-9041'));
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
    return `- ${p?.title} (${item.qty}x) ${item.size ? `[${item.size}]` : ''} ${item.monogram ? `[Monogram: ${item.monogram}]` : ''}`;
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
  renderMsiEisCockpit();
  renderAuditTrail();
  closeAllModals();
  const apptTpl = window.Zafira ? window.Zafira.t('toast_rendezvous_registered', 'Simulasi: permohonan janji temu dicatat lokal saja; tidak tersimpan di server.') : 'Simulasi: permohonan janji temu dicatat lokal saja; tidak tersimpan di server.';
  showToast(apptTpl);
}

// ==========================================================================
// ATELIER ADMIN WORKSPACE (KANBAN ORDER PIPELINE & TPS)
// ==========================================================================

function renderKanbanBoard() {
  const board = document.getElementById('kanban-board-container');
  if (!board) return;

  const cols = [
    { key: 'new', title: window.Zafira ? window.Zafira.t('msi_col_new', 'Baru (New)') : 'Baru (New)', color: '#0369a1' },
    { key: 'contacted', title: window.Zafira ? window.Zafira.t('msi_col_contacted', 'Dihubungi (Contacted)') : 'Dihubungi (Contacted)', color: '#b45309' },
    { key: 'confirmed', title: window.Zafira ? window.Zafira.t('msi_col_confirmed', 'Terkonfirmasi (Confirmed)') : 'Terkonfirmasi (Confirmed)', color: '#15803d' },
    { key: 'completed', title: window.Zafira ? window.Zafira.t('msi_col_completed', 'Selesai (Completed)') : 'Selesai (Completed)', color: '#7e22ce' }
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

              <!-- MSI Atelier Controls: Artisan & Authenticity Certificate & Work Order -->
              <div style="margin-top:10px; padding-top:8px; border-top:1px solid #f1f5f9; display:flex; flex-direction:column; gap:6px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <span style="font-size:10px; color:var(--muted); font-weight:700;">Artisan Kriya:</span>
                  <select class="form-select" style="width:auto; padding:2px 8px; font-size:11px;" onchange="adminAssignArtisan(${item.id}, this.value)">
                    <option value="Ibu Siti Khodijah" ${item.artisan === 'Ibu Siti Khodijah' ? 'selected' : ''}>Ibu Siti (Bordir)</option>
                    <option value="Madame Hélène" ${item.artisan === 'Madame Hélène' ? 'selected' : ''}>Mme Hélène (Paris Drape)</option>
                    <option value="Bapak Nyoman Sujana" ${item.artisan === 'Bapak Nyoman Sujana' ? 'selected' : ''}>Pak Nyoman (Pahat Logam)</option>
                    <option value="Belum Ditugaskan" ${!item.artisan || item.artisan === 'Belum Ditugaskan' ? 'selected' : ''}>Belum Ditugaskan</option>
                  </select>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                  <div style="display:flex; gap:4px;">
                    <button class="btn-outline" style="padding:3px 6px; font-size:10px; display:inline-flex; align-items:center; gap:3px;" onclick="openAuthenticityModal(${item.id})"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Sertifikat</button>
                    <button class="btn-outline" style="padding:3px 6px; font-size:10px; display:inline-flex; align-items:center; gap:3px;" onclick="openWorkOrderModal(${item.id})"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg> Work Order</button>
                  </div>
                  <select class="form-select" style="width:auto; padding:2px 6px; font-size:11px;" onchange="adminUpdateOrderStatus(${item.id}, this.value)">
                    <option value="new" ${item.status === 'new' ? 'selected' : ''}>New</option>
                    <option value="contacted" ${item.status === 'contacted' ? 'selected' : ''}>Contacted</option>
                    <option value="confirmed" ${item.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                    <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>Completed</option>
                  </select>
                </div>
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

function adminAssignArtisan(orderId, artisanName) {
  store.assignArtisan(orderId, artisanName);
  renderKanbanBoard();
  renderAuditTrail();
  showToast(`Pesanan #${orderId} ditugaskan kepada: ${artisanName}`);
}

function adminUpdateOrderStatus(id, newStatus) {
  store.updateOrderStatus(id, newStatus);
  renderKanbanBoard();
  renderAccountPortal();
  renderMsiEisCockpit();
  renderAuditTrail();
  showToast(`Pesanan #${id} diperbarui: ${newStatus.toUpperCase()}`);
}

// ==========================================================================
// VIP CLIENT ACCOUNT PORTAL (/akun)
// ==========================================================================

function renderAccountPortal() {
  const container = document.getElementById('account-history-list');
  if (!container) return;

  const orderTitle = window.Zafira ? window.Zafira.t('account_orders_title', 'Pesanan Adibusana & Janji Temu') : 'Pesanan Adibusana & Janji Temu';
  const certBtnText = window.Zafira ? window.Zafira.t('account_cert_btn', 'Lihat Sertifikat Keaslian Digital') : 'Lihat Sertifikat Keaslian Digital';
  const step1 = window.Zafira ? window.Zafira.t('account_step_1', '1. Konsultasi') : '1. Konsultasi';
  const step2 = window.Zafira ? window.Zafira.t('account_step_2', '2. Pola Drape') : '2. Pola Drape';
  const step3 = window.Zafira ? window.Zafira.t('account_step_3', '3. Kriya Tangan') : '3. Kriya Tangan';
  const step4 = window.Zafira ? window.Zafira.t('account_step_4', '4. Segel Lilin') : '4. Segel Lilin';
  const step5 = window.Zafira ? window.Zafira.t('account_step_5', '5. Pengiriman') : '5. Pengiriman';

  const serviceLabel = window.Zafira ? window.Zafira.t('account_service_label', 'Layanan:') : 'Layanan:';
  const scheduleLabel = window.Zafira ? window.Zafira.t('account_schedule_label', 'Jadwal:') : 'Jadwal:';
  const valueLabel = window.Zafira ? window.Zafira.t('account_value_label', 'Nilai:') : 'Nilai:';

  container.innerHTML = store.concierge.map(c => {
    const formattedPrice = window.Zafira ? window.Zafira.formatPrice(c.totalPrice || 14900000) : formatRupiah(c.totalPrice || 14900000);
    const isCompleted = c.status === 'completed';
    const isConfirmed = c.status === 'confirmed';
    const isContacted = c.status === 'contacted';

    return `
    <article class="order-ledger-card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
        <div>
          <span style="font-size:10px; letter-spacing:0.18em; text-transform:uppercase; color:var(--taupe); font-weight:700;">${orderTitle}</span>
          <h4 style="font-family:var(--serif); font-size:20px; color:var(--sapphire-midnight); margin:2px 0 0;">Commande N° ${c.id}</h4>
        </div>
        <span class="badge-status badge-${c.status}">${c.status}</span>
      </div>

      <!-- Haute Couture 5-Stage Stepper -->
      <div class="stepper-5">
        <div class="stepper-step completed">
          <div class="stepper-step-bar"></div>
          <div class="stepper-step-label">${step1}</div>
        </div>
        <div class="stepper-step ${isConfirmed || isCompleted ? 'completed' : isContacted ? 'active' : ''}">
          <div class="stepper-step-bar"></div>
          <div class="stepper-step-label">${step2}</div>
        </div>
        <div class="stepper-step ${isCompleted ? 'completed' : isConfirmed ? 'active' : ''}">
          <div class="stepper-step-bar"></div>
          <div class="stepper-step-label">${step3}</div>
        </div>
        <div class="stepper-step ${isCompleted ? 'completed' : ''}">
          <div class="stepper-step-bar"></div>
          <div class="stepper-step-label">${step4}</div>
        </div>
        <div class="stepper-step ${isCompleted ? 'completed' : ''}">
          <div class="stepper-step-bar"></div>
          <div class="stepper-step-label">${step5}</div>
        </div>
      </div>

      <p style="font-size:12.5px; color:var(--muted); margin-bottom:12px;">
        ${serviceLabel} <strong>${c.service}</strong> · ${scheduleLabel} <strong>${c.date}</strong> · ${valueLabel} <strong style="font-variant-numeric:tabular-nums;">${formattedPrice}</strong>
      </p>

      <div style="font-size:12px; background:var(--ivory); padding:12px 14px; border-radius:var(--radius-xs); border-left:3px solid var(--gold); white-space:pre-wrap; margin-bottom:14px; line-height:1.5;">${c.notes}</div>

      <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--line); padding-top:12px; flex-wrap:wrap; gap:8px;">
        <span style="font-size:11px; color:var(--muted);">Artisan: <strong>${c.artisan || 'Ibu Siti Khodijah'}</strong> · Atelier Jakarta</span>
        <div style="display:flex; gap:8px;">
          <button class="proto-btn" style="padding:6px 12px; font-size:10.5px; background:var(--sapphire-midnight); color:#fff;" onclick="openWorkOrderModal('${c.id}')">Fiche de Travail</button>
          <button class="btn-outline" style="padding:6px 12px; font-size:10.5px; display:inline-flex; align-items:center; gap:5px;" onclick="openAuthenticityModal(${c.id})"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> ${certBtnText}</button>
        </div>
      </div>
    </article>
  `;
  }).join('');
}

// ==========================================================================
// MANAGEMENT INFORMATION SYSTEM (MSI) MODULES
// Anthony's Framework: TPS, MIS/DSS, EIS, & Information Governance
// ==========================================================================

function switchMsiTab(tabKey) {
  // Check RBAC permissions
  if (store.currentRbacRole === 'artisan' && tabKey !== 'tps') {
    showToast('Akses ditolak: Master Artisan hanya memiliki otorisasi lembar kerja TPS.');
    return;
  }
  if (store.currentRbacRole === 'advisor' && (tabKey === 'eis' || tabKey === 'mis')) {
    showToast('Akses ditolak: Client Advisor hanya berwenang pada modul Operasional & Portal Klien.');
    return;
  }
  if (store.currentRbacRole === 'manager' && tabKey === 'eis') {
    showToast('Akses dibatasi: Kokpit EIS hanya dapat diakses oleh Maison Director.');
    return;
  }

  store.currentMsiTab = tabKey;
  document.querySelectorAll('.msi-tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.msi-pane').forEach(p => p.classList.remove('active'));

  document.getElementById(`msi-tab-btn-${tabKey}`)?.classList.add('active');
  document.getElementById(`msi-pane-${tabKey}`)?.classList.add('active');

  if (tabKey === 'mis') {
    renderMsiWipAndInventory();
  } else if (tabKey === 'eis') {
    renderMsiEisCockpit();
  } else if (tabKey === 'gov') {
    renderAuditTrail();
  }
}

function switchMsiRole(roleKey) {
  store.currentRbacRole = roleKey;
  store.addAuditLog('System Security', roleKey, 'RBAC_SECURITY', `Sesi beralih ke peran: ${roleKey.toUpperCase()}`);
  showToast(`Hak akses atelier disesuaikan: ${roleKey.toUpperCase()}.`);

  // If on restricted tab, fall back to permissible tab
  if (roleKey === 'artisan' && store.currentMsiTab !== 'tps') {
    switchMsiTab('tps');
  } else if (roleKey === 'advisor' && (store.currentMsiTab === 'eis' || store.currentMsiTab === 'mis')) {
    switchMsiTab('tps');
  } else if (roleKey === 'manager' && store.currentMsiTab === 'eis') {
    switchMsiTab('mis');
  }
}

function renderMsiWipAndInventory() {
  // 1. Render WIP Grid (Theory of Constraints)
  const wipContainer = document.getElementById('msi-wip-grid-container');
  if (wipContainer) {
    wipContainer.innerHTML = store.wipStations.map(station => {
      const pct = Math.round((station.current / station.limit) * 100);
      let barColor = '#10b981';
      let statusPill = '<span class="msi-status-pill msi-status-safe">Aman</span>';
      if (pct >= 80) {
        barColor = '#ef4444';
        statusPill = '<span class="msi-status-pill msi-status-danger">Kritis</span>';
      } else if (pct >= 60) {
        barColor = '#f59e0b';
        statusPill = '<span class="msi-status-pill msi-status-warn">Mendekati Batas</span>';
      }

      return `
        <div class="msi-wip-card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
            <strong style="font-size:13px; color:var(--sapphire-midnight);">${station.name}</strong>
            ${statusPill}
          </div>
          <div style="font-size:24px; font-family:var(--serif); font-weight:600; color:var(--sapphire-midnight);">
            ${station.current} <small style="font-size:13px; font-family:var(--sans); color:var(--muted);">/ ${station.limit} maks WIP</small>
          </div>
          <div class="msi-progress-bar">
            <div class="msi-progress-fill" style="width:${pct}%; background:${barColor};"></div>
          </div>
          <div style="font-size:10.5px; color:var(--muted); text-align:right;">Beban: ${pct}% kapasitas</div>
        </div>
      `;
    }).join('');
  }

  // 2. Render Raw Material Inventory Table
  const tbody = document.getElementById('msi-inventory-tbody');
  if (tbody) {
    tbody.innerHTML = store.rawMaterials.map(mat => {
      let statusHtml = '<span class="msi-status-pill msi-status-safe">Aman</span>';
      if (mat.stock <= mat.safetyStock) {
        statusHtml = '<span class="msi-status-pill msi-status-warn">Perlu Reorder</span>';
      }
      return `
        <tr>
          <td><strong style="font-family:monospace; font-size:11px;">${mat.code}</strong></td>
          <td>${mat.name}</td>
          <td style="color:var(--muted);">${mat.origin}</td>
          <td><strong>${mat.stock.toFixed(1)} ${mat.unit}</strong></td>
          <td style="color:var(--muted);">${mat.safetyStock} ${mat.unit}</td>
          <td>${statusHtml}</td>
          <td>
            <button class="btn-outline" style="padding:4px 10px; font-size:10.5px;" onclick="restockMaterialItem('${mat.id}', 10)">+ Restock 10</button>
          </td>
        </tr>
      `;
    }).join('');
  }
}

function restockMaterialItem(matId, qty) {
  store.restockMaterial(matId, qty);
  renderMsiWipAndInventory();
  renderAuditTrail();
  const mat = store.rawMaterials.find(m => m.id === matId);
  showToast(`Pasokan baru dicatat: +${qty} ${mat?.unit || ''} ${mat?.name || ''}`);
}

function restockMaterialPrompt() {
  const mat = store.rawMaterials[1]; // Sutra Lyon
  restockMaterialItem(mat.id, 10);
}

function renderMsiEisCockpit() {
  // Calculate dynamic GMV from concierge orders
  const baseGmv = 58450000;
  const additionalGmv = store.concierge.reduce((sum, c) => {
    return sum + (c.totalPrice || 14900000);
  }, 0);
  const totalGmv = baseGmv + additionalGmv;
  const aov = Math.round(totalGmv / (4 + store.concierge.length));

  const gmvEl = document.getElementById('eis-gmv-val');
  const aovEl = document.getElementById('eis-aov-val');
  if (gmvEl) gmvEl.textContent = formatRupiah(totalGmv);
  if (aovEl) aovEl.textContent = formatRupiah(aov);
}

function renderAuditTrail() {
  const tbody = document.getElementById('msi-audit-tbody');
  if (!tbody) return;

  tbody.innerHTML = store.auditLogs.map(log => `
    <tr>
      <td style="white-space:nowrap; font-size:11px; color:var(--muted);">${log.timestamp}</td>
      <td><strong>${log.actor}</strong></td>
      <td><span class="badge-status badge-confirmed" style="text-transform:uppercase; font-size:10px;">${log.role}</span></td>
      <td><strong style="font-family:monospace; font-size:11px; color:#0369a1;">${log.module}</strong></td>
      <td style="font-size:12px;">${log.action}</td>
      <td><span class="hash-pill">${log.hash.substring(0, 16)}...</span></td>
    </tr>
  `).join('');
}

function clearAuditLogDemo() {
  store.auditLogs = INITIAL_AUDIT_LOGS.slice(0, 2);
  store.save('zafira_v3_audit_logs', store.auditLogs);
  renderAuditTrail();
  showToast('Audit log berhasil di-reset untuk simulasi pengujian.');
}

function openAuthenticityModal(orderId) {
  const item = store.concierge.find(c => c.id === orderId);
  if (!item) return;

  const serial = item.serial || `ZFR-2026-${orderId.toString().padStart(4, '0')}`;
  const artisan = item.artisan || 'Madame Hélène & Ibu Siti Khodijah';
  const container = document.getElementById('authenticity-modal-content');

  if (container) {
    container.innerHTML = `
      <div class="cert-card">
        <div class="cert-seal-gold">Z</div>
        <div style="text-align:center; margin-bottom:24px;">
          <span style="font-size:10.5px; letter-spacing:0.25em; text-transform:uppercase; color:var(--gold); font-weight:700;">Maison de Haute Joaillerie & Adibusana</span>
          <h2 style="font-family:var(--serif); font-size:26px; color:var(--sapphire-midnight); margin:6px 0 4px 0;">Certificat d’Authenticité et de Kriya</h2>
          <p style="font-size:12px; color:var(--taupe); font-style:italic;">Bukti Resmi Asal-Usul & Pengerjaan Tangan Empu Kriya ZAFIRA</p>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:var(--radius-xs); padding:20px 24px; margin-bottom:20px; font-size:12.5px;">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px;">
            <div>
              <span style="color:var(--muted); font-size:11px; display:block;">Nomor Seri Koleksi (Batch Lot):</span>
              <strong style="font-family:monospace; font-size:14px; color:var(--sapphire-midnight);">${serial}</strong>
            </div>
            <div>
              <span style="color:var(--muted); font-size:11px; display:block;">Pemilik Terdaftar (VIC Client):</span>
              <strong style="color:var(--sapphire-midnight);">${item.name}</strong>
            </div>
          </div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:12px; border-top:1px solid #f1f5f9; padding-top:10px;">
            <div>
              <span style="color:var(--muted); font-size:11px; display:block;">Kreasi Adibusana:</span>
              <strong>${item.service}</strong>
            </div>
            <div>
              <span style="color:var(--muted); font-size:11px; display:block;">Master Artisan Penanggung Jawab:</span>
              <strong style="color:var(--gold);">${artisan}</strong>
            </div>
          </div>
          <div style="border-top:1px solid #f1f5f9; padding-top:10px;">
            <span style="color:var(--muted); font-size:11px; display:block;">Spesifikasi & Detail Pesanan:</span>
            <p style="color:var(--sapphire-midnight); font-size:12px; margin-top:4px; white-space:pre-wrap;">${item.notes}</p>
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--muted); border-top:1px solid rgba(197,160,89,0.3); padding-top:16px;">
          <div>
            <span>Verifikasi Atelier: <strong>Atelier Kebayoran Baru, Jakarta</strong></span><br>
            <span>Standard: <strong>Haute Horlogerie & Couture Compliance (ISO/IEC 27001)</strong></span>
          </div>
          <div style="text-align:right;">
            <span style="font-family:monospace; font-size:10px; color:#475569;">Hash Kriptografis: SHA-256</span><br>
            <span class="hash-pill">9a8f7c2b...verified</span>
          </div>
        </div>
      </div>
    `;
  }

  document.getElementById('authenticity-modal')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

// ==========================================================================
// VIEW SWITCHER & NAVIGATION
// ==========================================================================

function switchView(viewName) {
  store.currentView = viewName;
  document.querySelectorAll('.view-container').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.dock-btn, .proto-btn').forEach(el => el.classList.remove('active'));

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
    renderMsiWipAndInventory();
    renderMsiEisCockpit();
    renderAuditTrail();
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
  document.getElementById('drawer-backdrop')?.classList.remove('open');
  document.getElementById('cart-drawer')?.classList.remove('open');
  document.getElementById('profile-drawer')?.classList.remove('open');
  document.getElementById('mobile-nav-drawer')?.classList.remove('open');
}

function toggleDockCollapse() {
  store.isDockCollapsed = !store.isDockCollapsed;
  const dock = document.querySelector('.floating-luxury-dock');
  const btn = document.getElementById('dock-collapse-btn');
  if (dock) {
    dock.classList.toggle('dock-collapsed', store.isDockCollapsed);
    if (btn) {
      btn.innerHTML = store.isDockCollapsed
        ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
        : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>';
      btn.title = store.isDockCollapsed ? 'Buka Menu Navigasi Prototipe' : 'Kecilkan Menu Prototipe';
    }
  }
  playAtelierSound('click');
}

function rebalanceWipWorkload() {
  const station = store.wipStations.find(w => w.id === 'wip-3');
  if (station) {
    station.current = 2;
    station.status = 'optimal';
    store.save('zafira_v3_5_wip', store.wipStations);
    store.addAuditLog('Atelier Manager (Budi)', store.currentRbacRole, 'MIS_DSS_TOC', 'Beban stasiun bordir dialokasikan ke Master Artisan Ibu Siti Khodijah (WIP dioptimalkan: 2/4)');
    renderMsiWipAndInventory();
    renderAuditTrail();
    const alertBox = document.getElementById('msi-bottleneck-alert');
    if (alertBox) {
      alertBox.style.background = '#f0fdf4';
      alertBox.style.borderColor = '#86efac';
      alertBox.innerHTML = `
        <div>
          <strong style="color:#166534; font-size:13px; display:inline-flex; align-items:center; gap:6px;"><span class="indicator-dot" style="background:#166534;"></span> Beban Kerja Stasiun Bordir Berhasil Diseimbangkan (50% WIP)</strong>
          <p style="font-size:11.5px; color:#14532d; margin-top:4px;">Pesanan dialokasikan ke Master Artisan Ibu Siti Khodijah. Estimasi deviasi SLA: 0.0%.</p>
        </div>
        <span style="font-size:11px; font-weight:700; color:#166534; background:#dcfce7; padding:4px 12px; border-radius:12px;">Optimal</span>
      `;
    }
    playAtelierSound('chime');
    showToast('DSS: Kapasitas kriya berhasil diseimbangkan ke master artisan.');
  }
}

function openWorkOrderModal(orderId) {
  const order = store.concierge.find(c => c.id === orderId);
  if (!order) return;
  const container = document.getElementById('work-order-modal-content');
  if (container) {
    container.innerHTML = `
      <div style="border:1px solid #0f1c33; padding:24px; background:#fffdfa;">
        <div style="display:flex; justify-content:space-between; border-bottom:2px solid #0f1c33; padding-bottom:12px; margin-bottom:16px;">
          <div>
            <h2 style="font-family:var(--serif); font-size:22px; color:#0f1c33; margin:0;">ZAFIRA ATELIER · FICHE DE TRAVAIL</h2>
            <span style="font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:#b89858; font-weight:700;">Lembar Instruksi Kriya Haute Couture No. WO-${order.id}</span>
          </div>
          <div style="text-align:right;">
            <strong style="font-size:14px; font-family:monospace; color:#0f1c33;">${order.serial || 'ZFR-2026-' + order.id}</strong><br>
            <small style="color:var(--muted); font-size:10px;">Tanggal Terbit: ${order.date}</small>
          </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; font-size:12px;">
          <div style="background:#f8fafc; padding:12px; border-radius:2px; border:1px solid #e2e8f0;">
            <strong style="color:var(--sapphire-midnight);">Klien / Pemesan:</strong><br>
            ${order.name}<br>
            <span style="color:var(--muted);">${order.phone}</span>
          </div>
          <div style="background:#f8fafc; padding:12px; border-radius:2px; border:1px solid #e2e8f0;">
            <strong style="color:var(--sapphire-midnight);">Master Artisan Bertugas:</strong><br>
            <span style="color:#b89858; font-weight:700;">${order.artisan || 'Ibu Siti Khodijah (Senior Artisan)'}</span><br>
            <small style="color:var(--muted);">Supervisi: Direktur Kreatif ZAFIRA</small>
          </div>
        </div>

        <!-- Douglas Engelbart Compound High-Bandwidth Display: Technical Blueprint -->
        <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:14px; margin-bottom:16px;">
          <div style="background:#091528; color:#93c5fd; padding:12px; border-radius:var(--radius-xs); font-family:'JetBrains Mono', monospace; font-size:10px; border:1px solid #1e3a8a;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#fde047; font-weight:700;">
              <span style="display:inline-flex; align-items:center; gap:6px;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20"/></svg>
                SCHÉMA DE PATRONAGE · BLUEPRINT KRIYA
              </span>
              <span>ÉCHELLE 1:10</span>
            </div>
            <svg width="100%" height="82" viewBox="0 0 240 82" style="background:#050c18; border:1px dashed #3b82f6; border-radius:2px;">
              <path d="M 25 22 L 65 12 L 105 22 L 95 72 L 35 72 Z" fill="none" stroke="#60a5fa" stroke-width="1.5" stroke-dasharray="3,2"/>
              <path d="M 135 12 L 175 22 L 215 12 L 205 72 L 145 72 Z" fill="none" stroke="#60a5fa" stroke-width="1.5"/>
              <circle cx="65" cy="12" r="3" fill="#fbbf24"/>
              <circle cx="175" cy="22" r="3" fill="#fbbf24"/>
              <text x="38" y="47" fill="#93c5fd" font-size="8">Dada: 92cm</text>
              <text x="148" y="47" fill="#93c5fd" font-size="8">Pinggang: 74cm</text>
            </svg>
            <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:9px; color:#cbd5e1;">
              <span>Toleransi Presisi: 1.5mm</span>
              <span>Jahitan: French Seam 18 SPI</span>
            </div>
          </div>

          <div style="background:#f8fafc; padding:12px; border-radius:var(--radius-xs); border:1px solid #e2e8f0; font-size:11px;">
            <strong style="color:var(--sapphire-midnight);">Spesifikasi Benang & Kain:</strong>
            <ul style="margin:6px 0 0 16px; padding:0; line-height:1.6; color:#334155;">
              <li>Benang Jahit: Sutra Gutermann Mara No. 120</li>
              <li>Benang Bordir: Metalik Emas 18K Madeira</li>
              <li>Batik: Malam Dingin Solo, 9x Celupan Alami</li>
              <li>Kemasan: Signature Midnight Box + Or Ribbon</li>
            </ul>
          </div>
        </div>

        <div style="border:1px solid #e2e8f0; padding:14px; margin-bottom:16px; font-size:12px; background:#fff; border-left:3px solid var(--gold);">
          <strong style="color:var(--sapphire-midnight);">Instruksi Pengerjaan & Spesifikasi Khusus Klien:</strong>
          <p style="white-space:pre-wrap; margin-top:6px; color:#1e293b; line-height:1.6;">${order.notes}</p>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:flex-end; border-top:1px dashed #cbd5e1; padding-top:16px; font-size:11px; color:#64748b;">
          <div>
            <span>Standar QC: Nol Cacat Jahit Rolled-Hem, Benang Bebas Tarik, Segel Lilin Sah</span><br>
            <span>Tanda Tangan Pengrajin: ________________________</span>
          </div>
          <div>
            <button class="btn-gold" style="padding:8px 16px; font-size:11px; display:inline-flex; align-items:center; gap:6px;" onclick="window.print(); showToast('Mencetak Lembar Kerja Kriya...')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
              Cetak Lembar Kerja
            </button>
          </div>
        </div>
      </div>
    `;
  }
  document.getElementById('work-order-modal')?.classList.add('open');
  document.getElementById('drawer-backdrop')?.classList.add('open');
}

// ==========================================================================
// INITIALIZATION & SENSORY INTERACTION
// ==========================================================================

// Floating Dock Scroll Intelligence (Auto-Tuck on Scroll Down, Reveal on Scroll Up / Hover)
let lastScrollY = window.scrollY;
let isDockHovered = false;

window.addEventListener('scroll', (e) => {
  const dock = document.querySelector('.floating-luxury-dock');
  if (!dock || store.isDockCollapsed) return;
  let currentScrollY = window.scrollY;
  if (e.target && e.target.scrollTop !== undefined && e.target !== document) {
    currentScrollY = e.target.scrollTop;
  }
  if (currentScrollY > 70 && currentScrollY > lastScrollY && !isDockHovered) {
    dock.classList.add('dock-tucked');
  } else if (currentScrollY < lastScrollY || currentScrollY <= 70) {
    dock.classList.remove('dock-tucked');
  }
  lastScrollY = currentScrollY;
}, { passive: true, capture: true });

function openKeyboardShortcutsModal() {
  closeAllModals();
  const modal = document.getElementById('keyboard-shortcuts-modal');
  if (modal) {
    modal.classList.add('open');
    document.getElementById('drawer-backdrop')?.classList.add('open');
    playAtelierSound('click');
  }
}
window.openKeyboardShortcutsModal = openKeyboardShortcutsModal;

document.addEventListener('DOMContentLoaded', () => {
  renderCatalog();
  renderCartDrawer();
  renderKanbanBoard();
  renderAccountPortal();
  renderMsiWipAndInventory();
  renderMsiEisCockpit();
  renderAuditTrail();

  // Initialize currency pills
  document.querySelectorAll('.curr-opt').forEach(el => {
    el.classList.toggle('active', el.dataset.curr === (store.currency || 'IDR'));
  });

  // Live Re-rendering on Language and Theme Changes (HCI Nielsen Heuristic 1 & Cooper)
  window.addEventListener('zafira:lang-changed', (e) => {
    const lang = (e.detail && e.detail.lang) || localStorage.getItem('zafira-lang') || 'id';

    // Automatically harmonize default currency with standard international luxury practice
    if (lang === 'fr' || lang === 'it') {
      store.currency = 'EUR';
    } else if (lang === 'en') {
      store.currency = 'USD';
    } else {
      store.currency = 'IDR';
    }
    store.save('zafira_currency', store.currency);

    document.querySelectorAll('.curr-opt').forEach(el => {
      el.classList.toggle('active', el.dataset.curr === store.currency);
    });

    // Re-render currently active view
    if (store.currentView === 'storefront') {
      renderCatalog();
    } else if (store.currentView === 'studio') {
      const activeId = store.activeStudioProduct ? store.activeStudioProduct.id : 9;
      openProductStudio(activeId);
    } else if (store.currentView === 'admin') {
      renderKanbanBoard();
      renderMsiWipAndInventory();
      renderMsiEisCockpit();
    } else if (store.currentView === 'account') {
      renderAccountPortal();
    }
    renderCartDrawer();
  });

  // Handle URL Hash or Query Routing (Bridge between Storefront & Prototype)
  const hash = window.location.hash;
  const urlParams = new URLSearchParams(window.location.search);
  const requestedProductId = urlParams.get('p');
  if (requestedProductId) {
    const numericId = Number(requestedProductId);
    const product = store.products.find((item) => item.slug === requestedProductId || item.id === numericId);
    if (product) openProductStudio(product.id);
  } else if (hash === '#msi-view' || hash === '#admin') {
    switchView('admin');
  } else if (hash === '#studio-view' || hash === '#studio') {
    openProductStudio(9);
  } else if (hash === '#account-view' || hash === '#account') {
    switchView('account');
  }

  // Dock hover listeners for auto-tuck
  const dock = document.querySelector('.floating-luxury-dock');
  if (dock) {
    dock.addEventListener('mouseenter', () => {
      isDockHovered = true;
      dock.classList.remove('dock-tucked');
    });
    dock.addEventListener('mouseleave', () => {
      isDockHovered = false;
    });
  }

  // Search input handler
  const searchInput = document.getElementById('sf-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      store.searchQuery = e.target.value;
      renderCatalog();
    });
  }

  // Keyboard accessibility & Power User Accelerators (Shneiderman & Nielsen)
  document.addEventListener('keydown', (e) => {
    // Universal Emergency Exit (Escape)
    if (e.key === 'Escape' || e.key === 'Esc') {
      closeAllModals();
      return;
    }

    // Do not trigger hotkeys if user is currently typing in an input, textarea, or select
    const activeEl = document.activeElement;
    const tagName = activeEl ? activeEl.tagName.toLowerCase() : '';
    if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
      return;
    }

    if (e.key === '?' || (e.shiftKey && e.key === '/')) {
      e.preventDefault();
      const modal = document.getElementById('keyboard-shortcuts-modal');
      if (modal && modal.classList.contains('open')) {
        closeAllModals();
      } else {
        openKeyboardShortcutsModal();
      }
    } else if (e.key === 's' || e.key === 'S') {
      switchView('storefront');
      showToast('Navigasi Cepat: Storefront Koleksi');
    } else if (e.key === 'p' || e.key === 'P') {
      openProductStudio(9);
      showToast('Navigasi Cepat: Studio Kriya (Kebaya Lumière)');
    } else if (e.key === 'c' || e.key === 'C') {
      openCartDrawer();
    } else if (e.key === 'a' || e.key === 'A') {
      switchView('admin');
      showToast('Navigasi Cepat: Atelier Command (MSI)');
    } else if (e.key === 'u' || e.key === 'U') {
      openProfileDrawer();
      showToast('Navigasi Cepat: Profil Klien VIP & Salon');
    } else if (e.key === '/') {
      e.preventDefault();
      const sInput = document.getElementById('sf-search-input');
      if (sInput) {
        if (store.activeView !== 'storefront') switchView('storefront');
        sInput.focus();
        sInput.select();
      }
    }
  });

  // Global window exports for inline HTML onclick handlers
  window.openProfileDrawer = openProfileDrawer;
  window.closeProfileDrawer = closeProfileDrawer;
  window.openMobileNavDrawer = openMobileNavDrawer;
  window.closeMobileNavDrawer = closeMobileNavDrawer;
});
