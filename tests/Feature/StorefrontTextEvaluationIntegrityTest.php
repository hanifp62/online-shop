<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Memastikan dokumen evaluasi teks tiga kacamata (HCI, marketing, brand)
 * tersinkronisasi dengan berkas tampilan yang sebenarnya dan memuat seluruh
 * butir evaluasi teridentifikasi.
 */
class StorefrontTextEvaluationIntegrityTest extends TestCase
{
    private string $evalPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evalPath = dirname(__DIR__, 2).'/docs/04-implementation/STOREFRONT_TEXT_EVALUATION.md';
    }

    public function test_evaluation_document_exists_and_covers_all_identified_items(): void
    {
        $this->assertFileExists($this->evalPath, 'Dokumen evaluasi teks wajib tersedia.');

        $content = (string) file_get_contents($this->evalPath);
        $this->assertGreaterThan(1000, strlen($content), 'Dokumen evaluasi tidak boleh kosong.');

        // Memastikan seluruh ID temuan E-01 hingga E-13 terpetakan
        for ($i = 1; $i <= 13; $i++) {
            $id = sprintf('E-%02d', $i);
            $this->assertStringContainsString($id, $content, "Temuan {$id} wajib tercatat di dokumen evaluasi.");
        }

        // Memastikan tiga kacamata disebutkan secara eksplisit
        $this->assertStringContainsString('HCI', $content);
        $this->assertStringContainsString('Marketing', $content);
        $this->assertStringContainsString('Brand', $content);

        // Memastikan rujukan ke matriks nada bicara dan keputusan terkait
        $this->assertStringContainsString('VOICE_AND_TONE_MATRIX.md', $content);
    }

    public function test_evaluated_files_actually_exist_in_project(): void
    {
        $content = (string) file_get_contents($this->evalPath);

        preg_match_all('/`resources\/views\/([a-zA-Z0-9_\-\.\/]+)`/', $content, $matches);
        $this->assertNotEmpty($matches[1], 'Harus ada rujukan berkas views.');

        $uniqueFiles = array_unique($matches[1]);
        foreach ($uniqueFiles as $file) {
            // Abaikan pola wildcard seperti errors/*.blade.php
            if (str_contains($file, '*')) {
                continue;
            }
            $fullPath = dirname(__DIR__, 2).'/resources/views/'.$file;
            $this->assertFileExists($fullPath, "Berkas yang dievaluasi [{$file}] harus benar-benar ada di repositori.");
        }
    }
}
