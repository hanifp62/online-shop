<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['reviews' => Review::with('user:id,name')
            ->where('product_id', $request->query('product_id'))
            ->latest('id')->paginate(20)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
        $order = Order::where('user_id', $request->user()->id)
            ->where('id', $data['order_id'])
            ->whereHas('items', fn ($query) => $query->where('product_id', $data['product_id']))
            ->firstOrFail();
        if ($order->status !== 'completed') {
            return response()->json(['message' => 'Ulasan hanya untuk pesanan yang sudah selesai.'], 422);
        }

        if (Review::where('user_id', $request->user()->id)->where('order_id', $order->id)
            ->where('product_id', $data['product_id'])->exists()) {
            return response()->json(['message' => 'Ulasan untuk produk ini sudah dikirim.'], 422);
        }

        $review = Review::firstOrCreate(
            ['user_id' => $request->user()->id, 'order_id' => $order->id, 'product_id' => $data['product_id']],
            ['rating' => $data['rating'], 'body' => $data['body'] ?? null]
        );
        if (! $review->wasRecentlyCreated) {
            return response()->json(['message' => 'Ulasan untuk produk ini sudah dikirim.'], 422);
        }

        return response()->json(['review' => $review], 201);
    }
}
