<?php

namespace App\Console\Commands;

use App\Mail\OrderStatusChanged;
use App\Services\OrderNotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryOrderNotifications extends Command
{
    protected $signature = 'orders:retry-notifications';

    protected $description = 'Kirim ulang notifikasi order yang tertinggal di outbox';

    public function handle(OrderNotificationDispatcher $dispatcher): int
    {
        $now = now();
        $cutoff = $now->copy()->subMinutes(5);

        // Pre-filter deliverable rows so missing emails do not starve valid pending rows.
        // A row whose order or email is later supplied becomes eligible on the next tick.
        $rows = DB::table('order_notifications')
            ->join('orders', 'orders.id', '=', 'order_notifications.order_id')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->whereNull('order_notifications.dispatched_at')
            ->where('order_notifications.channel', 'mail')
            ->whereIn('order_notifications.status', array_keys(OrderStatusChanged::MESSAGES))
            ->where('order_notifications.created_at', '<=', $cutoff)
            ->where(function ($query) use ($now) {
                $query->whereNull('order_notifications.lease_expires_at')
                    ->orWhere('order_notifications.lease_expires_at', '<=', $now);
            })
            ->whereRaw("COALESCE(orders.customer_email, users.email) NOT IN ('', '0')")
            ->orderBy('order_notifications.id')
            ->limit(100)
            ->pluck('order_notifications.id');

        foreach ($rows as $id) {
            $dispatcher->dispatch($id);
        }

        $this->info('Processed '.$rows->count().' pending order notifications.');

        return self::SUCCESS;
    }
}
