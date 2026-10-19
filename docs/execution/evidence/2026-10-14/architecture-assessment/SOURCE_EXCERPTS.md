# Kutipan sumber kritis lengkap

OBSERVED; nomor baris merujuk snapshot audit. Tidak menjalankan kode ini.

## routes/web.php

```php
1: <?php
2: 
3: use App\Http\Controllers\AddressController;
4: use App\Http\Controllers\AdminAuthController;
5: use App\Http\Controllers\AdminCategoryController;
6: use App\Http\Controllers\AdminDashboardController;
7: use App\Http\Controllers\AdminOrderController;
8: use App\Http\Controllers\AdminProductController;
9: use App\Http\Controllers\AuthController;
10: use App\Http\Controllers\CartController;
11: use App\Http\Controllers\CatalogController;
12: use App\Http\Controllers\FavoriteController;
13: use App\Http\Controllers\OrderController;
14: use App\Http\Controllers\ProfileController;
15: use App\Http\Controllers\ReviewController;
16: use App\Http\Controllers\ShopController;
17: use App\Http\Controllers\StorefrontController;
18: use Illuminate\Cache\RateLimiting\Limit;
19: use Illuminate\Support\Facades\RateLimiter;
20: use Illuminate\Support\Facades\Route;
21: 
22: RateLimiter::for('login', fn ($job) => Limit::perMinute(10)->by($job->ip()));
23: RateLimiter::for('register', fn ($job) => Limit::perHour(5)->by($job->ip()));
24: RateLimiter::for('concierge', fn ($job) => Limit::perHour(3)->by($job->ip()));
25: RateLimiter::for('newsletter', fn ($job) => Limit::perDay(5)->by($job->ip()));
26: RateLimiter::for('checkout', fn ($job) => Limit::perMinute(10)->by($job->user()?->id ?: $job->ip()));
27: 
28: Route::get('/', [StorefrontController::class, 'index'])->name('home');
29: Route::get('/koleksi', [CatalogController::class, 'index'])->name('catalog.index');
30: Route::get('/belanja', [ShopController::class, 'index'])->name('shop.index');
31: Route::get('/prototype', fn () => redirect('/prototype/'))->name('prototype');
32: Route::get('/produk/{product:product_slug}', [StorefrontController::class, 'show'])->name('products.show');
33: Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('sitemap');
34: Route::post('/concierge', [StorefrontController::class, 'bookConcierge'])
35:     ->middleware('throttle:concierge')
36:     ->name('concierge.store');
37: Route::post('/newsletter', [StorefrontController::class, 'subscribe'])
38:     ->middleware('throttle:newsletter')
39:     ->name('newsletter.store');
40: 
41: Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
42: Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
43: Route::delete('/cart/items/{productId}', [CartController::class, 'destroy'])->whereNumber('productId')->name('cart.items.destroy');
44: Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
45: Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
46: 
47: Route::middleware('guest')->group(function () {
48:     Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
49:     Route::post('/register', [AuthController::class, 'register'])
50:         ->middleware('throttle:register')
51:         ->name('register.store');
52:     Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
53:     Route::post('/login', [AuthController::class, 'login'])
54:         ->middleware('throttle:login')
55:         ->name('login.process');
56: });
57: 
58: Route::post('/logout', [AuthController::class, 'logout'])
59:     ->middleware('auth')
60:     ->name('logout');
61: 
62: Route::middleware('auth')->group(function () {
63:     Route::get('/akun', [ProfileController::class, 'show'])->name('account.show');
64:     Route::patch('/akun', [ProfileController::class, 'update'])->name('account.update');
65: 
66:     Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
67:     Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
68:     Route::patch('/addresses/{id}', [AddressController::class, 'update'])->whereNumber('id')->name('addresses.update');
69:     Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->whereNumber('id')->name('addresses.destroy');
70: 
71:     Route::post('/checkout', [OrderController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
72:     Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
73:     Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
74:     Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id')->name('orders.cancel');
75: 
76:     Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
77: 
78:     Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
79:     Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
80:     Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy'])->whereNumber('productId')->name('favorites.destroy');
81: });
82: 
83: Route::middleware('guest:admin')->group(function () {
84:     Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
85:     Route::post('/admin/login', [AdminAuthController::class, 'login'])
86:         ->middleware('throttle:login')
87:         ->name('admin.login.process');
88: });
89: 
90: Route::middleware('auth:admin')->group(function () {
91:     Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
92:     Route::patch('/admin/concierge/{concierge}', [AdminDashboardController::class, 'updateConcierge'])->name('admin.concierge.update');
93:     Route::patch('/admin/password', [AdminDashboardController::class, 'updatePassword'])->name('admin.password.update');
94:     Route::post('/admin/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
95:     Route::patch('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
96:     Route::delete('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');
97:     Route::patch('/admin/products/{product:product_id}', [AdminProductController::class, 'update'])->name('admin.products.update');
98:     Route::post('/admin/products', [AdminProductController::class, 'store'])->name('admin.products.store');
99:     Route::delete('/admin/products/{product:product_id}', [AdminProductController::class, 'destroy'])->name('admin.products.destroy');
100:     Route::delete('/admin/subscribers/{subscriber}', [AdminDashboardController::class, 'destroySubscriber'])->name('admin.subscribers.destroy');
101:     Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
102:     Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show'])->whereNumber('id')->name('admin.orders.show');
103:     Route::patch('/admin/orders/{id}', [AdminOrderController::class, 'updateStatus'])->whereNumber('id')->name('admin.orders.update');
104:     Route::get('/admin/orders/{id}/invoice', [AdminOrderController::class, 'invoice'])->whereNumber('id')->name('admin.orders.invoice');
105:     Route::get('/admin/reports/orders.pdf', [AdminOrderController::class, 'report'])->name('admin.orders.report');
106:     Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
107: });
```

## app/Http/Controllers/CatalogController.php

```php
1: <?php
2: 
3: namespace App\Http\Controllers;
4: 
5: use App\Models\Category;
6: use App\Models\Product;
7: use Illuminate\Http\RedirectResponse;
8: use Illuminate\Http\Request;
9: use Illuminate\View\View;
10: 
11: class CatalogController extends Controller
12: {
13:     public function index(Request $request): View|RedirectResponse
14:     {
15:         $locale = $request->query('locale');
16:         $locale = is_string($locale) && in_array($locale, ['id', 'en', 'zh', 'fr', 'it'], true) ? $locale : 'id';
17:         $filters = [];
18:         foreach (['q', 'color', 'material'] as $key) {
19:             $value = $request->query($key);
20:             if (is_string($value) && mb_strlen(trim($value)) <= 100 && trim($value) !== '') {
21:                 $filters[$key] = trim($value);
22:             }
23:         }
24:         $category = $request->query('category');
25:         if (is_string($category) && ctype_digit($category) && $category !== '0') {
26:             $filters['category'] = $category;
27:         }
28:         $filters['locale'] = $locale;
29: 
30:         $productsQuery = Product::query()->with('category')->where('is_active', true);
31:         if (isset($filters['q'])) {
32:             $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q']));
33:             $productsQuery->where(function ($query) use ($term) {
34:                 foreach (['product_title', 'product_material', 'product_color'] as $column) {
35:                     $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", ['%'.$term.'%']);
36:                 }
37:             });
38:         }
39:         if (isset($filters['category'])) {
40:             $productsQuery->where('product_cat', $filters['category']);
41:         }
42:         foreach (['color' => 'product_color', 'material' => 'product_material'] as $filter => $column) {
43:             if (isset($filters[$filter])) {
44:                 $productsQuery->where($column, $filters[$filter]);
45:             }
46:         }
47: 
48:         $pageInput = $request->query('page');
49:         $page = is_string($pageInput) && ctype_digit($pageInput) && (int) $pageInput > 0
50:             ? min((int) $pageInput, PHP_INT_MAX) : 1;
51:         $products = $productsQuery->orderBy('product_id')->paginate(8, ['*'], 'page', $page);
52:         if ($page > $products->lastPage()) {
53:             return redirect()->to(url('/koleksi').'?'.http_build_query([...$filters, 'page' => $products->lastPage()]));
54:         }
55: 
56:         return view('catalog.index', [
57:             'products' => $products->appends($filters),
58:             'categories' => Category::query()->orderBy('cat_id')->get(),
59:             'colors' => Product::query()->whereNotNull('product_color')->where('product_color', '<>', '')->distinct()->orderBy('product_color')->pluck('product_color'),
60:             'materials' => Product::query()->whereNotNull('product_material')->where('product_material', '<>', '')->distinct()->orderBy('product_material')->pluck('product_material'),
61:             'filters' => $filters,
62:             'locale' => $locale,
63:             't' => (require resource_path('views/catalog/labels.php'))[$locale],
64:         ]);
65:     }
66: }
```

