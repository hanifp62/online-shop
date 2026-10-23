<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateGuardSession;
use App\Models\Address;
use App\Models\ConciergeRequest;
use App\Models\Favorite;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserInfoBackup;
use App\Support\AccountPageProps;
use App\Support\CartReminderUnsubscribePageProps;
use App\Support\FrontendPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class ProfileController extends Controller
{
    public function show(Request $request): View|InertiaResponse
    {
        $user = $request->user();
        $history = ConciergeRequest::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        return FrontendPage::render('Account/Show', 'account.show', [
            'user' => $user,
            'history' => $history,
        ], AccountPageProps::show($request, $user, $history));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'address1' => ['nullable', 'string', 'max:300'],
            'address2' => ['nullable', 'string', 'max:255'],
            'cart_reminder_opt_in' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(config('app.supported_locales'))],
        ]);

        $user->fill($validated)->save();

        if (array_key_exists('locale', $validated) && $validated['locale']) {
            $request->session()->put('locale', $validated['locale']);
        }

        return back()->with('success', trans('customer_messages.profile.updated'));
    }

    /** Pilihan bahasa: selalu ke sesi; untuk akun login juga ke profil agar ikut lintas perangkat. */
    public function updateLocale(Request $request): Response|RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', 'string', Rule::in(config('app.supported_locales'))]])['locale'];
        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return $request->expectsJson() ? response()->noContent() : back();
    }

    /** Hak akses & portabilitas: owner-only, tanpa hash/token; best-effort, bukan snapshot atomik. */
    public function export(Request $request): StreamedJsonResponse
    {
        $user = $request->user();
        $pageSize = 1000;
        // Symfony streams iterators, but encodes a LazyCollection via JsonSerializable eagerly.
        $pick = static fn ($query, array $keys): \Traversable => $query->lazyById($pageSize)
            ->map(static fn ($row) => $row->only($keys))->values()->getIterator();

        $data = [
            'generated_at' => now()->toISOString(),
            'profile' => $user->only(['name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'email_verified_at', 'cart_reminder_opt_in', 'locale', 'created_at']),
            'newsletter_subscribed' => $user->hasVerifiedEmail() && NewsletterSubscriber::where('email', $user->email)->exists(),
            'profile_snapshots' => $pick(UserInfoBackup::where('user_id', $user->id),
                ['first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'created_at']),
            'social_accounts' => $pick(SocialAccount::where('user_id', $user->id), ['provider', 'created_at']),
            'addresses' => $pick(Address::where('user_id', $user->id), ['label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code', 'is_default']),
            // Per-order item queries trade query count for bounded memory even for a very large order.
            'orders' => Order::where('user_id', $user->id)->lazyById($pageSize)->map(static fn (Order $o) => [
                'id' => $o->id, 'status' => $o->status, 'total' => $o->total, 'shipping_address' => $o->shipping_address,
                'customer_name' => $o->customer_name, 'customer_email' => $o->customer_email, 'customer_phone' => $o->customer_phone,
                'created_at' => $o->created_at,
                'items' => $pick($o->items(), ['product_title', 'unit_price', 'quantity', 'subtotal']),
            ])->values()->getIterator(),
            'returns' => $pick(OrderReturn::where('user_id', $user->id), ['return_number', 'order_id', 'status', 'reason', 'notes', 'requested_refund_amount', 'created_at']),
            'refunds' => $pick(Refund::where('user_id', $user->id), ['return_id', 'status', 'amount', 'created_at']),
            'reviews' => $pick(Review::where('user_id', $user->id), ['product_id', 'order_id', 'rating', 'body', 'created_at']),
            'favorites' => Favorite::where('user_id', $user->id)->lazyById($pageSize)
                ->map(static fn (Favorite $favorite) => $favorite->product_id)->values()->getIterator(),
            'concierge_requests' => $pick(ConciergeRequest::where('user_id', $user->id), ['service', 'name', 'email', 'phone', 'preferred_date', 'preferred_time', 'notes', 'status', 'created_at']),
        ];

        return response()->streamJson($data, 200, [
            'Content-Disposition' => 'attachment; filename="zafira-data-pribadi.json"',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Hak hapus: ditolak selama transaksi berjalan. Data pesanan/retur/refund disimpan
     * tanpa tautan akun (user_id null) untuk kewajiban pembukuan; sisanya dihapus.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate($user->has_password
            ? ['password' => ['required', 'current_password']]
            : ['confirmation' => ['required', 'in:HAPUS AKUN SAYA']]);

        $id = $user->id;
        $deleted = DB::transaction(function () use ($id): bool {
            // Shared identity lock contract: checkout and return creation lock users.id first too.
            $locked = User::whereKey($id)->lockForUpdate()->firstOrFail();
            $blocked = Order::where('user_id', $id)->whereIn('status', ['pending', 'paid', 'processing', 'shipped'])->exists()
                || OrderReturn::where('user_id', $id)->whereIn('status', [OrderReturn::STATUS_PENDING, OrderReturn::STATUS_APPROVED])->exists()
                || Refund::where('user_id', $id)->whereIn('status', [Refund::STATUS_DIAJUKAN, Refund::STATUS_DISETUJUI, Refund::STATUS_DANA_DIKIRIM])->exists();
            if ($blocked) {
                return false;
            }
            if ($locked->hasVerifiedEmail()) {
                NewsletterSubscriber::where('email', $locked->email)->delete();
            }
            ConciergeRequest::where('user_id', $id)->update(['name' => 'Akun dihapus', 'email' => 'dihapus@invalid', 'phone' => '-', 'notes' => null]);

            // Financial rows survive via SET NULL; profile PII follows the existing FK contract.
            $locked->delete();

            return true;
        });
        if (! $deleted) {
            return back()->withErrors(['delete' => trans('customer_messages.profile.delete_blocked')]);
        }

        // No remember-token write on the deleted model; preserve the other guard in a shared session.
        Auth::guard('web')->logoutCurrentDevice();
        AuthenticateGuardSession::clear($request, 'web');
        $request->session()->forget('cart');
        if (! Auth::guard('admin')->check()) {
            $request->session()->invalidate();
        } else {
            AuthenticateGuardSession::rotate($request, 'web');
        }
        $request->session()->regenerateToken();

        Log::info('account.deleted', ['user_id' => $id]);

        return redirect()->route('home')->with('success', trans('customer_messages.profile.deleted'));
    }

    /** Tautan bertanda tangan di email: GET hanya konfirmasi agar pemindai tautan tidak memberhentikan langganan. */
    public function confirmCartReminderUnsubscribe(Request $request, User $user): View|InertiaResponse
    {
        $action = $request->fullUrl();

        return FrontendPage::render('Account/CartReminderUnsubscribe', 'account.cart-reminder-unsubscribed',
            ['done' => false, 'action' => $action], CartReminderUnsubscribePageProps::from($action));
    }

    public function unsubscribeCartReminder(User $user): View|InertiaResponse
    {
        $user->forceFill(['cart_reminder_opt_in' => false])->save();

        return FrontendPage::render('Account/CartReminderUnsubscribe', 'account.cart-reminder-unsubscribed',
            ['done' => true, 'action' => null], CartReminderUnsubscribePageProps::from(null, true));
    }
}
