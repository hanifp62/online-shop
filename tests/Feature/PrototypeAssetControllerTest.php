<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrototypeAssetControllerTest extends TestCase
{
    public function test_serves_prototype_css_and_webp_images_with_security_headers_and_without_session_cookies(): void
    {
        $css = $this->get('/prototype/app.css?v=4.0.0');
        $css->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Set-Cookie');
        $this->assertStringStartsWith('text/css', (string) $css->headers->get('Content-Type'));
        $this->assertGreaterThan(100, (int) $css->headers->get('Content-Length', strlen((string) $css->baseResponse->getFile()->getPathname())));

        foreach ([
            'hero_couture.webp',
            'hero_couture-600.webp',
            'chemise_riviere.webp',
            'chemise_riviere-600.webp',
            'minaudiere_saphir.webp',
            'minaudiere_saphir-600.webp',
            'robe_elan.webp',
            'robe_elan-600.webp',
            'mocassin_riva-600.webp',
            'echarpe_eclat-600.webp',
            'robe_aurore-600.webp',
            'manteau_firenze.webp',
            'manteau_firenze-600.webp',
            'sac_seraphine.webp',
            'sac_seraphine-600.webp',
            'celeste_trench.webp',
            'celeste_trench-600.webp',
            'lumiere_scarf.webp',
            'lumiere_scarf-600.webp',
            'nocturne_watch.webp',
            'nocturne_watch-600.webp',
            'kebaya_lumiere.webp',
            'kebaya_lumiere-600.webp',
        ] as $asset) {
            $img = $this->get("/prototype/assets/{$asset}");
            $img->assertOk()
                ->assertHeader('Content-Type', 'image/webp')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('max-age=3600', (string) $img->headers->get('Cache-Control'));
        }
    }

    public function test_supports_conditional_etag_304_responses(): void
    {
        $first = $this->get('/prototype/assets/chemise_riviere.webp');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $second = $this->withHeaders(['If-None-Match' => $etag])
            ->get('/prototype/assets/chemise_riviere.webp');
        $second->assertStatus(304);
    }

    public function test_blocks_path_traversal_dotfiles_and_disallowed_extensions(): void
    {
        foreach ([
            '/prototype/..%2F.env',
            '/prototype/assets/..%2F..%2Fcomposer.json',
            '/prototype/.env',
            '/prototype/assets/.gitkeep',
            '/prototype/index.php',
            '/prototype/assets%5Cchemise_riviere.webp',
            '/prototype/assets/nonexistent.webp',
        ] as $blockedUri) {
            $this->get($blockedUri)->assertNotFound();
        }
    }

    public function test_bare_prototype_redirects_to_trailing_slash(): void
    {
        $this->get('/prototype')->assertRedirect('/prototype/');
    }
}
