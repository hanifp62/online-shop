<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'favorites' => Favorite::with('product')->where('user_id', $request->user()->id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,product_id']]);
        $favorite = Favorite::firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $data['product_id']]);

        return response()->json(['favorite' => $favorite]);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)->where('product_id', $productId)->delete();

        return response()->json(['ok' => true]);
    }
}
