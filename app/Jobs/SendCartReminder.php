<?php

namespace App\Jobs;

use App\Mail\CartReminder;
use App\Mail\RecipientLocale;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCartReminder implements ShouldQueue
{
    use Queueable;

    /** @param list<array{title: string, quantity: int}> $items */
    public function __construct(public int $userId, public array $items) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        // Opt-out bisa terjadi setelah klaim; cek ulang tepat sebelum mengirim.
        if (! $user?->cart_reminder_opt_in) {
            return;
        }
        Mail::to($user->email)->locale(RecipientLocale::resolve($user->locale))->send(new CartReminder($user, $this->items));
    }
}
