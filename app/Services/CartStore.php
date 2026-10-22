<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class CartStore
{
    public const COOKIE = 'guest_cart';

    /** All cart operations, including checkout, share this parent-row lock. */
    public static function locked(Request $request, callable $operation, bool $create = false): mixed
    {
        $legacy = $request->session()->get('cart', []);
        if ($request->session()->get('cart_recovery_snapshot') === $legacy) {
            $legacy = [];
        }
        CheckoutService::validateCart($legacy);
        $token = $request->cookie(self::COOKIE) ?? $request->session()->get('guest_cart_token');
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
        }
        $nested = DB::transactionLevel() > 0;
        try {
            $result = TransactionLockRetry::transaction(function () use ($request, $operation, $legacy, &$token, $create) {
                $userId = $request->user()?->id;
                // Match CheckoutService's user-before-cart lock order across devices.
                if ($userId) {
                    User::whereKey($userId)->lockForUpdate()->firstOrFail();
                    DB::table('carts')->insertOrIgnore(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
                    $cart = DB::table('carts')->where('user_id', $userId)->lockForUpdate()->first();
                }
                $guest = DB::table('carts')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                $validGuest = $guest && ! $guest->consumed_at && $guest->expires_at !== null
                    && now()->lessThan($guest->expires_at);
                if (! $userId) {
                    if ($validGuest) {
                        $cart = $guest;
                    } else {
                        $cart = null;
                    }
                }
                if (! $userId && ! $cart && ($create || $legacy !== [])) {
                    $token = bin2hex(random_bytes(32));
                    $id = DB::table('carts')->insertGetId(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
                    $cart = DB::table('carts')->where('id', $id)->lockForUpdate()->first();
                    $request->session()->put('guest_cart_token_hash', hash('sha256', $token));
                }
                $incoming = $legacy;
                if ($userId && $validGuest && $request->attributes->get('cart_allow_merge', false)) {
                    $sessionHash = $request->session()->get('guest_cart_token_hash');
                    $tokenMatches = is_string($sessionHash) && hash_equals($sessionHash, hash('sha256', $token));
                    if ($tokenMatches) {
                        foreach (self::items($guest->id) as $product => $quantity) {
                            $incoming[$product] = max($incoming[$product] ?? 0, $quantity);
                        }
                        DB::table('carts')->where('id', $guest->id)->update(['consumed_at' => now()]);
                        DB::table('cart_items')->where('cart_id', $guest->id)->delete();
                    }
                }
                if ($cart) {
                    DB::table('cart_items')->where('cart_id', $cart->id)->where('quantity', '>', 99)->update(['quantity' => 99]);
                    $current = self::items($cart->id);
                    foreach ($incoming as $product => $quantity) {
                        if (DB::table('products')->where('product_id', $product)->exists()) {
                            self::set($cart->id, (int) $product, min(99, max($current[$product] ?? 0, (int) $quantity)));
                        }
                    }
                }

                $cartId = $cart?->id;
                $items = $cartId ? self::items($cartId) : [];

                return $operation($cartId, $items);
            }, 3);
        } catch (QueryException|DeadlockException $exception) {
            if ($nested) {
                throw $exception;
            }
            $transient = TransactionLockRetry::retryable($exception, DB::getDriverName());
            if (! $transient) {
                throw $exception;
            }
            abort(503, __('customer_messages.checkout.busy'));
        }
        $request->session()->forget('cart');
        if (! $request->user()) {
            $token = $request->attributes->get('cart_token', $token);
            $request->session()->put('guest_cart_token', $token);
            // EncryptCookies encrypts this HttpOnly bearer cookie in the web stack.
            $secure = config('session.secure');
            Cookie::queue(cookie(self::COOKIE, $token, 60 * 24 * 30, '/', null, (bool) $secure, true, false, 'lax'));
        } else {
            self::detach($request);
        }

        return $result;
    }

    public static function items(int $id): array
    {
        return DB::table('cart_items')->where('cart_id', $id)->orderBy('id')->pluck('quantity', 'product_id')->map(fn ($quantity) => (int) $quantity)->all();
    }

    public static function set(int $id, int $product, int $quantity): void
    {
        DB::table('cart_items')->updateOrInsert(['cart_id' => $id, 'product_id' => $product], ['quantity' => min(99, $quantity), 'created_at' => now(), 'updated_at' => now()]);
        self::touch($id);
    }

    public static function touch(int $id): void
    {
        DB::table('carts')->where('id', $id)->update(['updated_at' => now()]);
        DB::table('carts')->where('id', $id)->whereNull('user_id')->update(['expires_at' => now()->addDays(30)]);
    }

    public static function remove(int $id, ?int $product = null): void
    {
        DB::table('cart_items')->where('cart_id', $id)->when($product !== null, fn ($query) => $query->where('product_id', $product))->delete();
        self::touch($id);
    }

    public static function detach(Request $request): void
    {
        $request->session()->forget('guest_cart_token');
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
