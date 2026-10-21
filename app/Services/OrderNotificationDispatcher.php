<?php

namespace App\Services;

use App\Mail\OrderStatusChanged;
use App\Mail\RecipientLocale;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderNotificationDispatcher
{
    private const LEASE_MINUTES = 10;

    /** Claim atomically, push outside a transaction, then complete only our claim. */
    public function dispatch(int $id): void
    {
        $token = null;

        try {
            $candidateToken = (string) Str::uuid();
            $now = now();
            $claimed = DB::table('order_notifications')->where('id', $id)->whereNull('dispatched_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', $now);
                })->update(['lease_token' => $candidateToken, 'lease_expires_at' => $now->copy()->addMinutes(self::LEASE_MINUTES)]);

            if ($claimed !== 1) {
                return;
            }

            $token = $candidateToken;

            $notification = DB::table('order_notifications')->where('id', $id)->first();
            $order = $notification ? Order::find($notification->order_id) : null;
            $email = $order?->customer_email ?? $order?->user?->email;
            if (! $notification || $notification->channel !== 'mail' || ! $order || ! $email || ! isset(OrderStatusChanged::MESSAGES[$notification->status])) {
                $this->release($id, $token);

                return;
            }

            Mail::to($email)->locale(RecipientLocale::resolve($order->locale ?? $order->user?->locale))
                ->queue((new OrderStatusChanged($order, $notification->status))->beforeCommit());

            DB::table('order_notifications')->where('id', $id)->where('lease_token', $token)
                ->update(['dispatched_at' => now(), 'lease_token' => null, 'lease_expires_at' => null]);
        } catch (\Throwable $exception) {
            report($exception);

            if ($token !== null) {
                try {
                    $this->release($id, $token);
                } catch (\Throwable $releaseException) {
                    // Expiration remains the recovery path if the database cannot release now.
                    report($releaseException);
                }
            }
        }
    }

    private function release(int $id, string $token): void
    {
        DB::table('order_notifications')->where('id', $id)->where('lease_token', $token)
            ->update(['lease_token' => null, 'lease_expires_at' => null]);
    }
}
