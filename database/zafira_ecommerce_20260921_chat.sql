-- ============================================================
-- ZAFIRA ecommerce — SQL lengkap terbaru
-- Database: ecommerce · MariaDB 10.4 · utf8mb4_unicode_ci
-- Dibuat: 2026-09-21 · 15 tabel + prosedur getcat
-- Data: 8 kategori, 12 produk (FR-IT-ID + provenance),
--       1 admin, 1 subscriber, 1 user test, 17 riwayat migrasi
-- Cara pakai:
--   mysql -u root -e "CREATE DATABASE zafira CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root zafira < file_ini.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ecommerce` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `ecommerce`; -- aktifkan baris ini bila import ke database bernama lain sudah dipilih via CLI

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ------------------ admins ------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(300) NOT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ cache ------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ categories ------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `cat_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cat_title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ concierge_requests ------------------
DROP TABLE IF EXISTS `concierge_requests`;
CREATE TABLE `concierge_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `service` varchar(255) NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `concierge_requests_user_id_foreign` (`user_id`),
  CONSTRAINT `concierge_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ jobs / batches ------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ migrations ------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ newsletter_subscribers ------------------
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletter_subscribers_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ password_reset_tokens ------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ products ------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_cat` bigint(20) unsigned NOT NULL,
  `product_brand` varchar(255) NOT NULL,
  `product_title` varchar(255) NOT NULL,
  `product_slug` varchar(255) DEFAULT NULL,
  `product_price` decimal(10,2) NOT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `product_badge` varchar(255) DEFAULT NULL,
  `product_color` varchar(255) DEFAULT NULL,
  `product_alt` varchar(255) DEFAULT NULL,
  `product_description` text DEFAULT NULL,
  `product_material` varchar(255) DEFAULT NULL,
  `product_composition` varchar(500) DEFAULT NULL,
  `product_origin` varchar(255) DEFAULT NULL,
  `product_care` text DEFAULT NULL,
  `product_craft` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `products_product_slug_unique` (`product_slug`),
  KEY `products_product_cat_foreign` (`product_cat`),
  CONSTRAINT `products_product_cat_foreign` FOREIGN KEY (`product_cat`) REFERENCES `categories` (`cat_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ sessions ------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ user_info_backups ------------------
DROP TABLE IF EXISTS `user_info_backups`;
CREATE TABLE `user_info_backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(300) NOT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address1` varchar(300) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_info_backups_user_id_foreign` (`user_id`),
  CONSTRAINT `user_info_backups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ users ------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address1` varchar(300) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ prosedur getcat ------------------
DROP PROCEDURE IF EXISTS `getcat`;
DELIMITER ;;
CREATE PROCEDURE `getcat`(IN `cid` INT)
SELECT * FROM `categories` WHERE `cat_id` = `cid` ;;
DELIMITER ;

-- ============================================================
-- DATA
-- ============================================================

INSERT INTO `admins` (`id`,`name`,`email`,`password`,`remember_token`,`created_at`,`updated_at`) VALUES
(1,'admin','admin@gmail.com','$2y$12$deLVSZDyR9vSf37J7q8aruHX0jScP97hxwgOv0wQDuhmsRRl.uRvK','cv1iyaKCTC5OPE2ISGunbeHXs2I05kNDKMB1Snz2X2pNr7eMXTCqEc1UswkQ','2026-09-13 20:26:06','2026-09-13 20:26:06');

INSERT INTO `categories` (`cat_id`,`cat_title`,`created_at`,`updated_at`) VALUES
(1,'Busana Wanita','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(2,'Busana Pria','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(3,'Tas Tangan','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(4,'Sepatu','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(5,'Aksesori','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(6,'Perhiasan','2026-09-13 20:26:05','2026-09-13 20:26:05'),
(8,'Warisan Nusantara','2026-09-20 19:39:42','2026-09-20 19:39:42'),
(9,'Koleksi Kapsul','2026-09-20 19:39:42','2026-09-20 19:39:42');

INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_08_31_000000_create_concierge_requests_table',1),
(5,'2026_08_31_000001_create_newsletter_subscribers_table',1),
(6,'2026_09_07_034040_create_categories_table',1),
(7,'2026_09_07_034058_create_products_table',1),
(8,'2026_09_07_120000_add_product_details_columns',1),
(9,'2026_09_07_125000_make_concierge_schedule_nullable',1),
(10,'2026_09_14_000001_add_legacy_profile_fields_to_users_table',1),
(11,'2026_09_14_000002_create_admins_table',1),
(12,'2026_09_14_000003_create_user_info_backups_table',1),
(13,'2026_09_14_000004_create_getcat_procedure',1),
(14,'2026_09_14_000005_add_remember_token_to_admins_table',1),
(15,'2026_09_14_000006_add_user_id_to_concierge_requests_table',1),
(17,'2026_09_21_000001_add_product_quality_columns',2),
(19,'2026_09_21_000002_add_unique_to_product_slug',3);

INSERT INTO `newsletter_subscribers` (`id`,`email`,`created_at`,`updated_at`) VALUES
(1,'admin@gmail.com','2026-09-13 20:26:06','2026-09-13 20:26:06');

INSERT INTO `users` (`id`,`name`,`first_name`,`last_name`,`email`,`mobile`,`address1`,`address2`,`email_verified_at`,`password`,`remember_token`,`created_at`,`updated_at`) VALUES
(1,'Test User','Jagapati','Yuliarti','test@example.com','087710854229','Dk. Bakhita No. 195','Palangka Raya','2026-09-13 20:26:06','$2y$12$68Ba6YB2wU0aPLgbWUj5E.jros0te4FU64tr9G3xrIakS0ehMF2eG','VwW883o2Le','2026-09-13 20:26:06','2026-09-13 20:26:06');

-- concierge_requests, user_info_backups, password_reset_tokens: kosong (transaksional/audit berjalan)
-- cache, cache_locks, sessions, jobs, job_batches, failed_jobs: kosong (transien, dibuat ulang otomatis)

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(1,3,'ZAFIRA','Sac Séraphine','sac-seraphine',18750000.00,'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1200&q=88','Ikon Maison','Cognac','Tas tangan kulit cokelat klasik','Siluet berstruktur dalam kulit anak sapi Italia, diselesaikan dengan kunci safir khas Maison.','Kulit anak sapi full-grain','100% kulit anak sapi, lining katun twill, kunci logam berlapis emas pucat','Kulit: Santa Croce, Toskana · Jahit: Atelier Jakarta','Simpan dalam dust bag, hindari paparan air dan parfum langsung. Kondisikan kulit tiap 6 bulan.','Dipotong tangan, jahitan saddle-stitch 7 titik per cm, tepi dicat dan dipoles 4 lapis.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(2,1,'ZAFIRA','Trench Céleste','celeste-trench',12400000.00,'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=1200&q=88','Baru','Travertine','Busana wanita warna krem','Trench lembut dengan konstruksi Parisian tailoring dan detail pinggang yang terinspirasi arsitektur Firenze.','Gabardine katun bonded','78% katun, 22% poliamida daur ulang; lining 100% cupro','Kain: Como, Italia · Jahit: Atelier Jakarta','Dry clean saja. Angin-anginkan setelah dipakai hujan, simpan digantung dengan shoulder yang tepat.','Kerah digunting tunggal tanpa sambungan, jahitan rantai ganda pada seam beban, kancing corozo dijahit silang.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(3,1,'ZAFIRA','Robe Élan','elan-dress',15900000.00,'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=1200&q=88','Eksklusif','Sapphire Night','Gaun malam elegan','Gaun sutra dengan drapery cair, dibuat untuk bergerak anggun dari aperitivo hingga soirée.','Sutra crepe de chine','100% sutra murbei 22 momme; furing 100% sutra habotai','Kain: Lyon, Prancis · Jahit: Atelier Jakarta','Dry clean dengan spesialis sutra. Simpan dalam garment bag kain, hindari gantungan kawat.','Drapery bias-cut satu panel, kelim tangan rolled-hem, resleting tersembunyi buatan tangan.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(4,4,'ZAFIRA','Mocassin Riva','riva-loafer',9850000.00,'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1200&q=88',NULL,'Espresso','Sepatu kulit wanita','Loafer kulit napa dengan proporsi ramping dan ornamen Z berlapis emas pucat.','Kulit napa','Upper 100% kulit napa, sol kulit dengan sisipan karet anti-selip','Kulit: Marche, Italia · Jahit: Atelier Jakarta','Semir krim netral tiap 8–10 kali pakai. Gunakan shoe tree cedar setelah dipakai.','Konstruksi Blake-stitched agar bisa disol ulang, apron dijahit tangan, ornamen Z dicor presisi.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(5,3,'ZAFIRA','Minaudière Saphir','saphir-clutch',22500000.00,'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=1200&q=88','Edisi Terbatas','Bleu Zafira','Tas pesta biru gelap','Minaudière malam berlapis satin biru safir dengan bingkai pahatan tangan dari atelier kami.','Satin sutra + bingkai logam pahat','Satin 100% sutra, bingkai kuningan berlapis emas, 40 kristal potong tangan','Satin: Lyon, Prancis · Pahat: Atelier Jakarta','Simpan dalam kotak berlapis beludru. Bersihkan satin dengan kain kering lembut, jangan cuci.','Bingkai dipahat tangan 12 jam per buah, nomor edisi diukir di dalam, 40 kristal dipasang satu per satu.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(6,2,'ZAFIRA','Manteau Firenze','firenze-coat',19800000.00,'https://images.unsplash.com/photo-1617137968427-85924c800a22?auto=format&fit=crop&w=1200&q=88','Baru','Tuscan Tobacco','Mantel pria berpotongan klasik','Mantel wol-kasmir dengan bahu arsitektural dan potongan panjang, dijahit oleh tangan ahli.','Wol double-face + kasmir','90% wol merino, 10% kasmir; kancing tanduk asli','Kain: Biella, Italia · Jahit: Atelier Jakarta','Angin-anginkan tiap selesai pakai, sikat dengan sikat pakaian alami. Dry clean maksimal 1x per musim.','Lapel digulung tangan (hand-rolled), canvas dada full-floating, lubang kancing boutonnière fungsional.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(7,5,'ZAFIRA','Carré Lumière','lumiere-scarf',4950000.00,'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d?auto=format&fit=crop&w=1200&q=88','Hadiah Pilihan','Sapphire & Sand','Syal sutra bermotif','Syal twill sutra dengan ilustrasi cahaya yang terurai melalui faset batu safir.','Twill sutra','100% sutra twill 14 momme, pewarnaan sablon tangan 12 warna','Sutra: Lyon, Prancis · Sablon: Pekalongan, Indonesia','Cuci tangan air dingin dengan sabun sutra, jemur teduh. Setrika suhu rendah dengan kain pelapis.','Motif digambar eksklusif untuk Maison, tepi hand-rolled, tiap warna disablon terpisah.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(8,6,'ZAFIRA','Montre Nocturne','nocturne-watch',28900000.00,'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?auto=format&fit=crop&w=1200&q=88','Signature','Midnight Blue','Jam tangan klasik','Arloji berprofil tipis dengan dial biru matahari, penanda emas, dan strap kulit buatan tangan.','Baja + safir + kulit','Case baja 316L, kristal safir anti-gores, movement otomatis 24 jewel, strap kulit aligator-grain','Movement: Besançon, Prancis · Perakitan & strap: Atelier Jakarta','Servis movement tiap 4–5 tahun. Hindari medan magnet dan air panas; lap case dengan kain mikrofiber.','Dial sunburst dilapisi 7 lapis lak, penanda emas dipasang tangan, tiap movement diuji 5 posisi 72 jam.','2026-09-13 20:26:05','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(9,8,'ZAFIRA','Kebaya Lumière','kebaya-lumiere',14900000.00,'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=1200&q=88','Warisan Nusantara','Ivoire & Or','Kebaya modern dengan bordir halus','Kebaya kontemporer dengan siluet Parisian dan bordir tangan pengrajin Kebayoran — warisan Indonesia yang berbicara Prancis.','Brokat + sutra organza','Brokat 60% viskosa 40% poliamida motif eksklusif, lapis sutra organza, payet kaca','Brokat: Calais, Prancis · Bordir tangan: Kebayoran, Jakarta · Jahit: Atelier Jakarta','Dry clean spesialis bordir. Simpan digantung dengan bantalan bahu, tutup dengan kain katun.','Bordir tambal-tangan 60 jam per helai oleh 3 pengrajin, kutu baru dijahit tangan, pola digunting mengikuti motif.','2026-09-20 19:39:42','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(10,8,'ZAFIRA','Chemise Rivière','chemise-riviere',6850000.00,'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=1200&q=88','Warisan Nusantara','Indigo Solo','Kemeja batik tulis kontemporer','Kemeja batik tulis Solo dalam potongan chemise Prancis — canting malam bertemu kerapian kerah Italia.','Katun primisima batik tulis','100% katun primisima, pewarnaan indigo alam 9 celupan, kancing kerang asli','Batik tulis: Laweyan, Solo · Jahit: Atelier Jakarta','Cuci tangan dengan lerak atau sampo lembut, jemur teduh terbalik. Jangan diperas.','Satu helai batik ditulis 3 minggu oleh pembatik senior, motif Rivière digambar eksklusif, jahitan French seam.','2026-09-20 19:39:42','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(11,9,'ZAFIRA','Écharpe Éclat','echarpe-eclat',8900000.00,'https://images.unsplash.com/photo-1583743814966-8936f5b7fdf1?auto=format&fit=crop&w=1200&q=88','Koleksi Kapsul','Or Palembang','Selendang songket dengan benang emas','Selendang songket Palembang berbenang emas, disandingkan sutra Lyon — kapsul pertemuan dua sungai mode.','Songket sutra + benang emas','Sutra 70%, benang emas metalik 30%, panel sutra Lyon 100% di ujung','Songket: Palembang, Indonesia · Sutra panel: Lyon, Prancis · Jahit: Atelier Jakarta','Dry clean saja. Gulung, jangan lipat, untuk menjaga benang emas. Simpan dengan silica gel.','Ditenun di alat tenun kayu 6 minggu per helai, motif lepus eksklusif, sambungan panel dijahit tangan tak terlihat.','2026-09-20 19:39:42','2026-09-20 19:39:42');

INSERT INTO `products` (`product_id`,`product_cat`,`product_brand`,`product_title`,`product_slug`,`product_price`,`product_image`,`product_badge`,`product_color`,`product_alt`,`product_description`,`product_material`,`product_composition`,`product_origin`,`product_care`,`product_craft`,`created_at`,`updated_at`) VALUES
(12,9,'ZAFIRA','Robe Aurore','robe-aurore',17200000.00,'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1200&q=88','Koleksi Kapsul','Terracotta Ikat','Gaun tenun ikat kontemporer','Gaun tenun ikat Flores dalam konstruksi couture — geometri ikat bertemu drapery lembut ala Riviera.','Tenun ikat + crepe wol','Tenun ikat katun 100% pewarna alam, crepe wol 100% untuk drapery, furing cupro','Tenun ikat: Maumere, Flores · Crepe: Biella, Italia · Jahit: Atelier Jakarta','Dry clean. Simpan digantung dalam garment bag; hindari parfum langsung pada tenun.','Kain ikat dipilih motif per helai agar simetris di badan, drapery bias-cut, kelim tangan penuh.','2026-09-20 19:39:42','2026-09-20 19:39:42');

SET FOREIGN_KEY_CHECKS=1;