## app/Http/Controllers/StorefrontController.php

```php
1: <?php
2: 
3: namespace App\Http\Controllers;
4: 
5: use App\Mail\ConciergeRequested;
6: use App\Mail\NewsletterWelcome;
7: use App\Models\Category;
8: use App\Models\ConciergeRequest;
9: use App\Models\NewsletterSubscriber;
10: use App\Models\Product;
11: use Illuminate\Http\JsonResponse;
12: use Illuminate\Http\RedirectResponse;
13: use Illuminate\Http\Request;
14: use Illuminate\Http\Response;
15: use Illuminate\Support\Facades\Cache;
16: use Illuminate\Support\Facades\Mail;
17: use Illuminate\Validation\Rule;
18: use Illuminate\View\View;
19: 
20: class StorefrontController extends Controller
21: {
22:     public function index(): View
23:     {
24:         // server-cache-lru: katalog jarang berubah, cache lintas-request 5 menit.
25:         // server-serialization: select kolom yang dipakai view saja.
26:         // Key katalog dihapus oleh AdminCategoryController setelah mutasi kategori/produk.
27:         $products = Cache::remember('storefront:products:v1', 300, fn () => Product::where('is_active', true)->with(['category:cat_id,cat_title'])
28:             ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
29:             ->orderBy('product_id')
30:             ->get());
31: 
32:         $categories = Cache::remember('storefront:categories:v1', 300, fn () => Category::orderBy('cat_id')->get(['cat_id', 'cat_title']));
33: 
34:         return view('welcome', [
35:             'categories' => $categories,
36:             'products' => $products->map(function (Product $product) {
37:                 return [
38:                     'id' => $product->product_slug ?? 'product-'.$product->product_id,
39:                     'name' => $product->product_title,
40:                     'category' => $product->category?->cat_title ?? 'Koleksi',
41:                     'price' => (int) $product->product_price,
42:                     'price_label' => 'Rp'.number_format($product->product_price, 0, ',', '.'),
43:                     'image' => $product->imageWidth(800),
44:                     'alt' => $product->product_alt,
45:                     'description' => $product->product_description,
46:                     'badge' => $product->product_badge,
47:                     'color' => $product->product_color,
48:                     'swatch' => $product->swatchHex(),
49:                     'material' => $product->product_material,
50:                     'origin' => $product->product_origin,
51:                 ];
52:             }),
53:             'jsonLd' => json_encode([
54:                 '@context' => 'https://schema.org',
55:                 '@type' => 'Organization',
56:                 'name' => 'ZAFIRA',
57:                 'description' => 'Rumah mode Paris–Italia–Indonesia: siluet Paris, keahlian Italia, batik tulis, songket, dan tenun Nusantara.',
58:                 'url' => url('/'),
59:                 'logo' => asset('favicon.svg'),
60:                 'sameAs' => [],
61:             ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
62:         ]);
63:     }
64: 
65:     public function show(string $slug): View
66:     {
67:         // async-dependencies analog: $related bergantung pada $product, jadi
68:         // rantai berurutan tidak bisa dihindari; yang dioptimalkan adalah
69:         // select minimal + cache per-produk (server-serialization, server-cache-lru).
70:         $product = Cache::remember(
71:             "storefront:product:{$slug}:v1",
72:             300,
73:             fn () => Product::where('is_active', true)->with(['category:cat_id,cat_title'])
74:                 ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
75:                 ->where('product_slug', $slug)
76:                 ->firstOrFail()
77:         );
78: 
79:         $related = Cache::remember(
80:             "storefront:related:{$product->product_cat}:{$product->product_id}:v1",
81:             300,
82:             fn () => Product::where('is_active', true)->with(['category:cat_id,cat_title'])
83:                 ->select(['product_id', 'product_cat', 'product_brand', 'product_title', 'product_slug', 'product_price', 'product_image', 'product_badge', 'product_color', 'product_alt', 'product_description', 'product_material', 'product_composition', 'product_origin', 'product_care', 'product_craft'])
84:                 ->where('product_cat', $product->product_cat)
85:                 ->where('product_id', '!=', $product->product_id)
86:                 ->take(3)
87:                 ->get()
88:         );
89: 
90:         return view('products.show', [
91:             'product' => $product,
92:             'related' => $related,
93:             'jsonLd' => json_encode([
94:                 '@context' => 'https://schema.org',
95:                 '@type' => 'Product',
96:                 'name' => $product->product_title,
97:                 'image' => $product->imageWidth(1200),
98:                 'description' => $product->product_alt,
99:                 'brand' => ['@type' => 'Brand', 'name' => 'ZAFIRA'],
100:                 'material' => $product->product_material,
101:                 'color' => $product->product_color,
102:                 'offers' => [
103:                     '@type' => 'Offer',
104:                     'url' => route('products.show', $product->product_slug),
105:                     'price' => (int) $product->product_price,
106:                     'priceCurrency' => 'IDR',
107:                     'availability' => 'https://schema.org/InStock',
108:                 ],
109:             ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
110:         ]);
111:     }
112: 
113:     public function sitemap(): Response
114:     {
115:         // robots.txt Disallow login/register/akun/admin, jadi sitemap hanya
116:         // berisi URL publik yang boleh di-crawl: home + produk.
117:         $urls = [route('home')];
118: 
119:         foreach (Product::where('is_active', true)->orderBy('product_id')->get(['product_slug']) as $product) {
120:             $urls[] = route('products.show', $product->product_slug);
121:         }
122: 
123:         $content = view('sitemap', ['urls' => $urls])->render();
124: 
125:         return response($content)
126:             ->header('Content-Type', 'application/xml')
127:             ->header('Cache-Control', 'public, max-age=3600');
128:     }
129: 
130:     public function bookConcierge(Request $request): JsonResponse|RedirectResponse
131:     {
132:         $this->normalizeEmail($request);
133: 
134:         $rawItems = $request->input('order_items');
135:         if (is_string($rawItems) && trim($rawItems) === '') {
136:             $request->merge(['order_items' => null]);
137:         } elseif (is_string($rawItems)) {
138:             $decoded = json_decode($rawItems, true);
139:             $request->merge(['order_items' => is_array($decoded) ? $decoded : $rawItems]);
140:         }
141: 
142:         $validated = $request->validate([
143:             'name' => ['required', 'string', 'max:100'],
144:             'email' => ['required', 'email', 'max:255'],
145:             'phone' => ['required', 'string', 'max:30'],
146:             'service' => ['required', 'in:private-styling,virtual-consultation,gifting,atelier'],
147:             'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
148:             'preferred_time' => ['nullable', 'date_format:H:i'],
149:             'notes' => ['nullable', 'string', 'max:1000'],
150:             'order_items' => ['nullable', 'array', 'max:20'],
151:             'order_items.*' => ['array'],
152:             'order_items.*.id' => ['required', 'string', 'max:255', Rule::exists('products', 'product_slug')->where('is_active', true)],
153:             'order_items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
154:             'order_items.*.size' => ['nullable', 'string', 'max:50'],
155:             'order_items.*.monogram' => ['nullable', 'string', 'regex:/^[A-Z]{1,3}$/'],
156:         ]);
157: 
158:         if (! empty($validated['order_items'])) {
159:             $lines = [];
160:             $total = 0;
161:             foreach ($validated['order_items'] as $item) {
162:                 $product = Product::where('is_active', true)->where('product_slug', $item['id'])->firstOrFail();
163:                 $qty = (int) $item['quantity'];
164:                 $price = (int) $product->product_price;
165:                 $priceStr = 'Rp'.number_format($price, 0, ',', '.');
166:                 $details = array_filter([
167:                     ! empty($item['size']) ? 'Ukuran: '.$item['size'] : null,
168:                     ! empty($item['monogram']) ? 'Monogram: '.$item['monogram'] : null,
169:                 ]);
170:                 $suffix = $details ? ' · '.implode(' · ', $details) : '';
171:                 $lines[] = "- {$product->product_title} ({$qty}x) {$priceStr}{$suffix}";
172:                 $total += $price * $qty;
173:             }
174:             $existingNotes = trim((string) ($validated['notes'] ?? ''));
175:             $summary = "[Pesanan Checkout]\n".implode("\n", $lines)."\nTotal: Rp".number_format($total, 0, ',', '.');
176:             $validated['notes'] = trim($summary.($existingNotes !== '' ? "\n\nCatatan: ".$existingNotes : ''));
177:         }
178:         unset($validated['order_items']);
179: 
180:         $concierge = ConciergeRequest::create($validated + ['user_id' => $request->user()?->id]);
181:         Mail::to($concierge->email)->queue(new ConciergeRequested($concierge));
182: 
183:         $message = 'Permintaan rendez-vous Anda telah diterima. ZAFIRA Concierge akan segera menghubungi Anda.';
184: 
185:         if ($request->expectsJson()) {
186:             return response()->json(['message' => $message], 201);
187:         }
188: 
189:         return back()->with('success', $message);
190:     }
191: 
192:     public function subscribe(Request $request): JsonResponse|RedirectResponse
193:     {
194:         $this->normalizeEmail($request);
195: 
196:         $validated = $request->validate([
197:             'email' => ['required', 'email', 'max:255'],
198:         ]);
199: 
200:         $subscriber = NewsletterSubscriber::firstOrCreate($validated);
201: 
202:         if ($subscriber->wasRecentlyCreated) {
203:             Mail::to($subscriber->email)->queue(new NewsletterWelcome($subscriber));
204:         }
205: 
206:         $message = 'Terima kasih telah berlangganan La Lettre ZAFIRA.';
207: 
208:         if ($request->expectsJson()) {
209:             return response()->json(['message' => $message]);
210:         }
211: 
212:         return back()->with('success', $message);
213:     }
214: }
```

