<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationCatalogParityTest extends TestCase
{
    public static function catalogs(): array
    {
        return array_map(fn ($locale) => [$locale, dirname(__DIR__, 2).'/lang/'.$locale.'/zafira.php'], ['id', 'en', 'fr', 'it', 'zh']);
    }

    #[DataProvider('catalogs')]
    public function test_catalog_has_same_keys_as_indonesian(string $locale, string $path): void
    {
        $this->assertFileExists($path);
        $reference = require dirname(__DIR__, 2).'/lang/id/zafira.php';
        $catalog = require $path;
        $this->assertSame(array_keys($reference), array_keys($catalog), $locale);
        foreach ($catalog as $value) {
            $this->assertIsString($value);
            $this->assertNotSame('', $value);
        }
        $this->assertSame($catalog, trans('zafira', [], $locale));
    }
}
