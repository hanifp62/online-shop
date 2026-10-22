<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontCacheReclaimer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cat_title' => ['required', 'string', 'max:255', 'unique:categories,cat_title'],
        ]);

        Category::create($validated);
        self::flushStorefrontCache();

        return back()->with('success', __('admin_ui.flash.category_added', ['title' => $validated['cat_title']]));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'cat_title' => ['required', 'string', 'max:255', 'unique:categories,cat_title,'.$category->cat_id.',cat_id'],
        ]);

        $category->update($validated);
        self::flushStorefrontCache();

        return back()->with('success', __('admin_ui.flash.category_updated', ['id' => $category->cat_id]));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if (Product::where('product_cat', $category->cat_id)->exists()) {
            return back()->withErrors(['cat_title' => __('admin_ui.flash.category_in_use')]);
        }

        $category->delete();
        self::flushStorefrontCache();

        return back()->with('success', __('admin_ui.flash.category_deleted', ['title' => $category->cat_title]));
    }

    public static function storefrontRevision(): string
    {
        $key = 'storefront:revision:v1';
        $revision = Cache::get($key);
        if (! is_string($revision)) {
            // Atomic add must not overwrite an invalidation racing with a cold reader.
            Cache::add($key, (string) Str::uuid(), 86400);
            $revision = Cache::get($key);
        }

        // If the revision was evicted again, use a new uncached namespace, not an old one.
        return is_string($revision) ? $revision : (string) Str::uuid();
    }

    /**
     * Hapus hanya key katalog storefront; jangan Cache::flush(), karena store
     * yang sama menyimpan penghitung rate limiter.
     *
     * @param  iterable<Product>  $stale  Produk sebelum diubah/dihapus (slug atau kategori lama).
     */
    public static function flushStorefrontCache(iterable $stale = []): void
    {
        // Change the namespace instead of enumerating every product and related key.
        // Readers already in flight may finish writing only to their old namespace.
        Cache::forever('storefront:revision:v1', (string) Str::uuid());
        StorefrontCacheReclaimer::pruneExpired(50);
        $keys = ['storefront:products:v3', 'storefront:categories:v2'];

        foreach ($stale as $product) {
            $keys[] = "storefront:product:{$product->product_slug}:v2";
            $keys[] = "storefront:related:{$product->product_cat}:{$product->product_id}:v3";
        }

        foreach (array_unique($keys) as $key) {
            Cache::forget($key);
        }
    }
}
