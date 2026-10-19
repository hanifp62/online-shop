<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class CatalogService
{
    public function __construct(private readonly CatalogSearchMatcher $matcher) {}

    public function url(array $filters): string
    {
        $parts = [];
        foreach ($filters as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $parts[] = urlencode($key).'='.urlencode((string) $item);
            }
        }

        return url('/koleksi').($parts ? '?'.implode('&', $parts) : '');
    }

    public function data(array $filters): array
    {
        $categories = Category::query()->orderBy('cat_id')->get();
        $slugGroups = $categories->groupBy(fn ($category) => Str::slug($category->cat_title));
        $categoryOptions = $slugGroups->filter(fn ($group, $slug) => $slug !== '' && $group->count() === 1)
            ->map(fn ($group) => $group->first());
        if (isset($filters['kategori'])) {
            $filters['kategori'] = array_values(array_intersect($filters['kategori'], $categoryOptions->keys()->all()));
            if (! $filters['kategori']) {
                unset($filters['kategori']);
            }
        }
        if (isset($filters['category']) && ! $categories->contains('cat_id', $filters['category'])) {
            unset($filters['category']);
        }
        $page = (int) ($filters['page'] ?? 1);
        unset($filters['page']);
        $query = $this->query($filters, $categoryOptions);
        $searchRecovery = null;
        $products = $query->paginate(8, ['*'], 'page', $page);
        if (isset($filters['q']) && $products->total() === 0 && $this->matcher->eligible(trim($filters['q']))) {
            $remaining = $filters;
            unset($remaining['q']);
            // shortcut: fuzzy scans max 500 candidates (safety cap); raise or index when catalog grows.
            $candidates = $this->query($remaining, $categoryOptions)->without('category')->limit(501)->get();
            if ($candidates->count() > 500) {
                $searchRecovery = 'narrow';
            } else {
                $ids = $candidates->filter(fn (Product $product) => $this->matcher->matches(
                    trim($filters['q']), implode(' ', [$product->product_title, $product->product_material, $product->product_color])
                ))->pluck('product_id');
                $products = $this->query($remaining, $categoryOptions)->whereIn('product_id', $ids)
                    ->paginate(8, ['*'], 'page', $page);
                $searchRecovery = $products->total() > 0 ? 'similar' : null;
            }
        }
        $locale = $filters['locale'];
        $t = (require resource_path('views/catalog/labels.php'))[$locale];

        return [
            'products' => $products, 'categories' => $categories, 'categoryOptions' => $categoryOptions,
            'colors' => Product::query()->whereNotNull('product_color')->where('product_color', '<>', '')->distinct()->orderBy('product_color')->pluck('product_color'),
            'materials' => Product::query()->whereNotNull('product_material')->where('product_material', '<>', '')->distinct()->orderBy('product_material')->pluck('product_material'),
            'filters' => $filters, 'locale' => $locale, 't' => $t,
            'isFuzzy' => $searchRecovery === 'similar',
            'searchRecovery' => $searchRecovery,
            'catalogUrl' => fn ($params) => $this->url($params),
            'canonical' => $this->url($page > 1 ? [...$filters, 'page' => min($page, $products->lastPage())] : $filters),
            'chips' => $this->chips($filters, $categories, $categoryOptions, $t),
        ];
    }

    private function query(array $filters, $categoryOptions)
    {
        $query = Product::query()->with('category')->where('is_active', true);
        if (isset($filters['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q']));
            $query->where(function ($query) use ($term) {
                foreach (['product_title', 'product_material', 'product_color'] as $column) {
                    $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
                }
            });
        }
        if (isset($filters['category'])) {
            $query->where('product_cat', $filters['category']);
        }
        if (isset($filters['kategori'])) {
            $query->whereIn('product_cat', array_map(fn ($slug) => $categoryOptions[$slug]->cat_id, $filters['kategori']));
        }
        foreach (['color' => 'product_color', 'material' => 'product_material'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        foreach (['harga_min' => '>=', 'harga_max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('product_price', $operator, $this->decimal($filters[$key]));
            }
        }
        if (isset($filters['tersedia'])) {
            $query->where('stock', '>', 0);
        }
        if (($filters['urut'] ?? '') === 'harga') {
            $query->orderBy('product_price');
        }
        if (($filters['urut'] ?? '') === 'terbaru') {
            return $query->orderByDesc('created_at')->orderByDesc('product_id');
        }
        if (($filters['urut'] ?? '') === 'tersedia') {
            return $query->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')->orderBy('product_id');
        }

        return $query->orderBy('product_id');
    }

    public function decimal(string $minor): string
    {
        $digits = str_pad(ltrim($minor, '0'), 3, '0', STR_PAD_LEFT);

        return substr($digits, 0, -2).'.'.substr($digits, -2);
    }

    private function chips(array $filters, $categories, $options, array $t): array
    {
        $chips = [];
        foreach ($filters as $key => $value) {
            if ($key === 'locale') {
                continue;
            }
            foreach (is_array($value) ? $value : [$value] as $item) {
                $remaining = $filters;
                unset($remaining[$key]);
                if (is_array($value)) {
                    $rest = array_values(array_diff($value, [$item]));
                    if ($rest) {
                        $remaining[$key] = $rest;
                    }
                    // Keep parameter order stable when one category is removed.
                    $remaining = array_replace(array_intersect_key($filters, $remaining), $remaining);
                }
                $label = match ($key) {
                    'kategori' => $options[$item]->cat_title,
                    'category' => $categories->firstWhere('cat_id', $item)->cat_title,
                    'harga_min', 'harga_max' => $t[$key].': '.$this->decimal($item).' IDR',
                    'tersedia' => $t['available'],
                    'urut' => $t[match ($item) { 'harga' => 'price_sort', 'tersedia' => 'available', default => 'newest' }],
                    default => $t[$key === 'q' ? 'search' : $key].': '.$item,
                };
                $chips[] = ['label' => $label, 'url' => $this->url($remaining)];
            }
        }

        return $chips;
    }
}
