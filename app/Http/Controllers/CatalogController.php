<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $locale = $request->query('locale');
        $locale = is_string($locale) && in_array($locale, ['id', 'en', 'zh', 'fr', 'it'], true) ? $locale : 'id';
        $filters = [];
        foreach (['q', 'color', 'material'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && mb_strlen(trim($value)) <= 100 && trim($value) !== '') {
                $filters[$key] = trim($value);
            }
        }
        $category = $request->query('category');
        if (is_string($category) && ctype_digit($category) && $category !== '0') {
            $filters['category'] = $category;
        }
        $filters['locale'] = $locale;

        $productsQuery = Product::query()->with('category')->where('is_active', true);
        if (isset($filters['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q']));
            $productsQuery->where(function ($query) use ($term) {
                foreach (['product_title', 'product_material', 'product_color'] as $column) {
                    $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
                }
            });
        }
        if (isset($filters['category'])) {
            $productsQuery->where('product_cat', $filters['category']);
        }
        foreach (['color' => 'product_color', 'material' => 'product_material'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $productsQuery->where($column, $filters[$filter]);
            }
        }

        $pageInput = $request->query('page');
        $page = is_string($pageInput) && ctype_digit($pageInput) && (int) $pageInput > 0
            ? min((int) $pageInput, PHP_INT_MAX) : 1;
        $products = $productsQuery->orderBy('product_id')->paginate(8, ['*'], 'page', $page);
        if ($page > $products->lastPage()) {
            return redirect()->to(url('/koleksi').'?'.http_build_query([...$filters, 'page' => $products->lastPage()]));
        }

        return view('catalog.index', [
            'products' => $products->appends($filters),
            'categories' => Category::query()->orderBy('cat_id')->get(),
            'colors' => Product::query()->whereNotNull('product_color')->where('product_color', '<>', '')->distinct()->orderBy('product_color')->pluck('product_color'),
            'materials' => Product::query()->whereNotNull('product_material')->where('product_material', '<>', '')->distinct()->orderBy('product_material')->pluck('product_material'),
            'filters' => $filters,
            'locale' => $locale,
            't' => (require resource_path('views/catalog/labels.php'))[$locale],
        ]);
    }
}
