<?php

namespace App\Jobs;

use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentDeadLetter;
use App\Services\CheckoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Batalkan order pending yang reservasinya kedaluwarsa; stok kembali lewat transisi cancelled. */
class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    public function handle(CheckoutService $checkout): int
    {
        $released = 0;
        // Order dengan notifikasi bayar sah yang tertunda tidak boleh dibatalkan sebelum diputar ulang.
        $held = PaymentDeadLetter::whereNull('resolved_at')->whereNotNull('order_id')->pluck('order_id');
        $orderIds = InventoryReservation::expired()->distinct()->pluck('order_id')->diff($held);

        foreach (Order::with('items')->whereIn('id', $orderIds)->get() as $order) {
            if ($order->status !== 'pending') {
                // Order sudah diproses; reservasi tidak lagi mengikat stok.
                $order->inventoryReservations()->where('status', 'reserved')->update(['status' => 'committed']);

                continue;
            }
            try {
                $checkout->transition($order, 'cancelled');
                $released++;
            } catch (HttpException|ValidationException $e) {
                // Status berubah bersamaan (mis. admin memproses); lewati, coba lagi di putaran berikut.
                Log::info('Reservasi tidak dirilis: status order berubah.', ['order_id' => $order->id]);
            }
        }

        return $released;
    }
}
