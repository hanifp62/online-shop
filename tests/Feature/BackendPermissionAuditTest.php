<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BackendPermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_endpoint_rejects_guests_and_customer_sessions(): void
    {
        $endpoints = [
            ['get', 'admin.dashboard', []],
            ['post', 'admin.categories.store', ['cat_title' => 'Baru']],
            ['patch', 'admin.categories.update', ['category' => 1]],
            ['delete', 'admin.categories.destroy', ['category' => 1]],
            ['post', 'admin.products.store', ['product_title' => 'Baru']],
            ['patch', 'admin.products.update', ['product' => 1]],
            ['delete', 'admin.products.destroy', ['product' => 1]],
            ['patch', 'admin.concierge.update', ['concierge' => 1]],
            ['delete', 'admin.subscribers.destroy', ['subscriber' => 1]],
            ['get', 'admin.orders.index', []],
            ['get', 'admin.orders.show', ['id' => 1]],
            ['patch', 'admin.orders.update', ['id' => 1]],
            ['get', 'admin.orders.invoice', ['id' => 1]],
            ['get', 'admin.orders.report', []],
            ['get', 'admin.auth-events.index', []],
            ['patch', 'admin.password.update', []],
            ['post', 'admin.logout', []],
        ];

        foreach ($endpoints as [$method, $name, $params]) {
            $this->{$method}(route($name, $params))->assertRedirect(route('admin.login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $name, $params]) {
            $this->{$method}(route($name, $params))->assertRedirect(route('admin.login'));
        }
    }

    public function test_customer_cannot_change_email_password_or_other_users_concierge_history(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $originalPasswordHash = $owner->password;
        $otherOriginalName = $other->name;

        ConciergeRequest::create([
            'user_id' => $other->id, 'name' => 'Rahasia', 'email' => 'other@example.test',
            'phone' => '08123456789', 'service' => 'atelier',
        ]);

        $this->actingAs($owner)->get(route('account.show'))
            ->assertOk()->assertDontSee('other@example.test');
        $this->patch(route('account.update'), [
            'name' => 'Nama Baru', 'email' => 'stolen@example.test',
            'password' => 'Compromised#123', 'user_id' => $other->id,
        ])->assertRedirect();

        $owner->refresh();
        $this->assertSame($owner->id, auth()->id());
        $this->assertSame('Nama Baru', $owner->name);
        $this->assertNotSame('stolen@example.test', $owner->email);
        $this->assertSame($originalPasswordHash, $owner->password);
        $this->assertSame($otherOriginalName, $other->fresh()->name);
        $this->assertNotSame($other->id, $owner->id);
    }

    public function test_public_concierge_cannot_set_owner_or_status(): void
    {
        Mail::fake();
        $other = User::factory()->create();
        $this->postJson(route('concierge.store'), [
            'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
            'service' => 'atelier', 'user_id' => $other->id, 'status' => 'completed',
        ])->assertCreated();
        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'guest@example.test', 'user_id' => null, 'status' => 'new',
        ]);
    }

    public function test_array_email_input_is_rejected_without_a_server_error(): void
    {
        foreach (['/register', '/login', '/concierge', '/newsletter'] as $uri) {
            $this->postJson($uri, ['email' => ['malformed']])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
    }

    public function test_login_password_must_be_a_string(): void
    {
        $user = User::factory()->create();
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        foreach (['/login' => $user->email, '/admin/login' => $admin->email] as $uri => $email) {
            $this->postJson($uri, ['email' => $email, 'password' => ['malformed']])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
    }

    public function test_dashboard_only_offers_allowed_concierge_transitions(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $expected = [
            'new' => ['new', 'contacted', 'confirmed', 'cancelled'],
            'contacted' => ['contacted', 'confirmed', 'cancelled'],
            'confirmed' => ['confirmed', 'completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        foreach ($expected as $status => $options) {
            $request = ConciergeRequest::create([
                'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
                'service' => 'atelier', 'status' => $status,
            ]);
            $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();
            $action = route('admin.concierge.update', $request);
            $nodes = $this->queryHtml($html, '//form[@action="'.$action.'"]//select[@name="status"]/option');
            $actual = [];
            foreach ($nodes as $node) {
                $actual[] = $node->getAttribute('value');
            }
            $this->assertSame($options, $actual, 'Options for '.$status);
            if ($options === []) {
                $this->assertCount(0, $this->queryHtml($html, '//form[@action="'.$action.'"]'));
            }
        }
    }

    public function test_concierge_cannot_skip_directly_from_new_to_completed(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $request = ConciergeRequest::create([
            'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
            'service' => 'atelier', 'status' => 'new',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.concierge.update', $request), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->assertSame('new', $request->fresh()->status);
    }

    public function test_full_valid_concierge_lifecycle_remains_allowed(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $request = ConciergeRequest::create([
            'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
            'service' => 'atelier', 'status' => 'new',
        ]);

        foreach (['contacted', 'confirmed', 'completed'] as $status) {
            $this->actingAs($admin, 'admin')
                ->patch(route('admin.concierge.update', $request), ['status' => $status])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $request->fresh()->status);
        }
    }

    public function test_confirmed_concierge_cannot_move_backward_but_can_be_cancelled(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $request = ConciergeRequest::create([
            'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
            'service' => 'atelier', 'status' => 'confirmed',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.concierge.update', $request), ['status' => 'contacted'])
            ->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $request->fresh()->status);

        $this->patch(route('admin.concierge.update', $request), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_newsletter_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('newsletter.store'), ['email' => "subscriber{$i}@example.test"])->assertOk();
        }

        $this->postJson(route('newsletter.store'), ['email' => 'blocked@example.test'])
            ->assertStatus(429);
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'blocked@example.test']);
    }

    public function test_completed_concierge_request_cannot_be_reopened(): void
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $request = ConciergeRequest::create([
            'name' => 'Tamu', 'email' => 'guest@example.test', 'phone' => '08123456789',
            'service' => 'atelier', 'status' => 'completed',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.concierge.update', $request), ['status' => 'new'])
            ->assertSessionHasErrors('status');
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_product_json_ld_escapes_html_tags_but_stays_valid_json(): void
    {
        $title = 'Tote</script><script>alert(1)</script>';
        $alt = 'Deskripsi</script> "kutipan" & \\ backslash';
        $category = Category::create(['cat_title' => 'Tas']);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => $title,
            'product_slug' => 'tote-xss',
            'product_price' => 15000000,
            'product_image' => 'https://example.test/tote.jpg',
            'product_alt' => $alt,
        ]);

        $response = $this->get(route('products.show', $product->product_slug))->assertOk();
        $html = $response->getContent();

        // Tidak ada pelarian keluar dari blok <script> di dokumen apa pun.
        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);

        $raw = $this->queryHtml($html, '//script[@type="application/ld+json"]');
        $this->assertCount(1, $raw);

        $data = json_decode(trim($raw->item(0)->textContent), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($title, $data['name']);
        $this->assertSame($alt, $data['description']);
        $this->assertSame((int) $product->product_price, $data['offers']['price']);
    }

    public function test_admin_dashboard_does_not_inject_stored_names_into_inline_javascript(): void
    {
        $payload = '\');alert(1);// "kutipan" & \\ </script><script>alert(9)</script>';
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'SecurePass#123']);
        $category = Category::create(['cat_title' => 'Tas'.$payload]);
        $product = Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Tote'.$payload,
            'product_slug' => 'tote-inline',
            'product_price' => 15000000,
            'product_image' => 'https://example.test/tote.jpg',
        ]);
        $subscriber = NewsletterSubscriber::create(['email' => 'sub'.$payload.'@example.test']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();
        $html = $response->getContent();
        $this->assertStringNotContainsString('</script><script>alert(9)</script>', $html);

        $actions = [
            'kategori' => route('admin.categories.destroy', $category->cat_id),
            'produk' => route('admin.products.destroy', $product->product_id),
            'subscriber' => route('admin.subscribers.destroy', $subscriber->id),
        ];

        foreach ($actions as $label => $action) {
            $forms = $this->queryHtml($html, sprintf('//form[@action="%s"][input[@name="_method" and @value="DELETE"]]', $action));
            $this->assertCount(1, $forms, "Form hapus {$label} tidak ditemukan.");

            // DOM sudah men-decode entitas HTML, jadi yang tersisa murni literal JS.
            $onsubmit = $forms->item(0)->getAttribute('onsubmit');
            $this->assertStringStartsWith("return confirm('Hapus {$label} ", $onsubmit);
            $this->assertStringEndsWith("?')", $onsubmit);

            // Payload hanya boleh muncul dalam bentuk pelarian aman.
            $this->assertStringContainsString('\u0027);alert(1);\/\/', $onsubmit);
            $this->assertStringContainsString('\u0022kutipan\u0022', $onsubmit);
            $this->assertStringContainsString('\u0026', $onsubmit);
            $this->assertStringContainsString('\\\\', $onsubmit);
            $this->assertStringContainsString('\u003C\/script\u003E', $onsubmit);

            foreach (["');alert(1);//", '"kutipan"', '</script>', '<script>'] as $unsafe) {
                $this->assertStringNotContainsString($unsafe, $onsubmit);
            }
        }
    }

    private function queryHtml(string $html, string $expression): \DOMNodeList
    {
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return (new DOMXPath($dom))->query($expression);
    }
}
