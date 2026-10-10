<?php

namespace App\Http\Controllers;

use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
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
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function index(): View
    {
        // server-cache-lru: katalog jarang berubah, cache lintas-request 5 menit.
        // server-serialization: select kolom yang dipakai view saja.
        // Cache di-flush oleh AdminCategoryController setiap mutasi kategori.
        $products = Cache::remember('storefront:products:v1', 300, fn () => Product::with(['category:cat_id,cat_title'])
            ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
            ->orderBy('product_id')
            ->get());

        $categories = Cache::remember('storefront:categories:v1', 300, fn () => Category::orderBy('cat_id')->get(['cat_id', 'cat_title']));

        return view('welcome', [
            'categories' => $categories,
            'products' => $products->map(function (Product $product) {
                return [
                    'id' => $product->product_slug ?? 'product-'.$product->product_id,
                    'name' => $product->product_title,
                    'category' => $product->category?->cat_title ?? 'Koleksi',
                    'price' => (int) $product->product_price,
                    'price_label' => 'Rp'.number_format($product->product_price, 0, ',', '.'),
                    'image' => $product->imageWidth(800),
                    'alt' => $product->product_alt,
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
                'description' => 'Rumah mode Paris–Italia–Indonesia: siluet Paris, keahlian Italia, batik tulis, songket, dan tenun Nusantara.',
                'url' => url('/'),
                'logo' => asset('favicon.svg'),
                'sameAs' => [],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function show(string $slug): View
    {
        // async-dependencies analog: $related bergantung pada $product, jadi
        // rantai berurutan tidak bisa dihindari; yang dioptimalkan adalah
        // select minimal + cache per-produk (server-serialization, server-cache-lru).
        $product = Cache::remember(
            "storefront:product:{$slug}:v1",
            300,
            fn () => Product::with(['category:cat_id,cat_title'])
                ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
                ->where('product_slug', $slug)
                ->firstOrFail()
        );

        $related = Cache::remember(
            "storefront:related:{$product->product_cat}:{$product->product_id}:v1",
            300,
            fn () => Product::with(['category:cat_id,cat_title'])
                ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
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
                    'availability' => 'https://schema.org/InStock',
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function sitemap(): Response
    {
        // robots.txt Disallow login/register/akun/admin, jadi sitemap hanya
        // berisi URL publik yang boleh di-crawl: home + produk.
        $urls = [route('home')];

        foreach (Product::orderBy('product_id')->get(['product_slug']) as $product) {
            $urls[] = route('products.show', $product->product_slug);
        }

        $content = view('sitemap', ['urls' => $urls])->render();

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function bookConcierge(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);

        $rawItems = $request->input('order_items');
        if ($rawItems === '') {
            $request->merge(['order_items' => null]);
        } elseif (is_string($rawItems) && trim($rawItems) !== '') {
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
            'order_items.*.id' => ['required', 'string', 'max:255', 'exists:products,product_slug'],
            'order_items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        if (! empty($validated['order_items'])) {
            $lines = [];
            $total = 0;
            foreach ($validated['order_items'] as $item) {
                $product = Product::where('product_slug', $item['id'])->firstOrFail();
                $qty = (int) $item['quantity'];
                $price = (int) $product->product_price;
                $priceStr = 'Rp'.number_format($price, 0, ',', '.');
                $lines[] = "- {$product->product_title} ({$qty}x) {$priceStr}";
                $total += $price * $qty;
            }
            $existingNotes = trim((string) ($validated['notes'] ?? ''));
            $summary = "[Pesanan Checkout]\n".implode("\n", $lines)."\nTotal: Rp".number_format($total, 0, ',', '.');
            $validated['notes'] = trim($summary.($existingNotes !== '' ? "\n\nCatatan: ".$existingNotes : ''));
        }
        unset($validated['order_items']);

        $concierge = ConciergeRequest::create($validated + ['user_id' => $request->user()?->id]);
        Mail::to($concierge->email)->queue(new ConciergeRequested($concierge));

        $message = 'Permintaan rendez-vous Anda telah diterima. ZAFIRA Concierge akan segera menghubungi Anda.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('success', $message);
    }

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate($validated);

        if ($subscriber->wasRecentlyCreated) {
            Mail::to($subscriber->email)->queue(new NewsletterWelcome($subscriber));
        }

        $message = 'Terima kasih telah berlangganan La Lettre ZAFIRA.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
