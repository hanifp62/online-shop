<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CartReminder extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param list<array{title: string, quantity: int}> $items */
    public function __construct(public User $user, public array $items) {}

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('cart-reminder.unsubscribe', ['user' => $this->user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail_ui.cart.subject', [], $this->locale));
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.cart-reminder', with: [
            'user' => $this->user,
            'items' => $this->items,
            'cartUrl' => route('shop.index'),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }
}
