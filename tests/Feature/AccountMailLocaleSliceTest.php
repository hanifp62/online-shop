<?php

namespace Tests\Feature;

use App\Mail\CartReminder;
use App\Mail\ConciergeRequested;
use App\Mail\NewsletterWelcome;
use App\Mail\OrderStatusChanged;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccountMailLocaleSliceTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['id', 'en', 'fr', 'it', 'zh'];

    public function test_catalogs_have_recursive_parity_and_nonempty_copy(): void
    {
        foreach (['account_ui', 'mail_ui'] as $catalog) {
            $reference = array_keys(Arr::dot(require base_path("lang/id/$catalog.php")));
            foreach (self::LOCALES as $locale) {
                $values = Arr::dot(require base_path("lang/$locale/$catalog.php"));
                $this->assertSame($reference, array_keys($values), "Mismatch in keys for $catalog in locale $locale");
                foreach ($values as $key => $value) {
                    $this->assertIsString($value, "Non-string value for $catalog.$key in $locale");
                    $this->assertNotSame('', trim($value), "Empty value for $catalog.$key in $locale");
                }
            }
        }
    }

    public function test_account_views_render_each_locale_with_escaped_user_content(): void
    {
        $user = User::factory()->create([
            'name' => '<Malicious & Co>',
            'first_name' => '<Malicious>',
            'last_name' => '<Co>',
            'email' => 'victim@example.test',
            'mobile' => '08123456789',
            'address1' => '<Street 1>',
            'address2' => '<City>',
        ]);

        $conciergeItem = (object) [
            'service' => 'Private Styling',
            'status' => 'confirmed',
            'preferred_date' => Carbon::now()->addDays(2),
            'preferred_time' => '14:00',
            'created_at' => Carbon::now(),
            'notes' => '<Special Request>',
        ];

        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);

            // 1. totp-enrol
            $enrol = view('account.totp-enrol', [
                'qr' => 'data:image/png;base64,aa',
                'secret' => '<bad>',
                'errors' => new ViewErrorBag,
            ])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $enrol);
            $this->assertStringContainsString(e(trans('account_ui.enrol.heading', [], $locale)), $enrol);
            $this->assertStringContainsString('&lt;bad&gt;', $enrol);
            $this->assertStringNotContainsString('<bad>', $enrol);

            // 2. totp-recovery
            $recovery = view('account.totp-recovery', ['codes' => ['<secret>']])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $recovery);
            $this->assertStringContainsString(e(trans('account_ui.recovery.heading', [], $locale)), $recovery);
            $this->assertStringContainsString('&lt;secret&gt;', $recovery);
            $this->assertStringNotContainsString('<secret>', $recovery);

            // 3. cart-reminder-unsubscribed (done state)
            $unsubDone = view('account.cart-reminder-unsubscribed', ['done' => true, 'action' => null])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $unsubDone);
            $this->assertStringContainsString(e(trans('account_ui.unsubscribe.done_heading', [], $locale)), $unsubDone);

            // 4. cart-reminder-unsubscribed (pending state)
            $unsubPending = view('account.cart-reminder-unsubscribed', ['done' => false, 'action' => 'https://example.test/action'])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $unsubPending);
            $this->assertStringContainsString(e(trans('account_ui.unsubscribe.heading', [], $locale)), $unsubPending);
            $this->assertStringContainsString(e(trans('account_ui.unsubscribe.submit', [], $locale)), $unsubPending);

            // 5. show
            $show = view('account.show', [
                'user' => $user,
                'history' => collect([$conciergeItem]),
                'errors' => new ViewErrorBag,
            ])->render();
            $this->assertStringContainsString('<html lang="'.$locale.'">', $show);
            $this->assertStringContainsString(e(trans('account_ui.show.title', [], $locale)), $show);
            $this->assertStringContainsString(e(trans('account_ui.profile.name', [], $locale)), $show);
            $this->assertStringContainsString(e(trans('account_ui.status.confirmed', [], $locale)), $show);
            $this->assertStringContainsString('&lt;Malicious &amp; Co&gt;', $show);
            $this->assertStringContainsString('&lt;Special Request&gt;', $show);
            $this->assertStringNotContainsString('<Malicious & Co>', $show);
            $this->assertStringNotContainsString('<Special Request>', $show);
        }
    }

    public function test_all_mail_views_render_localized_copy_and_preserve_proper_nouns(): void
    {
        $user = User::factory()->create(['name' => '<Customer>', 'first_name' => '<Customer>']);
        $subscriber = new NewsletterSubscriber(['email' => 'news@example.test']);
        $concierge = new ConciergeRequest([
            'name' => '<Klien>',
            'phone' => '0812345678',
            'notes' => '<Catatan Pribadi>',
            'email' => 'klien@example.test',
            'service' => 'Sur-Mesure',
            'preferred_date' => '2026-11-01',
            'preferred_time' => '10:00',
        ]);

        $order = new Order;
        $order->id = 1234;
        $order->total = 15000000;
        $order->setRelation('user', $user);

        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);

            // CartReminder mailable
            $cartHtml = (new CartReminder($user, [['title' => '<Silk Scarf>', 'quantity' => 2]]))->render();
            $this->assertStringContainsString(trans('mail_ui.cart.intro', [], $locale), $cartHtml);
            $this->assertStringContainsString(trans('mail_ui.common.regards', [], $locale), $cartHtml);
            $this->assertStringContainsString('&lt;Silk Scarf&gt;', $cartHtml);
            $this->assertStringContainsString('&lt;Customer&gt;', $cartHtml);
            $this->assertStringContainsString('ZAFIRA — La Maison de Lumière', $cartHtml);

            // NewsletterWelcome mailable
            $newsHtml = (new NewsletterWelcome($subscriber))->render();
            $this->assertStringContainsString(trans('mail_ui.newsletter.visit', [], $locale), $newsHtml);
            $this->assertStringContainsString('La Lettre ZAFIRA', $newsHtml);
            $this->assertStringContainsString('ZAFIRA — La Maison de Lumière', $newsHtml);

            // ConciergeRequested mailable
            $conciergeHtml = (new ConciergeRequested($concierge))->render();
            $this->assertStringContainsString(trans('mail_ui.concierge.received', [], $locale), $conciergeHtml);
            $this->assertStringContainsString(trans('mail_ui.concierge.details', [], $locale), $conciergeHtml);
            $this->assertStringContainsString('&lt;Klien&gt;', $conciergeHtml);
            $this->assertStringContainsString('&lt;Catatan Pribadi&gt;', $conciergeHtml);
            $this->assertStringContainsString('ZAFIRA — La Maison de Lumière', $conciergeHtml);

            // OrderStatusChanged mailable
            $orderHtml = (new OrderStatusChanged($order, 'paid'))->render();
            $this->assertStringContainsString(trans('mail_ui.order.number', [], $locale), $orderHtml);
            $this->assertStringContainsString(trans('mail_ui.order.total', [], $locale), $orderHtml);
            $this->assertStringContainsString('#1234', $orderHtml);
            $this->assertStringContainsString('Rp 15.000.000', $orderHtml);
            $this->assertStringContainsString('ZAFIRA — La Maison de Lumière', $orderHtml);
        }
    }

    public function test_indonesian_copy_is_preserved_identically_for_account_and_mail(): void
    {
        app()->setLocale('id');

        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'email' => 'budi@example.test',
        ]);

        $accountHtml = view('account.show', [
            'user' => $user,
            'history' => collect(),
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('Profil Saya — ZAFIRA', $accountHtml);
        $this->assertStringContainsString('Kembali ke Maison', $accountHtml);
        $this->assertStringContainsString('Kelola informasi kontak Anda untuk memudahkan komunikasi dengan concierge.', $accountHtml);
        $this->assertStringContainsString('Kirimi saya pengingat email bila keranjang tersimpan belum dibayar (maks. 1× per minggu)', $accountHtml);
        $this->assertStringContainsString('Verifikasi Dua Langkah (Opsional)', $accountHtml);
        $this->assertStringContainsString('Privasi &amp; Data Anda', $accountHtml);
        $this->assertStringContainsString('Riwayat Concierge &amp; Janji Temu Kriya', $accountHtml);

        $cartMail = (new CartReminder($user, [['title' => 'Gaun Sutra', 'quantity' => 1]]))->render();
        $this->assertStringContainsString('Pilihan Anda masih tersimpan di keranjang:', $cartMail);
        $this->assertStringContainsString('Lihat Keranjang', $cartMail);
        $this->assertStringContainsString('Dengan hormat,', $cartMail);
    }
}
