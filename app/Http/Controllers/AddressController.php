<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'addresses' => Address::where('user_id', $request->user()->id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAddress($request);
        $this->unsetDefaultIfRequested($request->user()->id, $validated['is_default'] ?? false);
        $address = Address::create($validated + ['user_id' => $request->user()->id]);

        return response()->json(['address' => $address], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();
        $validated = $this->validateAddress($request);
        $this->unsetDefaultIfRequested($request->user()->id, $validated['is_default'] ?? false, $address->id);
        $address->update($validated);

        return response()->json(['address' => $address]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Address::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function unsetDefaultIfRequested(int $userId, bool $isDefault, ?int $ignoreId = null): void
    {
        if (! $isDefault) {
            return;
        }

        Address::where('user_id', $userId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->update(['is_default' => false]);
    }
}
