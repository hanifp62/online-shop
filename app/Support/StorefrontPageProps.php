<?php

namespace App\Support;

use App\Models\Product;

final class StorefrontPageProps
{
    public const IMAGE_OVERRIDES = [
        'sac-seraphine' => 'minaudiere_saphir',
        'chemise-riviere' => 'chemise_riviere',
        'elan-dress' => 'robe_elan',
        'manteau-firenze' => 'manteau_firenze',
        'echarpe-eclat' => 'echarpe_eclat',
        'robe-aurore' => 'robe_aurore',
        'riva-loafer' => 'mocassin_riva',
    ];

    public static function common(): array
    {
        $user = auth()->guard('web')->user();

        return [
            'copy' => array_intersect_key(__('shop_ui'), array_flip(['app_js', 'pdp', 'header', 'footer', 'cart', 'welcome'])),
            'locale' => app()->getLocale(),
            'user' => $user ? $user->only(['name', 'email', 'mobile']) : null,
            'today' => now()->toDateString(),
            'year' => (int) date('Y'),
            'flash' => session('success'),
            'links' => [
                'home' => route('home'), 'catalog' => route('catalog.index'),
                'shop' => route('shop.index'), 'cart' => route('cart.show'),
                'favorites' => $user ? route('favorites.index') : null,
                'account' => route('account.show'), 'login' => route('login'),
                'logout' => route('logout'), 'locale' => route('locale.update'),
                'concierge' => route('concierge.store'), 'newsletter' => route('newsletter.store'),
                'locales' => array_map(fn ($code) => ['code' => $code, 'url' => request()->fullUrlWithQuery(['locale' => $code])], ['id', 'en', 'fr', 'it', 'zh']),
            ],
            'assets' => collect(['hero_couture', 'chemise_riviere', 'minaudiere_saphir', 'robe_elan', 'manteau_firenze', 'robe_aurore'])
                ->mapWithKeys(fn ($name) => [$name => [
                    'src' => asset("prototype/assets/{$name}.jpg"),
                    'webp' => asset("prototype/assets/{$name}.webp"),
                    'small' => asset("prototype/assets/{$name}-600.webp"),
                ]])->all(),
        ];
    }

    public static function home(array $data): array
    {
        $products = collect($data['products'])->map(function (array $product) {
            $override = self::IMAGE_OVERRIDES[$product['id']] ?? null;
            $product['productId'] = (int) $product['productId'];
            $product['href'] = url('/produk/'.rawurlencode($product['id']));
            $product['priceLabel'] = $product['price_label'];
            $product['image'] = $override ? asset("prototype/assets/{$override}.jpg") : $product['image'];
            $product['srcset'] = $override ? asset("prototype/assets/{$override}-600.webp").' 600w, '.asset("prototype/assets/{$override}.webp").' 896w' : null;

            return $product;
        })->all();

        return self::common() + [
            'products' => $products,
            'categories' => $data['categories']->map(fn ($category) => [
                'id' => $category->cat_id, 'title' => $category->cat_title,
                'href' => route('catalog.index', ['category' => $category->cat_id]),
            ])->all(),
        ];
    }

    public static function product(Product $product): array
    {
        return [
            'id' => $product->product_slug, 'productId' => (int) $product->product_id,
            'href' => route('products.show', $product->product_slug),
            'name' => $product->product_title, 'category' => $product->category?->cat_title,
            'price' => (int) $product->product_price,
            'priceLabel' => 'Rp'.number_format($product->product_price, 0, ',', '.'),
            'stock' => max(0, (int) $product->stock),
            'image' => $product->imageWidth(800), 'heroImage' => $product->imageWidth(1600),
            'srcset' => $product->imageSrcset([400, 800]),
            'heroSrcset' => $product->imageSrcset([800, 1200, 1600]),
            'alt' => $product->imageAlt(), 'badge' => $product->product_badge,
            'color' => $product->product_color, 'swatch' => $product->swatchHex(),
            'description' => $product->product_description, 'material' => $product->product_material,
            'composition' => $product->product_composition, 'origin' => $product->product_origin,
            'craft' => $product->product_craft, 'care' => $product->product_care,
            'hasSizes' => (bool) preg_match('/busana|dress|robe|kebaya|chemise|manteau|kemeja|gaun|mantel/i', $product->category?->cat_title.' '.$product->product_title),
        ];
    }

    public static function show(array $data): array
    {
        return self::common() + [
            'product' => self::product($data['product']),
            'related' => $data['related']->map(fn (Product $product) => self::product($product))->all(),
        ];
    }
}
