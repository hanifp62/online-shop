<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidingMicrocopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_images_without_description_keep_a_meaningful_name(): void
    {
        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'Maison ZAFIRA',
            'product_title' => 'Robe Lumière',
            'product_slug' => 'robe-lumiere', 'product_price' => 100000,
            'product_image' => '/robe.jpg', 'product_alt' => null, 'stock' => 3,
        ]);
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            foreach (['/', '/collections', '/pieces/'.$product->product_slug] as $url) {
                // Use named routes so URL changes do not weaken this rendering check.
                $route = match ($url) {
                    '/' => route('home'),
                    '/collections' => route('catalog.index'),
                    default => route('products.show', $product->product_slug),
                };
                $dom = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8">'.$this->get($route.'?locale='.$locale)->assertOk()->getContent());
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $xpath = new DOMXPath($dom);
                $images = $xpath->query('//img[@src="/robe.jpg"]');
                $this->assertGreaterThan(0, $images->length);
                foreach ($images as $image) {
                    $this->assertSame('Robe Lumière', $image->getAttribute('alt'));
                    $this->assertDoesNotMatchRegularExpression('/\.(jpg|png|webp)$/i', $image->getAttribute('alt'));
                }
                foreach ($xpath->query('//button | //a') as $control) {
                    $name = trim($control->getAttribute('aria-label') ?: $control->textContent);
                    $this->assertDoesNotMatchRegularExpression('/^(OK|Kirim|Submit|Click here|klik di sini)$/iu', $name);
                }
            }
        }
    }

    public function test_blank_alt_and_title_use_localized_product_label_across_storefront(): void
    {
        $category = Category::create(['cat_title' => 'Couture']);
        // Blank (empty) and whitespace-only values both bypass the NOT NULL column constraint.
        foreach (['empty' => '', 'whitespace' => " \t "] as $slug => $blank) {
            Product::create([
                'product_cat' => $category->cat_id, 'product_brand' => 'Maison ZAFIRA',
                'product_title' => $blank, 'product_slug' => $slug,
                'product_price' => 100000, 'product_image' => '/untitled.jpg',
                'product_alt' => $blank, 'stock' => 3,
            ]);
        }
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $this->assertNotSame('customer_messages.storefront.product_image', trans('customer_messages.storefront.product_image', [], $locale));
            foreach ([route('home'), route('catalog.index'), route('products.show', 'empty'), route('products.show', 'whitespace')] as $route) {
                $dom = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8">'.$this->get($route.'?locale='.$locale)->assertOk()->getContent());
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $xpath = new DOMXPath($dom);
                $images = $xpath->query('//img[@src="/untitled.jpg"]');
                $this->assertGreaterThan(0, $images->length);
                foreach ($images as $image) {
                    $this->assertSame(trans('customer_messages.storefront.product_image', [], $locale), $image->getAttribute('alt'));
                    $this->assertNotSame('', trim($image->getAttribute('alt')));
                }
                foreach ($xpath->query('//*[@data-image="/untitled.jpg"][@data-alt]') as $trigger) {
                    $this->assertSame(trans('customer_messages.storefront.product_image', [], $locale), $trigger->getAttribute('data-alt'));
                }
            }
        }
    }

    public function test_explicit_alt_and_title_remain_unchanged(): void
    {
        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'Maison ZAFIRA',
            'product_title' => 'Robe Lumière', 'product_slug' => 'explicit-robe',
            'product_price' => 100000, 'product_image' => '/explicit.jpg',
            'product_alt' => 'Robe brodée bleue', 'stock' => 3,
        ]);
        foreach ([route('home'), route('catalog.index'), route('products.show', $product->product_slug)] as $route) {
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$this->get($route)->assertOk()->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//img[@src="/explicit.jpg"]') as $image) {
                $this->assertSame('Robe brodée bleue', $image->getAttribute('alt'));
            }
            $this->assertGreaterThan(0, $xpath->query('//img[@src="/explicit.jpg"]')->length);
        }
    }

    public function test_only_runtime_placeholder_images_render_with_blank_alt(): void
    {
        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'Maison ZAFIRA',
            'product_title' => ' ', 'product_slug' => 'placeholder-check',
            'product_price' => 100000, 'product_image' => '/p.jpg', 'product_alt' => null, 'stock' => 3,
        ]);
        foreach ([route('home'), route('catalog.index'), route('products.show', $product->product_slug)] as $route) {
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">'.$this->get($route)->assertOk()->getContent());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            foreach ((new DOMXPath($dom))->query('//img') as $image) {
                if (trim($image->getAttribute('alt')) === '') {
                    // Quickview/dialog images start empty and get src + alt from the card at runtime.
                    $this->assertSame('', $image->getAttribute('src'), "$route: content image with blank alt");
                }
            }
        }
    }

    public function test_recovery_messages_include_the_next_action_in_every_locale(): void
    {
        $actions = [
            'id' => ['reset_invalid' => 'Minta tautan', 'invalid_recovery' => 'Gunakan kode', 'invalid' => 'Masukkan kode'],
            'en' => ['reset_invalid' => 'Request a new', 'invalid_recovery' => 'Use an unused', 'invalid' => 'Enter the current'],
            'fr' => ['reset_invalid' => 'Demandez un nouveau', 'invalid_recovery' => 'Utilisez un code', 'invalid' => 'Saisissez le code'],
            'it' => ['reset_invalid' => 'Richiedi un nuovo', 'invalid_recovery' => 'Usa un codice', 'invalid' => 'Inserisci il codice'],
            'zh' => ['reset_invalid' => '请重新申请', 'invalid_recovery' => '请使用未使用', 'invalid' => '请输入'],
        ];
        foreach ($actions as $locale => $messages) {
            foreach ($messages as $key => $action) {
                $group = $key === 'reset_invalid' ? 'auth' : 'customer_totp';
                $this->assertStringContainsString($action, trans("customer_messages.$group.$key", [], $locale));
            }
        }
    }
}
