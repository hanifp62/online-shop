<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorefrontCopyUsabilityTestIntegrityTest extends TestCase
{
    public function test_usability_test_kit_exists_and_discloses_unrun_sessions(): void
    {
        $root = dirname(__DIR__, 2);
        $docPath = $root.'/docs/04-implementation/STOREFRONT_COPY_USABILITY_TEST.md';
        $this->assertFileExists($docPath);

        $content = (string) file_get_contents($docPath);
        foreach (range(1, 9) as $number) {
            $this->assertStringContainsString(sprintf('E-%02d', $number), $content);
        }
        $this->assertStringContainsString('BELUM DIJALANKAN', $content);

        preg_match_all('/\| E-\d{2} \| `([^`]+)`/', $content, $matches);
        $this->assertCount(9, $matches[1], 'Tabel tujuan wajib memuat frasa Sesudah E-01..E-09.');
        $sourcePaths = [
            $root.'/resources/views',
            $root.'/resources/js',
            $root.'/public/js',
        ];
        $source = '';
        foreach ($sourcePaths as $path) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                    $source .= file_get_contents($file->getPathname());
                }
            }
        }

        $translatedPhrases = [
            'Tas belanja versi lama di peramban ini' => ['cart', 'legacy_title'],
            'Potongan voucher dan total akhir dipastikan saat pesanan dibuat.' => ['shop', 'summary_note'],
            'Pantau dan kelola pesanan koleksi ZAFIRA Anda.' => ['shop', 'lead'],
            'ZAFIRA — La Maison de Lumière · Haute Couture' => ['welcome', 'page_title'],
        ];

        foreach (array_unique($matches[1]) as $phrase) {
            if (isset($translatedPhrases[$phrase])) {
                [$group, $key] = $translatedPhrases[$phrase];
                $this->assertSame($phrase, trans("shop_ui.$group.$key", [], 'id'));
                $this->assertStringContainsString("__('shop_ui.$group.$key')", $source);
                foreach (['en', 'fr', 'it', 'zh'] as $locale) {
                    $text = trans("shop_ui.$group.$key", [], $locale);
                    $this->assertNotSame("shop_ui.$group.$key", $text, $locale);
                    $this->assertNotSame('', $text, $locale);
                }
            } else {
                $this->assertStringContainsString($phrase, $source, "Frasa Sesudah [{$phrase}] harus ada pada source storefront.");
            }
        }
    }
}
