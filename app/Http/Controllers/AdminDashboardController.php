<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInfoBackup;
use App\Http\Middleware\EnsureAdminTotp;
use App\Services\AuthEventRecorder;
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
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'contacted', 'confirmed', 'completed', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:255'],
            'uq' => ['nullable', 'string', 'max:255'],
        ]);

        $conciergeQuery = ConciergeRequest::with('user:id,name')->latest();

        if (! empty($validated['status'])) {
            $conciergeQuery->where('status', $validated['status']);
        }

        if (! empty($validated['q'])) {
            $q = '%'.$validated['q'].'%';
            $conciergeQuery->where(fn ($query) => $query
                ->where('name', 'like', $q)
                ->orWhere('email', 'like', $q)
                ->orWhere('phone', 'like', $q));
        }

        $userQuery = User::latest();

        if (! empty($validated['uq'])) {
            $uq = '%'.$validated['uq'].'%';
            $userQuery->where(fn ($query) => $query
                ->where('name', 'like', $uq)
                ->orWhere('first_name', 'like', $uq)
                ->orWhere('last_name', 'like', $uq)
                ->orWhere('email', 'like', $uq)
                ->orWhere('mobile', 'like', $uq));
        }

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'backups' => UserInfoBackup::count(),
                'concierge' => ConciergeRequest::count(),
                'subscribers' => NewsletterSubscriber::count(),
                'products' => Product::count(),
                'orders' => Order::count(),
            ],
            'filters' => [
                'status' => $validated['status'] ?? '',
                'q' => $validated['q'] ?? '',
                'uq' => $validated['uq'] ?? '',
            ],
            'latestUsers' => (clone $userQuery)->take(10)->get([
                'id', 'name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'created_at',
            ]),
            'latestBackups' => UserInfoBackup::with('user:id,name')->latest()->take(10)->get(),
            'latestConcierge' => $conciergeQuery->take(20)->get(),
            'categories' => Category::withCount('products')->orderBy('cat_id')->get(),
            'latestProducts' => Product::with('category:cat_id,cat_title')->latest('product_id')->take(8)->get(),
            'latestSubscribers' => NewsletterSubscriber::latest()->take(8)->get(),
            'latestOrders' => Order::with('user:id,name,email')->latest('id')->take(15)->get(),
        ]);
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
