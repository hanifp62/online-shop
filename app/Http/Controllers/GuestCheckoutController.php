<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartStore;
use App\Services\CheckoutService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class GuestCheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkout): JsonResponse
    {
        $rules = [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{5,28}$/'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 -]{5,28}$/'],
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
        ];
        foreach (['voucher_code', 'address_id', 'user_id', 'price', 'total', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        $contact = [];
        foreach (['customer_name', 'customer_email', 'customer_phone'] as $field) {
            $contact[$field] = trim($data[$field]);
        }
        $contact['customer_email'] = mb_strtolower($contact['customer_email']);
        $contact['customer_phone'] = str_replace([' ', '-'], '', $contact['customer_phone']);
        $address = [];
        foreach (['label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code'] as $field) {
            $address[$field] = isset($data[$field]) ? trim($data[$field]) : null;
        }
        $address['phone'] = str_replace([' ', '-'], '', $address['phone']);
        $session = $request->session();
        if (! $session->has('guest_checkout_scope')) {
            $session->put('guest_checkout_scope', bin2hex(random_bytes(32)));
        }
        $scope = hash('sha256', $session->get('guest_checkout_scope'));
        $cacheKey = 'guest_checkout_tokens.'.hash('sha256', $data['idempotency_key']);
        $cached = $session->get($cacheKey);
        if (is_array($cached) && isset($cached['token'])) {
            try {
                $cached['token'] = Crypt::decryptString($cached['token']);
            } catch (DecryptException) {
                $cached = null;
            }
        }
        $existing = Order::where('guest_scope_hash', $scope)->where('idempotency_key', $data['idempotency_key'])->exists();
        if (! $existing && (! $cached || $cached['expires'] <= now()->timestamp)) {
            $cached = ['token' => bin2hex(random_bytes(32)), 'expires' => now()->addMinutes(30)->timestamp];
            $session->put($cacheKey, ['token' => Crypt::encryptString($cached['token']), 'expires' => $cached['expires']]);
            // Persist before commit so a lost response does not lose the only recoverable token.
            $session->save();
        }
        [$order, $created] = CartStore::locked($request, function (?int $id, array $cart) use ($request, $checkout, $contact, $address, $data, $scope, $cached) {
            try {
                $result = $checkout->checkoutGuest($contact, $address, $cart, $data['idempotency_key'], $scope, $cached['token'] ?? '');
            } catch (\Throwable $exception) {
                // Preserve the legacy recovery payload on checkout failure, never as source of truth.
                $request->session()->put('cart', $cart);
                $request->session()->put('cart_recovery_snapshot', $cart);
                throw $exception;
            }
            if ($result[1] && $id !== null) {
                CartStore::remove($id);
            }

            return $result;
        });
        $token = $cached && $cached['expires'] > now()->timestamp
            && $order->tracking_expires_at->isFuture()
            && hash_equals($order->tracking_token_hash, hash('sha256', $cached['token'])) ? $cached['token'] : null;
        if ($token === null) {
            $session->forget($cacheKey);
        }

        $response = ['order' => self::projection($order), 'tracking_token' => $token];
        if ($token === null) {
            $response['tracking_recovery'] = 'unavailable';
            $response['message'] = __('customer_messages.guest.tracking_unavailable', ['id' => $order->id]);
        }

        return response()->json($response, $created ? 201 : 200);
    }

    public function track(Request $request): JsonResponse
    {
        $token = $request->isJson() ? $request->json('token') : $request->request->get('token');
        $order = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)
            ? Order::whereNull('user_id')->where('tracking_token_hash', hash('sha256', $token))->where('tracking_expires_at', '>', now())->with('items')->first()
            : null;

        return $order
            ? response()->json(['order' => self::projection($order)])
            : response()->json(['message' => __('customer_messages.guest.not_found')], 404);
    }

    private static function projection(Order $order): array
    {
        return $order->only(['id', 'status', 'subtotal', 'discount', 'shipping_fee', 'total']) + [
            'items' => $order->items->map(fn ($item) => $item->only(['product_title', 'quantity', 'unit_price', 'subtotal']))->all(),
            'shipment' => $order->shipmentSummary(),
        ];
    }
}
