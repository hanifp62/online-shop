<?php

namespace App\Mail;

use App\Models\ConciergeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConciergeRequested extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ConciergeRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail_ui.concierge.subject', [], $this->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.concierge-requested',
            with: [
                'request' => $this->request,
            ],
        );
    }
}