## app/Http/Controllers/CartController.php

```php
1: <?php
2: 
3: namespace App\Http\Controllers;
4: 
5: use App\Models\Product;
6: use App\Services\CheckoutService;
7: use Illuminate\Http\JsonResponse;
8: use Illuminate\Http\Request;
9: 
10: class CartController extends Controller
11: {
12:     public function show(Request $request): JsonResponse
13:     {
14:         return response()->json(CheckoutService::priced(CheckoutService::cart($request)));
15:     }
16: 
17:     public function store(Request $request): JsonResponse
18:     {
19:         $validated = $request->validate([
20:             'product_id' => ['required', 'integer', 'exists:products,product_id'],
21:             'quantity' => ['required', 'integer', 'min:1', 'max:'.CheckoutService::MAX_QUANTITY],
22:         ]);
23: 
24:         $product = Product::findOrFail($validated['product_id']);
25:         if (! $product->is_active || (int) $product->stock < $validated['quantity']) {
26:             return response()->json(['message' => 'Stok produk tidak mencukupi atau produk tidak aktif.'], 422);
27:         }
28: 
29:         $cart = CheckoutService::cart($request);
30:         $cart[$validated['product_id']] = $validated['quantity'];
31:         $request->session()->put(CheckoutService::cartKey($request), $cart);
32: 
33:         return response()->json(CheckoutService::priced($cart));
34:     }
35: 
36:     public function destroy(Request $request, int $productId): JsonResponse
37:     {
38:         $cart = CheckoutService::cart($request);
39:         unset($cart[$productId]);
40:         $request->session()->put(CheckoutService::cartKey($request), $cart);
41: 
42:         return response()->json(CheckoutService::priced($cart));
43:     }
44: 
45:     public function clear(Request $request): JsonResponse
46:     {
47:         $request->session()->forget(CheckoutService::cartKey($request));
48: 
49:         return response()->json(['items' => [], 'subtotal' => 0]);
50:     }
51: }
```

## app/Http/Controllers/OrderController.php

```php
1: <?php
2: 
3: namespace App\Http\Controllers;
4: 
5: use App\Models\Address;
6: use App\Models\Order;
7: use App\Services\CheckoutService;
8: use Illuminate\Http\JsonResponse;
9: use Illuminate\Http\Request;
10: 
11: class OrderController extends Controller
12: {
13:     public function __construct(private readonly CheckoutService $checkout) {}
14: 
15:     public function index(Request $request): JsonResponse
16:     {
17:         $orders = Order::where('user_id', $request->user()->id)
18:             ->with('items')
19:             ->latest('id')
20:             ->paginate(15);
21: 
22:         return response()->json($orders);
23:     }
24: 
25:     public function show(Request $request, int $id): JsonResponse
26:     {
27:         $order = Order::where('user_id', $request->user()->id)
28:             ->with(['items', 'voucher'])
29:             ->where('id', $id)
30:             ->firstOrFail();
31: 
32:         return response()->json(['order' => $order]);
33:     }
34: 
35:     public function store(Request $request): JsonResponse
36:     {
37:         $validated = $request->validate([
38:             'address_id' => ['required', 'integer'],
39:             'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
40:             'voucher_code' => ['nullable', 'string', 'max:40'],
41:         ]);
42: 
43:         $address = Address::where('user_id', $request->user()->id)
44:             ->where('id', $validated['address_id'])
45:             ->firstOrFail();
46: 
47:         [$order, $created] = $this->checkout->checkout(
48:             $request->user(),
49:             $address,
50:             CheckoutService::cart($request),
51:             $validated['idempotency_key'],
52:             $validated['voucher_code'] ?? null
53:         );
54: 
55:         if ($created) {
56:             $request->session()->forget(CheckoutService::cartKey($request));
57:         }
58: 
59:         return response()->json(['order' => $order], $created ? 201 : 200);
60:     }
61: 
62:     public function cancel(Request $request, int $id): JsonResponse
63:     {
64:         $order = Order::where('user_id', $request->user()->id)
65:             ->where('id', $id)
66:             ->firstOrFail();
67: 
68:         if (! $order->isCancellable()) {
69:             return response()->json(['message' => 'Pesanan ini tidak dapat dibatalkan.'], 422);
70:         }
71: 
72:         return response()->json(['order' => $this->checkout->transition($order, 'cancelled')]);
73:     }
74: }
```

## app/Services/CheckoutService.php

