/**
 * ZAFIRA — La Maison de Lumière
 * Core Multilanguage (ID, EN, FR, IT, ZH) & Theme Engine (Light, Dark, System)
 * High-End Quiet Luxury Architecture & Persistent Session Synchronization
 */
(function () {
    "use strict";

    // --- 1. COMPREHENSIVE LITERARY COUTURE TRANSLATION DICTIONARY ---
    const I18N = {
        zh: {
            announcement: "探索 ZAFIRA 系列",
            rendezvous_btn: "私人预约",
            nav_collection: "系列",
            nav_arrivals: "新品",
            nav_heritage: "传承",
            nav_atelier: "Atelier Z",
            nav_packaging: "L'Art d'Offrir",
            nav_services: "服务",
            nav_digital_atelier: "数字工坊",
            search_placeholder: "搜索…",
            search_aria: "搜索",
            wishlist_aria: "收藏",
            cart_aria: "购物袋",
            dock_storefront: "精品店",
            dock_studio: "作品工作室",
            dock_account: "贵宾账户",
            dock_atelier: "工坊",
            hero_eyebrow: "La Collection Première · 2026",
            hero_title: "诞生于光，<br>塑造于优雅",
            hero_subtitle: "巴黎的优雅与意大利的匠艺相遇，每件作品都映照您的气质。",
            hero_cta_explore: "探索系列",
            hero_cta_studio: "工艺与匠心工作室",
            hero_scroll: "探索",
            marquee_1: "L’Esprit Parisien",
            marquee_2: "La Mano Italiana",
            marquee_3: "群岛之魂",
            marquee_4: "源自雅加达",
            editorial_eyebrow: "Le Journal · 胶囊系列",
            editorial_title: "宋吉锦与丝绸，<br>Deux Rives.",
            editorial_desc: "巨港与里昂在披肩与礼服中相遇——木织机、金线与天然染料。",
            editorial_cta: "认识工匠",
            category_eyebrow: "L’Univers ZAFIRA",
            category_title: "发现您的世界",
            category_desc: "每个系列都是传统与现代之间的对话。",
            products_eyebrow: "Les Nouveautés",
            products_title: "令人心动的作品",
            products_count_suffix: "件作品",
            filter_all: "全部",
            filter_women: "女装",
            filter_men: "男装",
            filter_bags: "手袋",
            filter_shoes: "鞋履",
            filter_jewelry: "珠宝",
            filter_heritage: "群岛传承",
            filter_capsule: "胶囊系列",
            card_quick_view: "快速查看",
            card_add: "添加",
            card_studio_link: "Studio Z",
            cat_all: "全部系列",
            cat_nusantara: "群岛传承",
            cat_capsule: "高级定制胶囊系列",
            cat_women: "女装",
            cat_men: "男士大衣",
            cat_bags: "晚宴手袋",
            cat_shoes: "皮鞋",
            cat_scarves: "丝巾",
            maison_eyebrow: "L’Histoire de la Maison",
            maison_title: "如蓝宝石一般，<br>真正的美<br>源于淬炼。",
            maison_body: "ZAFIRA 相信，奢华无需喧哗。它存在于精准的针脚、材质的触感，以及离开房间后依然留存的自信。",
            maison_link: "Maison 的故事",
            heritage_eyebrow: "Trois Héritages · 三重传承",
            heritage_title: "三片土地，一座 Maison",
            heritage_subtitle: "巴黎赋予轮廓，意大利赋予匠艺，印度尼西亚赋予灵魂与材质。",
            heritage_1_title: "L’Esprit Parisien",
            heritage_1_desc: "轮廓、垂坠与从容的勇气——设计于巴黎，适合每一处风景。",
            heritage_1_proof: "里昂与比耶拉面料 · 斜裁工艺",
            heritage_2_title: "La Mano Italiana",
            heritage_2_desc: "圣克罗切皮革、马鞍缝线与布雷克缝制鞋履——可换底的匠心。",
            heritage_2_proof: "托斯卡纳与马尔凯皮革 · 手工修饰",
            heritage_3_title: "群岛之魂",
            heritage_3_desc: "梭罗手绘蜡染、巨港宋吉锦、弗洛勒斯织物与克巴约兰刺绣——鲜活的传承，而非装饰。",
            heritage_3_proof: "天然染料 · 木织机 · 公平计件报酬",
            atelier_eyebrow: "Atelier Z · 个性化定制",
            atelier_title: "让它成为<br>独属于您的作品。",
            atelier_desc: "灵感来自欧洲时装屋的定制服务，Atelier Z 为精选皮具提供字母刺绣。费用请咨询礼宾。",
            atelier_step_1: "选择最多三个大写字母",
            atelier_step_2: "选择金色、银色或蓝宝石色箔",
            atelier_step_3: "ZAFIRA 工匠手工刺绣",
            atelier_input_label: "您的姓名缩写",
            atelier_foil_label: "箔色",
            atelier_cta: "定制您的作品",
            packaging_eyebrow: "L'Art d'Offrir ZAFIRA",
            packaging_title: "经典礼盒与金色蜡封",
            packaging_desc: "每件作品均装入手工制作的深蓝宝石色硬盒，系上里昂斜纹丝带，并附有盖着 Maison ZAFIRA 胭脂金色蜡封的书法卡。",
            packaging_ribbon_label: "丝带选择：",
            packaging_ribbon_gold: "金色丝带",
            packaging_ribbon_sapphire: "蓝宝石色丝带",
            packaging_complimentary: "图示包装仅供参考；选项与费用请咨询礼宾。",
            services_eyebrow: "Les Services ZAFIRA",
            services_title: "不止一件作品，<br>更是一段体验。",
            service_1_title: "私人造型",
            service_1_desc: "联系礼宾顾问。",
            service_1_link: "安排预约",
            service_2_title: "公开透明",
            service_2_desc: "每件作品均注明材质、成分、产地与制作者。",
            service_2_link: "查看商品详情",
            service_3_title: "护理指南",
            service_3_desc: "可用的护理说明见商品页面；如有疑问请联系礼宾。",
            service_3_link: "联系礼宾顾问",
            service_4_title: "工艺卡",
            service_4_desc: "商品信息。",
            service_4_link: "查看详情",
            newsletter_eyebrow: "La Lettre ZAFIRA",
            newsletter_title: "走进 Maison 的世界。",
            newsletter_desc: "接收工坊故事、私人邀请，率先探索最新系列。",
            newsletter_placeholder: "您的电子邮箱…",
            newsletter_privacy: "订阅即表示您同意 ZAFIRA 隐私政策。",
            footer_motto: "巴黎的精神，意大利的匠艺，印度尼西亚的心。",
            footer_col_collection: "系列",
            footer_women: "女士",
            footer_men: "男士",
            footer_bags: "手袋",
            footer_accessories: "配饰",
            footer_col_maison: "La Maison",
            footer_story: "我们的故事",
            footer_atelier_z: "Atelier Z",
            footer_services: "服务",
            footer_rendezvous: "Rendez-vous",
            footer_account_portal: "贵宾客户门户",
            footer_savoir_title: "Savoir-Faire",
            savoir_1: "克巴约兰手工刺绣",
            savoir_2: "梭罗蜡染工艺",
            savoir_3: "弗洛勒斯毛梅雷伊卡特织物",
            savoir_4: "法国里昂欧根纱",
            newsletter_submit: "订阅",
            footer_col_support: "帮助",
            footer_contact: "联系我们",
            footer_shipping: "配送与退货",
            footer_care: "产品保养",
            footer_sizing: "尺寸指南",
            footer_legal_rights: "© 2026 ZAFIRA MAISON. 保留所有权利。",
            footer_privacy: "隐私",
            footer_terms: "条款",
            shortcuts_trigger: "快捷键（?）",
            footer_lang_label: "地区与语言",
            footer_theme_label: "外观设置",
            studio_back_btn: "返回浏览",
            studio_cart_btn: "购物袋",
            studio_tab_silhouette: "轮廓",
            studio_tab_material: "材质",
            studio_tab_savoir_faire: "Savoir-Faire",
            studio_tab_care: "保养",
            studio_label_composition: "成分：",
            studio_label_provenance: "产地：",
            studio_label_craft: "手工技艺：",
            studio_label_care: "服装保养：",
            studio_size_label: "工坊尺寸选择",
            studio_size_guide: "尺寸指南",
            studio_monogram_title: "金色字母刺绣定制",
            studio_packaging_desc: "蓝宝石色硬盒、里昂丝带与红色蜡封。",
            studio_add_bag_btn: "加入购物袋",
            studio_book_fitting_btn: "预约工坊私人试衣",
            account_subtitle: "ZAFIRA 客户空间",
            account_greeting_prefix: "您好，",
            account_back_btn: "返回浏览",
            account_profile_title: "贵宾客户资料",
            account_name_label: "姓名",
            account_email_label: "注册邮箱",
            account_phone_label: "WhatsApp 号码",
            account_address_label: "配送地址",
            account_orders_title: "预约与高级定制订单记录",
            account_orders_desc: "礼宾请求状态。",
            cart_header_title: "您的购物袋",
            cart_empty_title: "您的购物袋为空",
            cart_empty_desc: "我们的系列静候您的探索。",
            cart_empty_cta: "探索系列",
            cart_service_note: "配送安排与包装信息以礼宾确认为准",
            cart_total_label: "作品总额",
            cart_checkout_btn: "继续预约与下单",
            cart_shipping_calc: "配送费将在下一步计算。",
            shortcuts_title: "ZAFIRA 键盘快捷键",
            shortcuts_desc: "使用快捷键，高效浏览数字沙龙的各个空间。",
            shortcuts_close: "关闭指南",
            cart_remove: "移除",
            cart_size: "尺寸：",
            cart_monogram: "字母刺绣：",
            account_service_label: "服务：",
            account_schedule_label: "时间：",
            account_value_label: "金额：",
            toast_cart_added_title: "作品“{title}”（{size}）已加入购物袋。",
            toast_cart_removed: "“{title}”已从购物袋移除。",
            toast_cart_undo_btn: "撤销",
            toast_cart_restored: "作品“{title}”已放回购物袋。",
            toast_wax_sealed: "包装预览已更新。",
            toast_rendezvous_registered: "模拟：预约请求仅记录在本地，未保存至服务器。",
            toast_ribbon_updated: "包装丝带已更换为：{ribbon}。",
            nav_profile: "资料",
            profile_drawer_eyebrow: "贵宾客户空间 · Maison ZAFIRA",
            profile_drawer_title: "资料与沙龙偏好",
            profile_action_fitting: "预约私人试衣",
            profile_action_orders: "高级定制订单记录",
            profile_recent_order_title: "进行中的工艺订单",
            profile_monogram_pref: "字母刺绣缩写",
            profile_settings_title: "沙龙偏好与设置",
            profile_theme_label: "外观模式",
            profile_theme_desc: "选择展厅的视觉氛围",
            profile_lang_label: "地区与语言",
            profile_lang_desc: "语言与国际货币设置",
            profile_sound_label: "工坊音效",
            profile_sound_desc: "交互时播放柔和的提示音",
            profile_full_portal: "打开客户空间 →",
            profile_msi_link: "工坊",
            theme_light: "浅色模式",
            theme_dark: "深色模式",
            theme_system: "跟随系统",
            lang_selector: "语言",
        },
        id: {
            // Utilities & Navigation
            announcement:
                "Jelajahi koleksi ZAFIRA",
            rendezvous_btn: "Rendez-vous privat",
            nav_collection: "Koleksi",
            nav_arrivals: "Terbaru",
            nav_heritage: "Warisan",
            nav_atelier: "Atelier Z",
            nav_packaging: "L'Art d'Offrir",
            nav_services: "Layanan",
            nav_digital_atelier: "Atelier Digital",
            search_placeholder: "Cari…",
            search_aria: "Cari",
            wishlist_aria: "Favorit",
            cart_aria: "Tas Belanja",
            dock_storefront: "Boutique",
            dock_studio: "Studio PDP",
            dock_account: "Akun VIP",
            dock_atelier: "Atelier",

            // Hero & Editorial
            hero_eyebrow: "La Collection Première · 2026",
            hero_title: "Lahir dari Cahaya,<br>Dibentuk oleh Keanggunan",
            hero_subtitle:
                "Keanggunan Paris bertemu keahlian Italia dalam setiap kreasi yang memancarkan jiwa Anda.",
            hero_cta_explore: "Jelajahi koleksi",
            hero_cta_studio: "Studio Kriya & Savoir-Faire",
            hero_scroll: "Découvrir",
            marquee_1: "L’Esprit Parisien",
            marquee_2: "La Mano Italiana",
            marquee_3: "Jiwa Nusantara",
            marquee_4: "Depuis Jakarta",
            editorial_eyebrow: "Le Journal · Koleksi Kapsul",
            editorial_title: "Songket &amp; Sutra,<br>Deux Rives.",
            editorial_desc:
                "Palembang bertemu Lyon dalam selendang dan gaun kapsul — tenun kayu, benang emas, pewarna alam.",
            editorial_cta: "Temui pengrajin",

            // Catalog & Filters
            category_eyebrow: "L’Univers ZAFIRA",
            category_title: "Temukan dunia Anda",
            category_desc:
                "Setiap koleksi dirancang sebagai percakapan antara warisan dan modernitas.",
            products_eyebrow: "Les Nouveautés",
            products_title: "Objek-objek penuh pesona",
            products_count_suffix: "kreasi",
            filter_all: "Semua",
            filter_women: "Busana Wanita",
            filter_men: "Busana Pria",
            filter_bags: "Tas Tangan",
            filter_shoes: "Sepatu",
            filter_jewelry: "Perhiasan",
            filter_heritage: "Warisan Nusantara",
            filter_capsule: "Koleksi Kapsul",
            card_quick_view: "Lihat cepat",
            card_add: "Tambah",
            card_studio_link: "Studio Z",

            // Categories
            cat_all: "Semua Koleksi",
            cat_nusantara: "Warisan Nusantara",
            cat_capsule: "Kapsul Adibusana",
            cat_women: "Busana Wanita",
            cat_men: "Mantel Pria",
            cat_bags: "Tas Malam",
            cat_shoes: "Sepatu Kulit",
            cat_scarves: "Syal Sutra",

            // Heritage & Maison
            maison_eyebrow: "L’Histoire de la Maison",
            maison_title:
                "Seperti safir,<br>keindahan sejati<br>lahir dalam proses.",
            maison_body:
                "ZAFIRA lahir dari keyakinan bahwa kemewahan tidak perlu bersuara keras. Ia hadir dalam ketepatan jahitan, sentuhan material, dan rasa percaya diri yang tertinggal lama setelah Anda meninggalkan ruangan.",
            maison_link: "Kisah Maison",
            heritage_eyebrow: "Trois Héritages · Tiga Warisan",
            heritage_title: "Tiga tanah, satu Maison",
            heritage_subtitle:
                "Paris memberi siluet. Italia memberi tangan. Indonesia memberi jiwa dan materialnya.",
            heritage_1_title: "L’Esprit Parisien",
            heritage_1_desc:
                "Siluet, drapery, dan keberanian tampil tenang — digambar di Paris, dikenakan di mana saja.",
            heritage_1_proof: "Kain Lyon & Biella · potongan bias-cut",
            heritage_2_title: "La Mano Italiana",
            heritage_2_desc:
                "Kulit Santa Croce, jahitan saddle-stitch, sepatu Blake-stitched — keahlian yang bisa disol ulang.",
            heritage_2_proof: "Kulit Toskana & Marche · finishing tangan",
            heritage_3_title: "Jiwa Nusantara",
            heritage_3_desc:
                "Batik tulis Solo, songket Palembang, tenun Flores, bordir Kebayoran — warisan hidup, bukan ornamen.",
            heritage_3_proof: "Pewarna alam · tenun kayu · borongan adil",

            // Atelier Z Customizer
            atelier_eyebrow: "Atelier Z · Personnalisation",
            atelier_title: "Jadikan ia milik Anda,<br>sepenuhnya.",
            atelier_desc:
                "Terinspirasi layanan bespoke rumah mode Eropa, Atelier Z menghadirkan monogram pribadi pada kreasi kulit pilihan. Tanyakan biaya kepada concierge.",
            atelier_step_1: "Pilih hingga tiga inisial huruf kapital",
            atelier_step_2: "Tentukan nuansa foil emas, perak, atau safir",
            atelier_step_3: "Bordir tangan oleh artisan ZAFIRA",
            atelier_input_label: "Inisial Anda",
            atelier_foil_label: "Warna Foil",
            atelier_cta: "Personalisasi Kreasi Anda",

            // Packaging
            packaging_eyebrow: "L'Art d'Offrir ZAFIRA",
            packaging_title: "Kemasan Kado Ikonik & Segel Lilin Emas",
            packaging_desc:
                "Setiap kreasi dihantarkan dalam kotak kaku safir pekat yang dibuat dengan tangan, diikat pita sutra twill Lyon, dan dilindungi kartu kaligrafi bertanda stempel segel lilin carmine-emas Maison ZAFIRA.",
            packaging_ribbon_label: "Pilihan Pita Sutra:",
            packaging_ribbon_gold: "Pita Emas",
            packaging_ribbon_sapphire: "Pita Safir",
            packaging_complimentary:
                "Tampilan kemasan adalah ilustrasi; tanyakan pilihan dan biaya kepada concierge.",

            // Services & Telemetry
            services_eyebrow: "Les Services ZAFIRA",
            services_title: "Lebih dari sebuah kreasi,<br>sebuah pengalaman.",
            service_1_title: "Private Styling",
            service_1_desc: "Hubungi concierge.",
            service_1_link: "Atur rendez-vous",
            service_2_title: "Transparansi Penuh",
            service_2_desc:
                "Setiap kreasi mencantumkan material, komposisi, asal, dan siapa yang mengerjakannya.",
            service_2_link: "Lihat di halaman produk",
            service_3_title: "Panduan Perawatan",
            service_3_desc:
                "Petunjuk perawatan tercantum di halaman produk; concierge membantu bila Anda ragu.",
            service_3_link: "Hubungi concierge",
            service_4_title: "Kartu Kriya",
            service_4_desc: "Informasi produk.",
            service_4_link: "Lihat detail",

            // Newsletter & Footer
            newsletter_eyebrow: "La Lettre ZAFIRA",
            newsletter_title: "Masuk ke dunia Maison.",
            newsletter_desc:
                "Terima cerita atelier, undangan privat, dan akses pertama ke koleksi terbaru.",
            newsletter_placeholder: "nama @ contoh.id…",
            newsletter_privacy:
                "Dengan berlangganan, Anda menyetujui kebijakan privasi ZAFIRA.",
            footer_motto:
                "Parisian dalam jiwa. Italia dalam kriya. Indonesia di hati.",
            footer_col_collection: "Koleksi",
            footer_women: "Wanita",
            footer_men: "Pria",
            footer_bags: "Tas Tangan",
            footer_accessories: "Aksesori",
            footer_col_maison: "La Maison",
            footer_story: "Kisah Kami",
            footer_atelier_z: "Atelier Z",
            footer_services: "Layanan",
            footer_rendezvous: "Rendez-vous",
            footer_account_portal: "Portal Klien VIP",
            footer_savoir_title: "Savoir-Faire",
            savoir_1: "Bordir Tangan Kebayoran",
            savoir_2: "Batik Canting Malam Solo",
            savoir_3: "Tenun Ikat Maumere Flores",
            savoir_4: "Sutra Organza Lyon Prancis",
            newsletter_submit: "Daftar",
            footer_col_support: "Bantuan",
            footer_contact: "Hubungi Kami",
            footer_shipping: "Pengiriman & Retur",
            footer_care: "Perawatan Produk",
            footer_sizing: "Panduan Ukuran",
            footer_legal_rights:
                "© 2026 ZAFIRA MAISON. SELURUH HAK DILINDUNGI.",
            footer_privacy: "Privasi",
            footer_terms: "Ketentuan",
            shortcuts_trigger: "Pintasan (?)",
            footer_lang_label: "Wilayah & Bahasa",
            footer_theme_label: "Pilihan Tampilan",

            // Studio PDP Specifics
            studio_back_btn: "Kembali ke Penjelajahan",
            studio_cart_btn: "Tas Belanja",
            studio_tab_silhouette: "Siluet",
            studio_tab_material: "Material",
            studio_tab_savoir_faire: "Savoir-Faire",
            studio_tab_care: "Perawatan",
            studio_label_composition: "Komposisi:",
            studio_label_provenance: "Provenance:",
            studio_label_craft: "Keahlian Tangan:",
            studio_label_care: "Perawatan Busana:",
            studio_size_label: "PILIHAN UKURAN ATELIER",
            studio_size_guide: "Panduan Ukuran",
            studio_monogram_title: "Personalisasi Monogram Emas",
            studio_packaging_desc:
                "Kotak kaku safir, pita sutra Lyon, dan stempel segel lilin merah.",
            studio_add_bag_btn: "TAMBAH KE TAS BELANJA",
            studio_book_fitting_btn: "JADWALKAN FITTING PRIVAT DI ATELIER",

            // VIP Account Portal Specifics
            account_subtitle: "Espace Klien ZAFIRA",
            account_greeting_prefix: "Bonjour,",
            account_back_btn: "Kembali ke Penjelajahan",
            account_profile_title: "Profil Klien VIP",
            account_name_label: "Nama Lengkap",
            account_email_label: "Email Terdaftar",
            account_phone_label: "Nomor WhatsApp",
            account_address_label: "Alamat Pengiriman",
            account_orders_title: "Riwayat Janji Temu & Pesanan Adibusana",
            account_orders_desc: "Status permohonan concierge.",

            // MSI Admin Specifics

            // Cart Drawer & Modals
            cart_header_title: "Tas belanja Anda",
            cart_empty_title: "Tas Anda masih sunyi",
            cart_empty_desc: "Koleksi kami menunggu untuk Anda temukan.",
            cart_empty_cta: "Jelajahi koleksi",
            cart_service_note:
                "Pengiriman dan kemasan dikonfirmasi bersama concierge",
            cart_total_label: "Total Nilai Kreasi",
            cart_checkout_btn: "Lanjutkan ke Rendez-vous & Pemesanan",
            cart_shipping_calc:
                "Biaya pengiriman dihitung pada langkah berikutnya.",
            shortcuts_title: "Pintasan Keyboard ZAFIRA",
            shortcuts_desc:
                "Akselerator navigasi efisiensi tinggi untuk menjelajahi seluruh ruang salon digital.",
            shortcuts_close: "Tutup Panduan",
            cart_remove: "Hapus",
            cart_size: "Ukuran:",
            cart_monogram: "Monogram:",
            account_service_label: "Layanan:",
            account_schedule_label: "Jadwal:",
            account_value_label: "Nilai:",

            // Toast notifications
            toast_cart_added_title:
                'Kreasi "{title}" ({size}) berhasil ditambahkan ke Tas.',
            toast_cart_removed: '"{title}" dihapus dari tas belanja.',
            toast_cart_undo_btn: "Urungkan (Undo)",
            toast_cart_restored:
                'Kreasi "{title}" dikembalikan ke tas belanja.',
            toast_wax_sealed: "Pratinjau kemasan diperbarui.",
            toast_rendezvous_registered:
                "Simulasi: permohonan janji temu dicatat lokal saja; tidak tersimpan di server.",
            toast_ribbon_updated: "Pita kemasan diganti: {ribbon}.",

            // Profile & Settings Drawer
            nav_profile: "Profil",
            profile_drawer_eyebrow: "Espace Klien VIP · Maison ZAFIRA",
            profile_drawer_title: "Profil & Preferensi Salon",
            profile_action_fitting: "Reservasi Fitting Privat",
            profile_action_orders: "Riwayat Pesanan Adibusana",
            profile_recent_order_title: "Pesanan Kriya Berjalan",
            profile_monogram_pref: "Inisial Monogram",
            profile_settings_title: "Preferensi & Pengaturan Salon",
            profile_theme_label: "Mode Tampilan",
            profile_theme_desc: "Pilih suasana visual ruang pamer",
            profile_lang_label: "Wilayah & Bahasa",
            profile_lang_desc:
                "Penyelarasan bahasa dan mata uang internasional",
            profile_sound_label: "Akustik Kriya Atelier",
            profile_sound_desc: "Umpan balik audio lembut saat berinteraksi",
            profile_full_portal: "Buka Ruang Klien →",
            profile_msi_link: "Atelier",

            // Themes
            theme_light: "Mode Terang",
            theme_dark: "Mode Gelap",
            theme_system: "Sistem",
            lang_selector: "Bahasa",
        },

        en: {
            // Utilities & Navigation
            announcement:
                "Explore the ZAFIRA collection",
            rendezvous_btn: "Private Rendez-vous",
            nav_collection: "Collections",
            nav_arrivals: "New Arrivals",
            nav_heritage: "Heritage",
            nav_atelier: "Atelier Z",
            nav_packaging: "L'Art d'Offrir",
            nav_services: "Services",
            nav_digital_atelier: "Digital Atelier",
            search_placeholder: "Search…",
            search_aria: "Search",
            wishlist_aria: "Wishlist",
            cart_aria: "Shopping Bag",
            dock_storefront: "Boutique",
            dock_studio: "Studio PDP",
            dock_account: "VIP Account",
            dock_atelier: "Atelier",

            // Hero & Editorial
            hero_eyebrow: "La Collection Première · 2026",
            hero_title: "Born of Light,<br>Shaped by Elegance",
            hero_subtitle:
                "Parisian elegance meets Italian mastery in every creation that illuminates your soul.",
            hero_cta_explore: "Explore Collection",
            hero_cta_studio: "Craft Studio & Savoir-Faire",
            hero_scroll: "Discover",
            marquee_1: "L’Esprit Parisien",
            marquee_2: "La Mano Italiana",
            marquee_3: "Soul of Nusantara",
            marquee_4: "Depuis Jakarta",
            editorial_eyebrow: "Le Journal · Capsule Collection",
            editorial_title: "Songket &amp; Silk,<br>Two Shores.",
            editorial_desc:
                "Palembang meets Lyon in capsule shawls and gowns — wooden-loom weaving, gold thread, natural dyes.",
            editorial_cta: "Meet the artisans",

            // Catalog & Filters
            category_eyebrow: "L’Univers ZAFIRA",
            category_title: "Discover Your World",
            category_desc:
                "Every creation is designed as a dialogue between heritage and modernity.",
            products_eyebrow: "Les Nouveautés",
            products_title: "Enchanting Objects of Art",
            products_count_suffix: "creations",
            filter_all: "All",
            filter_women: "Women's Fashion",
            filter_men: "Men's Tailoring",
            filter_bags: "Evening Bags",
            filter_shoes: "Leather Shoes",
            filter_jewelry: "High Jewelry",
            filter_heritage: "Archipelago Heritage",
            filter_capsule: "Couture Capsule",
            card_quick_view: "Quick View",
            card_add: "Add to Bag",
            card_studio_link: "Studio Z",

            // Categories
            cat_all: "All Creations",
            cat_nusantara: "Archipelago Heritage",
            cat_capsule: "Haute Couture Capsule",
            cat_women: "Women's Ready-to-Wear",
            cat_men: "Men's Tailoring",
            cat_bags: "Evening Bags",
            cat_shoes: "Leather Footwear",
            cat_scarves: "Silk Scarves",

            // Heritage & Maison
            maison_eyebrow: "L’Histoire de la Maison",
            maison_title:
                "Like sapphire,<br>true beauty<br>emerges through mastery.",
            maison_body:
                "ZAFIRA was born from the belief that true luxury never shouts. It lives in stitch precision, tactile purity of materials, and effortless quiet confidence.",
            maison_link: "Maison Story",
            heritage_eyebrow: "Trois Héritages · Three Legacies",
            heritage_title: "Three lands, one Maison",
            heritage_subtitle:
                "Paris gives silhouette. Italy gives hand. Indonesia bestows its soul and living textile.",
            heritage_1_title: "L’Esprit Parisien",
            heritage_1_desc:
                "Silhouettes, drapery, and understated boldness — drafted in Paris, worn across the globe.",
            heritage_1_proof: "Lyon silks & Biella wools · true bias-cut",
            heritage_2_title: "La Mano Italiana",
            heritage_2_desc:
                "Santa Croce leathers, saddle-stitch craftsmanship, Blake-stitched resolable shoes.",
            heritage_2_proof:
                "Tuscan & Marche leathers · artisanal hand finish",
            heritage_3_title: "Soul of Nusantara",
            heritage_3_desc:
                "Solo hand-drawn batik, Palembang songket, Flores ikat, Kebayoran embroidery — living culture.",
            heritage_3_proof: "Natural dyes · wooden handloom · fair trade",

            // Atelier Z Customizer
            atelier_eyebrow: "Atelier Z · Personalization",
            atelier_title: "Make it intimately,<br>uniquely yours.",
            atelier_desc:
                "Inspired by European bespoke salons, Atelier Z offers gold-leaf monogramming on selected leather and couture pieces. Ask the concierge about costs.",
            atelier_step_1: "Choose up to three uppercase initials",
            atelier_step_2: "Select gold, silver, or sapphire hot-stamp foil",
            atelier_step_3: "Hand-embroidery by ZAFIRA artisans",
            atelier_input_label: "Your Initials",
            atelier_foil_label: "Foil Tone",
            atelier_cta: "Personalize Your Piece",

            // Packaging
            packaging_eyebrow: "L'Art d'Offrir ZAFIRA",
            packaging_title: "Signature Gift Packaging & Gold Wax Seal",
            packaging_desc:
                "Each piece is cradled inside a handcrafted sapphire rigid box, tied with Lyon silk twill ribbon, and sealed with a carmine-gold Maison wax seal.",
            packaging_ribbon_label: "Silk Ribbon Selection:",
            packaging_ribbon_gold: "Golden Ribbon",
            packaging_ribbon_sapphire: "Sapphire Ribbon",
            packaging_complimentary:
                "Packaging shown is illustrative; ask the concierge about options and costs.",

            // Services & Telemetry
            services_eyebrow: "Les Services ZAFIRA",
            services_title: "More than a creation,<br>a timeless experience.",
            service_1_title: "Private Styling",
            service_1_desc: "Contact concierge.",
            service_1_link: "Arrange rendez-vous",
            service_2_title: "Full Transparency",
            service_2_desc:
                "Every creation details its materials, origin, and maker.",
            service_2_link: "Discover product provenance",
            service_3_title: "Care guidance",
            service_3_desc:
                "Check the product page for available care instructions; contact the concierge with questions.",
            service_3_link: "Contact concierge",
            service_4_title: "Craft Card",
            service_4_desc: "Product information.",
            service_4_link: "View details",

            // Newsletter & Footer
            newsletter_eyebrow: "La Lettre ZAFIRA",
            newsletter_title: "Step into the Maison.",
            newsletter_desc:
                "Receive atelier dispatches, private salon invitations, and priority previews of new collections.",
            newsletter_placeholder: "name @ domain.com…",
            newsletter_privacy:
                "By subscribing, you agree to ZAFIRA's privacy policy.",
            footer_motto:
                "Parisian in spirit. Italian by craft. Indonesian at heart.",
            footer_col_collection: "Collections",
            footer_women: "Women",
            footer_men: "Men",
            footer_bags: "Evening Bags",
            footer_accessories: "Accessories",
            footer_col_maison: "The Maison",
            footer_story: "Our Story",
            footer_atelier_z: "Atelier Z",
            footer_services: "Services",
            footer_rendezvous: "Rendez-vous",
            footer_account_portal: "VIP Client Portal",
            footer_savoir_title: "Savoir-Faire",
            savoir_1: "Kebayoran Hand Embroidery",
            savoir_2: "Solo Wax-Resist Canting Batik",
            savoir_3: "Flores Maumere Handwoven Ikat",
            savoir_4: "Lyon Pure Organza Silk",
            newsletter_submit: "Subscribe",
            footer_col_support: "Concierge",
            footer_contact: "Contact Us",
            footer_shipping: "Shipping & Returns",
            footer_care: "Garment Care",
            footer_sizing: "Size Guide",
            footer_legal_rights: "© 2026 ZAFIRA MAISON. ALL RIGHTS RESERVED.",
            footer_privacy: "Privacy Policy",
            footer_terms: "Terms of Service",
            shortcuts_trigger: "Shortcuts (?)",
            footer_lang_label: "Region & Language",
            footer_theme_label: "Appearance",

            // Studio PDP Specifics
            studio_back_btn: "Back to Collections",
            studio_cart_btn: "Shopping Bag",
            studio_tab_silhouette: "Silhouette",
            studio_tab_material: "Materials",
            studio_tab_savoir_faire: "Savoir-Faire",
            studio_tab_care: "Garment Care",
            studio_label_composition: "Composition:",
            studio_label_provenance: "Provenance:",
            studio_label_craft: "Artisanal Craft:",
            studio_label_care: "Care Instructions:",
            studio_size_label: "ATELIER SIZE SELECTION",
            studio_size_guide: "Size Guide",
            studio_monogram_title: "Gold Monogram Personalization",
            studio_monogram_desc: "Choose monogram initials.",
            studio_packaging_desc:
                "Sapphire rigid gift box, Lyon silk ribbon, and red wax seal stamp.",
            studio_add_bag_btn: "ADD TO SHOPPING BAG",
            studio_book_fitting_btn: "BOOK PRIVATE ATELIER FITTING",

            // VIP Account Portal Specifics
            account_subtitle: "ZAFIRA Client Space",
            account_greeting_prefix: "Welcome,",
            account_back_btn: "Back to Collections",
            account_profile_title: "VIP Client Profile",
            account_name_label: "Full Name",
            account_email_label: "Registered Email",
            account_phone_label: "WhatsApp Number",
            account_address_label: "Delivery Address",
            account_orders_title: "Haute Couture Orders & Appointments History",
            account_orders_desc: "Concierge request status.",

            // MSI Admin Specifics

            // Cart Drawer & Modals
            cart_header_title: "Your Shopping Bag",
            cart_empty_title: "Your bag is quiet",
            cart_empty_desc: "Our creations await your discovery.",
            cart_empty_cta: "Explore Collections",
            cart_service_note:
                "Delivery and packaging are confirmed with the concierge",
            cart_total_label: "Total Valuation",
            cart_checkout_btn: "Proceed to Rendez-vous & Checkout",
            cart_shipping_calc: "Shipping fees calculated at subsequent step.",
            shortcuts_title: "ZAFIRA Keyboard Shortcuts",
            shortcuts_desc:
                "High-efficiency navigation accelerator for exploring the digital salon.",
            shortcuts_close: "Close Guide",
            cart_remove: "Remove",
            cart_size: "Size:",
            cart_monogram: "Monogram:",
            account_service_label: "Service:",
            account_schedule_label: "Schedule:",
            account_value_label: "Valuation:",

            // Toast notifications
            toast_cart_added_title:
                'Creation "{title}" ({size}) added to your Shopping Bag.',
            toast_cart_removed: '"{title}" removed from your shopping bag.',
            toast_cart_undo_btn: "Undo",
            toast_cart_restored:
                'Creation "{title}" restored to your shopping bag.',
            toast_wax_sealed: "Packaging preview updated.",
            toast_rendezvous_registered:
                "Simulation: appointment request noted locally only; it is not saved on the server.",
            toast_ribbon_updated: "Gift packaging ribbon updated: {ribbon}.",

            // Profile & Settings Drawer
            nav_profile: "Profile",
            profile_drawer_eyebrow: "VIP Client Space · Maison ZAFIRA",
            profile_drawer_title: "Profile & Salon Preferences",
            profile_action_fitting: "Reserve Private Fitting",
            profile_action_orders: "Haute Couture Orders History",
            profile_recent_order_title: "Active Haute Couture Order",
            profile_monogram_pref: "Monogram Initials",
            profile_settings_title: "Salon Preferences & Settings",
            profile_theme_label: "Appearance Mode",
            profile_theme_desc: "Select digital salon visual ambiance",
            profile_lang_label: "Region & Language",
            profile_lang_desc: "Synchronize international language & currency",
            profile_sound_label: "Atelier Acoustic Soundscape",
            profile_sound_desc: "Subtle tactile audio feedback on interactions",
            profile_full_portal: "Open Client Room →",
            profile_msi_link: "Atelier",

            // Themes
            theme_light: "Light Mode",
            theme_dark: "Dark Mode",
            theme_system: "System Default",
            lang_selector: "Language",
        },

        fr: {
            // Utilities & Navigation
            announcement:
                "Découvrez la collection ZAFIRA",
            rendezvous_btn: "Rendez-vous Privé",
            nav_collection: "Collections",
            nav_arrivals: "Nouveautés",
            nav_heritage: "Héritage",
            nav_atelier: "Atelier Z",
            nav_packaging: "L'Art d'Offrir",
            nav_services: "Services",
            nav_digital_atelier: "Atelier Numérique",
            search_placeholder: "Chercher…",
            search_aria: "Recherche",
            wishlist_aria: "Favoris",
            cart_aria: "Panier",
            dock_storefront: "Boutique",
            dock_studio: "Studio PDP",
            dock_account: "Espace Client",
            dock_atelier: "Atelier",

            // Hero & Editorial
            hero_eyebrow: "La Collection Première · 2026",
            hero_title: "Née de la Lumière,<br>Façonnée par l'Élégance",
            hero_subtitle:
                "L'élégance parisienne rencontre la virtuosité italienne dans chaque création qui révèle votre éclat intérieur.",
            hero_cta_explore: "Explorer la collection",
            hero_cta_studio: "Studio Kriya & Savoir-Faire",
            hero_scroll: "Découvrir",
            marquee_1: "L’Esprit Parisien",
            marquee_2: "La Mano Italiana",
            marquee_3: "Âme de l'Archipel",
            marquee_4: "Depuis Jakarta",
            editorial_eyebrow: "Le Journal · Collection Capsule",
            editorial_title: "Songket &amp; Soie,<br>Deux Rives.",
            editorial_desc:
                "Palembang rencontre Lyon dans des étoles et robes capsule — tissage sur métier en bois, fil d’or, teintures naturelles.",
            editorial_cta: "Rencontrer les artisans",

            // Catalog & Filters
            category_eyebrow: "L’Univers ZAFIRA",
            category_title: "Découvrez votre univers",
            category_desc:
                "Chaque collection est conçue comme un dialogue intime entre tradition et modernité.",
            products_eyebrow: "Les Nouveautés",
            products_title: "Objets de désir et de grâce",
            products_count_suffix: "créations",
            filter_all: "Tout",
            filter_women: "Prêt-à-Porter Femme",
            filter_men: "Tailleur Homme",
            filter_bags: "Minaudières du Soir",
            filter_shoes: "Souliers en Cuir",
            filter_jewelry: "Haute Joaillerie",
            filter_heritage: "Héritage de l'Archipel",
            filter_capsule: "Capsule Couture",
            card_quick_view: "Aperçu rapide",
            card_add: "Ajouter",
            card_studio_link: "Studio Z",

            // Categories
            cat_all: "Toutes les Créations",
            cat_nusantara: "Héritage de l'Archipel",
            cat_capsule: "Capsule Haute Couture",
            cat_women: "Prêt-à-Porter Femme",
            cat_men: "Tailleur Homme",
            cat_bags: "Minaudières du Soir",
            cat_shoes: "Souliers en Cuir",
            cat_scarves: "Carrés de Soie",

            // Heritage & Maison
            maison_eyebrow: "L’Histoire de la Maison",
            maison_title:
                "Comme le saphir,<br>la beauté véritable<br>naît dans le geste.",
            maison_body:
                "ZAFIRA est née de la conviction que le luxe n'a nul besoin d'éclat tapageur. Il réside dans la rigueur d'une piqûre, la pureté des soies et cette sérénité inaltérable qui vous accompagne.",
            maison_link: "L'Histoire de la Maison",
            heritage_eyebrow: "Trois Héritages · Savoir-Faire Tripartite",
            heritage_title: "Trois terres, une seule Maison",
            heritage_subtitle:
                "Paris insuffle la ligne. L'Italie façonne la main. L'Indonésie confère son âme et ses étoffes vivantes.",
            heritage_1_title: "L’Esprit Parisien",
            heritage_1_desc:
                "Lignes pures, drapés sculpturaux et audace sereine — esquissés à Paris, portés dans le monde entier.",
            heritage_1_proof:
                "Soies lyonnaises & laines de Biella · coupe en plein biais",
            heritage_2_title: "La Mano Italiana",
            heritage_2_desc:
                "Cuirs nobles de Santa Croce, coutures sellier, souliers cousus Blake ressemelables.",
            heritage_2_proof:
                "Cuirs de Toscane et des Marches · finitions faites main",
            heritage_3_title: "L’Âme de l'Archipel",
            heritage_3_desc:
                "Batik tracé à la cire à Solo, songket d'or de Palembang, ikat de Flores — un héritage vivant.",
            heritage_3_proof:
                "Teintures végétales · métiers à tisser manuels · commerce équitable",

            // Atelier Z Customizer
            atelier_eyebrow: "Atelier Z · Personnalisation",
            atelier_title: "Faites-en une œuvre<br>absolument vôtre.",
            atelier_desc:
                "Inspiré des salons sur-mesure de la haute couture, l'Atelier Z appose vos initiales dorées sur vos pièces de maroquinerie d'exception. Coût sur demande auprès du concierge.",
            atelier_step_1: "Choisissez jusqu'à trois initiales en majuscules",
            atelier_step_2:
                "Sélectionnez une dorure or chaud, argent ou saphir",
            atelier_step_3:
                "Broderie artisanale par les maîtres d'art ZAFIRA",
            atelier_input_label: "Vos Initiales",
            atelier_foil_label: "Teinte de Dorure",
            atelier_cta: "Personnaliser Votre Création",

            // Packaging
            packaging_eyebrow: "L'Art d'Offrir ZAFIRA",
            packaging_title: "Écrin Signature & Sceau de Cire d'Or",
            packaging_desc:
                "Chaque création est enveloppée dans un coffret rigide bleu saphir façonné à la main, ceint d'un ruban en sergé de soie de Lyon et scellé de cire carmin et or Maison ZAFIRA.",
            packaging_ribbon_label: "Ruban de Soie :",
            packaging_ribbon_gold: "Ruban d'Or",
            packaging_ribbon_sapphire: "Ruban Saphir",
            packaging_complimentary:
                "L’emballage présenté est illustratif ; demandez options et coûts au concierge.",

            // Services & Telemetry
            services_eyebrow: "Les Services ZAFIRA",
            services_title:
                "Bien plus qu'un vêtement,<br>une expérience de grâce.",
            service_1_title: "Stylisme Privé",
            service_1_desc: "Contactez la conciergerie.",
            service_1_link: "Prendre rendez-vous",
            service_2_title: "Transparence Absolue",
            service_2_desc:
                "Chaque pièce présente ses matières, son origine et son artisan.",
            service_2_link: "Consulter la traçabilité",
            service_3_title: "Conseils d’entretien",
            service_3_desc:
                "Consultez la fiche produit pour les consignes disponibles ; le concierge répond à vos questions.",
            service_3_link: "Contacter la conciergerie",
            service_4_title: "Carte de savoir-faire",
            service_4_desc: "Informations produit.",
            service_4_link: "Voir les détails",

            // Newsletter & Footer
            newsletter_eyebrow: "La Lettre ZAFIRA",
            newsletter_title: "Pénétrez dans l'univers de la Maison.",
            newsletter_desc:
                "Recevez les carnets de l'atelier, des invitations privées et les avant-premières exclusives.",
            newsletter_placeholder: "votre-nom @ domaine.fr…",
            newsletter_privacy:
                "En vous abonnant, vous acceptez la politique de confidentialité de ZAFIRA.",
            footer_motto:
                "Parisienne d’esprit. Italienne par le savoir-faire. Indonésienne de cœur.",
            footer_col_collection: "Collections",
            footer_women: "Femme",
            footer_men: "Homme",
            footer_bags: "Minaudières",
            footer_accessories: "Accessoires",
            footer_col_maison: "La Maison",
            footer_story: "Notre Histoire",
            footer_atelier_z: "Atelier Z",
            footer_services: "Services",
            footer_rendezvous: "Rendez-vous",
            footer_account_portal: "Espace Client VIP",
            footer_savoir_title: "Savoir-Faire",
            savoir_1: "Broderie d'Art de Kebayoran",
            savoir_2: "Batik Canting à la Cire de Solo",
            savoir_3: "Ikat Tissé Main de Maumere Florès",
            savoir_4: "Organza de Soie Pure de Lyon",
            newsletter_submit: "S'inscrire",
            footer_col_support: "Conciergerie",
            footer_contact: "Nous Contacter",
            footer_shipping: "Livraison & Retours",
            footer_care: "Entretien des Pièces",
            footer_sizing: "Guide des Tailles",
            footer_legal_rights: "© 2026 MAISON ZAFIRA. TOUS DROITS RÉSERVÉS.",
            footer_privacy: "Confidentialité",
            footer_terms: "Conditions Générales",
            shortcuts_trigger: "Raccourcis (?)",
            footer_lang_label: "Région & Langue",
            footer_theme_label: "Apparence",

            // Studio PDP Specifics
            studio_back_btn: "Retour aux Collections",
            studio_cart_btn: "Panier",
            studio_tab_silhouette: "Silhouette",
            studio_tab_material: "Matières",
            studio_tab_savoir_faire: "Savoir-Faire",
            studio_tab_care: "Entretien",
            studio_label_composition: "Composition :",
            studio_label_provenance: "Provenance :",
            studio_label_craft: "Façon Main :",
            studio_label_care: "Conseils d'Entretien :",
            studio_size_label: "SÉLECTION DES TAILLES ATELIER",
            studio_size_guide: "Guide des Tailles",
            studio_monogram_title: "Personnalisation Monogramme en Or",
            studio_packaging_desc:
                "Écrin rigide saphir, ruban de soie de Lyon et cachet de cire rouge.",
            studio_add_bag_btn: "AJOUTER AU PANIER",
            studio_book_fitting_btn: "RÉSERVER UN ESSAYAGE PRIVÉ À L'ATELIER",

            // VIP Account Portal Specifics
            account_subtitle: "Espace Client ZAFIRA",
            account_greeting_prefix: "Bonjour,",
            account_back_btn: "Retour aux Collections",
            account_profile_title: "Profil Client VIP",
            account_name_label: "Nom Complet",
            account_email_label: "Adresse E-mail",
            account_phone_label: "Numéro WhatsApp",
            account_address_label: "Adresse de Livraison",
            account_orders_title:
                "Historique des Commandes Haute Couture & Rendez-Vous",
            account_orders_desc: "État des demandes de conciergerie.",

            // MSI Admin Specifics

            // Cart Drawer & Modals
            cart_header_title: "Votre Panier d'Exception",
            cart_empty_title: "Votre panier est encore silencieux",
            cart_empty_desc: "Nos créations n'attendent que votre regard.",
            cart_empty_cta: "Explorer les Collections",
            cart_service_note:
                "Livraison et emballage confirmés avec le concierge",
            cart_total_label: "Valeur Totale",
            cart_checkout_btn: "Finaliser le Rendez-Vous & la Commande",
            cart_shipping_calc:
                "Frais de livraison calculés à l'étape suivante.",
            shortcuts_title: "Raccourcis Clavier ZAFIRA",
            shortcuts_desc:
                "Accélérateurs de navigation haute efficacité pour parcourir le salon numérique.",
            shortcuts_close: "Fermer le Guide",
            cart_remove: "Supprimer",
            cart_size: "Taille :",
            cart_monogram: "Monogramme :",
            account_service_label: "Prestation :",
            account_schedule_label: "Rendez-vous :",
            account_value_label: "Valeur :",

            // Toast notifications
            toast_cart_added_title:
                'Création "{title}" ({size}) ajoutée avec succès à votre Panier.',
            toast_cart_removed: '"{title}" retiré de votre panier.',
            toast_cart_undo_btn: "Annuler (Undo)",
            toast_cart_restored:
                'Création "{title}" replacée dans votre panier.',
            toast_wax_sealed: "Aperçu de l’emballage actualisé.",
            toast_rendezvous_registered:
                "Simulation : demande notée localement uniquement ; elle n\u2019est pas enregistrée sur le serveur.",
            toast_ribbon_updated: "Ruban du coffret mis à jour : {ribbon}.",

            // Profile & Settings Drawer
            nav_profile: "Profil",
            profile_drawer_eyebrow: "Espace Client VIP · Maison ZAFIRA",
            profile_drawer_title: "Profil & Préférences du Salon",
            profile_action_fitting: "Réserver un Essayage Privé",
            profile_action_orders: "Historique des Commandes Haute Couture",
            profile_recent_order_title: "Commande de Création en Cours",
            profile_monogram_pref: "Initiales Monogramme",
            profile_settings_title: "Préférences & Paramètres du Salon",
            profile_theme_label: "Mode d'Affichage",
            profile_theme_desc: "Choisissez l'ambiance visuelle du salon",
            profile_lang_label: "Région & Langue",
            profile_lang_desc: "Synchroniser langue et devise internationale",
            profile_sound_label: "Ambiance Acoustique de l'Atelier",
            profile_sound_desc: "Retour sonore délicat lors des interactions",
            profile_full_portal: "Ouvrir l'Espace Client →",
            profile_msi_link: "Atelier",

            // Themes
            theme_light: "Mode Clair",
            theme_dark: "Mode Sombre",
            theme_system: "Système",
            lang_selector: "Langue",
        },

        it: {
            // Utilities & Navigation
            announcement:
                "Scoprite la collezione ZAFIRA",
            rendezvous_btn: "Appuntamento Privato",
            nav_collection: "Collezioni",
            nav_arrivals: "Novità",
            nav_heritage: "Eredità",
            nav_atelier: "Atelier Z",
            nav_packaging: "L'Art d'Offrir",
            nav_services: "Servizi",
            nav_digital_atelier: "Atelier Digitale",
            search_placeholder: "Cerca",
            search_aria: "Cerca",
            wishlist_aria: "Desideri",
            cart_aria: "Borsa Shopping",
            dock_storefront: "Boutique",
            dock_studio: "Studio PDP",
            dock_account: "Spazio Clienti",
            dock_atelier: "Atelier",

            // Hero & Editorial
            hero_eyebrow: "La Collection Première · 2026",
            hero_title: "Nata dalla Luce,<br>Plasmata dall'Eleganza",
            hero_subtitle:
                "L'eleganza parigina incontra la maestria italiana in ogni creazione che illumina la vostra anima.",
            hero_cta_explore: "Esplora la collezione",
            hero_cta_studio: "Studio d'Arte & Savoir-Faire",
            hero_scroll: "Scopri",
            marquee_1: "L’Esprit Parisien",
            marquee_2: "La Mano Italiana",
            marquee_3: "Anima dell'Arcipelago",
            marquee_4: "Depuis Jakarta",
            editorial_eyebrow: "Le Journal · Collezione Capsule",
            editorial_title: "Songket &amp; Seta,<br>Due Rive.",
            editorial_desc:
                "Palembang incontra Lione in stole e abiti capsule — tessitura su telaio in legno, filo d’oro, tinture naturali.",
            editorial_cta: "Incontra gli artigiani",

            // Catalog & Filters
            category_eyebrow: "L’Univers ZAFIRA",
            category_title: "Scoprite il vostro universo",
            category_desc:
                "Ogni creazione nasce da un dialogo profondo tra nobile tradizione e modernità.",
            products_eyebrow: "Les Nouveautés",
            products_title: "Oggetti di pura grazia",
            products_count_suffix: "creazioni",
            filter_all: "Tutto",
            filter_women: "Abbigliamento Donna",
            filter_men: "Sartoria Uomo",
            filter_bags: "Borse da Sera",
            filter_shoes: "Calzature in Pelle",
            filter_jewelry: "Alta Gioielleria",
            filter_heritage: "Eredità dell'Arcipelago",
            filter_capsule: "Capsula Alta Moda",
            card_quick_view: "Anteprima",
            card_add: "Aggiungi",
            card_studio_link: "Studio Z",

            // Categories
            cat_all: "Tutte le Creazioni",
            cat_nusantara: "Eredità dell'Arcipelago",
            cat_capsule: "Capsula Alta Moda",
            cat_women: "Abbigliamento Donna",
            cat_men: "Sartoria Uomo",
            cat_bags: "Borse da Sera",
            cat_shoes: "Calzature in Pelle",
            cat_scarves: "Sciarpe di Seta",

            // Heritage & Maison
            maison_eyebrow: "L’Histoire de la Maison",
            maison_title:
                "Come lo zaffiro,<br>la vera bellezza<br>nasce nella maestria.",
            maison_body:
                "ZAFIRA nasce dalla certezza che il vero lusso non ha bisogno di alzare la voce. Vive nella precisione del punto, nella purezza delle sete e nell'armonia serena.",
            maison_link: "Storia della Maison",
            heritage_eyebrow: "Trois Héritages · Tre Nobili Eredità",
            heritage_title: "Tre terre, una sola Maison",
            heritage_subtitle:
                "Parigi dona la linea. L'Italia dona la mano sapiente. L'Indonesia infonde la sua anima e le sue sete vive.",
            heritage_1_title: "L’Esprit Parisien",
            heritage_1_desc:
                "Linee scultoree, drappeggi fluidi e audacia pacata — disegnati a Parigi, indossati nel mondo.",
            heritage_1_proof:
                "Sete di Lione & lane di Biella · taglio in sbieco puro",
            heritage_2_title: "La Mano Italiana",
            heritage_2_desc:
                "Pelli conciate a Santa Croce, cuciture a mano punto sellaio, scarpe cucite a Blake risuolabili.",
            heritage_2_proof:
                "Pelli toscane e marchigiane · rifiniture sartoriali",
            heritage_3_title: "Anima dell'Arcipelago",
            heritage_3_desc:
                "Batik a cera di Solo, songket d'oro di Palembang, ikat di Flores — cultura viva e patrimonio tangibile.",
            heritage_3_proof:
                "Tinture vegetali · telai manuali in legno · filiera etica",

            // Atelier Z Customizer
            atelier_eyebrow: "Atelier Z · Personalizzazione",
            atelier_title: "Rendetela un'opera<br>unicamente vostra.",
            atelier_desc:
                "Ispirato ai salotti d'alta moda europei, l'Atelier Z imprime il vostro monogramma d'oro su creazioni selezionate. Costi su richiesta al concierge.",
            atelier_step_1: "Scegliete fino a tre lettere maiuscole",
            atelier_step_2:
                "Selezionate la doratura a caldo oro, argento o zaffiro",
            atelier_step_3: "Ricamo artigianale dei maestri ZAFIRA",
            atelier_input_label: "Le Vostre Iniziali",
            atelier_foil_label: "Tonalità della Lamina",
            atelier_cta: "Personalizza la Tua Creazione",

            // Packaging
            packaging_eyebrow: "L'Art d'Offrir ZAFIRA",
            packaging_title: "Scatola d'Autore & Sigillo in Cera d'Oro",
            packaging_desc:
                "Ogni creazione è adagiata in una scatola rigida blu zaffiro realizzata a mano, cinta da nastro di seta di Lione e sigillata con ceralacca cremisi e oro Maison ZAFIRA.",
            packaging_ribbon_label: "Nastro di Seta :",
            packaging_ribbon_gold: "Nastro d'Oro",
            packaging_ribbon_sapphire: "Nastro Zaffiro",
            packaging_complimentary:
                "La confezione mostrata è illustrativa; chiedete opzioni e costi al concierge.",

            // Services & Telemetry
            services_eyebrow: "Les Services ZAFIRA",
            services_title: "Più di una creazione,<br>un'esperienza di grazia.",
            service_1_title: "Styling Privato",
            service_1_desc: "Contatta il concierge.",
            service_1_link: "Prenota appuntamento",
            service_2_title: "Trasparenza Totale",
            service_2_desc:
                "Ogni creazione presenta materiali, provenienza e artigiano.",
            service_2_link: "Scopri la tracciabilità",
            service_3_title: "Consigli di cura",
            service_3_desc:
                "Consultate la pagina prodotto per le istruzioni disponibili; il concierge risponde alle domande.",
            service_3_link: "Contatta il concierge",
            service_4_title: "Scheda artigianale",
            service_4_desc: "Informazioni prodotto.",
            service_4_link: "Vedi dettagli",

            // Newsletter & Footer
            newsletter_eyebrow: "La Lettre ZAFIRA",
            newsletter_title: "Entrate nel mondo della Maison.",
            newsletter_desc:
                "Ricevete le cronache dell'atelier, inviti ai salotti privati e anteprime assolute.",
            newsletter_placeholder: "nome @ dominio.it…",
            newsletter_privacy:
                "Iscrivendoti, accetti la politica sulla privacy di ZAFIRA.",
            footer_motto:
                "Parigina nello spirito. Italiana nel mestiere. Indonesiana nel cuore.",
            footer_col_collection: "Collezioni",
            footer_women: "Donna",
            footer_men: "Uomo",
            footer_bags: "Borse da Sera",
            footer_accessories: "Accessori",
            footer_col_maison: "La Maison",
            footer_story: "La Nostra Storia",
            footer_atelier_z: "Atelier Z",
            footer_services: "Servizi",
            footer_rendezvous: "Rendez-vous",
            footer_account_portal: "Portale Clienti VIP",
            footer_savoir_title: "Savoir-Faire",
            savoir_1: "Ricamo a Mano di Kebayoran",
            savoir_2: "Batik Canting Tradizionale di Solo",
            savoir_3: "Ikat Tessuto a Mano di Maumere Flores",
            savoir_4: "Organza di Pura Seta di Lione",
            newsletter_submit: "Iscriviti",
            footer_col_support: "Assistenza",
            footer_contact: "Contattaci",
            footer_shipping: "Spedizioni e Resi",
            footer_care: "Cura dei Capi",
            footer_sizing: "Guida alle Taglie",
            footer_legal_rights:
                "© 2026 MAISON ZAFIRA. TUTTI I DIRITTI RISERVATI.",
            footer_privacy: "Privacy Policy",
            footer_terms: "Termini e Condizioni",
            shortcuts_trigger: "Scorciatoie (?)",
            footer_lang_label: "Paese & Lingua",
            footer_theme_label: "Aspetto",

            // Studio PDP Specifics
            studio_back_btn: "Torna alle Collezioni",
            studio_cart_btn: "Borsa Shopping",
            studio_tab_silhouette: "Silhouette",
            studio_tab_material: "Materiali",
            studio_tab_savoir_faire: "Savoir-Faire",
            studio_tab_care: "Cura del Capo",
            studio_label_composition: "Composizione :",
            studio_label_provenance: "Provenienza :",
            studio_label_craft: "Mestieri d'Arte :",
            studio_label_care: "Consigli di Cura :",
            studio_size_label: "SELEZIONE TAGLIE ATELIER",
            studio_size_guide: "Guida alle Taglie",
            studio_monogram_title: "Personalizzazione Monogramma in Oro",
            studio_packaging_desc:
                "Scatola rigida zaffiro, nastro di seta di Lione e sigillo in cera rossa.",
            studio_add_bag_btn: "AGGIUNGI ALLA BORSA",
            studio_book_fitting_btn: "PRENOTA UNA PROVA PRIVATA IN ATELIER",

            // VIP Account Portal Specifics
            account_subtitle: "Spazio Clienti ZAFIRA",
            account_greeting_prefix: "Buongiorno,",
            account_back_btn: "Torna alle Collezioni",
            account_profile_title: "Profilo Cliente VIP",
            account_name_label: "Nome e Cognome",
            account_email_label: "Indirizzo E-mail",
            account_phone_label: "Numero WhatsApp",
            account_address_label: "Indirizzo di Spedizione",
            account_orders_title: "Cronologia Ordini Alta Moda & Appuntamenti",
            account_orders_desc: "Stato delle richieste concierge.",

            // MSI Admin Specifics

            // Cart Drawer & Modals
            cart_header_title: "La Vostra Borsa Shopping",
            cart_empty_title: "La vostra borsa è ancora silenziosa",
            cart_empty_desc: "Le nostre creazioni attendono il vostro tocco.",
            cart_empty_cta: "Esplora le Collezioni",
            cart_service_note:
                "Spedizione e confezione confermate con il concierge",
            cart_total_label: "Valutazione Totale",
            cart_checkout_btn: "Procedi all'Appuntamento & Ordine",
            cart_shipping_calc:
                "Tariffe di spedizione calcolate al passaggio successivo.",
            shortcuts_title: "Scorciatoie da Tastiera ZAFIRA",
            shortcuts_desc:
                "Acceleratori di navigazione ad alta efficienza per esplorare il salotto digitale.",
            shortcuts_close: "Chiudi Guida",
            cart_remove: "Rimuovi",
            cart_size: "Taglia:",
            cart_monogram: "Monogramma:",
            account_service_label: "Servizio:",
            account_schedule_label: "Programma:",
            account_value_label: "Valore:",

            // Toast notifications
            toast_cart_added_title:
                'Creazione "{title}" ({size}) aggiunta con successo alla vostra Borsa.',
            toast_cart_removed: '"{title}" rimosso dalla borsa.',
            toast_cart_undo_btn: "Annulla (Undo)",
            toast_cart_restored: 'Creazione "{title}" reinserita nella borsa.',
            toast_wax_sealed: "Anteprima confezione aggiornata.",
            toast_rendezvous_registered:
                "Simulazione: richiesta annotata solo localmente; non viene salvata sul server.",
            toast_ribbon_updated:
                "Nastro della confezione aggiornato: {ribbon}.",

            // Profile & Settings Drawer
            nav_profile: "Profilo",
            profile_drawer_eyebrow: "Spazio Clienti VIP · Maison ZAFIRA",
            profile_drawer_title: "Profilo & Preferenze del Salone",
            profile_action_fitting: "Prenota Prova Privata",
            profile_action_orders: "Cronologia Ordini Alta Moda",
            profile_recent_order_title: "Ordine di Alta Sartoria in Corso",
            profile_monogram_pref: "Iniziali Monogramma",
            profile_settings_title: "Preferenze & Impostazioni del Salone",
            profile_theme_label: "Modalità di Visualizzazione",
            profile_theme_desc: "Seleziona l'atmosfera visiva del salone",
            profile_lang_label: "Regione & Lingua",
            profile_lang_desc: "Sincronizza lingua e valuta internazionale",
            profile_sound_label: "Acustica Artigianale dell'Atelier",
            profile_sound_desc:
                "Feedback sonoro delicato durante le interazioni",
            profile_full_portal: "Apri Area Clienti →",
            profile_msi_link: "Atelier",

            // Themes
            theme_light: "Modalità Chiara",
            theme_dark: "Modalità Scura",
            theme_system: "Sistema Predefinito",
            lang_selector: "Lingua",
        },
    };

    // --- 2. MULTILINGUAL PRODUCT DATASET ---
    const MULTI_PRODUCTS = {
        9: {
            id: 9,
            cat_id: 8,
            price: 14900000,
            color_hex: "#dfcaa0",
            images: ["assets/hero_couture.jpg", "assets/echarpe_eclat.jpg"],
            id: {
                title: "Kebaya Lumière",
                badge: "Warisan Nusantara",
                color: "Ivoire & Or",
                desc: "Kebaya kontemporer dengan siluet Parisian dan bordir tangan pengrajin Kebayoran. Menjembatani keanggunan keraton dengan modernitas salon haute-couture Paris.",
                material:
                    "Brokat Prancis 60% viskosa 40% poliamida, dilapisi sutra organza Lyon murni, payet kristal kaca Venesia.",
                origin: "Brokat: Calais, Prancis · Bordir Tangan: Kebayoran Baru, Jakarta · Jahit: Atelier Utama ZAFIRA",
                care: "Dry clean khusus spesialis adibusana dan bordir tangan. Simpan digantung pada hanger berbalut katun organik ZAFIRA.",
            },
            en: {
                title: "Kebaya Lumière",
                badge: "Archipelago Heritage",
                color: "Ivoire & Gold",
                desc: "A contemporary kebaya sculpted in a Parisian silhouette with intricate artisan hand-embroidery, harmonizing royal Javanese heritage with Paris haute-couture salons.",
                material:
                    "French lace 60% viscose 40% polyamide, lined in pure Lyon organza silk, hand-stitched Venetian glass crystal paillettes.",
                origin: "Lace: Calais, France · Hand Embroidery: Kebayoran Baru, Jakarta · Tailoring: ZAFIRA Main Atelier",
                care: "Specialist haute couture dry clean only. Store suspended on ZAFIRA padded organic cotton hangers.",
            },
            fr: {
                title: "Kebaya Lumière",
                badge: "Héritage de l'Archipel",
                color: "Ivoire & Or",
                desc: "Une kebaya contemporaine sculptée dans une silhouette parisienne et rehaussée de broderies manuelles, reliant la majesté royale javanaise aux salons de haute couture parisiens.",
                material:
                    "Dentelle de Calais 60% viscose 40% polyamide, doublure en organza de soie pure de Lyon, cristaux vénitiens cousus main.",
                origin: "Dentelle : Calais, France · Broderie Main : Kebayoran Baru, Jakarta · Confection : Grand Atelier ZAFIRA",
                care: "Nettoyage à sec spécialisé haute couture. Conserver suspendu sur cintre molletonné de coton biologique ZAFIRA.",
            },
            it: {
                title: "Kebaya Lumière",
                badge: "Eredità dell'Arcipelago",
                color: "Avorio & Oro",
                desc: "Una kebaya contemporanea con silhouette parigina e ricamo manuale magistrale. Un ponte di grazia tra la regalità giavanese e i saloni dell'alta moda internazionale.",
                material:
                    "Pizzo di Calais 60% viscosa 40% poliammide, fodera in organza di pura seta di Lione, paillettes di cristallo veneziano.",
                origin: "Pizzo: Calais, Francia · Ricamo a Mano: Kebayoran Baru, Giacarta · Sartoria: Atelier Principale ZAFIRA",
                care: "Lavaggio a secco specializzato alta moda. Conservare appeso su gruccia imbottita in cotone organico ZAFIRA.",
            },
        },
        10: {
            id: 10,
            cat_id: 8,
            price: 6850000,
            color_hex: "#1b2e50",
            images: ["assets/chemise_riviere.jpg"],
            id: {
                title: "Chemise Rivière",
                badge: "Warisan Nusantara",
                color: "Indigo Solo",
                desc: "Kemeja batik tulis Solo dalam potongan chemise Prancis kontemporer. Harmonisasi canting malam tradisional dengan ketelitian garis jahit Neapolitan.",
                material:
                    "Katun primisima berserat panjang, pewarnaan indigo pasta alami daun Tom (Indigofera tinctoria).",
                origin: "Batik Tulis: Kampung Laweyan, Solo · Pola & Potongan: Milan · Jahit: Atelier Jakarta",
                care: "Cuci tangan dingin dengan sari buah lerak alami atau sampo sutra lembut. Jemur di tempat teduh terbalik.",
            },
            en: {
                title: "Chemise Rivière",
                badge: "Archipelago Heritage",
                color: "Solo Indigo",
                desc: "A hand-drawn Solo batik shirt in a contemporary French chemise cut. Traditional wax-resist wax work meets Neapolitan single-needle tailoring precision.",
                material:
                    "Extra-long staple premium cotton, dyed with pure natural Indigofera tinctoria leaf paste.",
                origin: "Hand-drawn Batik: Laweyan Village, Solo · Pattern: Milan · Tailoring: ZAFIRA Atelier Jakarta",
                care: "Hand wash cold with natural lerak extract or delicate silk shampoo. Dry flat in shade inside-out.",
            },
            fr: {
                title: "Chemise Rivière",
                badge: "Héritage de l'Archipel",
                color: "Indigo de Solo",
                desc: "Chemise en batik tracé à la main à Solo dans une coupe chemise française contemporaine. Fusion entre le savoir-faire canting javanais et la coupe napolitaine.",
                material:
                    "Coton peigné fibres extra-longues, teinture naturelle à la pâte d'Indigofera tinctoria.",
                origin: "Batik Tracé Main : Village de Laweyan, Solo · Patronage : Milan · Confection : Atelier Jakarta",
                care: "Lavage délicat à froid avec extrait de lerak ou shampoing doux pour soies. Séchage à l'ombre sur l'envers.",
            },
            it: {
                title: "Chemise Rivière",
                badge: "Eredità dell'Arcipelago",
                color: "Indaco Solo",
                desc: "Camicia in batik fatto a mano a Solo con taglio chemise francese contemporaneo. Armonia perfetta tra canting tradizionale e precisione sartoriale napoletana.",
                material:
                    "Cotone a fibra extra lunga, tintura con pasta naturale di foglie d'Indigofera tinctoria.",
                origin: "Batik a Mano: Villaggio Laweyan, Solo · Modelleria: Milano · Sartoria: Atelier Giacarta",
                care: "Lavaggio a mano a freddo con essenza naturale di lerak. Asciugare all'ombra al rovescio.",
            },
        },
        3: {
            id: 3,
            cat_id: 1,
            price: 15900000,
            color_hex: "#0f1c33",
            images: ["assets/robe_elan.jpg"],
            id: {
                title: "Robe Élan",
                badge: "Kapsul Adibusana",
                color: "Sapphire Night",
                desc: "Gaun sutra crepe de chine dengan drapery bias-cut satu panel utuh. Didesain untuk menciptakan ilusi siluet memanjang yang bergerak mengikuti ritme tubuh.",
                material:
                    "100% Sutra Murbei Lyon 22 momme murni, lapisan sutra habotai.",
                origin: "Sutra: Lyon, Prancis · Pemotongan Bias: Paris · Jahit: Atelier Jakarta",
                care: "Dry clean khusus spesialis sutra. Jangan diperas atau disetrika uap langsung.",
            },
            en: {
                title: "Robe Élan",
                badge: "Couture Capsule",
                color: "Sapphire Night",
                desc: "A pure silk crepe de chine gown featuring seamless single-panel bias-cut drapery, creating an elongating, fluid silhouette that moves with the body.",
                material:
                    "100% Pure Mulberry Lyon Silk 22 momme, Habotai silk lining.",
                origin: "Silk: Lyon, France · Bias Pattern: Paris · Tailoring: ZAFIRA Atelier Jakarta",
                care: "Specialist silk dry clean only. Never wring or apply direct steam.",
            },
            fr: {
                title: "Robe Élan",
                badge: "Capsule Haute Couture",
                color: "Bleu Nuit Saphir",
                desc: "Robe en crêpe de chine de soie pure avec drapé d'un seul tenant coupé en biais, offrant une silhouette élancée qui danse au rythme des pas.",
                material:
                    "100% Soie de mûrier de Lyon 22 mommes, doublure en habotai de soie.",
                origin: "Soie : Lyon, France · Coupe Biais : Paris · Confection : Atelier Jakarta",
                care: "Nettoyage à sec spécialisé pour soies. Ne pas tordre ni repasser à la vapeur directe.",
            },
            it: {
                title: "Robe Élan",
                badge: "Capsula Alta Moda",
                color: "Notte Zaffiro",
                desc: "Abito in crêpe de chine di pura seta con drappeggio a pannello unico tagliato in sbieco, creando una linea sinuosa ed elegante.",
                material:
                    "100% Pura Seta di Gelso di Lione 22 momme, fodera in seta habotai.",
                origin: "Seta: Lione, Francia · Taglio in Sbieco: Parigi · Sartoria: Atelier Giacarta",
                care: "Lavaggio a secco specializzato seta. Non strizzare né vaporizzare a contatto diretto.",
            },
        },
        5: {
            id: 5,
            cat_id: 3,
            price: 22500000,
            color_hex: "#17263f",
            images: ["assets/minaudiere_saphir.jpg"],
            id: {
                title: "Minaudière Saphir",
                badge: "Edisi Terbatas",
                color: "Bleu Zafira",
                origin: "Satin: Prancis · Pahat Logam: Kotagede & Jakarta · Perakitan: Atelier ZAFIRA",
                care: "Simpan dalam kotak berlapis beludru khusus ZAFIRA. Bersihkan dengan kain mikrofiber kering.",
            },
            en: {
                title: "Minaudière Saphir",
                badge: "Limited Edition",
                color: "Zafira Blue",
                origin: "Satin: France · Metal Sculpture: Kotagede & Jakarta · Assembly: ZAFIRA Atelier",
                care: "Store in protective velvet-lined ZAFIRA case. Polish with dry microfiber cloth.",
            },
            fr: {
                title: "Minaudière Saphir",
                badge: "Édition Limitée",
                color: "Bleu Zafira",
                desc: "Minaudière du soir gainée de satin bleu saphir avec monture en laiton doré 24 carats ciselé à la main. Chaque exemplaire est numéroté dans la doublure.",
                origin: "Satin : France · Orfèvrerie : Kotagede & Jakarta · Montage : Atelier ZAFIRA",
                care: "Conserver dans son écrin de velours ZAFIRA. Nettoyer avec une chamoisine en microfibre sèche.",
            },
            it: {
                title: "Minaudière Saphir",
                badge: "Edizione Limitata",
                color: "Blu Zafira",
                origin: "Raso: Francia · Oreficeria: Kotagede & Giacarta · Montaggio: Atelier ZAFIRA",
                care: "Custodire nella scatola foderata in velluto ZAFIRA. Pulire con panno in microfibra asciutto.",
            },
        },
        6: {
            id: 6,
            cat_id: 2,
            price: 19800000,
            color_hex: "#8a7868",
            images: ["assets/manteau_firenze.jpg"],
            id: {
                title: "Manteau Firenze",
                badge: "Baru",
                color: "Tuscan Tobacco",
                desc: "Mantel pria dengan bahu arsitektural dan siluet panjang yang berwibawa. Dijahit dengan konstruksi full-canvas wol kuda yang menyesuaikan bentuk tubuh pemakainya seiring waktu.",
                material:
                    "90% Wol Merino Biella Italia, 10% Kasmir Mongolia; kancing tanduk kerbau asli.",
                origin: "Kain: Biella, Italia · Pola: Firenze · Jahit: Atelier Jakarta",
                care: "Angin-anginkan setelah dipakai. Dry clean profesional maksimal 1x per musim.",
            },
            en: {
                title: "Manteau Firenze",
                badge: "New Arrival",
                color: "Tuscan Tobacco",
                desc: "An architectural men's overcoat with clean shoulders and an authoritative long silhouette. Tailored with a traditional horsehair full-floating canvas that molds to the wearer over time.",
                material:
                    "90% Italian Biella Merino Wool, 10% Mongolian Cashmere; genuine horn buttons.",
                origin: "Cloth: Biella, Italy · Pattern: Florence · Tailoring: ZAFIRA Atelier Jakarta",
                care: "Air thoroughly after wearing. Professional dry clean maximum once per season.",
            },
            fr: {
                title: "Manteau Firenze",
                badge: "Nouveauté",
                color: "Tabac Toscan",
                desc: "Manteau d'homme architectural aux épaules nettes et à la silhouette élancée magistrale. Confectionné avec un entoilage traditionnel en crin de cheval qui épouse la morphologie avec noblesse.",
                material:
                    "90% Laine mérinos de Biella, 10% Pur cachemire de Mongolie ; boutons en corne véritable.",
                origin: "Drap : Biella, Italie · Modélisme : Florence · Confection : Atelier Jakarta",
                care: "Aérer sur cintre adapté après usage. Nettoyage à sec professionnel au plus une fois par saison.",
            },
            it: {
                title: "Manteau Firenze",
                badge: "Nuovo Arrivo",
                color: "Tabacco Toscano",
                desc: "Cappotto maschile architettonico con spalle strutturate e silhouette lunga e solenne. Cucito con canapaflex e crine di cavallo flottante che si adatta alle linee del corpo nel tempo.",
                material:
                    "90% Lana Merino di Biella, 10% Puro Cashmere Mongolo; bottoni in vero corno.",
                origin: "Tessuto: Biella, Italia · Modelleria: Firenze · Sartoria: Atelier Giacarta",
                care: "Arieggiare dopo l'uso su gruccia idonea. Lavaggio a secco professionale massimo una volta a stagione.",
            },
        },
        11: {
            id: 11,
            cat_id: 9,
            price: 8900000,
            color_hex: "#b89858",
            images: ["assets/echarpe_eclat.jpg"],
            id: {
                title: "Écharpe Éclat",
                badge: "Kapsul Adibusana",
                color: "Or Palembang",
                desc: "Selendang adibusana hasil kolaborasi maestro tenun Palembang dan sutra Lyon Prancis. Representasi kemewahan wastra Nusantara di panggung dunia.",
                material:
                    "Sutra 70%, Benang emas metalik 30%, Panel sutra twill Lyon 100%.",
                origin: "Songket: Palembang, Sumatra Selatan · Sutra: Lyon, Prancis · Jahit: Jakarta",
                care: "Dry clean khusus wastra emas. Gulung pada tabung karton bebas asam, jangan dilipat.",
            },
            en: {
                title: "Écharpe Éclat",
                badge: "Couture Capsule",
                color: "Palembang Gold",
                desc: "A haute-couture stole uniting master weavers of Palembang and Lyon silk heritage. A luminous presentation of Indonesian archipelago craftsmanship on the world stage.",
                material:
                    "70% Heritage Silk, 30% Fine Metallic Gold Thread, 100% Lyon Silk Twill Panel.",
                origin: "Songket: Palembang, South Sumatra · Silk: Lyon, France · Tailoring: ZAFIRA Atelier",
                care: "Specialist gold-textile dry clean. Store rolled around an acid-free cylinder; never fold.",
            },
            fr: {
                title: "Écharpe Éclat",
                badge: "Capsule Haute Couture",
                color: "Or Palembang",
                desc: "Étole de haute couture issue de la collaboration entre les maîtres tisserands de Palembang et les soyeux de Lyon. Une célébration de l'art textile indonésien sur la scène mondiale.",
                material:
                    "70% Soie, 30% Fils d'or métallisés, panneau en sergé de soie pure de Lyon 100%.",
                origin: "Songket : Palembang, Sumatra du Sud · Soie : Lyon, France · Confection : Atelier Jakarta",
                care: "Nettoyage à sec spécialisé pour tissages d'or. Rouler sur cylindre sans acide, ne jamais plier.",
            },
            it: {
                title: "Écharpe Éclat",
                badge: "Capsula Alta Moda",
                color: "Oro Palembang",
                desc: "Stola di alta moda nata dalla maestria dei maestri tessitori di Palembang e della seta di Lione. Un omaggio prezioso al patrimonio tessile indonesiano.",
                material:
                    "70% Seta, 30% Filo d'oro metallizzato, pannello in twill di seta di Lione 100%.",
                origin: "Songket: Palembang, Sumatra · Seta: Lione, Francia · Sartoria: Atelier Giacarta",
                care: "Lavaggio a secco specializzato per filati preziosi. Conservare arrotolato, non piegare.",
            },
        },
        12: {
            id: 12,
            cat_id: 9,
            price: 17200000,
            color_hex: "#b45309",
            images: ["assets/robe_aurore.jpg"],
            id: {
                title: "Robe Aurore",
                badge: "Kapsul Adibusana",
                color: "Terracotta Ikat",
                desc: "Gaun malam dengan perpaduan tenun ikat Maumere pewarna alam dan crepe wol Italia. Mengangkat wastra Flores ke ranah keanggunan soirée internasional.",
                material:
                    "Tenun ikat katun pewarna alam 100%, Crepe wol Biella 100%.",
                origin: "Tenun Ikat: Maumere, Flores · Kain Penunjang: Italia · Jahit: Atelier Jakarta",
                care: "Dry clean spesialis tenun tangan. Simpan digantung dalam garment bag kain.",
            },
            en: {
                title: "Robe Aurore",
                badge: "Couture Capsule",
                color: "Terracotta Ikat",
                desc: "An evening gown harmonizing naturally-dyed Maumere ikat with supple Italian wool crepe, elevating Indonesian island heritage into global evening elegance.",
                material:
                    "100% Naturally-dyed cotton Ikat, 100% Biella wool crepe lining.",
                origin: "Ikat: Maumere, Flores · Fine Suiting: Italy · Tailoring: ZAFIRA Atelier Jakarta",
                care: "Specialist handwoven textile dry clean. Store suspended inside breathable garment bag.",
            },
            fr: {
                title: "Robe Aurore",
                badge: "Capsule Haute Couture",
                color: "Terre Cuite Ikat",
                desc: "Robe du soir unissant l'ikat de Maumere aux teintures végétales naturelles et le crêpe de laine italien. Une élévation du patrimoine insulaire indonésien.",
                material:
                    "100% Coton ikat teintures végétales, crêpe de laine de Biella 100%.",
                origin: "Ikat : Maumere, Florès · Étoffes : Italie · Confection : Grand Atelier ZAFIRA",
                care: "Nettoyage à sec spécialisé pour tissages d'art. Conserver sous housse de coton respirant.",
            },
            it: {
                title: "Robe Aurore",
                badge: "Capsula Alta Moda",
                color: "Terracotta Ikat",
                desc: "Abito da sera che unisce il prezioso ikat di Maumere tinto con erbe naturali al crêpe di lana italiano. Eleganza sublime per serate di gala.",
                material:
                    "100% Cotone ikat tinto con radici naturali, crêpe di lana Biella 100%.",
                origin: "Ikat: Maumere, Flores · Tessuti: Italia · Sartoria: Atelier ZAFIRA Giacarta",
                care: "Lavaggio a secco specializzato per tessuti a mano. Custodire in custodia traspirante.",
            },
        },
        4: {
            id: 4,
            cat_id: 4,
            price: 9850000,
            color_hex: "#3b2f2f",
            images: ["assets/mocassin_riva.jpg"],
            id: {
                title: "Mocassin Riva",
                badge: "Signature",
                color: "Espresso",
                desc: "Loafer kulit napa dengan proporsi ramping dan ornamen logo Z berlapis emas pucat. Nyaman untuk mobilitas tinggi tanpa mengorbankan formalitas adibusana.",
                material:
                    "100% Kulit Napa Anak Sapi, sol kulit sol nabati dengan tapak karet tersembunyi.",
                origin: "Penyamakan Kulit: Marche, Italia · Perakitan: Atelier Sepatu ZAFIRA Jakarta",
                care: "Gunakan shoe-tree kayu cedar saat tidak dipakai. Bersihkan dengan krim kulit netral.",
            },
            en: {
                title: "Mocassin Riva",
                badge: "Signature",
                color: "Espresso",
                desc: "A refined calfskin loafer balancing slender proportions with an understated pale gold Z emblem. Engineered for poise and effortless day-to-evening mobility.",
                material:
                    "100% Full-grain Calf Napa, vegetable-tanned leather sole with concealed rubber traction.",
                origin: "Tannery: Marche, Italy · Assembly: ZAFIRA Footwear Atelier Jakarta",
                care: "Insert cedar shoe trees after every wear. Condition with neutral leather balm.",
            },
            fr: {
                title: "Mocassin Riva",
                badge: "Signature",
                color: "Expresso",
                desc: "Mocassin aux lignes racées rehaussé du monogramme Z en finition or pâle satiné. Alliant confort souverain et élégance de salon.",
                material:
                    "100% Cuir nappa de veau pleine fleur, semelle cuir au tannage végétal avec patin gomme.",
                origin: "Tannerie : Les Marches, Italie · Assemblage : Atelier Bottier ZAFIRA Jakarta",
                care: "Placer des embauchoirs en cèdre après usage. Nourrir au cirage incolore de prestige.",
            },
            it: {
                title: "Mocassin Riva",
                badge: "Firma",
                color: "Espresso",
                desc: "Mocassino in pelle di vitello dalle proporzioni slanciate impreziosito dallo stemma Z dorato. Comfort impareggiabile per l'alta società.",
                material:
                    "100% Vitello Nappa pieno fiore, suola in cuoio a concia vegetale con inserto in gomma.",
                origin: "Conceria: Marche, Italia · Assemblaggio: Atelier Calzaturiero ZAFIRA Giacarta",
                care: "Inserire tendiscarpe in cedro. Trattare periodicamente con crema nutriente neutra.",
            },
        },
    };

    // Cross-reference aliases
    MULTI_PRODUCTS[1] = MULTI_PRODUCTS[9];
    MULTI_PRODUCTS[2] = MULTI_PRODUCTS[10];
    MULTI_PRODUCTS[13] = MULTI_PRODUCTS[4];

    // Badge mapping table
    const BADGE_MAP = {
        "Warisan Nusantara": {
            id: "Warisan Nusantara",
            en: "Archipelago Heritage",
            fr: "Héritage de l'Archipel",
            it: "Eredità dell'Arcipelago",
        },
        "Kapsul Adibusana": {
            id: "Kapsul Adibusana",
            en: "Couture Capsule",
            fr: "Capsule Haute Couture",
            it: "Capsula Alta Moda",
        },
        "Edisi Terbatas": {
            id: "Edisi Terbatas",
            en: "Limited Edition",
            fr: "Édition Limitée",
            it: "Edizione Limitata",
        },
        Baru: {
            id: "Baru",
            en: "New Arrival",
            fr: "Nouveauté",
            it: "Nuovo Arrivo",
        },
        Signature: {
            id: "Signature",
            en: "Signature",
            fr: "Signature",
            it: "Firma",
        },
        "Ikon Maison": {
            id: "Ikon Maison",
            en: "Maison Icon",
            fr: "Icône de la Maison",
            it: "Icona della Maison",
        },
        Eksklusif: {
            id: "Eksklusif",
            en: "Exclusive",
            fr: "Exclusivité",
            it: "Esclusivo",
        },
    };

    function getBadgeLocalized(rawBadge, lang) {
        if (!rawBadge) return "";
        if (!lang) lang = getLanguage();
        if (BADGE_MAP[rawBadge] && BADGE_MAP[rawBadge][lang]) {
            return BADGE_MAP[rawBadge][lang];
        }
        for (const bKey in BADGE_MAP) {
            const bObj = BADGE_MAP[bKey];
            if (Object.values(bObj).includes(rawBadge)) {
                return bObj[lang] || rawBadge;
            }
        }
        return rawBadge;
    }

    // --- 3. STORAGE & THEME ENGINE ---
    const THEME_KEY = "zafira_theme";
    const LANG_KEY = "zafira_lang";

    function safeGet(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function safeSet(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {}
    }

    let themeSetting;
    let languageSetting;

    // A present canonical key wins, even when invalid; only absent keys migrate.
    function readPref(key, legacyKey, isValid) {
        const current = safeGet(key);
        if (current !== null) return isValid(current) ? current : null;
        const legacy = safeGet(legacyKey);
        if (isValid(legacy)) {
            safeSet(key, legacy);
            return legacy;
        }
        return null;
    }

    function isValidTheme(value) {
        return value === "light" || value === "dark" || value === "system";
    }

    function isValidLanguage(value) {
        return !!value && Object.prototype.hasOwnProperty.call(I18N, value);
    }

    function getTheme() {
        return (
            themeSetting ||
            (themeSetting =
                readPref(THEME_KEY, "zafira-theme", isValidTheme) || "system")
        );
    }

    function applyTheme(mode) {
        const root = document.documentElement;
        if (!isValidTheme(mode)) mode = "system";
        themeSetting = mode;
        safeSet(THEME_KEY, mode);

        let effectiveTheme = mode;
        if (mode === "system") {
            const isDark =
                window.matchMedia &&
                window.matchMedia("(prefers-color-scheme: dark)").matches;
            effectiveTheme = isDark ? "dark" : "light";
        }
        root.setAttribute("data-theme", effectiveTheme);
        root.setAttribute("data-theme-setting", mode);

        // Apply color-scheme meta
        let meta = document.querySelector('meta[name="color-scheme"]');
        if (!meta) {
            meta = document.createElement("meta");
            meta.name = "color-scheme";
            document.head.appendChild(meta);
        }
        meta.content = effectiveTheme;

        // Update active states on switcher buttons
        document.querySelectorAll("[data-set-theme]").forEach((btn) => {
            const btnMode = btn.getAttribute("data-set-theme");
            const isActive = btnMode === mode;
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-pressed", String(isActive));
            btn.setAttribute("aria-checked", String(isActive));
        });

        // Update theme selects if present
        document.querySelectorAll(".zafira-theme-select").forEach((sel) => {
            sel.value = mode;
        });

        // Update dock widgets
        const dockThemeText = document.getElementById("dock-theme-text");
        if (dockThemeText) {
            const lang = getLanguage();
            const dict = I18N[lang] || I18N.id;
            dockThemeText.textContent =
                mode === "light"
                    ? dict.theme_light || "Terang"
                    : mode === "dark"
                      ? dict.theme_dark || "Gelap"
                      : dict.theme_system || "Sistem";
        }

        // Dispatch event
        window.dispatchEvent(
            new CustomEvent("zafira:theme-changed", {
                detail: { theme: mode, effectiveTheme },
            }),
        );
    }

    function getLanguage() {
        const server = document.documentElement.getAttribute?.("data-server-locale");
        return (languageSetting ||= (isValidLanguage(server) || server === "zh")
            ? server
            : readPref(LANG_KEY, "zafira-lang", isValidLanguage) || "id");
    }

    let pendingLanguage;
    let savingLanguage = false;
    // Last locale the server accepted; a later failure must fall back here, not to the page-load locale.
    let confirmedLanguage;

    async function selectLanguage(lang) {
        if (!isValidLanguage(lang) && lang !== "zh") return;
        const endpoint = document.documentElement.getAttribute?.("data-locale-url");
        if (!endpoint) return applyLanguage(lang);
        pendingLanguage = lang;
        if (savingLanguage) return;
        savingLanguage = true;
        const status = document.getElementById("locale-status");
        const messages = {
            id: ["Menyimpan bahasa…", "Bahasa belum tersimpan. Silakan coba lagi."],
            en: ["Saving language…", "Language could not be saved. Please try again."],
            fr: ["Enregistrement de la langue…", "Impossible d’enregistrer la langue. Réessayez."],
            it: ["Salvataggio della lingua…", "Impossibile salvare la lingua. Riprova."],
            zh: ["正在保存语言…", "无法保存语言，请重试。"],
        };
        // Serialize writes: aborting fetch does not cancel a server-side session/profile write.
        while (pendingLanguage) {
            const chosen = pendingLanguage;
            pendingLanguage = null;
            if (status) status.textContent = messages[getLanguage()][0];
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!token) throw new Error("Missing CSRF token");
                const response = await fetch(endpoint, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": token },
                    body: JSON.stringify({ locale: chosen }),
                });
                if (!response.ok) throw new Error("Locale save failed");
                confirmedLanguage = chosen;
                const url = new URL(window.location.href);
                url.searchParams.delete("locale");
                window.history.replaceState(null, "", url);
                if (!pendingLanguage) {
                    applyLanguage(chosen);
                    if (status) status.textContent = "";
                }
            } catch (error) {
                if (!pendingLanguage) {
                    applyLanguage(confirmedLanguage || getLanguage());
                    if (status) status.textContent = messages[getLanguage()][1];
                }
            }
        }
        savingLanguage = false;
    }

    function applyLanguage(lang) {
        if (!I18N[lang]) lang = "id";
        languageSetting = lang;
        safeSet(LANG_KEY, lang);
        document.documentElement.setAttribute("lang", lang);
        document.documentElement.setAttribute("data-lang", lang);

        const dict = I18N[lang];
        // Explicit lang="fr" islands (brand phrases) retain their language.
        if (document.body?.classList?.contains("storefront")) {
            document.body.setAttribute("lang", lang);
        }

        // 1. Text translations [data-i18n]
        document.querySelectorAll("[data-i18n]").forEach((el) => {
            const key = el.getAttribute("data-i18n");
            if (dict[key] !== undefined) {
                if (dict[key].includes("<br>") || dict[key].includes("&")) {
                    el.innerHTML = dict[key];
                } else {
                    el.textContent = dict[key];
                }
            }
        });

        // 2. Placeholder translations [data-i18n-placeholder]
        document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
            const key = el.getAttribute("data-i18n-placeholder");
            if (dict[key] !== undefined) {
                el.setAttribute("placeholder", dict[key]);
            }
        });

        // 3. Title & Aria translations
        document.querySelectorAll("[data-i18n-title]").forEach((el) => {
            const key = el.getAttribute("data-i18n-title");
            if (dict[key] !== undefined) {
                el.setAttribute("title", dict[key]);
            }
        });
        document.querySelectorAll("[data-i18n-aria-label]").forEach((el) => {
            const key = el.getAttribute("data-i18n-aria-label");
            if (dict[key] !== undefined) {
                el.setAttribute("aria-label", dict[key]);
            }
        });

        // 4. Update language select dropdowns
        document.querySelectorAll(".zafira-lang-select").forEach((sel) => {
            sel.value = lang;
        });

        // 5. Update active states on all language buttons
        document.querySelectorAll("[data-set-lang]").forEach((btn) => {
            const btnLang = btn.getAttribute("data-set-lang");
            const isActive = btnLang === lang;
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-pressed", String(isActive));
            btn.setAttribute("aria-checked", String(isActive));
        });

        // 6. Update dock widgets
        const dockLangCode = document.getElementById("dock-lang-code");
        if (dockLangCode) {
            dockLangCode.textContent = lang.toUpperCase();
        }
        const dockThemeText = document.getElementById("dock-theme-text");
        if (dockThemeText) {
            const mode = getTheme();
            dockThemeText.textContent =
                mode === "light"
                    ? dict.theme_light || "Terang"
                    : mode === "dark"
                      ? dict.theme_dark || "Gelap"
                      : dict.theme_system || "Sistem";
        }

        // 7. Dispatch event for deep view re-rendering (Prototype & Storefront)
        window.dispatchEvent(
            new CustomEvent("zafira:lang-changed", { detail: { lang, dict } }),
        );
    }

    // Helper translation getter
    function t(key, fallback = "") {
        const lang = getLanguage();
        const dict = I18N[lang] || I18N.id;
        return dict[key] !== undefined ? dict[key] : fallback;
    }

    // Currency Formatter
    function formatPrice(amountIdr, langOrCurr) {
        if (!langOrCurr) langOrCurr = getLanguage();
        const l = String(langOrCurr).toLowerCase();
        if (l === "fr" || l === "it" || l === "eur") {
            const eur = Math.round(amountIdr / 16800);
            return eur.toLocaleString("fr-FR") + " €";
        } else if (l === "en" || l === "usd") {
            const usd = Math.round(amountIdr / 15700);
            return "$ " + usd.toLocaleString("en-US");
        }
        return "Rp " + Number(amountIdr || 0).toLocaleString("id-ID");
    }

    // --- 4. ATTACH LISTENERS & INITIALIZE ---
    function init() {
        // 1. Theme initialization
        const currentTheme = getTheme();
        applyTheme(currentTheme);

        // Watch OS system theme changes
        if (window.matchMedia) {
            const media = window.matchMedia("(prefers-color-scheme: dark)");
            media.addEventListener("change", () => {
                if (getTheme() === "system") {
                    applyTheme("system");
                }
            });
        }

        // 2. Language initialization
        const currentLang = getLanguage();
        applyLanguage(currentLang);

        // 3. Delegate click handlers globally
        document.addEventListener("click", (e) => {
            const themeBtn = e.target.closest("[data-set-theme]");
            if (themeBtn) {
                e.preventDefault();
                const mode = themeBtn.getAttribute("data-set-theme");
                applyTheme(mode);
                return;
            }

            const langBtn = e.target.closest("[data-set-lang]");
            if (langBtn) {
                e.preventDefault();
                selectLanguage(langBtn.getAttribute("data-set-lang"));
                return;
            }
        });

        // 4. Delegate select change handlers for language and theme
        document.addEventListener("change", (e) => {
            if (e.target.matches(".zafira-lang-select")) {
                selectLanguage(e.target.value);
            }
            if (e.target.matches(".zafira-theme-select")) {
                applyTheme(e.target.value);
            }
        });

        // 5. Dynamic content auto-translator (MutationObserver)
        if (window.MutationObserver) {
            let debounced = null;
            const observer = new MutationObserver((mutations) => {
                let hasNewNodes = false;
                for (const m of mutations) {
                    if (m.addedNodes && m.addedNodes.length > 0) {
                        hasNewNodes = true;
                        break;
                    }
                }
                if (hasNewNodes) {
                    clearTimeout(debounced);
                    debounced = setTimeout(() => {
                        const cur = getLanguage();
                        const d = I18N[cur] || I18N.id;
                        document
                            .querySelectorAll("[data-i18n]")
                            .forEach((el) => {
                                const k = el.getAttribute("data-i18n");
                                if (
                                    d[k] !== undefined &&
                                    el.getAttribute("data-i18n-cur") !== cur
                                ) {
                                    if (
                                        d[k].includes("<br>") ||
                                        d[k].includes("&")
                                    ) {
                                        el.innerHTML = d[k];
                                    } else {
                                        el.textContent = d[k];
                                    }
                                    el.setAttribute("data-i18n-cur", cur);
                                }
                            });
                    }, 30);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    // Run immediately if DOM ready, or listen to DOMContentLoaded
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    // Expose global API
    window.Zafira = window.Zafira || {};
    window.Zafira.I18N = I18N;
    window.Zafira.MULTI_PRODUCTS = MULTI_PRODUCTS;
    window.Zafira.t = t;
    window.Zafira.formatPrice = formatPrice;
    window.Zafira.getBadgeLocalized = getBadgeLocalized;
    window.Zafira.setLanguage = selectLanguage;
    window.Zafira.getLanguage = getLanguage;
    window.Zafira.setTheme = applyTheme;
    window.Zafira.getTheme = getTheme;

    window.Zafira.toggleThemeQuick = function () {
        const current = getTheme();
        const sequence = ["light", "dark", "system"];
        const next =
            sequence[(sequence.indexOf(current) + 1) % sequence.length];
        applyTheme(next);
    };
    window.Zafira.cycleLangQuick = function () {
        const current = getLanguage();
        const sequence = ["id", "en", "fr", "it"];
        const next =
            sequence[(sequence.indexOf(current) + 1) % sequence.length];
        selectLanguage(next);
    };
    window.toggleZafiraThemeQuick = window.Zafira.toggleThemeQuick;
    window.cycleZafiraLangQuick = window.Zafira.cycleLangQuick;
})();
