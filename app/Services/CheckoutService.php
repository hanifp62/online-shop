<?php

namespace App\Services;

use App\Mail\OrderStatusChanged;
use App\Mail\RecipientLocale;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public const MAX_QUANTITY = 99;

    private const MAX_LOCK_RETRIES = 3;

    private const LOCK_RETRY_BASE_MICROSECONDS = 25_000;

    /** Kunci sesi lama; dipertahankan untuk jembatan migrasi satu kali. */
    public static function cartKey(Request $request): string
    {
        return 'cart';
    }

    /** Keranjang persisten di database; mendukung kunci product_id maupun lineKey varian. */
    public static function cart(Request $request): array
    {
        return CartStore::locked($request, fn (?int $id, array $items) => $items);
    }

    public static function validateCart(array $cart): void
    {
        foreach ($cart as $lineKey => $quantity) {
            $parsed = CartStore::parseLineKey($lineKey);
            if ($parsed === null || is_bool($quantity)
                || filter_var($quantity, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) === null
                || (int) $quantity < 1 || (int) $quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.invalid_cart')]);
            }
        }
    }

    /** Harga selalu diambil ulang dari database, bukan dari klien atau sesi. */
    public static function priced(array $cart): array
    {
        self::validateCart($cart);

        $parsedLines = [];
        $totalsByProduct = [];
        foreach ($cart as $lineKey => $quantity) {
            [$productId, $size, $monogram] = CartStore::parseLineKey($lineKey);
            $qty = (int) $quantity;
            $parsedLines[] = [
                'line_key' => (string) CartStore::lineKey($productId, $size, $monogram),
                'product_id' => $productId,
                'size' => $size !== '' ? $size : null,
                'monogram' => $monogram !== '' ? $monogram : null,
                'quantity' => $qty,
            ];
            $totalsByProduct[$productId] = ($totalsByProduct[$productId] ?? 0) + $qty;
        }

        $products = Product::whereIn('product_id', array_keys($totalsByProduct))->get()->keyBy('product_id');
        $lines = [];
        foreach ($parsedLines as $entry) {
            $product = $products->get($entry['product_id']);
            if (! $product) {
                continue;
            }
            $unit = self::idr($product->product_price);
            $totalRequestedForProduct = $totalsByProduct[$entry['product_id']];
            $lines[] = [
                'line_key' => $entry['line_key'],
                'product_id' => $entry['product_id'],
                'product_title' => $product->product_title,
                'product_slug' => $product->product_slug,
                'product_image' => $product->product_image,
                'size' => $entry['size'],
                'monogram' => $entry['monogram'],
                'unit_price' => $unit,
                'quantity' => $entry['quantity'],
                'subtotal' => $unit * $entry['quantity'],
                'available' => (bool) $product->is_active && (int) $product->stock >= $totalRequestedForProduct,
            ];
        }

        return ['items' => $lines, 'subtotal' => array_sum(array_column($lines, 'subtotal'))];
    }

    public static function idr(mixed $price): int
    {
        if (! preg_match('/^([0-9]{1,12})(?:\.0+)?$/', (string) $price, $matches)) {
            throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.invalid_price')]);
        }

        return (int) $matches[1];
    }

    /** Rincian biaya tanpa voucher, memakai aturan ongkir dan pajak yang sama dengan createOrder(). */
    public static function baseQuote(array $priced): array
    {
        $shipping = self::shippingFee();
        $tax = self::tax($priced['subtotal']);

        return [
            'shipping_fee' => $shipping,
            'discount' => 0,
            'tax' => $tax,
            'total' => $priced['subtotal'] + $tax + $shipping,
        ];
    }

    /**
     * Pratinjau rincian dengan voucher sebelum pesanan dibuat (tanpa klaim kuota).
     * Aturan kelayakan dan aritmetika sama dengan createOrder() agar tidak ada biaya kejutan.
     */
    public function quote(User $user, array $cart, ?string $voucherCode): array
    {
        $priced = self::priced($cart);
        $voucherCode = $voucherCode ? mb_strtoupper(trim($voucherCode)) : null;
        $discount = $voucherCode ? min($this->eligibleVoucher($user, $voucherCode)->discount_amount, $priced['subtotal']) : 0;
        $shipping = self::shippingFee();
        $tax = self::tax($priced['subtotal'] - $discount);

        return $priced + [
            'voucher_code' => $voucherCode,
            'shipping_fee' => $shipping,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $priced['subtotal'] - $discount + $tax + $shipping,
        ];
    }

    public function checkout(User $user, Address $address, array $cart, string $key, ?string $voucherCode): array
    {
        self::validateCart($cart);
        $voucherCode = $voucherCode ? mb_strtoupper(trim($voucherCode)) : null;

        $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            return [$this->replay($existing, $address, $cart, $voucherCode), false];
        }

        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.empty')]);
        }

        $priced = self::priced($cart);
        $discount = 0;
        if ($voucherCode) {
            $voucher = $this->eligibleVoucher($user, $voucherCode);
            $discount = min($voucher->discount_amount, $priced['subtotal']);
        }
        $shipping = self::shippingFee();
        $tax = self::tax($priced['subtotal'] - $discount);

        $hash = $this->hash($address->id, $voucherCode, $cart, $shipping, $tax);

        return $this->attempt(
            fn () => $this->place($user, $address, $cart, $key, $hash, $voucherCode),
            fn () => Order::where('user_id', $user->id)->where('idempotency_key', $key)->first(),
            fn (Order $order) => $this->replay($order, $address, $cart, $voucherCode)
        );
    }

    public function checkoutGuest(array $contact, array $address, array $cart, string $key, string $scope, string $token): array
    {
        self::validateCart($cart);
        $lookup = fn () => Order::whereNull('user_id')->where('guest_scope_hash', $scope)->where('idempotency_key', $key);
        $replay = function (Order $order) use ($contact, $address, $cart) {
            $items = $cart !== [] ? $cart : $this->reconstructOrderItems($order);
            if (! hash_equals($order->payload_hash, $this->guestHash($contact, $address, $items, $order->shipping_fee, $order->tax))) {
                abort(409, __('customer_messages.checkout.idempotency_conflict'));
            }

            return $order->load('items');
        };
        if ($existing = $lookup()->first()) {
            return [$replay($existing), false];
        }
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.empty')]);
        }

        // Tamu tanpa voucher: pajak atas subtotal penuh.
        $hash = $this->guestHash($contact, $address, $cart, self::shippingFee(), self::tax(self::priced($cart)['subtotal']));

        return $this->attempt(function () use ($lookup, $replay, $contact, $address, $cart, $key, $scope, $token, $hash) {
            if ($existing = $lookup()->lockForUpdate()->first()) {
                return $replay($existing);
            }

            return $this->createOrder(null, $address, $cart, $key, $hash, null, $contact + [
                'guest_scope_hash' => $scope,
                'tracking_token_hash' => hash('sha256', $token),
                'tracking_expires_at' => now()->addDays(30),
            ]);
        }, fn () => $lookup()->first(), $replay);
    }

    private function reconstructOrderItems(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $key = CartStore::lineKey((int) $item->product_id, (string) ($item->size ?? ''), (string) ($item->monogram ?? ''));
            $items[$key] = (int) $item->quantity;
        }

        return $items;
    }

    private function guestHash(array $contact, array $address, array $cart, int $shipping, int $tax): string
    {
        $items = array_map('intval', $cart);
        ksort($items);

        return hash('sha256', json_encode([$contact, $address, null, $items, $shipping, $tax]));
    }

    private function attempt(callable $place, callable $lookup, callable $replay): array
    {
        $nested = DB::transactionLevel() > 0;
        for ($attempt = 0; $attempt <= self::MAX_LOCK_RETRIES; $attempt++) {
            try {
                $order = DB::transaction($place);

                return [$order, $order->wasRecentlyCreated];
            } catch (QueryException|DeadlockException $e) {
                if ($nested && ($e instanceof DeadlockException || $this->isTransientLock($e))) {
                    throw $e;
                }
                $transient = $this->isTransientLock($e);
                if (! $transient && ! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                $existing = $lookup();
                if ($existing) {
                    return [$replay($existing), false];
                }
                if (! $transient) {
                    throw $e;
                }
                if ($attempt === self::MAX_LOCK_RETRIES) {
                    abort(503, __('customer_messages.checkout.busy'));
                }
                usleep(self::LOCK_RETRY_BASE_MICROSECONDS * (2 ** $attempt) + random_int(0, 5_000));
            }
        }

        throw new \LogicException('Checkout retry loop exhausted unexpectedly.');
    }

    private function errorInfo(\Throwable $e): array
    {
        if ($e instanceof QueryException) {
            return (array) ($e->errorInfo ?? []);
        }
        $previous = $e->getPrevious();

        return $previous instanceof QueryException ? (array) ($previous->errorInfo ?? []) : [];
    }

    /** Retry seluruh transaksi: SQLite BUSY/LOCKED atau MariaDB deadlock. Timeout 1205 tidak termasuk. */
    private function isTransientLock(\Throwable $e): bool
    {
        return TransactionLockRetry::retryable($e, DB::getDriverName());
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $info = $this->errorInfo($e);
        $expected = DB::getDriverName() === 'sqlite' ? 19 : 1062;

        return (int) ($info[1] ?? 0) === $expected;
    }

    private function replay(Order $order, Address $address, array $cart, ?string $voucherCode): Order
    {
        // Keranjang dikosongkan setelah commit; retry jaringan membawa keranjang kosong.
        $items = $cart !== [] ? $cart : $this->reconstructOrderItems($order);
        if (! hash_equals($order->payload_hash, $this->hash($address->id, $voucherCode, $items, $order->shipping_fee, $order->tax))) {
            abort(409, __('customer_messages.checkout.idempotency_conflict'));
        }

        return $order->load('items');
    }

    private function hash(int $addressId, ?string $voucherCode, array $cart, int $shipping, int $tax): string
    {
        $items = array_map('intval', $cart);
        ksort($items);

        return hash('sha256', json_encode([$addressId, $voucherCode, $items, $shipping, $tax]));
    }

    private function place(User $user, Address $address, array $cart, string $key, string $hash, ?string $voucherCode): Order
    {
        User::whereKey($user->id)->lockForUpdate()->firstOrFail();

        $existing = Order::where('user_id', $user->id)->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($existing) {
            return $this->replay($existing, $address, $cart, $voucherCode);
        }

        return $this->createOrder($user, $address->snapshot(), $cart, $key, $hash, $voucherCode);
    }

    private function createOrder(?User $user, array $address, array $cart, string $key, string $hash, ?string $voucherCode, array $guest = []): Order
    {
        $lockIds = [];
        foreach (array_keys($cart) as $rawKey) {
            $parsed = CartStore::parseLineKey($rawKey);
            if ($parsed !== null && $parsed[0] > 0) {
                $lockIds[$parsed[0]] = $parsed[0];
            }
        }
        if ($lockIds !== []) {
            ksort($lockIds);
            Product::whereIn('product_id', array_values($lockIds))->orderBy('product_id')->lockForUpdate()->get();
        }

        $priced = self::priced($cart);
        if (count($priced['items']) !== count($cart)) {
            throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.unavailable')]);
        }

        // Urutan kunci baris produk deterministik & teragregasi per product_id agar varian ganda dari produk sama aman dari race condition.
        $groupedStock = [];
        foreach ($priced['items'] as $line) {
            $pid = $line['product_id'];
            if (! isset($groupedStock[$pid])) {
                $groupedStock[$pid] = ['product_id' => $pid, 'product_title' => $line['product_title'], 'quantity' => 0];
            }
            $groupedStock[$pid]['quantity'] += $line['quantity'];
        }
        ksort($groupedStock);

        foreach ($groupedStock as $stockRequirement) {
            $updated = Product::whereKey($stockRequirement['product_id'])
                ->where('is_active', true)
                ->where('stock', '>=', $stockRequirement['quantity'])
                ->decrement('stock', $stockRequirement['quantity']);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['cart' => __('customer_messages.checkout.stock', ['product' => $stockRequirement['product_title']])]);
            }
        }

        $voucher = $voucherCode ? $this->claimVoucher($user, $voucherCode) : null;
        $discount = $voucher ? min($voucher->discount_amount, $priced['subtotal']) : 0;
        $shipping = self::shippingFee();
        $tax = self::tax($priced['subtotal'] - $discount);

        $order = Order::create([
            'user_id' => $user?->id,
            'status' => 'pending',
            'subtotal' => $priced['subtotal'],
            'discount' => $discount,
            'tax' => $tax,
            'shipping_fee' => $shipping,
            'total' => $priced['subtotal'] - $discount + $tax + $shipping,
            'idempotency_key' => $key,
            'payload_hash' => $hash,
            'shipping_address' => $address,
            'voucher_id' => $voucher?->id,
            'locale' => RecipientLocale::resolve(app()->getLocale()),
        ] + $guest);

        foreach ($priced['items'] as $line) {
            $order->items()->create([
                'product_id' => $line['product_id'],
                'product_title' => $line['product_title'],
                'size' => $line['size'],
                'monogram' => $line['monogram'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'subtotal' => $line['subtotal'],
            ]);
        }
        $order->statusHistory()->create(['from_status' => null, 'to_status' => 'pending']);
        foreach ($groupedStock as $stockRequirement) {
            $order->inventoryReservations()->create([
                'product_id' => $stockRequirement['product_id'],
                'quantity' => $stockRequirement['quantity'],
                'expires_at' => now()->addMinutes(60),
            ]);
        }

        return $order->load('items');
    }

    public static function shippingFee(): int
    {
        return self::configInt('checkout.shipping_fee', PHP_INT_MAX);
    }

    public static function taxRateBps(): int
    {
        return self::configInt('checkout.tax_rate_bps', 10_000);
    }

    public static function tax(int $taxable): int
    {
        $bps = self::taxRateBps();
        $taxable = max(0, $taxable);

        return intdiv($taxable, 10_000) * $bps + intdiv($taxable % 10_000 * $bps + 5_000, 10_000);
    }

    private static function configInt(string $key, int $max): int
    {
        $value = filter_var(config($key, 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
        if ($value === false) {
            throw new \UnexpectedValueException("Konfigurasi {$key} tidak valid.");
        }

        return $value;
    }

    private function eligibleVoucher(User $user, string $code): Voucher
    {
        $voucher = Voucher::where('code', $code)->where('is_active', true)->first();
        if (! $voucher) {
            throw ValidationException::withMessages(['voucher_code' => __('customer_messages.checkout.invalid_voucher')]);
        }

        if ($voucher->per_user_limit > 0) {
            $used = Order::where('user_id', $user->id)
                ->where('voucher_id', $voucher->id)
                ->where('status', '!=', 'cancelled')
                ->count();
            if ($used >= $voucher->per_user_limit) {
                throw ValidationException::withMessages(['voucher_code' => __('customer_messages.checkout.voucher_limit')]);
            }
        }

        if ($voucher->used >= $voucher->quota) {
            throw ValidationException::withMessages(['voucher_code' => __('customer_messages.checkout.voucher_exhausted')]);
        }

        return $voucher;
    }

    private function claimVoucher(User $user, string $code): Voucher
    {
        $voucher = $this->eligibleVoucher($user, $code);

        $claimed = Voucher::whereKey($voucher->id)
            ->where('is_active', true)
            ->whereColumn('used', '<', 'quota')
            ->increment('used');
        if ($claimed !== 1) {
            throw ValidationException::withMessages(['voucher_code' => __('customer_messages.checkout.voucher_exhausted')]);
        }

        return $voucher;
    }

    public function transition(Order $order, string $status): Order
    {
        if ($order->status === $status && $status === 'cancelled') {
            return $order;
        }

        if (in_array($status, Order::MANUAL_BLOCKED, true)) {
            throw ValidationException::withMessages(['status' => __('customer_messages.checkout.paid_unavailable')]);
        }

        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages(['status' => __('customer_messages.checkout.transition', ['from' => $order->status, 'to' => $status])]);
        }

        DB::transaction(function () use ($order, $status) {
            $applied = $this->applyTransition($order, $status, Auth::guard('admin')->id());
            if (! $applied) {
                abort(409, __('customer_messages.checkout.changed'));
            }
        });

        return $order->refresh()->load('items');
    }

    public function markPaid(Order $order): bool
    {
        return $this->applyTransition($order, 'paid', null);
    }

    private function notifyCustomer(Order $order, string $status): void
    {
        $email = $order->customer_email ?? $order->user?->email;
        if (! isset(OrderStatusChanged::MESSAGES[$status]) || ! $email) {
            return;
        }
        $claimed = DB::table('order_notifications')->insertOrIgnore([
            'order_id' => $order->id, 'status' => $status, 'channel' => 'mail', 'created_at' => now(),
        ]);
        if ($claimed === 1) {
            $id = (int) DB::table('order_notifications')->where('order_id', $order->id)
                ->where('status', $status)->where('channel', 'mail')->value('id');
            DB::afterCommit(fn () => app(OrderNotificationDispatcher::class)->dispatch($id));
        }
    }

    private function applyTransition(Order $order, string $status, ?int $adminId): bool
    {
        $updated = Order::whereKey($order->id)->where('status', $order->status)->update(['status' => $status]);
        if ($updated !== 1) {
            return false;
        }

        $order->statusHistory()->create([
            'from_status' => $order->status,
            'to_status' => $status,
            'admin_id' => $adminId,
        ]);

        $this->notifyCustomer($order, $status);

        $locale = RecipientLocale::resolve($order->locale ?? $order->user?->locale);

        if ($status === 'shipped') {
            $shipment = $order->shipment()->firstOrCreate([], ['status' => Shipment::STATUS_PENDING_MANIFEST, 'shipped_at' => now()]);
            $shipment->trackingEvents()->create([
                'status' => Shipment::STATUS_PENDING_MANIFEST,
                'description' => __('customer_messages.shipment.dispatched', [], $locale),
                'recorded_at' => now(),
            ]);
        }

        if ($status === 'completed' && $shipment = $order->shipment) {
            $shipment->update(['status' => Shipment::STATUS_DELIVERED, 'delivered_at' => now()]);
            $shipment->trackingEvents()->create([
                'status' => Shipment::STATUS_DELIVERED,
                'description' => __('customer_messages.shipment.delivered', [], $locale),
                'recorded_at' => now(),
            ]);
        }

        if ($status === 'paid' || $status === 'processing' || $status === 'completed') {
            $order->inventoryReservations()->where('status', 'reserved')->update(['status' => 'committed']);
        }

        if ($status === 'cancelled') {
            $order->inventoryReservations()->where('status', 'reserved')->update(['status' => 'released']);
            foreach ($order->items as $item) {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);
            }
            if ($order->voucher_id) {
                Voucher::whereKey($order->voucher_id)->where('used', '>', 0)->decrement('used');
            }
        }

        return true;
    }
}
