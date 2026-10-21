<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Memastikan dokumen inventarisasi teks permukaan toko tetap sinkron
 * dengan seluruh berkas Blade yang ada di aplikasi.
 */
class StorefrontTextInventoryIntegrityTest extends TestCase
{
    public function test_all_blade_views_are_listed_in_inventory_document(): void
    {
        $root = dirname(__DIR__, 2);
        $docPath = $root.'/docs/04-implementation/STOREFRONT_TEXT_INVENTORY.md';
        $this->assertFileExists($docPath);

        $docContent = file_get_contents($docPath);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/resources/views'));
        $missing = [];
        $checked = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $relPath = str_replace($root.'/', '', $file->getPathname());
            if (! str_contains($docContent, $relPath)) {
                $missing[] = $relPath;
            }
            $checked++;
        }

        $this->assertGreaterThan(30, $checked, 'Jumlah berkas blade yang diperiksa');
        $this->assertEmpty($missing, "Berkas Blade berikut belum dicatat pada inventaris teks:\n".implode("\n", $missing));
    }
}
