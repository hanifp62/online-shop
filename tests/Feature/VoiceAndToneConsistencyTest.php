<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Memastikan salinan mikrocopy (Blade views) mematuhi matriks nada bicara
 * ZAFIRA dan bebas dari istilah promosi murahan/kasar.
 */
class VoiceAndToneConsistencyTest extends TestCase
{
    /** @var list<string> Frasa yang dilarang oleh matriks nada bicara ZAFIRA. */
    private const BANNED_PHRASES = [
        'diskon gila',
        'banting harga',
        'promo heboh',
        'klik di sini',
        'troli belanja',
        'buruh jahit',
    ];

    /**
     * Gerbang wajib: seluruh permukaan teks (Blade, lang, JS) harus bebas
     * frasa terlarang sebelum salinan dipakai.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function textSurfaces(): array
    {
        $root = dirname(__DIR__, 2);

        $surfaces = [
            'blade views' => [$root.'/resources/views', ['blade.php']],
            'berkas terjemahan' => [$root.'/lang', ['php', 'json']],
            'javascript sumber' => [$root.'/resources/js', ['js']],
            'javascript publik' => [$root.'/public/js', ['js']],
        ];

        // Permukaan yang belum ada di proyek (mis. lang/) otomatis tercakup begitu dibuat.
        return array_filter($surfaces, fn (array $surface): bool => is_dir($surface[0]));
    }

    #[DataProvider('textSurfaces')]
    public function test_text_surfaces_do_not_contain_banned_phrases(string $dir, array $extensions): void
    {
        $violators = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $matchesExtension = false;
            foreach ($extensions as $extension) {
                if (str_ends_with($file->getFilename(), '.'.$extension)) {
                    $matchesExtension = true;
                    break;
                }
            }
            if (! $matchesExtension) {
                continue;
            }

            $content = strtolower(file_get_contents($file->getPathname()));
            foreach (self::BANNED_PHRASES as $banned) {
                if (str_contains($content, $banned)) {
                    $violators[] = $file->getPathname()." memuat frasa terlarang '{$banned}'";
                }
            }
        }

        $this->assertEmpty($violators, "Pelanggaran matriks nada bicara ditemukan:\n".implode("\n", $violators));
    }

    public function test_voice_and_tone_matrix_document_exists(): void
    {
        $matrixPath = dirname(__DIR__, 2).'/docs/02-product/VOICE_AND_TONE_MATRIX.md';
        $this->assertFileExists($matrixPath);

        $content = file_get_contents($matrixPath);
        $this->assertStringContainsString('Elegansi Bersahaja', $content);
        $this->assertStringContainsString('Storefront', $content);
        $this->assertStringContainsString('Checkout', $content);
    }
}