```php
1: <?php
2: 
3: namespace App\Services;
4: 
5: use App\Models\Address;
6: use App\Models\Order;
7: use App\Models\Product;
8: use App\Models\User;
9: use App\Models\Voucher;
10: use Illuminate\Database\QueryException;
11: use Illuminate\Http\Request;
12: use Illuminate\Support\Facades\DB;
13: use Illuminate\Validation\ValidationException;
14: 
15: class CheckoutService
16: {
17:     public const MAX_QUANTITY = 99;
18: 
19:     /** Keranjang server di sesi (bertahan saat login); hanya menyimpan product_id => quantity. */
20:     public static function cartKey(Request $request): string
21:     {
22:         return 'cart';
23:     }
24: 
25:     public static function cart(Request $request): array
26:     {
27:         return $request->session()->get(self::cartKey($request), []);
28:     }
29: 
30:     private static function validateCart(array $cart): void
31:     {
32:         foreach ($cart as $productId => $quantity) {
33:             if (! ctype_digit((string) $productId) || is_bool($quantity)
34:                 || filter_var($quantity, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) === null
35:                 || (int) $quantity < 1 || (int) $quantity > self::MAX_QUANTITY) {
36:                 throw ValidationException::withMessages(['cart' => 'Isi keranjang tidak valid.']);
37:             }
38:         }
39:     }
40: 
41:     /** Harga selalu diambil ulang dari database, bukan dari klien atau sesi. */
42:     public static function priced(array $cart): array
43:     {
44:         self::validateCart($cart);
45: 
46:         $products = Product::whereIn('product_id', array_keys($cart))->get()->keyBy('product_id');
47:         $lines = [];
48:         foreach ($cart as $productId => $quantity) {
49:             $product = $products->get($productId);
50:             if (! $product) {
51:                 continue;
52:             }
53:             $unit = self::idr($product->product_price);
54:             $lines[] = [
55:                 'product_id' => (int) $productId,
56:                 'product_title' => $product->product_title,
57:                 'unit_price' => $unit,
58:                 'quantity' => (int) $quantity,
59:                 'subtotal' => $unit * (int) $quantity,
60:                 'available' => (bool) $product->is_active && (int) $product->stock >= (int) $quantity,
61:             ];
62:         }
63: 
64:         return ['items' => $lines, 'subtotal' => array_sum(array_column($lines, 'subtotal'))];
65:     }
66: 
67:     public static function idr(mixed $price): int
68:     {
69:         if (! preg_match('/^([0-9]{1,12})(?:\.0+)?$/', (string) $price, $matches)) {
70:             throw ValidationException::withMessages(['cart' => 'Harga produk harus berupa rupiah bulat yang valid.']);
71:         }
72: 
73:         return (int) $matches[1];
74:     }
75: 
76:     public function checkout(User $user, Address $address, array $cart, string $key, ?string $voucherCode): array
77:     {
78:         self::validateCart($cart);
79:         $voucherCode = $voucherCode ? mb_strtoupper(trim($voucherCode)) : null;
80: 
81:         $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->first();
82:         if ($existing) {
83:             return [$this->replay($existing, $address, $cart, $voucherCode), false];
84:         }
85: 
86:         if ($cart === []) {
87:             throw ValidationException::withMessages(['cart' => 'Keranjang belanja masih kosong.']);
88:         }
89: 
90:         $hash = $this->hash($address->id, $voucherCode, $cart);
91: 
92:         try {
93:             $order = DB::transaction(fn () => $this->place($user, $address, $cart, $key, $hash, $voucherCode));
94:         } catch (QueryException $e) {
95:             // Permintaan paralel dengan kunci sama: unique index menang, kembalikan pesanan pemenang.
96:             $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->first();
97:             if (! $existing) {
98:                 throw $e;
99:             }
100: 
101:             return [$this->replay($existing, $address, $cart, $voucherCode), false];
102:         }
103: 
104:         return [$order, true];
105:     }
106: 
107:     private function replay(Order $order, Address $address, array $cart, ?string $voucherCode): Order
108:     {
109:         // Keranjang dikosongkan setelah commit; retry jaringan membawa keranjang kosong.
110:         $items = $cart !== [] ? $cart : $order->items->pluck('quantity', 'product_id')->all();
111:         if (! hash_equals($order->payload_hash, $this->hash($address->id, $voucherCode, $items))) {
112:             abort(409, 'Kunci idempotensi sudah dipakai untuk pesanan berbeda.');
113:         }
114: 
115:         return $order->load('items');
116:     }
117: 
118:     private function hash(int $addressId, ?string $voucherCode, array $cart): string
119:     {
120:         $items = array_map('intval', $cart);
121:         ksort($items);
122: 
123:         return hash('sha256', json_encode([$addressId, $voucherCode, $items]));
124:     }
125: 
126:     private function place(User $user, Address $address, array $cart, string $key, string $hash, ?string $voucherCode): Order
127:     {
128:         // Serialisasi checkout per pengguna untuk batas voucher per pengguna.
129:         User::whereKey($user->id)->lockForUpdate()->first();
130: 
131:         $priced = self::priced($cart);
132:         if (count($priced['items']) !== count($cart)) {
133:             throw ValidationException::withMessages(['cart' => 'Ada produk yang tidak lagi tersedia.']);
134:         }
135: 
136:         foreach ($priced['items'] as $line) {
137:             $updated = Product::whereKey($line['product_id'])
138:                 ->where('is_active', true)
139:                 ->where('stock', '>=', $line['quantity'])
140:                 ->decrement('stock', $line['quantity']);
141:             if ($updated !== 1) {
142:                 throw ValidationException::withMessages(['cart' => "Stok {$line['product_title']} tidak mencukupi."]);
143:             }
144:         }
145: 
146:         $voucher = $voucherCode ? $this->claimVoucher($user, $voucherCode) : null;
147:         $discount = $voucher ? min($voucher->discount_amount, $priced['subtotal']) : 0;
148:         // shortcut: ongkir 0 sampai tarif pengiriman ditentukan.
149:         $shipping = 0;
150: 
151:         $order = Order::create([
152:             'user_id' => $user->id,
153:             'status' => 'pending',
154:             'subtotal' => $priced['subtotal'],
155:             'discount' => $discount,
156:             'shipping_fee' => $shipping,
157:             'total' => $priced['subtotal'] - $discount + $shipping,
158:             'idempotency_key' => $key,
159:             'payload_hash' => $hash,
160:             'shipping_address' => $address->snapshot(),
161:             'voucher_id' => $voucher?->id,
162:         ]);
163: 
164:         foreach ($priced['items'] as $line) {
165:             unset($line['available']);
166:             $order->items()->create($line);
167:         }
168: 
169:         return $order->load('items');
170:     }
171: 
172:     private function claimVoucher(User $user, string $code): Voucher
173:     {
174:         $voucher = Voucher::where('code', $code)->where('is_active', true)->first();
175:         if (! $voucher) {
176:             throw ValidationException::withMessages(['voucher_code' => 'Voucher tidak valid.']);
177:         }
178: 
179:         if ($voucher->per_user_limit > 0) {
180:             $used = Order::where('user_id', $user->id)
181:                 ->where('voucher_id', $voucher->id)
182:                 ->where('status', '!=', 'cancelled')
183:                 ->count();
184:             if ($used >= $voucher->per_user_limit) {
185:                 throw ValidationException::withMessages(['voucher_code' => 'Batas pemakaian voucher Anda sudah tercapai.']);
186:             }
187:         }
188: 
189:         $claimed = Voucher::whereKey($voucher->id)
190:             ->where('is_active', true)
191:             ->whereColumn('used', '<', 'quota')
192:             ->increment('used');
193:         if ($claimed !== 1) {
194:             throw ValidationException::withMessages(['voucher_code' => 'Kuota voucher sudah habis.']);
195:         }
196: 
197:         return $voucher;
198:     }
199: 
200:     /** Ubah status; stok dan kuota voucher dikembalikan tepat sekali saat transisi ke cancelled. */
201:     public function transition(Order $order, string $status): Order
202:     {
203:         if ($order->status === $status && $status === 'cancelled') {
204:             return $order;
205:         }
206: 
207:         if (! $order->canTransitionTo($status)) {
208:             throw ValidationException::withMessages(['status' => "Status {$order->status} tidak dapat diubah ke {$status}."]);
209:         }
210: 
211:         DB::transaction(function () use ($order, $status) {
212:             $updated = Order::whereKey($order->id)->where('status', $order->status)->update(['status' => $status]);
213:             if ($updated !== 1) {
214:                 abort(409, 'Status pesanan berubah, muat ulang lalu coba lagi.');
215:             }
216: 
217:             if ($status === 'cancelled') {
218:                 foreach ($order->items as $item) {
219:                     Product::whereKey($item->product_id)->increment('stock', $item->quantity);
220:                 }
221:                 if ($order->voucher_id) {
222:                     Voucher::whereKey($order->voucher_id)->where('used', '>', 0)->decrement('used');
223:                 }
224:             }
225:         });
226: 
227:         return $order->refresh()->load('items');
228:     }
229: }
```

## app/Models/Order.php

```php
1: <?php
2: 
3: namespace App\Models;
4: 
5: use Illuminate\Database\Eloquent\Model;
6: use Illuminate\Database\Eloquent\Relations\BelongsTo;
7: use Illuminate\Database\Eloquent\Relations\HasMany;
8: 
9: class Order extends Model
10: {
11:     public const STATUS_FLOW = [
12:         'pending' => ['paid', 'cancelled'],
13:         'paid' => ['processing', 'cancelled'],
14:         'processing' => ['shipped', 'cancelled'],
15:         'shipped' => ['completed'],
16:         'completed' => [],
17:         'cancelled' => [],
18:     ];
19: 
20:     protected $fillable = [
21:         'user_id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total',
22:         'idempotency_key', 'payload_hash', 'shipping_address', 'voucher_id',
23:     ];
24: 
25:     protected function casts(): array
26:     {
27:         return [
28:             'shipping_address' => 'array',
29:             'subtotal' => 'integer',
30:             'discount' => 'integer',
31:             'shipping_fee' => 'integer',
32:             'total' => 'integer',
33:         ];
34:     }
35: 
36:     public function user(): BelongsTo
37:     {
38:         return $this->belongsTo(User::class);
39:     }
40: 
41:     public function voucher(): BelongsTo
42:     {
43:         return $this->belongsTo(Voucher::class);
44:     }
45: 
46:     public function items(): HasMany
47:     {
48:         return $this->hasMany(OrderItem::class);
49:     }
50: 
51:     public function canTransitionTo(string $status): bool
52:     {
53:         return in_array($status, self::STATUS_FLOW[$this->status] ?? [], true);
54:     }
55: 
56:     public function isCancellable(): bool
57:     {
58:         return $this->canTransitionTo('cancelled') || $this->status === 'cancelled';
59:     }
60: }
```

## app/Http/Controllers/AdminOrderController.php

