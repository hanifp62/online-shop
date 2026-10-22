<?php

namespace App\Support;

use App\Models\Product;
use App\Services\CatalogService;

final class CatalogPageProps
{
    public static function from(array $data, CatalogService $service): array
    {
        $products = $data['products'];
        $filters = $data['filters'];
        $locale = $data['locale'];
        $withoutSearch = $filters;
        unset($withoutSearch['q']);
        $copy = $data['t'];
        if (! $data['isFuzzy']) {
            unset($copy['fuzzy_notice']);
        }

        return [
            't' => $copy,
            'catalogCopy' => __('shop_ui.catalog'),
            'products' => [
                'data' => $products->getCollection()->map(fn (Product $product) => [
                    'id' => $product->product_id,
                    'title' => $product->product_title,
                    'slug' => $product->product_slug,
                    'href' => url('/produk/'.$product->product_slug),
                    'category' => $product->category?->cat_title,
                    'priceLabel' => 'Rp'.number_format((float) $product->product_price, 0, ',', '.'),
                    'stock' => max(0, (int) $product->stock),
                    'image' => $product->product_image ? $product->imageWidth(600) : null,
                    'imageSrcset' => $product->imageSrcset([400, 600, 800]),
                    'quickImage' => $product->imageWidth(800),
                    'alt' => $product->imageAlt(),
                    'description' => $product->product_description,
                    'material' => $product->product_material,
                    'color' => $product->product_color,
                ])->all(),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'prev_url' => $products->onFirstPage() ? null : $service->url([...$filters, 'page' => $products->currentPage() - 1]),
                'next_url' => $products->hasMorePages() ? $service->url([...$filters, 'page' => $products->currentPage() + 1]) : null,
            ],
            'filters' => $filters,
            'categories' => $data['categoryOptions']->map(fn ($category, $slug) => ['slug' => $slug, 'title' => $category->cat_title])->values()->all(),
            'colors' => $data['colors']->all(),
            'materials' => $data['materials']->all(),
            'chips' => $data['chips'],
            'links' => [
                'home' => route('home'), 'shop' => route('shop.index'), 'index' => route('catalog.index'),
                'reset' => $service->url(['locale' => $locale]),
                'clear' => $locale === 'id' ? route('catalog.index') : $service->url(['locale' => $locale]),
                'withoutSearch' => $service->url($withoutSearch),
                'cart' => route('cart.items.store'), 'favorites' => route('favorites.store'),
                'locales' => array_map(fn ($code) => ['code' => $code, 'label' => strtoupper($code), 'url' => $service->url([...$filters, 'locale' => $code])], ['id', 'en', 'zh', 'fr', 'it']),
            ],
            'signedIn' => auth()->guard('web')->check(),
            'isFuzzy' => $data['isFuzzy'], 'searchRecovery' => $data['searchRecovery'],
            'canonical' => $data['canonical'], 'year' => (int) date('Y'),
        ];
    }
}
