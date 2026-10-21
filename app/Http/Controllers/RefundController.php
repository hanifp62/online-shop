<?php

namespace App\Http\Controllers;

use App\Models\OrderReturn;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::where('user_id', $request->user()->id)->latest('id')->get()
            ->map(fn (Refund $refund) => RefundService::summary($refund));

        return response()->json(['refunds' => $refunds]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $return = OrderReturn::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();

        $refund = $this->refunds->request($return, $validated['reason']);

        return response()->json(['refund' => RefundService::summary($refund)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Refund::STATUS_FLOW))],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'provider_refund_id' => ['nullable', 'string', 'max:64'],
        ]);

        $refund = $this->refunds->transition(Refund::findOrFail($id), $validated['status'], $validated);

        return response()->json(['refund' => RefundService::summary($refund)]);
    }
}