```php
1: <?php
2: 
3: namespace App\Http\Controllers;
4: 
5: use App\Models\Order;
6: use App\Services\CheckoutService;
7: use App\Services\PdfDocumentService;
8: use Illuminate\Http\JsonResponse;
9: use Illuminate\Http\RedirectResponse;
10: use Illuminate\Http\Request;
11: use Illuminate\Http\Response;
12: use Illuminate\Support\Carbon;
13: use Illuminate\Validation\Rule;
14: use Illuminate\Validation\ValidationException;
15: 
16: class AdminOrderController extends Controller
17: {
18:     private const REPORT_MAX_ORDERS = 500;
19: 
20:     public function __construct(private readonly CheckoutService $checkout) {}
21: 
22:     public function index(Request $request): JsonResponse
23:     {
24:         $orders = Order::with(['items', 'user:id,name,email'])
25:             ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
26:             ->latest('id')
27:             ->paginate(20);
28: 
29:         return response()->json($orders);
30:     }
31: 
32:     public function show(int $id): JsonResponse
33:     {
34:         return response()->json(['order' => Order::with(['items', 'voucher', 'user'])->findOrFail($id)]);
35:     }
36: 
37:     public function updateStatus(Request $request, int $id): JsonResponse|RedirectResponse
38:     {
39:         $order = Order::with('items')->findOrFail($id);
40:         $validated = $request->validate([
41:             'status' => ['required', 'string', Rule::in(['paid', 'processing', 'shipped', 'completed', 'cancelled'])],
42:         ]);
43: 
44:         $order = $this->checkout->transition($order, $validated['status']);
45: 
46:         if (! $request->expectsJson()) {
47:             return back()->with('success', "Status pesanan #{$order->id} menjadi {$order->status}.");
48:         }
49: 
50:         return response()->json(['order' => $order]);
51:     }
52: 
53:     /** Laporan PDF berdasarkan created_at UTC; nilai pesanan bukan bukti pembayaran. */
54:     public function report(Request $request, PdfDocumentService $pdf): Response
55:     {
56:         $validated = $request->validate([
57:             'period' => ['required', Rule::in(['today', '7d', '30d', 'custom'])],
58:             'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
59:             'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
60:         ]);
61: 
62:         $now = Carbon::now('UTC');
63:         [$from, $to] = match ($validated['period']) {
64:             'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
65:             '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
66:             '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
67:             'custom' => [
68:                 Carbon::createFromFormat('!Y-m-d', $validated['from'], 'UTC')->startOfDay(),
69:                 Carbon::createFromFormat('!Y-m-d', $validated['to'], 'UTC')->endOfDay(),
70:             ],
71:         };
72: 
73:         if ($from->diffInDays($to) >= 366) {
74:             throw ValidationException::withMessages(['to' => 'Rentang laporan maksimal 366 hari.']);
75:         }
76: 
77:         // Ambil satu baris lebih dari batas untuk mendeteksi kelebihan tanpa query count terpisah.
78:         $orders = Order::with('user:id,name')->whereBetween('created_at', [$from, $to])
79:             ->orderBy('created_at')->orderBy('id')
80:             ->limit(self::REPORT_MAX_ORDERS + 1)->get();
81: 
82:         if ($orders->count() > self::REPORT_MAX_ORDERS) {
83:             return response(
84:                 'Laporan melebihi '.self::REPORT_MAX_ORDERS.' pesanan. Persempit rentang tanggal lalu unduh ulang.',
85:                 422,
86:                 ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store']
87:             );
88:         }
89: 
90:         $active = $orders->where('status', '!=', 'cancelled');
91:         $lines = [
92:             'Periode (UTC): '.$from->format('d-m-Y H:i').' hingga '.$to->format('d-m-Y H:i').' UTC',
93:             'Pesanan dibuat: '.$orders->count().' (dibatalkan: '.($orders->count() - $active->count()).')',
94:             'Nilai pesanan non-batal: Rp '.number_format((int) $active->sum('total'), 0, ',', '.'),
95:             'Catatan: nilai pesanan tercatat, bukan pendapatan terbayar/terverifikasi.',
96:             '',
97:         ];
98: 
99:         foreach (array_keys(Order::STATUS_FLOW) as $status) {
100:             $lines[] = 'Status '.strtoupper($status).': '.$orders->where('status', $status)->count();
101:         }
102: 
103:         $lines[] = '';
104: 
105:         foreach ($orders as $order) {
106:             $customer = trim((string) ($order->user?->name ?? '')) ?: '-';
107:             $lines[] = sprintf('#%d | %s | %s | Pelanggan: %s | Rp %s', $order->id, $order->created_at->format('d-m-Y H:i'), strtoupper($order->status), $customer, number_format($order->total, 0, ',', '.'));
108:         }
109: 
110:         $filename = 'zafira-laporan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';
111: 
112:         return response($pdf->render('ZAFIRA - LAPORAN PESANAN', $lines), 200, [
113:             'Content-Type' => 'application/pdf',
114:             'Content-Disposition' => 'attachment; filename="'.$filename.'"',
115:             'Cache-Control' => 'private, no-store',
116:         ]);
117:     }
118: 
119:     /**
120:      * Slip pesanan HTML ramah cetak tanpa dependensi eksternal.
121:      * shortcut: format HTML responsif sampai pustaka PDF khusus disetujui.
122:      */
123:     public function invoice(int $id): Response
124:     {
125:         $order = Order::with(['items', 'user'])->findOrFail($id);
126:         $addr = $order->shipping_address ?? [];
127: 
128:         $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Slip Pesanan #'.$order->id.'</title>'
129:             .'<style>body{font-family:serif;max-width:700px;margin:20px auto;color:#1c1b1a}h1{letter-spacing:2px;font-size:20px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border-bottom:1px solid #ddd;padding:8px;text-align:left}th:last-child,td:last-child{text-align:right}</style></head>'
130:             .'<body><h1>ZAFIRA &mdash; SLIP PESANAN #'.$order->id.'</h1>'
131:             .'<p>Tanggal: '.$order->created_at->format('d M Y H:i').'<br>Klien: '.e($order->user->name).' ('.e($order->user->email).')<br>'
132:             .'Status: '.strtoupper($order->status).'<br>Tujuan: '.e($addr['line1'] ?? '-').', '.e($addr['city'] ?? '-').' '.e($addr['postal_code'] ?? '').'</p>'
133:             .'<table><thead><tr><th>Produk</th><th>Jml</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';
134: 
135:         foreach ($order->items as $item) {
136:             $html .= '<tr><td>'.e($item->product_title).'</td><td>'.$item->quantity.'</td><td>Rp '.number_format($item->unit_price, 0, ',', '.').'</td><td>Rp '.number_format($item->subtotal, 0, ',', '.').'</td></tr>';
137:         }
138: 
139:         $html .= '</tbody></table><p style="text-align:right;font-weight:bold;margin-top:20px">'
140:             .'Subtotal: Rp '.number_format($order->subtotal, 0, ',', '.').'<br>'
141:             .'Diskon: Rp '.number_format($order->discount, 0, ',', '.').'<br>'
142:             .'Total: Rp '.number_format($order->total, 0, ',', '.').'</p></body></html>';
143: 
144:         return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
145:     }
146: }
```

## app/Services/PdfDocumentService.php

