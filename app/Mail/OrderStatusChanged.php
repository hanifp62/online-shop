<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Status yang memicu email; salinan subjek/isi ada di lang/{locale}/mail_ui.php (order.status.<kode>). */
    public const MESSAGES = [
        'paid' => ['mail_ui.order.status.paid.subject', 'mail_ui.order.status.paid.body'],
        'shipped' => ['mail_ui.order.status.shipped.subject', 'mail_ui.order.status.shipped.body'],
        'completed' => ['mail_ui.order.status.completed.subject', 'mail_ui.order.status.completed.body'],
        'cancelled' => ['mail_ui.order.status.cancelled.subject', 'mail_ui.order.status.cancelled.body'],
    ];

    public function __construct(public Order $order, public string $status)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(self::MESSAGES[$this->status][0], [], $this->locale).' · #'.$this->order->id);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-status-changed', with: [
            'order' => $this->order,
            'message' => __(self::MESSAGES[$this->status][1], [], $this->locale),
        ]);
    }
}
