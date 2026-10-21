<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Hapus hanya key katalog storefront; jangan Cache::flush(), karena store
     * yang sama menyimpan penghitung rate limiter.
     *
     * @param  iterable<Product>  $stale  Produk sebelum diubah/dihapus (slug atau kategori lama).
     */
    public static function flushStorefrontCache(iterable $stale = []): void
    {
        $keys = ['storefront:products:v3', 'storefront:categories:v2'];

        $products = Product::get(['product_id', 'product_cat', 'product_slug']);
        foreach ([...$products, ...$stale] as $product) {
            $keys[] = "storefront:product:{$product->product_slug}:v2";
            $keys[] = "storefront:related:{$product->product_cat}:{$product->product_id}:v3";
        }

        foreach (array_unique($keys) as $key) {
            Cache::forget($key);
        }
    }
}
