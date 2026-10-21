<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartStore;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $priced = CheckoutService::priced(CheckoutService::cart($request));

        return response()->json($priced + CheckoutService::baseQuote($priced));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,product_id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CheckoutService::MAX_QUANTITY],
            'size' => ['prohibited'],
            'monogram' => ['prohibited'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if (! $product->is_active || (int) $product->stock < $validated['quantity']) {
            return response()->json(['message' => __('customer_messages.cart.stock')], 422);
        }

        $cart = CartStore::locked($request, function (int $id) use ($validated) {
            CartStore::set($id, (int) $validated['product_id'], (int) $validated['quantity']);

            return CartStore::items($id);
        }, create: true);

        $priced = CheckoutService::priced($cart);

        return response()->json($priced + CheckoutService::baseQuote($priced));
    }

    public function adjust(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-99,99'],
            'size' => ['prohibited'],
            'monogram' => ['prohibited'],
        ]);
        $delta = (int) $validated['delta'];
        $cart = CartStore::locked($request, function (?int $id, array $items) use ($productId, $delta) {
            $product = Product::findOrFail($productId);
            $quantity = max(0, ($items[$productId] ?? 0) + $delta);
            if ($delta > 0 && (! $product->is_active || $quantity > (int) $product->stock || $quantity > CheckoutService::MAX_QUANTITY)) {
                throw ValidationException::withMessages([
                    'delta' => __('customer_messages.cart.adjust_stock'),
                ]);
            }
            if ($id !== null) {
                if ($quantity === 0) {
                    CartStore::remove($id, $productId);
                } else {
                    CartStore::set($id, $productId, $quantity);
                }
            }

            return $id === null ? [] : CartStore::items($id);
        }, create: $delta > 0);

        $priced = CheckoutService::priced($cart);

        return response()->json($priced + CheckoutService::baseQuote($priced));
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        [$cart, $removedQuantity] = CartStore::locked($request, function (?int $id, array $items) use ($productId) {
            if ($id === null) {
                return [[], 0];
            }
            CartStore::remove($id, $productId);

            return [CartStore::items($id), $items[$productId] ?? 0];
        });

        $priced = CheckoutService::priced($cart);

        return response()->json($priced + CheckoutService::baseQuote($priced) + ['removed_quantity' => $removedQuantity]);
    }

    public function clear(Request $request): JsonResponse
    {
        CartStore::locked($request, fn (?int $id) => $id === null ? null : CartStore::remove($id));

        return response()->json(['items' => [], 'subtotal' => 0]);
    }
}
