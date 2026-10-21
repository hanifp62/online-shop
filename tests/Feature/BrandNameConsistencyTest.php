<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Memastikan nama brand lama ("Amadis") tidak muncul kembali di seluruh berkas
 * yang menjadi permukaan pelanggan/admin (views, routes, controller, mail,
 * notifikasi, aset publik, dan .env.example).
 */
class BrandNameConsistencyTest extends TestCase
{
    private const CUSTOMER_FACING_DIRECTORIES = [
        'resources/views',
        'routes',
        'app/Http',
        'app/Mail',
        'app/Notifications',
        'public',
    ];

    public function test_customer_facing_source_tree_contains_no_amadis_references(): void
    {
        $root = dirname(__DIR__, 2);
        $scanned = 0;

        $env = file_get_contents($root.'/.env.example');
        // Kunci konfigurasi internal tidak tampil kepada pelanggan.
        $this->assertDoesNotMatchRegularExpression('/amadis/i', preg_replace('/^#?\s*AMADIS_[A-Z_]*=.*$/mi', '', $env), '.env.example harus bebas nama brand lama di luar kunci internal');
        $scanned++;

        foreach (self::CUSTOMER_FACING_DIRECTORIES as $relative) {
            $dir = $root.'/'.$relative;
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['php', 'js', 'css', 'html', 'svg'], true)) {
                    continue;
                }
                $relativeName = substr($file->getPathname(), strlen($root) + 1);
                $content = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/amadis/i',
                    $content,
                    "{$relativeName} memuat 'amadis' (wajib ZAFIRA)"
                );
                $scanned++;
            }
        }

        $this->assertGreaterThan(50, $scanned, 'harus memindai minimal 50 berkas permukaan pelanggan');
    }

    public function test_demo_documents_use_single_brand_name(): void
    {
        $dir = dirname(__DIR__, 2).'/docs/demo';
        $checked = 0;
        foreach (glob($dir.'/*.{md,txt}', GLOB_BRACE) as $file) {
            $this->assertStringNotContainsString('ZAFIRA AMADIS', file_get_contents($file), basename($file).' memakai nama ganda "ZAFIRA AMADIS"');
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_demo_product_description_uses_zafira(): void
    {
        $source = file_get_contents(database_path('seeders/AmadisDemoSeeder.php'));
        $this->assertStringContainsString(
            "'product_description' => 'Fixture sintetis ZAFIRA.",
            $source,
            'seeder demo harus menyebut ZAFIRA, bukan AMADIS'
        );
    }
}
