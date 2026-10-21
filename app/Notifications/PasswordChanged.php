<?php

namespace App\Notifications;

use App\Mail\RecipientLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChanged extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = RecipientLocale::resolve($notifiable->locale);

        return (new MailMessage)
            ->subject(__('mail_ui.password_changed.subject', [], $locale))
            ->line(__('mail_ui.password_changed.changed', [], $locale))
            ->line(__('mail_ui.password_changed.warning', [], $locale))
            ->action(__('mail_ui.password_changed.action', [], $locale), route('login'));
    }
}
