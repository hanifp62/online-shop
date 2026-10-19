<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AmadisDemoSeeder extends Seeder
{
    public const PRODUCT_COUNT = 76;

    public const STOCK_TEST_SLUG = 'amadis-demo-stock-37';

    public function run(): void
    {
        require_once base_path('scripts/amadis/guard.php');
        $root = amadisGuard();
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== $root.'/database.sqlite') {
            throw new RuntimeException('Demo seeding rejected: active connection differs from isolated identity.');
        }

        $connection->transaction(function () use ($connection): void {
            $categories = [
                ['title' => 'DEMO — Busana Couture', 'piece' => 'Robe Lumière', 'material' => 'Sutra'],
                ['title' => 'DEMO — Maroquinerie', 'piece' => 'Sac Saphir', 'material' => 'Kulit'],
                ['title' => 'DEMO — Aksesori Sutra', 'piece' => 'Carré Éclat', 'material' => 'Sutra'],
                ['title' => 'DEMO — Warisan Nusantara', 'piece' => 'Selendang Ikat', 'material' => 'Tenun katun'],
            ];
            $slugs = [self::STOCK_TEST_SLUG];
            for ($number = 2; $number <= self::PRODUCT_COUNT; $number++) {
                $slugs[] = sprintf('amadis-demo-%03d', $number);
            }

            // Refuse mixed datasets rather than deleting or relabeling existing records.
            if ($connection->table('products')->whereNotIn('product_slug', $slugs)->exists()
                || $connection->table('products')->whereNull('product_slug')->exists()
                || $connection->table('categories')->whereNotIn('cat_title', array_column($categories, 'title'))->exists()) {
                throw new RuntimeException('Demo seeding rejected: non-demo catalog exists. Use a new isolated database.');
            }

            $categoryIds = [];
            foreach ($categories as $category) {
                $matches = $connection->table('categories')->where('cat_title', $category['title']);
                if ((clone $matches)->count() > 1) {
                    throw new RuntimeException('Demo seeding rejected: duplicate category title.');
                }
                $categoryIds[] = $matches->value('cat_id') ?? $connection->table('categories')->insertGetId([
                    'cat_title' => $category['title'],
                    'created_at' => '2026-10-12 00:00:00',
                    'updated_at' => '2026-10-12 00:00:00',
                ], 'cat_id');
            }

            foreach ($slugs as $index => $slug) {
                // Preserve stock already consumed by experiments; reruns never refill inventory.
                if ($connection->table('products')->where('product_slug', $slug)->exists()) {
                    continue;
                }
                $categoryIndex = intdiv($index, 19);
                $category = $categories[$categoryIndex];
                $number = $index + 1;
                $connection->table('products')->insert([
                    'product_cat' => $categoryIds[$categoryIndex],
                    'product_brand' => 'ZAFIRA DEMO',
                    'product_title' => $index === 0 ? 'DEMO — AM-10 Stock 37' : sprintf('DEMO — %s %02d', $category['piece'], $number),
                    'product_slug' => $slug,
                    'product_price' => 1250000 + $index * 250000,
                    'stock' => $index === 0 ? 37 : 10 + ($index % 11),
                    'is_active' => true,
                    'product_image' => null,
                    'product_badge' => 'Données de démonstration',
                    'product_color' => ['Sapphire', 'Noir', 'Crème', 'Espresso'][$index % 4],
                    'product_alt' => 'Produk sintetis untuk demonstrasi; bukan foto produk nyata.',
                    'product_description' => 'Fixture sintetis AMADIS. Harga, stok, dan narasi bukan inventori atau transaksi operasional.',
                    'product_material' => $category['material'],
                    'product_composition' => 'Komposisi simulasi; bukan klaim sertifikasi bahan.',
                    'product_origin' => 'Atelier demonstrasi — provenance simulasi.',
                    'product_care' => 'Panduan perawatan demonstrasi; validasi sebelum penggunaan nyata.',
                    'product_craft' => 'Narasi kriya simulasi untuk menguji tampilan detail.',
                    'created_at' => '2026-10-12 00:00:00',
                    'updated_at' => '2026-10-12 00:00:00',
                ]);
            }

            if ($connection->table('products')->count() !== self::PRODUCT_COUNT
                || $connection->table('products')->where('is_active', true)->count() !== self::PRODUCT_COUNT
                || $connection->table('categories')->count() !== 4) {
                throw new RuntimeException('Demo fixture invariant failed: expected 76 active products and four categories.');
            }
        });
    }
}
