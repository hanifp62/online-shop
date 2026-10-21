<?php

namespace Tests\Feature;

use App\Jobs\SendCartReminder;
use App\Mail\CartReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CartReminderTest extends TestCase
{
    use RefreshDatabase;

    private function cartFor(User $user, \DateTimeInterface $updatedAt, bool $active = true): void
    {
        $product = DB::table('products')->value('product_id');
        DB::table('products')->where('product_id', $product)->update(['stock' => 5, 'is_active' => $active]);
        $cart = DB::table('carts')->insertGetId(['user_id' => $user->id, 'created_at' => $updatedAt, 'updated_at' => $updatedAt]);
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_id' => $product, 'quantity' => 2, 'created_at' => $updatedAt, 'updated_at' => $updatedAt]);
    }

    private function user(bool $optIn = true): User
    {
        return User::factory()->create(['cart_reminder_opt_in' => $optIn, 'email_verified_at' => now()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
    }

    public function test_opted_in_user_with_stale_cart_gets_one_reminder_only(): void
    {
        $user = $this->user();
        $this->cartFor($user, now()->subHours(30));

        $this->artisan('cart-reminders:send')->assertSuccessful();
        $this->artisan('cart-reminders:send')->assertSuccessful();

        Mail::assertSent(CartReminder::class, 1);
        Mail::assertSent(CartReminder::class, fn ($mail) => $mail->hasTo($user->email) && $mail->items[0]['quantity'] === 2);
        $this->assertNotNull($user->fresh()->cart_reminded_at);
    }

    public function test_no_reminder_without_opt_in_or_verified_email_or_outside_window(): void
    {
        $this->cartFor($this->user(false), now()->subHours(30));
        $unverified = User::factory()->create(['cart_reminder_opt_in' => true, 'email_verified_at' => null]);
        $this->cartFor($unverified, now()->subHours(30));
        $this->cartFor($this->user(), now()->subHours(2));
        $this->cartFor($this->user(), now()->subDays(10));

        $this->artisan('cart-reminders:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_cart_with_only_inactive_products_is_skipped(): void
    {
        $this->cartFor($this->user(), now()->subHours(30), active: false);

        $this->artisan('cart-reminders:send')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_rate_limit_is_one_per_week_even_after_cart_changes(): void
    {
        $user = $this->user();
        $user->forceFill(['cart_reminded_at' => now()->subDays(3)])->save();
        $this->cartFor($user, now()->subHours(30));

        $this->artisan('cart-reminders:send')->assertSuccessful();
        Mail::assertNothingSent();

        $this->travel(5)->days();
        DB::table('carts')->where('user_id', $user->id)->update(['updated_at' => now()->subHours(25)]);
        $this->artisan('cart-reminders:send')->assertSuccessful();
        Mail::assertSent(CartReminder::class, 1);
    }

    public function test_profile_toggle_and_signed_unsubscribe(): void
    {
        $user = $this->user(false);
        $this->actingAs($user)->patch(route('account.update'), ['name' => $user->name, 'cart_reminder_opt_in' => '1'])->assertRedirect();
        $this->assertTrue($user->fresh()->cart_reminder_opt_in);

        auth()->logout();
        $this->get(route('cart-reminder.unsubscribe', ['user' => $user->id]))->assertForbidden();
        $this->assertTrue($user->fresh()->cart_reminder_opt_in);

        $signed = URL::signedRoute('cart-reminder.unsubscribe', ['user' => $user->id]);
        $this->get($signed)->assertOk()->assertSee('Hentikan pengingat keranjang?');
        $this->assertTrue($user->fresh()->cart_reminder_opt_in, 'GET only confirms (link scanners)');

        $this->post(route('cart-reminder.unsubscribe.store', ['user' => $user->id]))->assertForbidden();
        $this->post(URL::signedRoute('cart-reminder.unsubscribe.store', ['user' => $user->id]))
            ->assertOk()->assertSee('Pengingat keranjang dihentikan');
        $this->assertFalse($user->fresh()->cart_reminder_opt_in);
    }

    public function test_queued_reminder_is_dropped_if_user_opted_out_before_delivery(): void
    {
        $user = $this->user();
        $user->forceFill(['cart_reminder_opt_in' => false])->save();

        (new SendCartReminder($user->id, [['title' => 'Gaun', 'quantity' => 1]]))->handle();

        Mail::assertNothingSent();
    }

    public function test_reminder_mail_renders_items_and_unsubscribe_link(): void
    {
        $user = $this->user();
        $html = (new CartReminder($user, [['title' => 'Gaun Sutra', 'quantity' => 1]]))->render();

        $this->assertStringContainsString('Gaun Sutra', $html);
        $this->assertStringContainsString('pengingat-keranjang/berhenti/'.$user->id, $html);
        $this->assertStringContainsString('signature=', $html);
    }
}
