<?php

namespace App\Console\Commands;

use App\Jobs\SendCartReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendCartReminders extends Command
{
    protected $signature = 'cart-reminders:send';

    protected $description = 'Kirim pengingat keranjang tersimpan (opt-in) yang telantar';

    public function handle(): int
    {
        $sent = 0;
        DB::table('carts')->join('users', 'users.id', '=', 'carts.user_id')
            ->where('users.cart_reminder_opt_in', true)->whereNotNull('users.email_verified_at')
            ->whereBetween('carts.updated_at', [now()->subDays(7), now()->subHours(24)])
            ->where(fn ($q) => $q->whereNull('users.cart_reminded_at')
                ->orWhereColumn('users.cart_reminded_at', '<', 'carts.updated_at'))
            ->where(fn ($q) => $q->whereNull('users.cart_reminded_at')
                ->orWhere('users.cart_reminded_at', '<', now()->subWeek()))
            ->select('carts.id as cart_id', 'carts.updated_at as cart_updated_at', 'users.id as user_id', 'users.cart_reminded_at as previous_reminded_at')
            ->orderBy('carts.id')->chunk(100, function ($carts) use (&$sent) {
                foreach ($carts as $cart) {
                    $items = DB::table('cart_items')->join('products', 'products.product_id', '=', 'cart_items.product_id')
                        ->where('cart_items.cart_id', $cart->cart_id)->where('products.is_active', true)
                        ->get(['products.product_title as title', 'cart_items.quantity'])
                        ->map(fn ($item) => ['title' => $item->title, 'quantity' => (int) $item->quantity])->all();
                    if ($items === []) {
                        continue;
                    }
                    $claimed = DB::table('users')->where('id', $cart->user_id)->where('cart_reminder_opt_in', true)
                        ->where(fn ($q) => $q->whereNull('cart_reminded_at')->orWhere('cart_reminded_at', '<', now()->subWeek()))
                        ->whereExists(fn ($q) => $q->selectRaw('1')->from('carts')
                            ->where('id', $cart->cart_id)->where('updated_at', $cart->cart_updated_at))
                        ->update(['cart_reminded_at' => now()]);
                    if (! $claimed) {
                        continue;
                    }
                    try {
                        SendCartReminder::dispatch((int) $cart->user_id, $items);
                        $sent++;
                    } catch (\Throwable $e) {
                        DB::table('users')->where('id', $cart->user_id)->update(['cart_reminded_at' => $cart->previous_reminded_at]);
                        throw $e;
                    }
                }
            });
        $this->info("Queued {$sent} cart reminder(s).");

        return self::SUCCESS;
    }
}
