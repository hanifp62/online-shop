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

    public const ALLOWED_SIZES = [
        '', 'XS', 'S', 'M', 'L', 'XL', 'ALL SIZE',
        'FR 34', 'FR 36', 'FR 38', 'FR 40', 'FR 42', 'FR 44',
    ];

    public static function normalizeSize(mixed $size): string
    {
        if ($size === null || $size === '') {
            return '';
        }
        $clean = preg_replace('/\s+/', ' ', strtoupper(trim((string) $size))) ?? '';

        return in_array($clean, self::ALLOWED_SIZES, true) ? $clean : '__INVALID__';
    }

    public static function normalizeMonogram(mixed $monogram): string
    {
        if ($monogram === null || $monogram === '') {
            return '';
        }
        $clean = strtoupper(trim((string) $monogram));

        return preg_match('/^[A-Z]{1,3}$/', $clean) === 1 ? $clean : '__INVALID__';
    }

    public static function lineKey(int $productId, string $size = '', string $monogram = ''): string|int
    {
        if ($size === '' && $monogram === '') {
            return $productId;
        }

        return "{$productId}|{$size}|{$monogram}";
    }

    /** @return array{0: int, 1: string, 2: string}|null */
    public static function parseLineKey(string|int $key): ?array
    {
        $raw = (string) $key;
        if (ctype_digit($raw) && (int) $raw > 0) {
            return [(int) $raw, '', ''];
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || (int) $parts[0] <= 0) {
            return null;
        }

        $size = self::normalizeSize($parts[1]);
        $monogram = self::normalizeMonogram($parts[2]);
        if ($size === '__INVALID__' || $monogram === '__INVALID__') {
            return null;
        }

        return [(int) $parts[0], $size, $monogram];
    }

    public static function totalQuantityForProduct(array $items, int $productId, ?string $excludeKey = null): int
    {
        $sum = 0;
        foreach ($items as $key => $qty) {
            if ($excludeKey !== null && (string) $key === $excludeKey) {
                continue;
            }
            $parsed = self::parseLineKey($key);
            if ($parsed !== null && $parsed[0] === $productId) {
                $sum += (int) $qty;
            }
        }

        return $sum;
    }

    /** All cart operations, including checkout, share this parent-row lock. */
    public static function locked(Request $request, callable $operation, bool $create = false): mixed
    {
        $legacy = $request->session()->get('cart', []);
        if ($request->session()->get('cart_recovery_snapshot') === $legacy) {
            $legacy = [];
        }
        CheckoutService::validateCart($legacy);
        $cookieToken = $request->cookie(self::COOKIE);
        $token = $cookieToken ?? $request->session()->get('guest_cart_token');
        if (! is_string($token) || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
        }
        $nested = DB::transactionLevel() > 0;
        try {
            $result = TransactionLockRetry::transaction(function () use ($request, $operation, $legacy, &$token, $cookieToken, $create) {
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
                        $request->session()->put('guest_cart_token_hash', hash('sha256', $token));
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
                    $hasTrustedCookie = is_string($cookieToken) && hash_equals($cookieToken, $token);
                    $tokenMatches = (is_string($sessionHash) && hash_equals($sessionHash, hash('sha256', $token)))
                        || ($sessionHash === null && $hasTrustedCookie);
                    if ($tokenMatches) {
                        foreach (self::items($guest->id) as $lineKey => $quantity) {
                            $incoming[$lineKey] = max($incoming[$lineKey] ?? 0, $quantity);
                        }
                        DB::table('carts')->where('id', $guest->id)->update(['consumed_at' => now()]);
                        DB::table('cart_items')->where('cart_id', $guest->id)->delete();
                        $request->session()->forget('guest_cart_token_hash');
                    }
                }
                if ($cart) {
                    DB::table('cart_items')->where('cart_id', $cart->id)->where('quantity', '>', 99)->update(['quantity' => 99]);
                    $current = self::items($cart->id);
                    foreach ($incoming as $lineKey => $quantity) {
                        $parsed = self::parseLineKey($lineKey);
                        if ($parsed !== null && DB::table('products')->where('product_id', $parsed[0])->exists()) {
                            [$prodId, $sz, $mono] = $parsed;
                            $canonicalKey = self::lineKey($prodId, $sz, $mono);
                            self::set($cart->id, $prodId, min(99, max($current[$canonicalKey] ?? 0, (int) $quantity)), $sz, $mono);
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
        $rows = DB::table('cart_items')
            ->where('cart_id', $id)
            ->orderBy('id')
            ->get(['product_id', 'size', 'monogram', 'quantity']);

        $result = [];
        foreach ($rows as $row) {
            $key = self::lineKey((int) $row->product_id, (string) ($row->size ?? ''), (string) ($row->monogram ?? ''));
            $result[$key] = (int) $row->quantity;
        }

        return $result;
    }

    public static function set(int $id, int $product, int $quantity, string $size = '', string $monogram = ''): void
    {
        DB::table('cart_items')->updateOrInsert(
            [
                'cart_id' => $id,
                'product_id' => $product,
                'size' => $size,
                'monogram' => $monogram,
            ],
            [
                'quantity' => min(99, $quantity),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        self::touch($id);
    }

    public static function touch(int $id): void
    {
        DB::table('carts')->where('id', $id)->update(['updated_at' => now()]);
        DB::table('carts')->where('id', $id)->whereNull('user_id')->update(['expires_at' => now()->addDays(30)]);
    }

    public static function remove(int $id, ?int $product = null, ?string $size = null, ?string $monogram = null): void
    {
        DB::table('cart_items')
            ->where('cart_id', $id)
            ->when($product !== null, fn ($query) => $query->where('product_id', $product))
            ->when($size !== null, fn ($query) => $query->where('size', $size))
            ->when($monogram !== null, fn ($query) => $query->where('monogram', $monogram))
            ->delete();
        self::touch($id);
    }

    public static function detach(Request $request): void
    {
        $request->session()->forget('guest_cart_token');
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
