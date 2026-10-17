<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(CheckoutService::priced(CheckoutService::cart($request)));
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
            return response()->json(['message' => 'Stok produk tidak mencukupi atau produk tidak aktif.'], 422);
        }

        $cart = CheckoutService::cart($request);
        $cart[$validated['product_id']] = $validated['quantity'];
        $request->session()->put(CheckoutService::cartKey($request), $cart);

        return response()->json(CheckoutService::priced($cart));
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $cart = CheckoutService::cart($request);
        unset($cart[$productId]);
        $request->session()->put(CheckoutService::cartKey($request), $cart);

        return response()->json(CheckoutService::priced($cart));
    }

    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget(CheckoutService::cartKey($request));

        return response()->json(['items' => [], 'subtotal' => 0]);
    }
}
