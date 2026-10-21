<?php

namespace Tests\Feature;

use App\Jobs\SendCartReminder;
use App\Mail\CartReminder;
use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Mail\OrderStatusChanged;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveredMailLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_mail_queues_session_locale_and_rejects_unsupported_locale(): void
    {
        Mail::fake();
        app()->setLocale('id');

        $this->withSession(['locale' => 'fr'])->postJson('/newsletter', ['email' => 'fr@example.test'])->assertOk();
        Mail::assertQueued(NewsletterWelcome::class, fn ($mail) => $mail->locale === 'fr'
            && unserialize(serialize($mail))->locale === 'fr'
            && str_contains($mail->render(), trans('mail_ui.newsletter.visit', [], 'fr')));

        $this->withSession(['locale' => 'invalid'])->postJson('/newsletter', ['email' => 'other@example.test'])->assertOk();
        Mail::assertQueued(NewsletterWelcome::class, fn ($mail) => $mail->hasTo('other@example.test') && $mail->locale === 'id');
    }

    public function test_concierge_guest_queues_session_locale(): void
    {
        Mail::fake();
        $this->withSession(['locale' => 'fr'])->postJson('/concierge', [
            'name' => 'Client', 'email' => 'client@example.test', 'phone' => '08123456789',
            'service' => 'atelier',
        ])->assertCreated();
        Mail::assertQueued(ConciergeRequested::class, fn ($mail) => $mail->locale === 'fr');
    }

    public function test_cart_job_sends_in_user_locale_even_when_worker_uses_indonesian(): void
    {
        Mail::fake();
        app()->setLocale('id');
        $user = User::factory()->create(['locale' => 'fr', 'cart_reminder_opt_in' => true]);
        (new SendCartReminder($user->id, [['title' => 'Silk', 'quantity' => 1]]))->handle();
        Mail::assertSent(CartReminder::class, fn ($mail) => $mail->locale === 'fr'
            && str_contains($mail->render(), trans('mail_ui.cart.intro', [], 'fr')));
    }

    public function test_order_notification_queues_serializable_recipient_locale(): void
    {
        Mail::fake();
        app()->setLocale('id');
        $user = User::factory()->create(['locale' => 'fr']);
        $order = Order::create([
            'user_id' => $user->id, 'status' => 'pending', 'subtotal' => 1000,
            'discount' => 0, 'tax' => 0, 'shipping_fee' => 0, 'total' => 1000,
            'idempotency_key' => 'mail-locale-test', 'payload_hash' => str_repeat('a', 64),
        ]);
        app(CheckoutService::class)->markPaid($order);
        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->locale === 'fr'
            && unserialize(serialize($mail))->locale === 'fr'
            && str_contains($mail->render(), trans('mail_ui.order.status.paid.body', [], 'fr')));
    }

    public function test_every_status_and_locale_has_translated_subject_and_body(): void
    {
        $order = new Order;
        $order->id = 42;
        foreach (config('app.supported_locales') as $locale) {
            foreach (array_keys(OrderStatusChanged::MESSAGES) as $status) {
                $subject = trans("mail_ui.order.status.$status.subject", [], $locale);
                $body = trans("mail_ui.order.status.$status.body", [], $locale);
                $this->assertNotSame("mail_ui.order.status.$status.subject", $subject);
                $this->assertNotSame("mail_ui.order.status.$status.body", $body);
                $this->assertNotSame('', trim($subject));
                $this->assertNotSame('', trim($body));
                $mail = (new OrderStatusChanged($order, $status))->locale($locale);
                $this->assertSame($subject.' · #42', $mail->envelope()->subject);
                $this->assertStringContainsString($body, $mail->render());
            }
        }
    }

    public function test_other_subjects_follow_mailable_locale(): void
    {
        $user = new User;
        $subscriber = new NewsletterSubscriber;
        $request = new ConciergeRequest;
        foreach (config('app.supported_locales') as $locale) {
            foreach ([
                [(new CartReminder($user, []))->locale($locale), 'cart.subject'],
                [(new NewsletterWelcome($subscriber))->locale($locale), 'newsletter.subject'],
                [(new ConciergeRequested($request))->locale($locale), 'concierge.subject'],
            ] as [$mail, $key]) {
                $subject = trans("mail_ui.$key", [], $locale);
                $this->assertNotSame("mail_ui.$key", $subject);
                $this->assertNotSame('', trim($subject));
                $this->assertSame($subject, $mail->envelope()->subject);
            }
        }
    }
}