```php
1: <?php
2: 
3: namespace App\Services;
4: 
5: use Dompdf\Dompdf;
6: use Dompdf\Options;
7: 
8: class PdfDocumentService
9: {
10:     private const LINES_PER_PAGE = 44;
11: 
12:     private const MAX_LINE_LENGTH = 90;
13: 
14:     /** @param list<string> $lines */
15:     public function render(string $title, array $lines): string
16:     {
17:         $cacheDir = storage_path('app/private/pdf');
18:         if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0700, true) && ! is_dir($cacheDir)) {
19:             throw new \RuntimeException('PDF storage directory unavailable.');
20:         }
21:         $storage = realpath(storage_path());
22:         if ($storage === false || ! str_starts_with(realpath($cacheDir).DIRECTORY_SEPARATOR, $storage.DIRECTORY_SEPARATOR)) {
23:             throw new \RuntimeException('PDF cache must remain inside application storage.');
24:         }
25: 
26:         $fontDir = resource_path('fonts');
27:         $font = $fontDir.'/NotoSansSC-Regular.ttf';
28:         if (! is_file($font) || ! is_readable($font)) {
29:             throw new \RuntimeException('PDF font unavailable.');
30:         }
31: 
32:         $options = new Options;
33:         $options->setIsRemoteEnabled(false);
34:         $options->setIsPhpEnabled(false);
35:         $options->setIsJavascriptEnabled(false);
36:         $options->setAllowedProtocols(['file://']);
37:         $options->setChroot([$fontDir, $cacheDir]);
38:         $options->setFontDir($cacheDir);
39:         $options->setFontCache($cacheDir);
40:         $options->setTempDir($cacheDir);
41: 
42:         // Noto's CJK cmap needs more than PHP's default 128 MB during font parsing/subsetting.
43:         $memoryLimit = ini_get('memory_limit');
44:         $raised = false;
45:         if ($memoryLimit !== false && $memoryLimit !== '-1' && ini_parse_quantity($memoryLimit) < 268435456) {
46:             $raised = ini_set('memory_limit', '256M') !== false;
47:         }
48:         try {
49:             $dompdf = new Dompdf($options);
50:             $metrics = $dompdf->getFontMetrics();
51:             if (! $metrics->registerFont(['family' => 'Report', 'style' => 'normal', 'weight' => 'normal'], $font)) {
52:                 throw new \RuntimeException('PDF font registration failed.');
53:             }
54:             $reportFont = $metrics->getFont('Report', 'normal');
55:             $wrapped = [];
56:             foreach ($lines as $line) {
57:                 $line = $this->clean($line);
58:                 do {
59:                     $length = min(mb_strlen($line), self::MAX_LINE_LENGTH);
60:                     while ($length > 1 && $metrics->getTextWidth(mb_substr($line, 0, $length), $reportFont, 10) > 483) {
61:                         $length--;
62:                     }
63:                     if ($length < mb_strlen($line)) {
64:                         $space = mb_strrpos(mb_substr($line, 0, $length + 1), ' ');
65:                         if ($space !== false && $space > 0) {
66:                             $length = $space;
67:                         }
68:                     }
69:                     $wrapped[] = rtrim(mb_substr($line, 0, $length));
70:                     $line = ltrim(mb_substr($line, $length));
71:                 } while ($line !== '');
72:             }
73:             $pages = array_chunk($wrapped, self::LINES_PER_PAGE) ?: [[]];
74:             $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
75:                 .'@page{margin:56pt 56pt 50pt;}'
76:                 .'body{font-family:Report;font-size:10pt;line-height:14pt;margin:0;}'
77:                 .'h1{font-size:15pt;font-weight:normal;line-height:20pt;margin:0 0 6pt;overflow-wrap:break-word;}'
78:                 .'.line{height:14pt;white-space:pre;}'
79:                 .'</style></head><body>';
80:             foreach ($pages as $number => $page) {
81:                 if ($number > 0) {
82:                     $html .= '<div style="page-break-before:always"></div>';
83:                 }
84:                 $html .= '<h1>'.$this->escape($this->clean($title)).'</h1>';
85:                 foreach ($page as $line) {
86:                     $html .= '<div class="line">'.$this->escape($line).'</div>';
87:                 }
88:             }
89:             $html .= '</body></html>';
90: 
91:             $dompdf->loadHtml($html, 'UTF-8');
92:             $dompdf->setPaper('A4');
93:             $dompdf->render();
94:             $dompdf->getCanvas()->page_text(56, 794, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $reportFont, 8);
95: 
96:             return $dompdf->output();
97:         } finally {
98:             unset($dompdf, $metrics, $reportFont);
99:             gc_collect_cycles();
100:             // Restore only when the original limit is still above current usage; PHP rejects lower limits otherwise.
101:             if ($raised && memory_get_usage(true) < ini_parse_quantity($memoryLimit)) {
102:                 ini_set('memory_limit', $memoryLimit);
103:             }
104:         }
105:     }
106: 
107:     private function clean(string $text): string
108:     {
109:         $previous = mb_substitute_character();
110:         try {
111:             mb_substitute_character(0x3F);
112:             $text = mb_scrub($text, 'UTF-8');
113:         } finally {
114:             mb_substitute_character($previous);
115:         }
116:         if (class_exists(\Normalizer::class)) {
117:             $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
118:         }
119: 
120:         return preg_replace('/\p{Cc}/u', '?', $text) ?? '';
121:     }
122: 
123:     private function escape(string $text): string
124:     {
125:         return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
126:     }
127: }
```

## app/Http/Middleware/SecurityHeaders.php

```php
1: <?php
2: 
3: namespace App\Http\Middleware;
4: 
5: use Closure;
6: use Illuminate\Http\Request;
7: use Symfony\Component\HttpFoundation\Response;
8: 
9: class SecurityHeaders
10: {
11:     public function handle(Request $request, Closure $next): Response
12:     {
13:         $response = $next($request);
14: 
15:         $response->headers->set('X-Content-Type-Options', 'nosniff');
16:         $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
17:         $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
18:         $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
19:         $response->headers->set('X-XSS-Protection', '0');
20: 
21:         if (config('security.csp_report_only', false)) {
22:             $response->headers->set(
23:                 'Content-Security-Policy-Report-Only',
24:                 $this->reportOnlyPolicy(),
25:             );
26:         }
27: 
28:         return $response;
29:     }
30: 
31:     /**
32:      * Allow known storefront asset hosts without hiding inline-code violations.
33:      * Report-Only does not block resources.
34:      * Reporting endpoint is omitted until one exists (see config/security.php).
35:      */
36:     private function reportOnlyPolicy(): string
37:     {
38:         return implode('; ', [
39:             "default-src 'self'",
40:             "base-uri 'self'",
41:             "object-src 'none'",
42:             "frame-ancestors 'self'",
43:             "img-src 'self' data: https://images.unsplash.com",
44:             "font-src 'self' https://fonts.bunny.net",
45:             // shortcut: inline <style>/style= used across views, drop 'unsafe-inline' once moved to CSS files.
46:             "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
47:             // Strict on purpose: inline bootstrap scripts surface as reports to fix before enforcing.
48:             "script-src 'self'",
49:             "connect-src 'self'",
50:             "form-action 'self'",
51:         ]);
52:     }
53: }
```

## Lampiran UI statis — sumber aktual (tanpa browser)
Rentang berikut dikutip apa adanya dari source. Mereka membuktikan struktur, bukan perilaku runtime atau WCAG conformance.

### `resources/views/welcome.blade.php`

```text
 557:     <aside class="drawer cart-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="cart-title" tabindex="-1">
 558:         <div class="drawer-header"><div><span class="eyebrow">Votre Sélection</span><h2 id="cart-title"><span data-i18n="cart_aria">Tas Belanja</span> <small class="cart-count-text">(0)</small></h2></div><button class="icon-button" type="button" data-close-cart aria-label="Tutup tas belanja"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
 559:         <!-- Undo Banner (Shneiderman Rule 6 / Nielsen Heuristic 3) -->
 560:         <div id="welcome-undo-banner" style="display:none; padding:10px 16px; background:#eff6ff; border-bottom:1px solid #bfdbfe; font-size:12px; color:#1e40af; justify-content:space-between; align-items:center;">
 561:             <span id="welcome-undo-text">Kreasi dihapus dari tas belanja.</span>
 562:             <button type="button" id="welcome-undo-btn" style="background:none; border:none; color:#1d4ed8; font-weight:700; text-decoration:underline; cursor:pointer;">Batalkan (Undo)</button>
 563:         </div>
 564:         <div class="cart-body" data-cart-items></div>
 565:         <div class="cart-empty" data-cart-empty><span class="empty-gem"></span><h3 data-i18n="cart_empty_title">Tas Anda masih kosong</h3><p data-i18n="cart_empty_desc">Temukan kreasi yang akan menjadi bagian dari kisah Anda.</p><button class="button button-dark" type="button" data-close-cart data-i18n="cart_empty_cta">Jelajahi koleksi</button></div>
...
 698:     <div class="modal-backdrop" id="welcome-shortcuts-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="shortcuts-title" tabindex="-1" style="display:none; position:fixed; inset:0; background:rgba(8,16,29,0.72); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center;" data-close-shortcuts>
 699:         <div class="modal-window" style="background:var(--surface, #fff); max-width:440px; width:90%; padding:28px; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.3); border:1px solid var(--line);" onclick="event.stopPropagation()">
 700:             <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
 701:                 <h3 id="shortcuts-title" style="font-family:'Bodoni Moda', serif; font-size:20px; color:var(--ink, #17263f); margin:0;" data-i18n="shortcuts_title">Pintasan Keyboard ZAFIRA</h3>
 702:                 <button type="button" data-close-shortcuts style="background:none; border:none; cursor:pointer; color:var(--muted); display:flex; align-items:center;" aria-label="Tutup panduan pintasan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
 703:             </div>
...
```

### `resources/views/catalog/index.blade.php`

```text
 532:         @endif
 533:     </main>
 534: 
 535:     <!-- Accessible Native Dialog Quick View -->
 536:     <dialog id="catalog-quick-dialog" class="catalog-dialog" aria-modal="true" aria-labelledby="dialog-title">
 537:         <button type="button" class="catalog-dialog-close" id="dialog-close-btn" aria-label="{{ $t['close'] }}">✕</button>
 538:         <div class="catalog-dialog-inner">
 539:             <div class="catalog-dialog-media">
 540:                 <img id="dialog-image" src="" alt="" width="400" height="500">
 541:             </div>
 542:             <div class="catalog-dialog-content">
 543:                 <span class="catalog-card-category" id="dialog-category"></span>
...
 591:                         s1.textContent = mat;
 592:                         attrsContainer.appendChild(s1);
 593:                     }
 594:                     if (col) {
 595:                         var s2 = document.createElement('span');
 596:                         s2.textContent = col;
 597:                         attrsContainer.appendChild(s2);
 598:                     }
 599: 
 600:                     if (typeof dialog.showModal === 'function') {
 601:                         dialog.showModal();
 602:                         closeBtn.focus();
 603:                     }
 604:                 });
 605:             });
 606: 
 607:             function closeDialog() {
 608:                 if (dialog.open) {
 609:                     dialog.close();
 610:                     if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
 611:                         lastActiveElement.focus();
 612:                     }
 613:                 }
 614:             }
 615: 
 616:             var feedback = document.getElementById('catalog-feedback');
...
```

### `resources/views/shop/index.blade.php`

