<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $returns = OrderReturn::where('user_id', $request->user()->id)
            ->with('items')
            ->latest('id')
            ->get()
            ->map(fn (OrderReturn $return) => self::summary($return));

        return response()->json(['returns' => $returns]);
    }

    public function eligibility(Request $request, int $id): JsonResponse
    {
        return response()->json(self::eligibilityFor($this->ownedOrder($request, $id)));
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(OrderReturn::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.condition_note' => ['nullable', 'string', 'max:255'],
        ]);

        $return = DB::transaction(function () use ($request, $id, $validated) {
            // Lock the order row so two concurrent submissions cannot both claim the remaining quantity.
            $order = Order::where('user_id', $request->user()->id)->where('id', $id)->lockForUpdate()->firstOrFail();
            $eligibility = self::eligibilityFor($order);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['order' => $eligibility['reason']]);
            }

            $returnable = collect($eligibility['items'])->keyBy('order_item_id');
            $lines = [];
            foreach ($validated['items'] as $index => $line) {
                $item = $returnable->get($line['order_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(["items.{$index}.order_item_id" => __('customer_messages.returns.wrong_item')]);
                }
                if ($line['quantity'] > $item['returnable_quantity']) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => __('customer_messages.returns.quantity', ['product' => $item['product_title'], 'quantity' => $item['returnable_quantity']])]);
                }
                $lines[] = [
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['unit_price'] * $line['quantity'],
                    'condition_note' => $line['condition_note'] ?? null,
                ];
            }

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'return_number' => OrderReturn::generateReturnNumber($order->id),
                'status' => OrderReturn::STATUS_PENDING,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'requested_refund_amount' => array_sum(array_column($lines, 'subtotal')),
            ]);
            $return->items()->createMany($lines);

            return $return->load('items');
        });

        return response()->json(['return' => self::summary($return)], 201);
    }

    private function ownedOrder(Request $request, int $id): Order
    {
        return Order::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();
    }

    /** Syarat kelayakan: pesanan selesai dan lunas, masih dalam jendela retur, dan masih ada kuantitas yang belum diajukan. */
    public static function eligibilityFor(Order $order): array
    {
        $base = ['eligible' => false, 'window_days' => OrderReturn::WINDOW_DAYS, 'deadline' => null, 'reasons' => OrderReturn::reasons(), 'items' => []];

        if ($order->status !== 'completed') {
            return $base + ['reason' => __('customer_messages.returns.incomplete')];
        }

        // Pesanan selesai tanpa ledger lunas adalah anomali; jangan buka klaim refund di atasnya.
        if (! $order->hasSettledPayment()) {
            return $base + ['reason' => __('customer_messages.returns.unpaid')];
        }

        $completedAt = $order->statusHistory()->where('to_status', 'completed')->latest('id')->value('created_at');
        $deadline = Carbon::parse($completedAt ?? $order->updated_at)->addDays(OrderReturn::WINDOW_DAYS);
        $base['deadline'] = $deadline->toISOString();
        if ($deadline->isPast()) {
            return $base + ['reason' => __('customer_messages.returns.expired', ['days' => OrderReturn::WINDOW_DAYS])];
        }

        // Ajuan yang ditolak membebaskan kembali kuantitasnya.
        $claimed = ReturnItem::whereHas('orderReturn', fn ($q) => $q->where('order_id', $order->id)->where('status', '!=', OrderReturn::STATUS_REJECTED))
            ->selectRaw('order_item_id, SUM(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id');

        $items = $order->items()->get()->map(fn ($item) => [
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_title' => $item->product_title,
            'unit_price' => (int) $item->unit_price,
            'ordered_quantity' => (int) $item->quantity,
            'returnable_quantity' => max(0, (int) $item->quantity - (int) ($claimed[$item->id] ?? 0)),
        ])->filter(fn ($item) => $item['returnable_quantity'] > 0)->values()->all();

        if ($items === []) {
            return $base + ['reason' => __('customer_messages.returns.all_claimed')];
        }

        return ['eligible' => true, 'reason' => null, 'items' => $items] + $base;
    }

    private static function summary(OrderReturn $return): array
    {
        return [
            'id' => $return->id,
            'return_number' => $return->return_number,
            'order_id' => $return->order_id,
            'status' => $return->status,
            'reason' => $return->reason,
            'reason_label' => OrderReturn::reasons()[$return->reason] ?? $return->reason,
            'notes' => $return->notes,
            'requested_refund_amount' => $return->requested_refund_amount,
            'items' => $return->items->map(fn (ReturnItem $item) => $item->only(['order_item_id', 'product_id', 'quantity', 'unit_price', 'subtotal', 'condition_note']))->values()->all(),
            'next_step' => __('customer_messages.returns.next_step'),
            'created_at' => $return->created_at?->toISOString(),
        ];
    }
}
