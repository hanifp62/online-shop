<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RescueBackendAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('register');
        RateLimiter::clear('concierge');
        RateLimiter::clear('newsletter');
    }

    public function test_admin_login_ignores_customer_intended_route(): void
    {
        $admin = Admin::create(['name' => 'QA Admin', 'email' => 'qa-admin@example.test', 'password' => 'SecurePass#123']);

        $this->get(route('account.show'))->assertRedirect(route('login'));
        $this->post(route('admin.login.process'), [
            'email' => $admin->email,
            'password' => 'SecurePass#123',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_is_redirected_to_customer_login_for_customer_routes(): void
    {
        $this->get(route('account.show'))
            ->assertRedirect(route('login'));

        $this->patch(route('account.update'), ['name' => 'Baru'])
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_admin_login_for_admin_routes(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->patch(route('admin.password.update'), [])
            ->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_customer_is_redirected_to_admin_login_on_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_customer_guest_routes_redirect_authenticated_customer_away(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get(route('login'))
            ->assertRedirect('/');

        $this->actingAs($user, 'web')
            ->get(route('register'))
            ->assertRedirect('/');
    }

    public function test_admin_guest_routes_redirect_authenticated_admin_away(): void
    {
        $admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.local',
            'password' => 'SecurePass#123',
        ]);

        // Middleware guest:admin memakai tujuan default Laravel ('/'), bukan dashboard.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.login'))
            ->assertRedirect('/');
    }

    public function test_account_update_sanitizes_mass_assignment_and_prevents_idor(): void
    {
        $userA = User::factory()->create(['name' => 'User A', 'email' => 'a@zafira.maison']);
        $userB = User::factory()->create(['name' => 'User B', 'email' => 'b@zafira.maison']);

        $originalPassword = $userA->password;

        $this->actingAs($userA, 'web')
            ->from(route('account.show'))
            ->patch(route('account.update'), [
                'id' => $userB->id,
                'name' => 'User A Updated',
                'email' => 'attacker@hack.local',
                'password' => 'InjectedPassword#123',
                'first_name' => 'Alice',
                'last_name' => 'Wonderland',
                'mobile' => '+6281234567890',
                'address1' => 'Street 1',
                'address2' => 'Suite 2',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('success');

        $userA->refresh();
        $userB->refresh();

        $this->assertSame('User A Updated', $userA->name);
        $this->assertSame('a@zafira.maison', $userA->email);
        $this->assertSame($originalPassword, $userA->password);
        $this->assertSame('User B', $userB->name);
        $this->assertSame('b@zafira.maison', $userB->email);

        $this->assertDatabaseHas('user_info_backups', [
            'user_id' => $userA->id,
            'first_name' => 'Alice',
            'mobile' => '+6281234567890',
        ]);
    }

    public function test_account_update_validates_duplicate_name_for_other_users(): void
    {
        $userA = User::factory()->create(['name' => 'ExistingUser']);
        $userB = User::factory()->create(['name' => 'ActiveUser']);

        $this->actingAs($userB, 'web')
            ->from(route('account.show'))
            ->patch(route('account.update'), [
                'name' => 'ExistingUser',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHasErrors(['name']);

        $this->assertSame('ActiveUser', $userB->fresh()->name);
    }

    public function test_web_write_routes_reject_requests_without_csrf_token(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $response = $this->post('/newsletter', ['email' => 'csrf@test.local']);

        $response->assertStatus(419);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'csrf@test.local']);
    }

    public function test_customer_logout_requires_post_and_invalidates_session(): void
    {
        $user = User::factory()->create();
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($user, 'web');
        $this->assertAuthenticated('web');

        $this->get(route('logout'))
            ->assertStatus(405);

        // Tanpa token CSRF: ditolak dengan status 419
        $this->post(route('logout'))
            ->assertStatus(419);
        $this->assertAuthenticated('web');

        // Dengan token sesi yang valid: berhasil logout
        $response = $this->post(route('logout'), ['_token' => session()->token()]);
        $response->assertRedirect('/');
        $this->assertGuest('web');
    }

    public function test_admin_logout_requires_post_and_preserves_customer_if_both_active(): void
    {
        $user = User::factory()->create(['password' => 'Dual-customer!Password123']);
        $admin = Admin::create([
            'name' => 'Dual Admin',
            'email' => 'dual@test.local',
            'password' => 'Pass#1234',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Dual-customer!Password123'])->assertRedirect('/');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Pass#1234'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');

        $this->get(route('admin.logout'))->assertStatus(405);
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        auth()->forgetGuards();
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_customer_logout_preserves_admin_session_if_both_active(): void
    {
        $user = User::factory()->create(['password' => 'Dual-customer!Password123']);
        $admin = Admin::create([
            'name' => 'Dual Admin 2',
            'email' => 'dual2@test.local',
            'password' => 'Pass#1234',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Dual-customer!Password123'])->assertRedirect('/');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Pass#1234'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        auth()->forgetGuards();
        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_login_rate_limiting_triggers_after_ten_attempts(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->post('/login', [
                'email' => 'invalid@user.com',
                'password' => 'WrongPass#123',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'invalid@user.com',
            'password' => 'WrongPass#123',
        ])->assertStatus(429);
    }

    public function test_register_rate_limiting_triggers_after_five_attempts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $email = "register{$i}@test.local";
            $this->post('/register', [
                'name' => "Register {$i}",
                'email' => $email,
                'password' => 'Password#123',
                'password_confirmation' => 'Password#123',
            ])->assertRedirect('/');
            // Middleware guest berjalan sebelum throttle; keluar agar request berikut dihitung limiter.
            auth()->guard('web')->logout();
        }

        $this->post('/register', [
            'name' => 'Blocked Register',
            'email' => 'blocked-register@test.local',
            'password' => 'Password#123',
            'password_confirmation' => 'Password#123',
        ])->assertStatus(429);

        $this->assertDatabaseMissing('users', ['email' => 'blocked-register@test.local']);
    }

    public function test_concierge_rate_limiting_triggers_after_three_attempts(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Concierge Tester',
            'email' => 'tester@maison.test',
            'phone' => '08123456789',
            'service' => 'private-styling',
        ];

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson(route('concierge.store'), $payload)->assertCreated();
        }

        $this->postJson(route('concierge.store'), $payload)->assertStatus(429);
    }

    public function test_concierge_validation_handles_xss_safely_in_notes_and_names(): void
    {
        Mail::fake();
        $xss = '<script>alert("XSS")</script>';

        $response = $this->postJson(route('concierge.store'), [
            'name' => 'Tester '.$xss,
            'email' => 'xss@maison.test',
            'phone' => '+628123456789',
            'service' => 'atelier',
            'notes' => 'Catatan '.$xss,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'xss@maison.test',
            'name' => 'Tester '.$xss,
        ]);

        // Cek bahwa admin dashboard dan akun user tidak me-render script tag mentah
        $admin = Admin::create([
            'name' => 'Admin XSS',
            'email' => 'admin-xss@test.local',
            'password' => 'Pass#1234',
        ]);

        $responseAdmin = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $responseAdmin->assertOk();
        $responseAdmin->assertDontSee($xss, false);
        $responseAdmin->assertSee(e($xss), false);
    }

    public function test_concierge_order_items_rejects_nonexistent_product_slug(): void
    {
        Mail::fake();

        $response = $this->postJson(route('concierge.store'), [
            'name' => 'Valid User',
            'email' => 'valid@test.maison',
            'phone' => '0811111111',
            'service' => 'atelier',
            'order_items' => json_encode([
                ['id' => 'non-existent-slug-xyz', 'quantity' => 1],
            ]),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('order_items.0.id');
    }

    public function test_concierge_order_items_enforces_max_quantity_and_monogram_format(): void
    {
        $category = Category::create(['cat_title' => 'Couture']);
        $product = Product::create([
            'product_title' => 'Robe Soie',
            'product_slug' => 'robe-soie',
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_price' => 20000000,
            'product_image' => 'https://example.com/robe.jpg',
        ]);

        $responseInvalidQty = $this->postJson(route('concierge.store'), [
            'name' => 'User Qty',
            'email' => 'qty@test.maison',
            'phone' => '0811111111',
            'service' => 'atelier',
            'order_items' => json_encode([
                ['id' => $product->product_slug, 'quantity' => 50],
            ]),
        ]);
        $responseInvalidQty->assertUnprocessable()
            ->assertJsonValidationErrors('order_items.0.quantity');

        $responseInvalidMonogram = $this->postJson(route('concierge.store'), [
            'name' => 'User Monogram',
            'email' => 'mono@test.maison',
            'phone' => '0811111111',
            'service' => 'atelier',
            'order_items' => json_encode([
                ['id' => $product->product_slug, 'quantity' => 1, 'monogram' => 'toolong'],
            ]),
        ]);
        $responseInvalidMonogram->assertUnprocessable()
            ->assertJsonValidationErrors('order_items.0.monogram');
    }

    public function test_admin_destructive_category_delete_prevents_orphan_products(): void
    {
        $this->seed(AdminSeeder::class);
        $admin = Admin::first();

        $category = Category::create(['cat_title' => 'Kategori Terisi']);
        $product = Product::create([
            'product_title' => 'Produk Proteksi',
            'product_slug' => 'produk-proteksi',
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_price' => 1000000,
            'product_image' => 'https://example.com/p.jpg',
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.categories.destroy', $category->cat_id))
            ->assertSessionHasErrors(['cat_title']);

        $this->assertDatabaseHas('categories', ['cat_id' => $category->cat_id]);
    }

    public function test_sitemap_returns_valid_xml_with_proper_headers_and_no_private_routes(): void
    {
        $response = $this->get(route('sitemap'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertHeader('Cache-Control', 'max-age=3600, public');

        $content = $response->streamedContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringNotContainsString('/admin', $content);
        $this->assertStringNotContainsString('/akun', $content);
        $this->assertStringNotContainsString('/login', $content);
        $this->assertStringNotContainsString('/register', $content);
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader('X-XSS-Protection', '0');
    }
}
