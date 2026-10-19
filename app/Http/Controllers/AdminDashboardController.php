<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminTotp;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInfoBackup;
use App\Services\AuthEventRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        // Nomor halaman malformed (mis. kpage=abc) hanya diabaikan: satu tabel rusak
        // tidak boleh mematikan dashboard, dan nilainya tak boleh diulang ke tautan/form.
        foreach (['kpage', 'ppage', 'opage', 'upage', 'bpage', 'cpage', 'spage'] as $pageKey) {
            $pageValue = $request->query($pageKey);
            if ($pageValue !== null && ! (is_scalar($pageValue) && preg_match('/^[1-9][0-9]*$/', (string) $pageValue))) {
                $request->query->remove($pageKey);
                $request->request->remove($pageKey);
            }
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'contacted', 'confirmed', 'completed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:255'],
            'uq' => ['nullable', 'string', 'max:255'],
            'csort' => ['nullable', Rule::in(['name', 'email', 'created_at', 'status', 'preferred_date'])],
            'usort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'cdir' => ['nullable', Rule::in(['asc', 'desc'])],
            'udir' => ['nullable', Rule::in(['asc', 'desc'])],
            'kq' => ['nullable', 'string', 'max:255'],
            'pq' => ['nullable', 'string', 'max:255'],
            'oq' => ['nullable', 'string', 'max:255'],
            'bq' => ['nullable', 'string', 'max:255'],
            'sq' => ['nullable', 'string', 'max:255'],
            'ksort' => ['nullable', Rule::in(['cat_title', 'cat_id', 'products_count'])],
            'psort' => ['nullable', Rule::in(['product_title', 'product_price', 'product_id', 'stock'])],
            'osort' => ['nullable', Rule::in(['id', 'created_at', 'total'])],
            'bsort' => ['nullable', Rule::in(['created_at', 'email', 'first_name', 'user_id'])],
            'ssort' => ['nullable', Rule::in(['email', 'created_at'])],
            'kdir' => ['nullable', Rule::in(['asc', 'desc'])],
            'pdir' => ['nullable', Rule::in(['asc', 'desc'])],
            'odir' => ['nullable', Rule::in(['asc', 'desc'])],
            'bdir' => ['nullable', Rule::in(['asc', 'desc'])],
            'sdir' => ['nullable', Rule::in(['asc', 'desc'])],
            'ostatus' => ['nullable', Rule::in(array_keys(Order::STATUS_FLOW))],
            // Batas wajar: offset dalam mahal dan tak berguna bagi operator; pakai filter/pencarian.
            'kpage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ppage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'opage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'upage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'bpage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'cpage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'spage' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        // Kolom urut hanya dari allowlist di atas; input mentah tidak pernah masuk orderBy.
        $conciergeSort = $validated['csort'] ?? 'created_at';
        $conciergeDir = $validated['cdir'] ?? 'desc';
        $userSort = $validated['usort'] ?? 'created_at';
        $userDir = $validated['udir'] ?? 'desc';

        $conciergeQuery = ConciergeRequest::with('user:id,name')
            ->orderBy($conciergeSort, $conciergeDir)
            ->orderBy('id', 'desc');

        if (! empty($validated['status'])) {
            $conciergeQuery->where('status', $validated['status']);
        }

        if (($validated['q'] ?? '') !== '') {
            $conciergeQuery->where(fn ($query) => $this->containsAny($query, ['name', 'email', 'phone'], $validated['q']));
        }

        $userQuery = User::query();
        if ($userSort === 'name') {
            // Urut sesuai nama yang ditampilkan view: trim(first.' '.last) ?: name.
            $joined = in_array($userQuery->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))"
                : "COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')";
            $userQuery->orderByRaw("COALESCE(NULLIF(TRIM({$joined}), ''), name) ".($userDir === 'asc' ? 'asc' : 'desc'));
        } else {
            $userQuery->orderBy($userSort, $userDir);
        }
        $userQuery->orderBy('id', 'desc');

        if (($validated['uq'] ?? '') !== '') {
            $userQuery->where(fn ($query) => $this->containsAny($query, ['name', 'first_name', 'last_name', 'email', 'mobile'], $validated['uq']));
        }

        $filters = array_replace([
            'ksort' => 'cat_id', 'kdir' => 'asc',
            'psort' => 'product_id', 'pdir' => 'desc',
            'osort' => 'id', 'odir' => 'desc',
            'bsort' => 'created_at', 'bdir' => 'desc',
            'ssort' => 'created_at', 'sdir' => 'desc',
        ], array_filter($validated, fn ($value) => $value !== null));
        $tableState = array_filter($request->only([
            'q', 'status', 'csort', 'cdir', 'cpage', 'uq', 'usort', 'udir', 'upage',
            'kq', 'ksort', 'kdir', 'kpage', 'pq', 'psort', 'pdir', 'ppage',
            'oq', 'osort', 'odir', 'opage', 'ostatus', 'bq', 'bsort', 'bdir', 'bpage',
            'sq', 'ssort', 'sdir', 'spage',
        ]), fn ($value) => is_scalar($value) && $value !== '');
        $categoryQuery = Category::withCount('products')
            ->when(($filters['kq'] ?? '') !== '', fn ($query) => $this->containsAny($query, ['cat_title'], $filters['kq']));
        $productQuery = Product::with('category:cat_id,cat_title')
            ->when(($filters['pq'] ?? '') !== '', fn ($query) => $this->containsAny($query, ['product_title'], $filters['pq']));
        $orderQuery = Order::with('user:id,name,email')
            ->when(($filters['ostatus'] ?? '') !== '', fn ($query) => $query->where('status', $filters['ostatus']));
        if (($filters['oq'] ?? '') !== '') {
            $orderQuery->where(function ($query) use ($filters) {
                $term = $filters['oq'];
                $this->containsAny($query, ['customer_name', 'customer_email'], $term);
                if (ctype_digit($term) && strlen($term) <= 18) {
                    $query->orWhere('orders.id', (int) $term);
                }
                $query->orWhereHas('user', fn ($user) => $this->containsAny($user, ['name', 'email'], $term));
            });
        }
        $backupQuery = UserInfoBackup::with('user:id,name');
        if (($filters['bq'] ?? '') !== '') {
            $backupQuery->where(function ($query) use ($filters) {
                $this->containsAny($query, ['first_name', 'last_name', 'email'], $filters['bq']);
                $query->orWhereHas('user', fn ($user) => $this->containsAny($user, ['name'], $filters['bq']));
            });
        }
        $subscriberQuery = NewsletterSubscriber::query()
            ->when(($filters['sq'] ?? '') !== '', fn ($query) => $this->containsAny($query, ['email'], $filters['sq']));
        $categoryOptions = Category::orderBy('cat_id')->get(['cat_id', 'cat_title']);

        return view('admin.dashboard', [
            'tableState' => $tableState,
            'categoryOptions' => $categoryOptions,
            'stats' => [
                'users' => User::count(),
                'backups' => UserInfoBackup::count(),
                'concierge' => ConciergeRequest::count(),
                'subscribers' => NewsletterSubscriber::count(),
                'products' => Product::count(),
                'orders' => Order::count(),
                'categories' => $categoryOptions->count(),
            ],
            'filters' => array_replace($filters, [
                'status' => $validated['status'] ?? '',
                'q' => $validated['q'] ?? '',
                'uq' => $validated['uq'] ?? '',
                'csort' => $conciergeSort,
                'cdir' => $conciergeDir,
                'usort' => $userSort,
                'udir' => $userDir,
            ]),
            'latestUsers' => $this->paginateClamped($userQuery, $validated, 'upage', 10, [
                'id', 'name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'created_at',
            ])->withQueryString()->fragment('panel-user'),
            'latestBackups' => $this->paginateClamped($backupQuery->orderBy($filters['bsort'], $filters['bdir'])->orderBy('id', 'desc'), $validated, 'bpage', 10)->withQueryString()->fragment('panel-audit'),
            'latestConcierge' => $this->paginateClamped($conciergeQuery, $validated, 'cpage', 20)->withQueryString()->fragment('panel-concierge'),
            'categories' => $this->paginateClamped($categoryQuery->orderBy($filters['ksort'], $filters['kdir'])->orderBy('cat_id', 'desc'), $validated, 'kpage', 10)->withQueryString()->fragment('panel-kategori'),
            'latestProducts' => $this->paginateClamped($productQuery->orderBy($filters['psort'], $filters['pdir'])->orderBy('product_id', 'desc'), $validated, 'ppage', 10)->withQueryString()->fragment('panel-produk'),
            'latestSubscribers' => $this->paginateClamped($subscriberQuery->orderBy($filters['ssort'], $filters['sdir'])->orderBy('id', 'desc'), $validated, 'spage', 10)->withQueryString()->fragment('panel-subscriber'),
            'latestOrders' => $this->paginateClamped($orderQuery->orderBy($filters['osort'], $filters['odir'])->orderBy('id', 'desc'), $validated, 'opage', 15)->withQueryString()->fragment('panel-pesanan'),
        ]);
    }

    // paginate() tidak pernah menjepit nomor halaman, jadi halaman di luar jangkauan
    // merender empty state palsu tanpa nav pagination — operator terkunci tanpa jalan kembali.
    // Total dihitung sekali lalu dioper ke paginate() supaya tidak ada query count kedua.
    private function paginateClamped($query, array $validated, string $pageName, int $perPage, array $columns = ['*']): LengthAwarePaginator
    {
        $total = $query->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, (int) ($validated[$pageName] ?? 1));

        return $query->paginate($perPage, $columns, $pageName, min($page, $lastPage), $total);
    }

    // Uses `LIKE ? ESCAPE ?` with both values bound, so no backslash literal sits in the SQL text.
    // Verified on the SQLite test suite only; MySQL/PostgreSQL runtime not exercised.
    private function containsAny($query, array $columns, string $term): void
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        // Grouped so the ORs never escape into a caller's constraints (e.g. whereHas join condition).
        $query->where(function ($group) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $group->orWhereRaw("{$column} like ? escape ?", [$pattern, '\\']);
            }
        });
    }

    public function updateConcierge(Request $request, ConciergeRequest $concierge): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ConciergeRequest::TRANSITIONS[$concierge->status] ?? [])],
        ]);

        $concierge->update($validated);

        $owner = $concierge->user_id ? " milik user #{$concierge->user_id}" : " ({$concierge->email})";

        return back()->with('success', "Status permintaan #{$concierge->id}{$owner} menjadi {$validated['status']} — terlihat di riwayat /akun.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $admin = $request->user('admin');
        $usesTotp = $admin->requiresTotp();

        DB::transaction(function () use ($admin, $validated, $usesTotp): void {
            $admin->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ]);
            if ($usesTotp) {
                $admin->totp_last_used_at = null;
                $admin->totpRecoveryCodes()->delete();
            }
            $admin->save();
        });

        if ($usesTotp) {
            // Admin guard only; the shared web guard session is left intact.
            Auth::guard('admin')->logout();
            $request->session()->forget(EnsureAdminTotp::SESSION_KEY);
            $request->session()->regenerate(true);
            app(AuthEventRecorder::class)->recordSessionRotated('admin', $admin->id, $request->ip(), 'password_update');

            return redirect()->route('admin.login')
                ->with('success', 'Password diganti. Masuk lagi; kode cadangan lama sudah dicabut.');
        }

        return back()->with('success', 'Password admin berhasil diganti.');
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();

        return back()->with('success', "Subscriber {$email} berhasil dihapus.");
    }
}
