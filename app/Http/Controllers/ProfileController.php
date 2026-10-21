<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'history' => ConciergeRequest::where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get(),
        ]);
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

    /** Hak akses & portabilitas: salinan data pribadi milik akun ini saja, tanpa hash/token. */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $pick = fn ($rows, array $keys) => $rows->map(fn ($row) => $row->only($keys))->values()->all();

        $data = [
            'generated_at' => now()->toISOString(),
            'profile' => $user->only(['name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'email_verified_at', 'cart_reminder_opt_in', 'locale', 'created_at']),
            'newsletter_subscribed' => NewsletterSubscriber::where('email', $user->email)->exists(),
            'social_accounts' => $pick(SocialAccount::where('user_id', $user->id)->get(), ['provider', 'created_at']),
            'addresses' => $pick(Address::where('user_id', $user->id)->get(), ['label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code', 'is_default']),
            'orders' => Order::where('user_id', $user->id)->with('items')->get()->map(fn (Order $o) => [
                'id' => $o->id, 'status' => $o->status, 'total' => $o->total, 'shipping_address' => $o->shipping_address,
                'customer_name' => $o->customer_name, 'customer_email' => $o->customer_email, 'customer_phone' => $o->customer_phone,
                'created_at' => $o->created_at,
                'items' => $o->items->map(fn ($i) => $i->only(['product_title', 'unit_price', 'quantity', 'subtotal']))->all(),
            ])->all(),
            'returns' => $pick(OrderReturn::where('user_id', $user->id)->get(), ['return_number', 'order_id', 'status', 'reason', 'notes', 'requested_refund_amount', 'created_at']),
            'refunds' => $pick(Refund::where('user_id', $user->id)->get(), ['return_id', 'status', 'amount', 'created_at']),
            'reviews' => $pick(Review::where('user_id', $user->id)->get(), ['product_id', 'order_id', 'rating', 'body', 'created_at']),
            'favorites' => Favorite::where('user_id', $user->id)->pluck('product_id')->all(),
            'concierge_requests' => $pick(ConciergeRequest::where('user_id', $user->id)->get(), ['service', 'name', 'email', 'phone', 'preferred_date', 'preferred_time', 'notes', 'status', 'created_at']),
        ];

        return response()->json($data, 200, [
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

        $blocked = Order::where('user_id', $user->id)->whereIn('status', ['pending', 'paid', 'processing', 'shipped'])->exists()
            || OrderReturn::where('user_id', $user->id)->whereIn('status', [OrderReturn::STATUS_PENDING, OrderReturn::STATUS_APPROVED])->exists()
            || Refund::where('user_id', $user->id)->whereIn('status', [Refund::STATUS_DIAJUKAN, Refund::STATUS_DISETUJUI, Refund::STATUS_DANA_DIKIRIM])->exists();
        if ($blocked) {
            return back()->withErrors(['delete' => trans('customer_messages.profile.delete_blocked')]);
        }

        $id = $user->id;
        // Logout dulu: logout() menyimpan remember_token baru dan akan memasukkan ulang baris yang sudah dihapus.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($user) {
            NewsletterSubscriber::where('email', $user->email)->delete();
            ConciergeRequest::where('user_id', $user->id)->update(['name' => 'Akun dihapus', 'email' => 'dihapus@invalid', 'phone' => '-', 'notes' => null]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            // Sisanya diatur FK: alamat, favorit, ulasan, tautan sosial, cadangan, keranjang → hapus; pesanan/retur/refund/concierge → user_id null.
            $user->delete();
        });

        Log::info('account.deleted', ['user_id' => $id]);

        return redirect()->route('home')->with('success', trans('customer_messages.profile.deleted'));
    }

    /** Tautan bertanda tangan di email: GET hanya konfirmasi agar pemindai tautan tidak memberhentikan langganan. */
    public function confirmCartReminderUnsubscribe(Request $request, User $user): View
    {
        return view('account.cart-reminder-unsubscribed', ['done' => false, 'action' => $request->fullUrl()]);
    }

    public function unsubscribeCartReminder(User $user): View
    {
        $user->forceFill(['cart_reminder_opt_in' => false])->save();

        return view('account.cart-reminder-unsubscribed', ['done' => true, 'action' => null]);
    }
}