```text
   1: <!DOCTYPE html>
   2: <html lang="id">
   3: <head>
   4: <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
   5: <title>Belanja — ZAFIRA La Maison de Lumière</title>
   6: <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=bodoni-moda:400,500,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
   7: <style>
   8: :root{--sapphire:#0d1b2a;--linen:#f4ede2;--gold:#a17d36;--ivory:#faf7f2;--line:#d8d0c5}
   9: *{box-sizing:border-box}body{margin:0;background:var(--ivory);color:var(--sapphire);font:15px/1.65 Manrope,sans-serif}a{color:var(--sapphire)}a:hover{color:var(--gold)}h1,h2,h3,.brand{font-family:'Bodoni Moda',serif;font-weight:400}h1{font-size:clamp(2.3rem,5vw,4rem);margin:.2em 0}h2{font-size:2rem;margin:0 0 .5rem}h3{font-size:1.4rem;margin:.5rem 0}.brand{font-size:1.7rem;letter-spacing:.18em;text-decoration:none}header,main,footer{max-width:1100px;margin:auto;padding:24px}header{display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap}nav{display:flex;gap:16px;flex-wrap:wrap}nav a{min-height:44px;display:inline-flex;align-items:center}main{padding-bottom:72px}.intro{border-top:1px solid var(--line);padding:36px 0}.eyebrow{color:var(--gold);letter-spacing:.2em;text-transform:uppercase;font-size:.75rem}section.panel{background:white;border:1px solid var(--line);padding:clamp(18px,3vw,32px);margin:20px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px}.stack{display:grid;gap:12px}.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.item{border-top:1px solid var(--line);padding:14px 0;overflow-wrap:anywhere}.muted{color:#555}label{display:block;font-size:.85rem;font-weight:600}input,select,textarea,button{font:inherit}input,select,textarea{width:100%;padding:10px 12px;border:1px solid #827b72;background:white;min-height:44px;color:var(--sapphire)}textarea{min-height:80px}button,.button{border:1px solid var(--sapphire);background:var(--sapphire);color:white;cursor:pointer;padding:10px 16px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}button:hover,button:focus-visible,.button:hover,.button:focus-visible{background:var(--gold);border-color:var(--gold);color:white}button:disabled{opacity:.5;cursor:not-allowed}.secondary{background:white;color:var(--sapphire)}:focus-visible{outline:3px solid var(--gold);outline-offset:2px}.notice{background:var(--linen);border-left:4px solid var(--gold);padding:14px 18px}.status{min-height:24px}footer{border-top:1px solid var(--line);color:#555}.skip{position:absolute;left:-9999px}.skip:focus{left:12px;top:12px;background:white;padding:12px;z-index:10}
  10: </style>
  11: </head>
  12: <body>
  13: <a class="skip" href="#main">Lewati ke konten utama</a>
  14: <header><a class="brand" href="{{ route('home') }}">ZAFIRA</a><nav aria-label="Navigasi belanja"><a href="{{ route('catalog.index') }}">Koleksi</a><a href="#tas">Tas</a><a href="#alamat">Alamat</a><a href="#pesanan">Pesanan</a><a href="#favorit">Favorit</a>@auth<a href="{{ route('account.show') }}">Akun</a>@else<a href="{{ route('login') }}">Masuk</a>@endauth</nav></header>
  15: <main id="main"><div class="intro"><span class="eyebrow">Le Salon Privé</span><h1>Belanja di Maison</h1><p>Kelola pesanan nyata dari koleksi ZAFIRA.</p><p class="notice">Tas lama pada beranda atau halaman produk disimpan di peramban dan <strong>tidak tersambung</strong> ke tas server ini. Item dalam tas lama tidak otomatis muncul di sini. Tambahkan ulang dari <a href="{{ route('catalog.index') }}">Koleksi</a> sebelum checkout. Ukuran, warna pilihan, dan monogram <strong>belum didukung</strong> dalam pesanan ini; produk dikirim sesuai spesifikasi katalog. Jangan lanjutkan checkout bila Anda memerlukan opsi tersebut.</p></div>
  16: <p id="feedback" role="status" aria-live="polite" class="notice status">Memuat belanja…</p>
  17: <div class="grid"><section class="panel" id="tas"><h2>Tas server</h2><div id="cart-list"></div><p id="cart-total"></p><button type="button" id="clear-cart" class="secondary">Kosongkan tas</button></section>
  18: <section class="panel" id="alamat"><h2>Alamat pengiriman</h2><div id="address-list"></div>@auth<form id="address-form" class="stack"><h3>Tambah alamat</h3><label>Label<input name="label" required maxlength="50" placeholder="Rumah"></label><label>Nama penerima<input name="recipient_name" required maxlength="120" autocomplete="name"></label><label>Telepon<input name="phone" required maxlength="30" autocomplete="tel"></label><label>Alamat jalan<input name="line1" required maxlength="255" autocomplete="street-address"></label><label>Detail tambahan<input name="line2" maxlength="255"></label><label>Kota<input name="city" required maxlength="120" autocomplete="address-level2"></label><label>Provinsi<input name="province" required maxlength="120" autocomplete="address-level1"></label><label>Kode pos<input name="postal_code" required maxlength="20" autocomplete="postal-code"></label><label class="row"><input style="width:auto" type="checkbox" name="is_default" value="1"> Jadikan alamat utama</label><button>Tambah alamat</button></form>@endauth</section></div>
...
 126:     $('clear-cart').onclick = () => action(async () => { if (!confirm('Kosongkan seluruh tas server?')) return; await api(@json(route('cart.clear')), 'DELETE'); checkoutKey = null; await loadCart(); message('Tas server dikosongkan.'); });
 127:     if (loggedIn) {
 128:         $('address-form').onsubmit = event => { event.preventDefault(); const fields = new FormData(event.target); action(async () => { await api(@json(route('addresses.store')), 'POST', Object.fromEntries(fields)); event.target.reset(); await loadAddresses(); message('Alamat tersimpan.'); }); };
 129:         $('checkout-form').addEventListener('input', () => { checkoutKey = null; });
 130:         $('checkout-form').onsubmit = event => { event.preventDefault(); action(async () => {
 131:             if (!$('checkout-address').value) { message('Pilih alamat pengiriman terlebih dahulu.'); return; }
 132:             if (!cart.items.length || cart.items.some(i => !i.available)) { message('Periksa tas dan stok sebelum checkout.'); return; }
 133:             const form = event.target;
 134:             const payload = {address_id:Number(form.address_id.value), voucher_code:form.voucher_code.value.trim() || null, idempotency_key:checkoutKey ||= crypto.randomUUID()};
 135:             try { const result = await api(@json(route('checkout.store')), 'POST', payload); checkoutKey = null; await loadCart(); await loadOrders(); message(`Pesanan #${result.order.id} dibuat: pending / belum dibayar. Total ${money(result.order.total)}. Tidak ada pembayaran online.`); }
 136:             catch (error) { if ([409, 422].includes(error.status)) checkoutKey = null; throw error; }
 137:         }); };
 138:     }
 139:     action(async () => { await loadCart(); if (loggedIn) await Promise.all([loadAddresses(), loadOrders(), loadFavorites()]); else { $('address-list').append(text('p', 'Masuk untuk menyimpan alamat.')); $('order-list').append(text('p', 'Masuk untuk melihat pesanan.')); $('favorite-list').append(text('p', 'Masuk untuk melihat favorit.')); } message('Tas server siap.'); });
...
```

### `resources/views/admin/dashboard.blade.php`

```text
 175:             <tbody>
 176:             @foreach ($latestProducts as $product)
 177:                 <tr>
 178:                     <td><a href="{{ route('products.show', $product->product_slug) }}">{{ $product->product_title }}</a><br><small style="color:var(--muted)">{{ $product->product_slug }}</small></td>
 179:                     <td class="num">Rp{{ number_format($product->product_price, 0, ',', '.') }}</td>
 180:                     <td>
 181:                         <form class="status-form" method="POST" action="{{ route('admin.products.update', $product->product_id) }}">
 182:                             @csrf
 183:                             @method('PATCH')
 184:                             <select name="product_cat" aria-label="Kategori {{ $product->product_title }}">
 185:                                 @foreach ($categories as $category)
 186:                                     <option value="{{ $category->cat_id }}" @selected($product->product_cat == $category->cat_id)>{{ $category->cat_title }}</option>
 187:                                 @endforeach
 188:                             </select>
 189:                             <input name="stock" type="number" min="0" max="100000" step="1" value="{{ $product->stock }}" required aria-label="Stok {{ $product->product_title }}" style="width:90px">
 190:                             <select name="is_active" aria-label="Visibilitas {{ $product->product_title }}"><option value="1" @selected($product->is_active)>Aktif</option><option value="0" @selected(! $product->is_active)>Nonaktif</option></select>
 191:                             <button type="submit">Simpan</button>
