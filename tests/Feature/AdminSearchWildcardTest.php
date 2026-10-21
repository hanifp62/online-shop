<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInfoBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSearchWildcardTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->withoutVite();
        $this->actingAs(Admin::create([
            'name' => 'Operator',
            'email' => 'ops@example.test',
            'password' => 'Password123!',
        ]), 'admin');
    }

    public function test_numeric_order_search_matches_exact_id_without_like_predicate(): void
    {
        $this->login();

        $matchingOrder = Order::create([
            'customer_name' => 'Numeric Match',
            'customer_email' => 'numeric-match@example.test',
            'total' => 500,
            'idempotency_key' => 'numeric-match-1',
            'payload_hash' => str_repeat('a', 64),
        ]);
        Order::create([
            'customer_name' => 'Numeric Other',
            'customer_email' => 'numeric-other@example.test',
            'total' => 600,
            'idempotency_key' => 'numeric-other-1',
            'payload_hash' => str_repeat('b', 64),
        ])->forceFill(['id' => $matchingOrder->id + 10])->save();

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = strtolower($query->sql);
        });

        $this->get(route('admin.dashboard', ['oq' => (string) $matchingOrder->id]))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1
                && $rows->first()->id === $matchingOrder->id);

        $orderSearchSql = implode(' ', array_filter($sql, fn ($query) => str_contains($query, 'from "orders"')));
        $this->assertDoesNotMatchRegularExpression('/\b(?:orders\.)?"?id"?\s+like\b/', $orderSearchSql);
        $this->assertStringContainsString('"orders"."id" = ?', $orderSearchSql);
    }

    public function test_non_numeric_order_search_uses_text_columns_without_id_like_predicate(): void
    {
        $this->login();

        Order::create([
            'customer_name' => 'Text Match',
            'customer_email' => 'text-match@example.test',
            'total' => 700,
            'idempotency_key' => 'text-match-1',
            'payload_hash' => str_repeat('c', 64),
        ]);

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = strtolower($query->sql);
        });

        $this->get(route('admin.dashboard', ['oq' => 'Text Match']))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1);

        $orderSearchSql = implode(' ', array_filter($sql, fn ($query) => str_contains($query, 'from "orders"')));
        $this->assertDoesNotMatchRegularExpression('/\b(?:orders\.)?"?id"?\s+like\b/', $orderSearchSql);
        $this->assertStringNotContainsString('"orders"."id" = ?', $orderSearchSql);
    }

    public function test_overlong_digit_order_search_does_not_overflow_or_touch_id(): void
    {
        $this->login();

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = strtolower($query->sql);
        });

        $this->get(route('admin.dashboard', ['oq' => str_repeat('9', 30)]))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 0);

        $orderSearchSql = implode(' ', array_filter($sql, fn ($query) => str_contains($query, 'from "orders"')));
        $this->assertDoesNotMatchRegularExpression('/\b(?:orders\.)?"?id"?\s+like\b/', $orderSearchSql);
        $this->assertStringNotContainsString('"id" =', $orderSearchSql);
    }

    public function test_wildcard_percent_and_underscore_do_not_match_all_records(): void
    {
        $this->login();

        $category = Category::create(['cat_title' => 'Plain Category']);
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'Maison',
            'product_title' => 'Plain Product',
            'product_slug' => 'plain-product',
            'product_price' => 100,
            'stock' => 10,
        ]);
        $user = User::factory()->create([
            'name' => 'Plain User',
            'first_name' => 'Plain',
            'last_name' => 'User',
            'email' => 'plain@example.test',
            'mobile' => '08123456789',
        ]);
        Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Plain Order Customer',
            'customer_email' => 'plain-order@example.test',
            'total' => 500,
            'idempotency_key' => 'plain-order-1',
            'payload_hash' => str_repeat('b', 64),
        ]);
        UserInfoBackup::create([
            'user_id' => $user->id,
            'first_name' => 'PlainBackup',
            'last_name' => 'User',
            'email' => 'plain-backup@example.test',
        ]);
        ConciergeRequest::create([
            'name' => 'Plain Concierge',
            'email' => 'plain-concierge@example.test',
            'phone' => '0812999999',
            'service' => 'consultation',
            'preferred_date' => now()->addDay(),
            'status' => 'new',
        ]);
        NewsletterSubscriber::create([
            'email' => 'plain-sub@example.test',
        ]);

        // Single % or _ must not match ordinary records that lack % or _
        $response = $this->get(route('admin.dashboard', [
            'kq' => '%',
            'pq' => '_',
            'oq' => '%',
            'uq' => '_',
            'bq' => '%',
            'q' => '_',
            'sq' => '%',
        ]))->assertOk();

        $response->assertViewHas('categories', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestProducts', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestBackups', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestConcierge', fn ($rows) => $rows->total() === 0);
        $response->assertViewHas('latestSubscribers', fn ($rows) => $rows->total() === 0);
    }

    public function test_literal_percent_underscore_and_backslash_are_matched_correctly(): void
    {
        $this->login();

        $catWithPercent = Category::create(['cat_title' => 'Sale 50% Off']);
        $catOther = Category::create(['cat_title' => 'Regular Item']);

        $prodWithPercent = Product::create([
            'product_cat' => $catWithPercent->cat_id,
            'product_brand' => 'Maison',
            'product_title' => 'Special 50% Silk',
            'product_slug' => 'special-50-percent-silk',
            'product_price' => 200,
            'stock' => 5,
        ]);
        $prodWithBackslash = Product::create([
            'product_cat' => $catOther->cat_id,
            'product_brand' => 'Maison',
            'product_title' => 'Item\\Detail',
            'product_slug' => 'item-backslash-detail',
            'product_price' => 300,
            'stock' => 2,
        ]);
        Product::create([
            'product_cat' => $catOther->cat_id,
            'product_brand' => 'Maison',
            'product_title' => 'Item-Regular',
            'product_slug' => 'item-regular',
            'product_price' => 150,
            'stock' => 8,
        ]);

        $orderWithPercent = Order::create([
            'customer_name' => 'Guest 50% Buyer',
            'customer_email' => 'buyer_50%@example.test',
            'total' => 1000,
            'idempotency_key' => 'order-pct-1',
            'payload_hash' => str_repeat('c', 64),
        ]);
        $orderWithBackslash = Order::create([
            'customer_name' => 'Guest\\Name',
            'customer_email' => 'guest\\slash@example.test',
            'total' => 1100,
            'idempotency_key' => 'order-slash-1',
            'payload_hash' => str_repeat('d', 64),
        ]);

        // Literal underscore: '_50' must hit only the email containing '_50', not any char + '50'
        $this->get(route('admin.dashboard', ['oq' => '_50']))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $orderWithPercent->id);

        // Literal search for 50%
        $this->get(route('admin.dashboard', ['pq' => '50%']))
            ->assertOk()
            ->assertViewHas('latestProducts', fn ($rows) => $rows->total() === 1 && $rows->first()->product_id === $prodWithPercent->product_id);

        $this->get(route('admin.dashboard', ['kq' => '50%']))
            ->assertOk()
            ->assertViewHas('categories', fn ($rows) => $rows->total() === 1 && $rows->first()->cat_id === $catWithPercent->cat_id);

        $this->get(route('admin.dashboard', ['oq' => '50%']))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $orderWithPercent->id);

        // Literal search for backslash: item\detail
        $this->get(route('admin.dashboard', ['pq' => 'Item\\Detail']))
            ->assertOk()
            ->assertViewHas('latestProducts', fn ($rows) => $rows->total() === 1 && $rows->first()->product_id === $prodWithBackslash->product_id);

        // Single backslash should only match rows containing backslash
        $this->get(route('admin.dashboard', ['pq' => '\\']))
            ->assertOk()
            ->assertViewHas('latestProducts', fn ($rows) => $rows->total() === 1 && $rows->first()->product_id === $prodWithBackslash->product_id);

        $this->get(route('admin.dashboard', ['oq' => '\\']))
            ->assertOk()
            ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $orderWithBackslash->id);
    }
}
