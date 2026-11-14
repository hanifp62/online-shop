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
    /** @return array{0: string, 1: string} */
    private function validatedVariants(Request $request): array
    {
        $rawSize = $request->input('size');
        $rawMonogram = $request->input('monogram');

        $size = CartStore::normalizeSize($rawSize);
        if ($size === '__INVALID__') {
            throw ValidationException::withMessages([
                'size' => __('validation.in', ['attribute' => 'size']),
            ]);
        }

        $monogram = CartStore::normalizeMonogram($rawMonogram);
        if ($monogram === '__INVALID__') {
            throw ValidationException::withMessages([
                'monogram' => __('validation.regex', ['attribute' => 'monogram']),
            ]);
        }

        return [$size, $monogram];
    }

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
            'mode' => ['nullable', 'string', 'in:set,add'],
        ]);
        [$size, $monogram] = $this->validatedVariants($request);
        $productId = (int) $validated['product_id'];
        $requestedQty = (int) $validated['quantity'];
        $mode = $validated['mode'] ?? 'set';

        $product = Product::findOrFail($productId);
        if (! $product->is_active || (int) $product->stock < $requestedQty) {
            return response()->json(['message' => __('customer_messages.cart.stock')], 422);
        }

        try {
            $cart = CartStore::locked($request, function (int $id, array $items) use ($product, $productId, $requestedQty, $size, $monogram, $mode) {
                $targetKey = CartStore::lineKey($productId, $size, $monogram);
                $currentLineQty = (int) ($items[$targetKey] ?? 0);
                $nextLineQty = $mode === 'add' ? $currentLineQty + $requestedQty : $requestedQty;
                $otherVariantsQty = CartStore::totalQuantityForProduct($items, $productId, (string) $targetKey);

                if (! $product->is_active || $nextLineQty > CheckoutService::MAX_QUANTITY || ($otherVariantsQty + $nextLineQty) > (int) $product->stock) {
                    throw ValidationException::withMessages(['cart_stock' => __('customer_messages.cart.stock')]);
                }

                CartStore::set($id, $productId, $nextLineQty, $size, $monogram);

                return CartStore::items($id);
            }, create: true);
        } catch (ValidationException $e) {
            if ($e->errors()['cart_stock'] ?? null) {
                return response()->json(['message' => __('customer_messages.cart.stock')], 422);
            }
            throw $e;
        }

        $priced = CheckoutService::priced($cart);

        return response()->json($priced + CheckoutService::baseQuote($priced));
    }

    public function adjust(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-99,99'],
        ]);
        [$size, $monogram] = $this->validatedVariants($request);
        $delta = (int) $validated['delta'];

        $cart = CartStore::locked($request, function (?int $id, array $items) use ($productId, $delta, $size, $monogram) {
            $product = Product::findOrFail($productId);
            $targetKey = CartStore::lineKey($productId, $size, $monogram);
            $quantity = max(0, (int) ($items[$targetKey] ?? 0) + $delta);
            $otherVariantsQty = CartStore::totalQuantityForProduct($items, $productId, (string) $targetKey);

            if ($delta > 0 && (! $product->is_active || ($otherVariantsQty + $quantity) > (int) $product->stock || $quantity > CheckoutService::MAX_QUANTITY)) {
                throw ValidationException::withMessages([
                    'delta' => __('customer_messages.cart.adjust_stock'),
                ]);
            }
            if ($id !== null) {
                if ($quantity === 0) {
                    CartStore::remove($id, $productId, $size, $monogram);
                } else {
                    CartStore::set($id, $productId, $quantity, $size, $monogram);
                }
            }

            return $id === null ? [] : CartStore::items($id);
        }, create: $delta > 0);

        $priced = CheckoutService::priced($cart);

        return response()->json($priced + CheckoutService::baseQuote($priced));
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $hasSpecificVariant = $request->has('size') || $request->has('monogram');
        [$size, $monogram] = $hasSpecificVariant ? $this->validatedVariants($request) : [null, null];

        [$cart, $removedQuantity] = CartStore::locked($request, function (?int $id, array $items) use ($productId, $hasSpecificVariant, $size, $monogram) {
            if ($id === null) {
                return [[], 0];
            }
            if ($hasSpecificVariant) {
                $targetKey = CartStore::lineKey($productId, (string) $size, (string) $monogram);
                $removed = (int) ($items[$targetKey] ?? 0);
                CartStore::remove($id, $productId, (string) $size, (string) $monogram);

                return [CartStore::items($id), $removed];
            }

            $removed = CartStore::totalQuantityForProduct($items, $productId);
            CartStore::remove($id, $productId);

            return [CartStore::items($id), $removed];
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
