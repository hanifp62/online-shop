<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Anggaran performa HP: ukuran aset gambar terukur, dimensi eksplisit (anti-CLS), lazy-load di bawah fold. */
class MobilePerformanceBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_IMAGE_BUDGET = 80 * 1024;

    private const DESKTOP_IMAGE_BUDGET = 170 * 1024;

    public function test_every_local_asset_has_webp_variants_within_budget(): void
    {
        $jpgs = glob(public_path('prototype/assets/*.jpg'));
        $this->assertNotEmpty($jpgs);

        foreach ($jpgs as $jpg) {
            $base = substr($jpg, 0, -4);
            $this->assertFileExists($base.'-600.webp');
            $this->assertFileExists($base.'.webp');
            $this->assertLessThanOrEqual(self::MOBILE_IMAGE_BUDGET, filesize($base.'-600.webp'), basename($base).'-600.webp melebihi anggaran HP');
            $this->assertLessThanOrEqual(self::DESKTOP_IMAGE_BUDGET, filesize($base.'.webp'), basename($base).'.webp melebihi anggaran desktop');
        }
    }

    public function test_home_serves_responsive_hero_and_lazy_images_with_dimensions(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('hero_couture-600.webp', $html);
        $this->assertMatchesRegularExpression('/<link rel="preload" as="image"[^>]*hero_couture-600\.webp/', $html);
        $this->assertImagesAreStable($html);
    }

    public function test_auth_pages_use_responsive_visual(): void
    {
        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('hero_couture-600.webp 600w', $html);
            $this->assertImagesAreStable($html);
        }
    }

    public function test_product_srcset_only_for_width_aware_cdn(): void
    {
        $cdn = new Product(['product_image' => 'https://images.unsplash.com/p?auto=format&w=1200&q=88']);
        $this->assertSame(
            'https://images.unsplash.com/p?auto=format&w=400&q=88 400w, https://images.unsplash.com/p?auto=format&w=800&q=88 800w',
            $cdn->imageSrcset([400, 800])
        );

        $this->assertSame('', (new Product(['product_image' => '/storage/a.jpg']))->imageSrcset());
        $this->assertSame('', (new Product(['product_image' => null]))->imageSrcset());
    }

    /** Setiap <img> wajib punya width+height; selain gambar prioritas (fetchpriority=high) wajib lazy. */
    private function assertImagesAreStable(string $html): void
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);
        $this->assertNotEmpty($matches[0]);

        foreach ($matches[0] as $img) {
            $this->assertMatchesRegularExpression('/\swidth="\d+"/', $img, "Tanpa width: $img");
            $this->assertMatchesRegularExpression('/\sheight="\d+"/', $img, "Tanpa height: $img");
            if (! str_contains($img, 'fetchpriority="high"') && ! str_contains($img, 'id="') && ! str_contains($img, 'data-quick-image')) {
                $this->assertStringContainsString('loading="lazy"', $img, "Gambar di bawah fold tidak lazy: $img");
            }
        }
    }
}
