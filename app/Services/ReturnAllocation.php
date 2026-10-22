<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Refund;
use Illuminate\Validation\ValidationException;

/** Immutable order snapshots; shipping is not automatically refundable. */
final class ReturnAllocation
{
    public const VERSION = 'net-balance-v2';

    public static function lines(Order $order): array
    {
        $items = $order->items()->orderBy('id')->get();
        $gross = (int) $items->sum('subtotal');
        if ($gross <= 0 || $gross !== $order->subtotal || $order->discount < 0 || $order->discount > $gross
            || $order->tax < 0 || $order->total !== $gross - $order->discount + $order->tax + $order->shipping_fee) {
            throw ValidationException::withMessages(['order' => __('customer_messages.refund.excess')]);
        }
        $net = $gross - $order->discount + $order->tax;
        $cumulative = 0;
        $previous = 0;
        $allocations = [];
        foreach ($items as $item) {
            $cumulative += (int) $item->subtotal;
            $next = self::portion($net, $cumulative, $gross);
            $allocations[$item->id] = $next - $previous;
            $previous = $next;
        }

        return $allocations;
    }

    /** Caller holds user -> order. Lock claims and obligations before using their stored balances. */
    public static function balances(Order $order): array
    {
        $allocations = self::lines($order);
        $items = $order->items()->get()->keyBy('id');
        $balances = [];
        foreach ($items as $item) {
            $balances[$item->id] = ['quantity' => (int) $item->quantity, 'amount' => $allocations[$item->id]];
        }
        $returns = OrderReturn::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
        $refunds = Refund::where('order_id', $order->id)->where('status', '!=', Refund::STATUS_DITOLAK)
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($returns as $return) {
            $obligations = $refunds->where('return_id', $return->id);
            if ($return->status === OrderReturn::STATUS_REJECTED) {
                if ($obligations->isNotEmpty()) {
                    self::reconciliationRequired();
                }

                continue;
            }
            $total = 0;
            foreach ($return->items()->orderBy('id')->lockForUpdate()->get() as $line) {
                $item = $items->get($line->order_item_id);
                if (! $item || $line->quantity <= 0 || $line->quantity > $item->quantity || $line->subtotal < 0) {
                    self::reconciliationRequired();
                }
                if ($line->allocation_version === self::VERSION) {
                    // Released quantity and released money need not share the original unit average.
                    if ($line->allocation_quantity_before === null || $line->allocation_amount_before === null
                        || $line->allocation_quantity_before < $line->quantity || $line->allocation_quantity_before > $item->quantity
                        || $line->allocation_amount_before < 0 || $line->allocation_amount_before > $allocations[$item->id]
                        || $line->subtotal !== self::portion($line->allocation_amount_before, $line->quantity, $line->allocation_quantity_before)) {
                        self::reconciliationRequired();
                    }
                } elseif ($line->allocation_version === null && $line->allocation_quantity_before === null && $line->allocation_amount_before === null) {
                    // Unknown legacy rows must still satisfy the old net-unit bounds; never bless gross as v2.
                    $floor = self::portion($allocations[$item->id], (int) $line->quantity, (int) $item->quantity);
                    $ceil = $allocations[$item->id] - self::portion($allocations[$item->id], (int) $item->quantity - (int) $line->quantity, (int) $item->quantity);
                    if ($line->subtotal < $floor || $line->subtotal > $ceil) {
                        self::reconciliationRequired();
                    }
                } else {
                    self::reconciliationRequired();
                }
                $balances[$item->id]['quantity'] -= (int) $line->quantity;
                $balances[$item->id]['amount'] -= (int) $line->subtotal;
                $total += (int) $line->subtotal;
            }
            if ($total !== $return->requested_refund_amount || $obligations->count() > 1
                || $obligations->contains(fn ($refund) => $refund->amount !== $total)) {
                self::reconciliationRequired();
            }
        }
        foreach ($balances as $balance) {
            if ($balance['quantity'] < 0 || $balance['amount'] < 0
                || ($balance['quantity'] === 0 && $balance['amount'] !== 0)) {
                self::reconciliationRequired();
            }
        }

        return $balances;
    }

    public static function claimAmount(int $netLine, int $orderedQuantity, array $balance, int $quantity): int
    {
        if ($balance['quantity'] <= 0 || $balance['quantity'] > $orderedQuantity
            || $balance['amount'] < 0 || $balance['amount'] > $netLine
            || $quantity <= 0 || $quantity > $balance['quantity']) {
            self::reconciliationRequired();
        }

        return self::portion($balance['amount'], $quantity, $balance['quantity']);
    }

    private static function reconciliationRequired(): never
    {
        throw ValidationException::withMessages([
            'manual_reconciliation' => 'Alokasi retur historis memerlukan rekonsiliasi manual sebelum klaim atau refund baru.',
        ]);
    }

    /** floor(value * numerator / denominator), without overflowing the intermediate product. */
    public static function portion(int $value, int $numerator, int $denominator): int
    {
        if ($value < 0 || $numerator < 0 || $denominator <= 0 || $numerator > $denominator) {
            throw new \InvalidArgumentException('Invalid monetary allocation ratio.');
        }
        $whole = intdiv($value, $denominator) * $numerator;
        $part = $value % $denominator;
        $quotient = 0;
        $remainder = 0;
        foreach (str_split(decbin($numerator)) as $bit) {
            $quotient *= 2;
            if ($remainder >= $denominator - $remainder) {
                $remainder -= $denominator - $remainder;
                $quotient++;
            } else {
                $remainder *= 2;
            }
            if ($bit === '1') {
                if ($remainder >= $denominator - $part) {
                    $remainder -= $denominator - $part;
                    $quotient++;
                } else {
                    $remainder += $part;
                }
            }
        }

        return $whole + $quotient;
    }
}
