<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminOrderController;
use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CrossChannelLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_uses_authenticated_recipient_without_session_choice(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create(['locale' => 'fr']))
            ->postJson('/newsletter', ['email' => 'reader@example.test'])->assertSuccessful();
        Mail::assertQueued(NewsletterWelcome::class, fn ($mail) => $mail->locale === 'fr');
    }

    public function test_concierge_uses_explicit_request_locale(): void
    {
        Mail::fake();
        $this->postJson('/concierge?locale=fr', [
            'name' => 'Test Reader',
            'email' => 'reader@example.test',
            'phone' => '+62 812 3456 7890',
            'service' => 'private-styling',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time' => '14:00',
        ])->assertCreated();
        Mail::assertQueued(ConciergeRequested::class, fn ($mail) => $mail->locale === 'fr');
    }

    public function test_null_notification_locale_never_borrows_sender_locale(): void
    {
        app()->setLocale('fr');
        $mail = (new PasswordChanged)->toMail(new User);
        $this->assertSame(trans('mail_ui.password_changed.subject', [], 'id'), $mail->subject);
    }

    public function test_framework_notifications_render_in_recipient_locale_and_restore_worker(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create(['locale' => 'fr']);
        app()->setLocale('id');
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('test-token');
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertSame('Vérifiez votre adresse e-mail', $messages[0]->getOriginalMessage()->getSubject());
        $this->assertSame('Réinitialisez votre mot de passe', $messages[1]->getOriginalMessage()->getSubject());
        $this->assertSame('id', app()->getLocale());
    }

    public function test_guest_order_mail_and_invoice_use_saved_locale_not_operator(): void
    {
        Mail::fake();
        $order = Order::create([
            'customer_email' => 'guest@example.test', 'locale' => 'fr', 'status' => 'pending',
            'idempotency_key' => 'locale', 'payload_hash' => str_repeat('a', 64),
        ]);
        app()->setLocale('id');
        app(CheckoutService::class)->markPaid($order);
        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->locale === 'fr'
            && $mail->hasTo('guest@example.test')
            && unserialize(serialize($mail))->locale === 'fr');
        $html = app(AdminOrderController::class)->invoice($order->id)->getContent();
        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString(e(trans('admin_ui.invoice.date', [], 'fr')), $html);
        $this->assertSame('id', app()->getLocale());
    }

    public function test_order_without_recipient_email_claims_no_notification_slot(): void
    {
        Mail::fake();
        $order = Order::create([
            'customer_email' => null, 'locale' => 'fr', 'status' => 'pending',
            'idempotency_key' => 'no-email', 'payload_hash' => str_repeat('b', 64),
        ]);
        $this->assertTrue(app(CheckoutService::class)->markPaid($order));
        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('order_notifications', ['order_id' => $order->id]);
    }
}
