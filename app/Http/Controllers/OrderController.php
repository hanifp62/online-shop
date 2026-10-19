<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->latest('id')
            ->paginate(15);

        return response()->json($orders);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with(['items', 'voucher'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['order' => $order]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return app(GuestCheckoutController::class)->store($request, $this->checkout);
        }

        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
        ]);

        $address = Address::where('user_id', $request->user()->id)
            ->where('id', $validated['address_id'])
            ->firstOrFail();

        [$order, $created] = \App\Services\CartStore::locked($request, function (?int $id, array $cart) use ($request, $address, $validated) {
            try {
                $result = $this->checkout->checkout($request->user(), $address, $cart, $validated['idempotency_key'], $validated['voucher_code'] ?? null);
            } catch (\Throwable $exception) {
                // Preserve the legacy recovery payload on checkout failure, never as source of truth.
                $request->session()->put('cart', $cart);
                $request->session()->put('cart_recovery_snapshot', $cart);
                throw $exception;
            }
            if ($result[1] && $id !== null) {
                \App\Services\CartStore::remove($id);
            }

            return $result;
        });

        return response()->json(['order' => $order], $created ? 201 : 200);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        if (! $order->isCancellable()) {
            return response()->json(['message' => 'Pesanan ini tidak dapat dibatalkan.'], 422);
        }

        return response()->json(['order' => $this->checkout->transition($order, 'cancelled')]);
    }
}
