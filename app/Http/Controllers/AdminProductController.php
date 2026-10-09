<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_title' => ['required', 'string', 'max:255'],
            'product_cat' => ['required', 'integer', Rule::exists('categories', 'cat_id')],
            'product_price' => ['required', 'numeric', 'min:0'],
            'product_slug' => ['nullable', 'string', 'max:255', 'unique:products,product_slug'],
            'product_image' => ['required', 'string', 'max:1000'],
            'product_badge' => ['nullable', 'string', 'max:50'],
            'product_color' => ['nullable', 'string', 'max:50'],
            'product_alt' => ['nullable', 'string', 'max:255'],
            'product_description' => ['nullable', 'string', 'max:2000'],
            'product_material' => ['nullable', 'string', 'max:255'],
            'product_composition' => ['nullable', 'string', 'max:500'],
            'product_origin' => ['nullable', 'string', 'max:255'],
            'product_care' => ['nullable', 'string', 'max:2000'],
            'product_craft' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($validated['product_slug'])) {
            $baseSlug = Str::slug($validated['product_title']);
            $slug = $baseSlug;
            $count = 1;
            while (Product::where('product_slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }
            $validated['product_slug'] = $slug;
        }

        if (empty($validated['product_alt'])) {
            $validated['product_alt'] = $validated['product_title'].' ZAFIRA';
        }

        $validated['product_brand'] = 'ZAFIRA';

        $product = Product::create($validated);
        AdminCategoryController::flushStorefrontCache();

        return back()->with('success', "Produk {$product->product_title} berhasil ditambahkan.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'product_title' => ['sometimes', 'string', 'max:255'],
            'product_cat' => ['sometimes', 'integer', Rule::exists('categories', 'cat_id')],
            'product_price' => ['sometimes', 'numeric', 'min:0'],
            'product_slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'product_slug')->ignore($product->product_id, 'product_id')],
            'product_image' => ['sometimes', 'string', 'max:1000'],
            'product_badge' => ['sometimes', 'nullable', 'string', 'max:50'],
            'product_color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'product_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'product_material' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_composition' => ['sometimes', 'nullable', 'string', 'max:500'],
            'product_origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_care' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'product_craft' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $product->update($validated);
        AdminCategoryController::flushStorefrontCache();

        if (isset($validated['product_cat']) && count($validated) === 1) {
            $category = Category::getById($validated['product_cat']);

            return back()->with(
                'success',
                "Produk {$product->product_title} dipindah ke {$category?->cat_title} — filter user ikut berubah."
            );
        }

        return back()->with(
            'success',
            "Produk {$product->product_title} berhasil diperbarui."
        );
    }

    public function destroy(Product $product): RedirectResponse
    {
        $title = $product->product_title;
        $product->delete();
        AdminCategoryController::flushStorefrontCache();

        return back()->with('success', "Produk {$title} berhasil dihapus.");
    }
}
