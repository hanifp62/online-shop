<?php

namespace App\Jobs;

use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentDeadLetter;
use App\Services\CheckoutService;
use App\Services\TransactionLockRetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Expiry and webhook holds serialize on the same order row. */
class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    public function handle(CheckoutService $checkout): int
    {
        $released = 0;
        $orderIds = InventoryReservation::expired()->distinct()->pluck('order_id');
        foreach ($orderIds as $id) {
            $released += TransactionLockRetry::transaction(function () use ($id, $checkout): int {
                $order = Order::whereKey($id)->lockForUpdate()->first();
                if (! $order) {
                    return 0;
                }
                if ($order->status !== 'pending') {
                    $order->inventoryReservations()->where('status', 'reserved')->update([
                        'status' => $order->status === 'cancelled' ? 'released' : 'committed',
                    ]);

                    return 0;
                }
                if (! $order->inventoryReservations()->expired()->exists()
                    || PaymentDeadLetter::where('order_id', $id)->whereNull('resolved_at')->exists()) {
                    return 0;
                }
                $checkout->transition($order->load('items'), 'cancelled');

                return 1;
            }, 3);
        }

        return $released;
    }
}
