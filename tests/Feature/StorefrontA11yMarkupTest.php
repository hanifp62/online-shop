<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang aksesibilitas markup storefront (WCAG 2.2 AA, dicek statis dari HTML
 * terender). Ini bukan pengganti audit axe/browser, tetapi menutup regresi yang
 * paling sering muncul: landmark hilang, gambar tanpa alt, kontrol tanpa nama.
 */
class StorefrontA11yMarkupTest extends TestCase
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

    private function seedCatalog(): Product
    {
        $category = Category::create(['cat_title' => 'Robe de Soirée']);

        return Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'Maison ZAFIRA',
            'product_title' => 'Robe Lumière',
            'product_slug' => 'robe-lumiere',
            'product_price' => 125000000,
            'stock' => 4,
        ]);
    }

    /** Halaman publik yang wajib lulus pemeriksaan dasar. */
    private function publicPages(): array
    {
        $product = $this->seedCatalog();

        return [
            'home' => route('home'),
            'katalog' => route('catalog.index'),
            'belanja' => route('shop.index'),
            'produk' => route('products.show', $product->product_slug),
            'masuk' => route('login'),
            'daftar' => route('register'),
            'lupa kata sandi' => route('password.request'),
        ];
    }

    public function test_public_pages_declare_language_title_and_a_single_main_landmark(): void
    {
        foreach ($this->publicPages() as $label => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $xpath = $this->xpath($html);

            $lang = $xpath->query('//html')->item(0)?->getAttribute('lang');
            $this->assertNotSame('', (string) $lang, "{$label}: <html lang> wajib terisi");

            $title = $xpath->query('//title')->item(0)?->textContent;
            $this->assertNotSame('', trim((string) $title), "{$label}: <title> wajib terisi");

            $this->assertSame(1, $xpath->query('//main')->length, "{$label}: tepat satu <main>");
            $this->assertGreaterThan(0, $xpath->query('//h1')->length, "{$label}: wajib punya <h1>");
        }
    }

    public function test_every_image_on_public_pages_has_an_alt_attribute(): void
    {
        foreach ($this->publicPages() as $label => $url) {
            $xpath = $this->xpath($this->get($url)->assertOk()->getContent());

            foreach ($xpath->query('//img') as $img) {
                $this->assertTrue(
                    $img->hasAttribute('alt'),
                    "{$label}: <img src=\"{$img->getAttribute('src')}\"> tanpa atribut alt"
                );
            }
        }
    }

    public function test_every_interactive_control_on_public_pages_has_an_accessible_name(): void
    {
        foreach ($this->publicPages() as $label => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $xpath = $this->xpath($html);

            foreach ($xpath->query('//a | //button') as $node) {
                $name = trim($node->textContent)
                    ?: trim($node->getAttribute('aria-label'))
                    ?: trim($node->getAttribute('title'));

                if ($name === '' && $node->getAttribute('aria-labelledby') !== '') {
                    $id = $node->getAttribute('aria-labelledby');
                    $name = trim((string) $xpath->query("//*[@id='{$id}']")->item(0)?->textContent);
                }

                if ($name === '' && $node->getAttribute('aria-hidden') === 'true') {
                    continue;
                }

                $outline = $node->nodeName.' '.($node->getAttribute('href') ?: $node->getAttribute('class'));
                $this->assertNotSame('', $name, "{$label}: kontrol tanpa nama aksesibel → {$outline}");
            }
        }
    }

    public function test_every_form_field_on_public_pages_is_programmatically_labelled(): void
    {
        foreach ($this->publicPages() as $label => $url) {
            $xpath = $this->xpath($this->get($url)->assertOk()->getContent());

            foreach ($xpath->query('//input | //select | //textarea') as $field) {
                if (in_array($field->getAttribute('type'), ['hidden', 'submit', 'button'], true)) {
                    continue;
                }

                $id = $field->getAttribute('id');
                $labelled = ($id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0)
                    || trim($field->getAttribute('aria-label')) !== ''
                    || $field->getAttribute('aria-labelledby') !== ''
                    || $xpath->query('ancestor::label', $field)->length > 0;

                $this->assertTrue(
                    $labelled,
                    "{$label}: field name=\"{$field->getAttribute('name')}\" tanpa label terprogram"
                );
            }
        }
    }

    public function test_login_validation_errors_are_linked_to_their_field(): void
    {
        $this->from(route('login'))
            ->post(route('login.process'), ['email' => 'bukan-email', 'password' => ''])
            ->assertRedirect(route('login'));

        $xpath = $this->xpath($this->get(route('login'))->assertOk()->getContent());
        $input = $xpath->query("//input[@name='email']")->item(0);

        $this->assertNotNull($input, 'input email terender');
        $this->assertSame('true', $input->getAttribute('aria-invalid'));

        $target = $input->getAttribute('aria-describedby');
        $this->assertNotSame('', $target, 'email wajib menunjuk pesan galatnya');
        $this->assertNotSame(
            '',
            trim((string) $xpath->query("//*[@id='{$target}']")->item(0)?->textContent),
            'target aria-describedby wajib berisi pesan'
        );
    }

    public function test_authenticated_account_pages_keep_the_same_landmark_contract(): void
    {
        $user = User::factory()->create();

        foreach ([
            'akun' => route('account.show'),
            'totp enrol' => route('account.totp.enrol'),
        ] as $label => $url) {
            $xpath = $this->xpath($this->actingAs($user)->get($url)->assertOk()->getContent());

            $this->assertSame(1, $xpath->query('//main')->length, "{$label}: tepat satu <main>");
            $this->assertGreaterThan(0, $xpath->query('//h1')->length, "{$label}: wajib punya <h1>");

            foreach ($xpath->query('//img') as $img) {
                $this->assertTrue($img->hasAttribute('alt'), "{$label}: <img> tanpa alt");
            }
        }
    }

    public function test_api_endpoints_return_json_not_empty_html(): void
    {
        $user = User::factory()->create();
        $this->get(route('cart.show'))->assertOk()->assertHeader('content-type', 'application/json');
        $this->actingAs($user)->get(route('orders.index'))->assertOk()->assertHeader('content-type', 'application/json');
    }
}
