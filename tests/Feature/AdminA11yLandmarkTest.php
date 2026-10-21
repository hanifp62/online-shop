<?php

namespace Tests\Feature;

use App\Models\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kontrak landmark & nama aksesibel untuk halaman HTML konsol admin. */
class AdminA11yLandmarkTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    public function test_admin_html_pages_have_lang_main_heading_alt_and_named_controls(): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);

        $pages = [
            'login' => [null, route('admin.login')],
            'dashboard' => [$admin, route('admin.dashboard')],
            'auth events' => [$admin, route('admin.auth-events.index')],
        ];

        foreach ($pages as $label => [$actor, $url]) {
            $request = $actor ? $this->actingAs($actor, 'admin') : $this;
            $xpath = $this->xpath($request->get($url)->assertOk()->getContent());

            $this->assertNotSame('', (string) $xpath->query('//html')->item(0)?->getAttribute('lang'), "{$label}: lang");
            $this->assertSame(1, $xpath->query('//main')->length, "{$label}: tepat satu <main>");
            $this->assertGreaterThan(0, $xpath->query('//h1')->length, "{$label}: <h1>");

            foreach ($xpath->query('//img') as $img) {
                $this->assertTrue($img->hasAttribute('alt'), "{$label}: <img> tanpa alt");
            }

            foreach ($xpath->query('//a | //button') as $node) {
                if ($node->getAttribute('aria-hidden') === 'true') {
                    continue;
                }
                $name = trim($node->textContent) ?: trim($node->getAttribute('aria-label')) ?: trim($node->getAttribute('title'));
                $this->assertNotSame('', $name, "{$label}: {$node->nodeName} tanpa nama aksesibel");
            }
        }
    }
}