...
```

### `resources/js/app.js`

```text
   1: import './bootstrap';
   2: 
   3: const select = (selector, scope = document) => scope.querySelector(selector);
   4: const selectAll = (selector, scope = document) => [...scope.querySelectorAll(selector)];
   5: const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
   6: const smoothScrollTo = (element) => {
   7:     if (!element) return;
   8:     element.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
   9: };
...
  68: const storefront = select('.storefront');
  69: 
  70: if (storefront) {
  71:     const overlay = select('[data-overlay]');
  72:     const cartDrawer = select('.cart-drawer');
  73:     const searchPanel = select('.search-panel');
  74:     const quickViewModal = select('.quick-view-modal');
  75:     const conciergeModal = select('.concierge-modal');
  76:     const profileDrawer = select('#welcome-profile-drawer');
  77:     const sizeGuideDrawer = select('[data-size-guide]');
  78:     const shortcutsModal = select('#welcome-shortcuts-backdrop');
  79:     const mobileMenu = select('.mobile-menu');
  80:     [cartDrawer, searchPanel, quickViewModal, conciergeModal, profileDrawer, sizeGuideDrawer, shortcutsModal].forEach((element) => {
  81:         if (!element) return;
  82:         element.inert = true;
  83:         element.setAttribute('aria-hidden', 'true');
  84:     });
...
 176:             select('.modal-window', element)?.classList.add('open');
 177:         }
 178:         element.inert = false;
 179:         element.classList.add('open');
 180:         element.setAttribute('aria-hidden', 'false');
 181:         syncOverlay();
 182:         const preferredFocus = element === searchPanel
 183:             ? select('[data-search-input]', element)
 184:             : select('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])', element);
 185:         // Retry while opening transitions keep the target unfocusable.
 186:         const focusInside = (attempt = 0) => {
 187:             if (!element.classList.contains('open') || !preferredFocus || element.contains(document.activeElement)) return;
 188:             preferredFocus.focus();
 189:             if (document.activeElement !== preferredFocus && attempt < 10) {
 190:                 setTimeout(() => focusInside(attempt + 1), 50);
 191:             }
 192:         };
 193:         setTimeout(focusInside, 50);
 194:     }
 195: 
 196:     function closeElement(element, restoreFocus = true) {
 197:         if (!element) return;
 198:         const wasOpen = element.classList.contains('open');
 199:         element.classList.remove('open');
 200:         element.setAttribute('aria-hidden', 'true');
 201:         element.inert = true;
 202:         if (element === shortcutsModal) {
 203:             select('.modal-window', element)?.classList.remove('open');
 204:             element.style.display = 'none';
 205:         }
 206:         syncOverlay();
 207:         const opener = element._opener || lastFocusedElement;
 208:         if (wasOpen && restoreFocus && !select('.open[role="dialog"], .mobile-menu.open')) opener?.focus?.();
 209:     }    
 210: 
...
 355:     function renderCart() {
 356:         const container = select('[data-cart-items]');
 357:         const empty = select('[data-cart-empty]');
 358:         const footer = select('[data-cart-footer]');
 359:         if (!container || !empty || !footer) return;
 360: 
 361:         container.innerHTML = '';
 362:         empty.hidden = cart.length > 0;
 363:         footer.hidden = cart.length === 0;
 364: 
 365:         cart.forEach((item) => {
 366:             const element = document.createElement('article');
 367:             element.className = 'cart-item';
 368:             element.dataset.cartId = cartKey(item);
 369:             element.innerHTML = `
 370:                 <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" width="100" height="125" loading="lazy">
 371:                 <div class="cart-item-info">
 372:                     <span>${escapeHtml(item.category)}</span>
 373:                     <h3><a href="/produk/${encodeURIComponent(item.id)}">${escapeHtml(item.name)}</a></h3>
 374:                     <p translate="no">${escapeHtml(item.priceLabel)}</p>
 375:                     ${item.size ? `<p>Ukuran: ${escapeHtml(item.size)}</p>` : ''}
 376:                     ${item.monogram ? `<p>Monogram: ${escapeHtml(item.monogram)}</p>` : ''}
 377:                     <div class="quantity-control" aria-label="Jumlah ${escapeHtml(item.name)}">
 378:                         <button type="button" data-quantity="minus" aria-label="Kurangi jumlah"><svg><use href="#icon-minus"/></svg></button>
 379:                         <span>${escapeHtml(item.quantity)}</span>
 380:                         <button type="button" data-quantity="plus" aria-label="Tambah jumlah"><svg><use href="#icon-plus"/></svg></button>
 381:                     </div>
 382:                 </div>
 383:                 <button class="cart-remove" type="button" data-remove-cart aria-label="Hapus ${escapeHtml(item.name)}"><svg><use href="#icon-close"/></svg></button>
 384:             `;
 385:             container.appendChild(element);
 386:         });
 387: 
 388:         const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
 389:         const totalElement = select('[data-cart-total]');
 390:         if (totalElement) totalElement.textContent = rupiah(total);
...
 795:             }
 796:             if (initialSearch && searchInput) {
 797:                 searchInput.value = initialSearch;
 798:                 searchProducts(initialSearch);
 799:             }
 800:         } catch {
 801:             // URL sync is progressive enhancement.
 802:         }
 803:     });
 804: 
 805:     document.addEventListener('keydown', (event) => {
 806:         if (event.key === 'Escape') {
 807:             closeAll();
 808:             closeElement(mobileMenu);
 809:             menuButton?.setAttribute('aria-expanded', 'false');
 810:             select('.account-menu')?.classList.remove('open');
 811:         }
 812: 
 813:         if (event.key === 'Tab') {
 814:             const activeLayer = [shortcutsModal, sizeGuideDrawer, conciergeModal, quickViewModal, searchPanel, cartDrawer, profileDrawer, mobileMenu]
 815:                 .find((element) => element?.classList.contains('open'));
 816:             if (!activeLayer) return;
 817:             const focusable = selectAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', activeLayer)
 818:                 .filter((element) => element.offsetParent !== null);
 819:             if (!focusable.length) return;
 820:             const first = focusable[0];
 821:             const last = focusable[focusable.length - 1];
 822:             if (event.shiftKey && document.activeElement === first) {
 823:                 event.preventDefault();
 824:                 last.focus();
 825:             } else if (!event.shiftKey && document.activeElement === last) {
 826:                 event.preventDefault();
 827:                 first.focus();
 828:             }
 829:         }
 830:     });
 831: 
 832:     const header = select('#site-header');
...
```

### `resources/css/app.css`

```text
 932: /* Transparent targets retain the painted control dimensions. */
 933: .icon-button, .filter-pill, .foil, .desktop-nav a, .footer-links a,
 934: .footer-links button, .footer-bottom a, .footer-bottom [data-open-shortcuts], .product-info h3 button,
 935: #btn-ribbon-gold, #btn-ribbon-sapphire {
 936:     position: relative;
 937: }
 938: :is(.icon-button:not([data-open-search]), .wishlist-button, .product-add, .filter-pill, .foil,
 939: .desktop-nav a, .footer-links a, .footer-links button, .footer-bottom a, .footer-bottom [data-open-shortcuts],
 940: .product-info h3 button, #btn-ribbon-gold, #btn-ribbon-sapphire)::before {
 941:     content: '';
 942:     position: absolute;
 943:     left: 50%;
 944:     top: 50%;
 945:     width: max(100%, 44px);
 946:     height: max(100%, 44px);
 947:     transform: translate(-50%, -50%);
 948:     background: transparent;
 949: }
 950: /* Mobile search sits beside the brand: expand vertically only so it never covers the home link. */
 951: .site-header [data-open-search]::before {
 952:     width: 100%;
 953:     content: '';
 954:     position: absolute;
 955:     left: 0;
 956:     top: 50%;
 957:     height: max(100%, 44px);
 958:     transform: translateY(-50%);
 959: }
 960: /* Theme/lang toggles keep 24px boxes with no expanded hit area (WCAG 2.5.8 spacing exception: 24px circles at 26px pitch don't overlap). */
 961: .header-actions { gap: 8px; }
 962: .foil-options { gap: 32px; }
 963: .footer-links { gap: 24px; }
 964: .filter-bar { padding-top: 5px; margin-top: -5px; row-gap: 10px; }
 965: #live-catalog-search-input, #monogram-input, #newsletter-email,
 966: .zafira-lang-select, .zafira-theme-select { min-height: 44px; }
 967: /* Compact announcement controls retain 24px targets; 44px would change the bar layout. */
 968: .announcement-bar .lang-btn, .announcement-bar .theme-btn { min-width: 24px; min-height: 24px; }
...
```
