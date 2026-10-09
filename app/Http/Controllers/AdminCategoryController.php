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

        return back()->with('success', "Kategori {$validated['cat_title']} ditambahkan.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'cat_title' => ['required', 'string', 'max:255', 'unique:categories,cat_title,'.$category->cat_id.',cat_id'],
        ]);

        $category->update($validated);
        self::flushStorefrontCache();

        return back()->with('success', "Kategori #{$category->cat_id} diperbarui.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if (Product::where('product_cat', $category->cat_id)->exists()) {
            return back()->withErrors(['cat_title' => 'Kategori dipakai produk, tidak bisa dihapus.']);
        }

        $category->delete();
        self::flushStorefrontCache();

        return back()->with('success', "Kategori {$category->cat_title} dihapus.");
    }

    public static function flushStorefrontCache(): void
    {
        // Detail produk di-cache per-slug (storefront:product:{slug}:v1) dan
        // relasi per-kategori, jadi mutasi kategori/produk harus flush semua
        // cache storefront. Cache::flush hanya menyentuh tabel cache, aman
        // untuk sesi database dan antrean jobs.
        Cache::flush();
    }
}
