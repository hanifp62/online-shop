<?php

namespace App\Http\Controllers;

use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Mail\RecipientLocale;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(): View
    {
        // server-cache-lru: katalog jarang berubah, cache lintas-request 5 menit.
        // server-serialization: select kolom yang dipakai view saja.
        // Key katalog dihapus oleh AdminCategoryController setelah mutasi kategori/produk.
        $products = Cache::remember('storefront:products:v3', 300, fn () => Product::where('is_active', true)->with(['category:cat_id,cat_title'])
            ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'stock', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
            ->orderBy('product_id')
            ->get());

        $categories = Cache::remember('storefront:categories:v2', 300, fn () => Category::orderBy('cat_id')->get(['cat_id', 'cat_title']));

        return view('welcome', [
            'categories' => $categories,
            'products' => $products->map(function (Product $product) {
                return [
                    'id' => $product->product_slug ?? 'product-'.$product->product_id,
                    'name' => $product->product_title,
                    'stock' => max(0, (int) $product->stock),
                    'category' => $product->category?->cat_title ?? trans('customer_messages.storefront.collection'),
                    'price' => (int) $product->product_price,
                    'price_label' => 'Rp'.number_format($product->product_price, 0, ',', '.'),
                    'image' => $product->imageWidth(800),
                    'alt' => $product->imageAlt(),
                    'description' => $product->product_description,
                    'badge' => $product->product_badge,
                    'color' => $product->product_color,
                    'swatch' => $product->swatchHex(),
                    'material' => $product->product_material,
                    'origin' => $product->product_origin,
                ];
            }),
            'jsonLd' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'ZAFIRA',
                'description' => trans('customer_messages.storefront.organization_description'),
                'url' => url('/'),
                'logo' => asset('favicon.svg'),
                'sameAs' => [],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        ]);
    }

    public function show(string $slug): View
    {
        // server-cache-lru: produk per-PDP tidak di-cache — stok dan is_active
        // harus segar tiap request setelah checkout/cancel/admin, agar penanda
        // schema.org InStock/OutOfStock tidak menyajikan stok usang.
        $product = Product::where('is_active', true)->with(['category:cat_id,cat_title'])
            ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'stock', 'is_active', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
            ->where('product_slug', $slug)
            ->firstOrFail();

        $related = Cache::remember(
            "storefront:related:{$product->product_cat}:{$product->product_id}:v3",
            300,
            fn () => Product::where('is_active', true)->with(['category:cat_id,cat_title'])
                ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'stock', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
                ->where('product_cat', $product->product_cat)
                ->where('product_id', '!=', $product->product_id)
                ->take(3)
                ->get()
        );

        return view('products.show', [
            'product' => $product,
            'related' => $related,
            'jsonLd' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->product_title,
                'image' => $product->imageWidth(1200),
                'description' => $product->product_alt,
                'brand' => ['@type' => 'Brand', 'name' => 'ZAFIRA'],
                'material' => $product->product_material,
                'color' => $product->product_color,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => route('products.show', $product->product_slug),
                    'price' => (int) $product->product_price,
                    'priceCurrency' => 'IDR',
                    // Stok dan status aktif berasal dari kueri request ini.
                    'availability' => $product->is_active === true && (int) $product->stock > 0
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
        ]);
    }

    public function sitemap(): Response
    {
        // robots.txt Disallow login/register/akun/admin, jadi sitemap hanya
        // berisi URL publik yang boleh di-crawl: home + produk.
        $urls = [route('home')];

        foreach (Product::where('is_active', true)->orderBy('product_id')->get(['product_slug']) as $product) {
            $urls[] = route('products.show', $product->product_slug);
        }

        $content = view('sitemap', ['urls' => $urls])->render();

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function bookConcierge(Request $request): JsonResponse|RedirectResponse
    {
        $this->normalizeEmail($request);

        $rawItems = $request->input('order_items');
        if (is_string($rawItems) && trim($rawItems) === '') {
            $request->merge(['order_items' => null]);
        } elseif (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            $request->merge(['order_items' => is_array($decoded) ? $decoded : $rawItems]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'service' => ['required', 'in:private-styling,virtual-consultation,gifting,atelier'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'order_items' => ['nullable', 'array', 'max:20'],
            'order_items.*' => ['array'],
            'order_items.*.id' => ['required', 'string', 'max:255', Rule::exists('products', 'product_slug')->where('is_active', true)],
            'order_items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'order_items.*.size' => ['nullable', 'string', 'max:50'],
            'order_items.*.monogram' => ['nullable', 'string', 'regex:/^[A-Z]{1,3}$/'],
        ]);

        if (! empty($validated['order_items'])) {
            $lines = [];
            $total = 0;
            foreach ($validated['order_items'] as $item) {
                $product = Product::where('is_active', true)->where('product_slug', $item['id'])->firstOrFail();
                $qty = (int) $item['quantity'];
                $price = (int) $product->product_price;
                $priceStr = 'Rp'.number_format($price, 0, ',', '.');
                $details = array_filter([
                    ! empty($item['size']) ? 'Ukuran: '.$item['size'] : null,
                    ! empty($item['monogram']) ? 'Monogram: '.$item['monogram'] : null,
                ]);
                $suffix = $details ? ' · '.implode(' · ', $details) : '';
                $lines[] = "- {$product->product_title} ({$qty}x) {$priceStr}{$suffix}";
                $total += $price * $qty;
            }
            $existingNotes = trim((string) ($validated['notes'] ?? ''));
            $summary = "[Pesanan Checkout]\n".implode("\n", $lines)."\nTotal: Rp".number_format($total, 0, ',', '.');
            $validated['notes'] = trim($summary.($existingNotes !== '' ? "\n\nCatatan: ".$existingNotes : ''));
        }
        unset($validated['order_items']);

        $concierge = ConciergeRequest::create($validated + ['user_id' => $request->user()?->id]);
        Mail::to($concierge->email)->locale(RecipientLocale::resolve(app()->getLocale()))->queue(new ConciergeRequested($concierge));

        $message = __('customer_messages.storefront.concierge_received');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('success', $message);
    }

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate($validated);

        if ($subscriber->wasRecentlyCreated) {
            Mail::to($subscriber->email)->locale(RecipientLocale::resolve(app()->getLocale()))->queue(new NewsletterWelcome($subscriber));
        }

        $message = __('customer_messages.storefront.subscribed');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
