<?php

namespace Tests\Feature;

use App\Enums\AuthEventName;
use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInfoBackup;
use App\Services\AuthEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListContextTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
    }

    private function concierge(int $count, string $status, string $name): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ConciergeRequest::create([
                'name' => "{$name} {$i}", 'email' => "{$name}{$i}@example.test", 'phone' => '0811',
                'service' => 'Fitting', 'status' => $status,
            ]);
        }
    }

    public function test_auth_events_show_result_count_with_and_without_filters(): void
    {
        $admin = $this->admin();
        $recorder = app(AuthEventRecorder::class);
        for ($i = 0; $i < 3; $i++) {
            $recorder->record(AuthEventName::LOGIN_FAILURE, 'failure', 'admin', $admin->id, '127.0.0.1');
        }
        $recorder->record(AuthEventName::LOGIN_FAILURE, 'success', 'admin', $admin->id, '127.0.0.1');

        $this->actingAs($admin, 'admin')->get(route('admin.auth-events.index'))
            ->assertOk()->assertSee('<p data-result-count>4 hasil</p>', false);

        $this->get(route('admin.auth-events.index', ['outcome' => 'failure']))
            ->assertOk()->assertSee('<p data-result-count>3 hasil</p>', false);
    }

    public function test_dashboard_concierge_filters_show_chips_count_and_reset(): void
    {
        $this->concierge(25, 'new', 'Vivienne');
        $this->concierge(2, 'contacted', 'Celeste');
        $this->concierge(2, 'new', 'Anna');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['status' => 'new', 'q' => 'Vivienne']))
            ->assertOk()
            ->assertViewHas('latestConcierge', fn ($rows) => $rows->count() === 20
                && $rows->every(fn ($row) => $row->status === 'new' && str_starts_with($row->name, 'Vivienne ')))
            ->assertSee('data-active-filter="status: new"', false)
            ->assertSee('data-active-filter="q: Vivienne"', false)
            ->assertSeeText('Menampilkan 20 dari 25 hasil')
            ->assertSee('href="'.route('admin.dashboard').'#panel-concierge"', false);
    }

    public function test_dashboard_user_filter_shows_chip_count_and_escapes_value(): void
    {
        User::factory()->count(12)->create(['name' => 'Madame Vivienne']);
        User::factory()->create(['name' => 'Other']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['uq' => 'Vivienne']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->count() === 10
                && $rows->every(fn ($row) => $row->name === 'Madame Vivienne'))
            ->assertSee('data-active-filter="uq: Vivienne"', false)
            ->assertSeeText('Menampilkan 10 dari 12 hasil')
            ->assertSee('href="'.route('admin.dashboard').'#panel-user"', false);

        $this->get(route('admin.dashboard', ['uq' => '<b>x</b>']))
            ->assertOk()
            ->assertDontSee('<b>x</b>', false)
            ->assertSee('data-active-filter="uq: &lt;b&gt;x&lt;/b&gt;"', false)
            ->assertSeeText('Menampilkan 0 dari 0 hasil');
    }

    public function test_user_search_treats_wildcards_as_literals(): void
    {
        User::factory()->create(['name' => 'a_b', 'email' => 'ab@example.test']);
        User::factory()->create(['name' => 'axb', 'email' => 'axb@example.test']);
        User::factory()->create(['name' => '100% Cotton', 'email' => 'cotton@example.test']);
        User::factory()->create(['name' => '1000 Cotton', 'email' => 'cotton2@example.test']);
        User::factory()->create(['name' => 'A\\B', 'email' => 'backslash@example.test']);
        User::factory()->create(['name' => 'AB', 'email' => 'plain@example.test']);
        User::factory()->create(['name' => 'A!B', 'email' => 'bang@example.test']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['uq' => 'a_b']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'a_b');

        $this->get(route('admin.dashboard', ['uq' => '100%']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1 && $rows->first()->name === '100% Cotton');

        $this->get(route('admin.dashboard', ['uq' => 'A\\B']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'A\\B');

        $this->get(route('admin.dashboard', ['uq' => 'A!B']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'A!B');
    }

    public function test_order_search_treats_wildcards_as_literals(): void
    {
        $user = User::factory()->create();
        $names = ['a_b', 'axb', '100% Cotton', '1000 Cotton', 'A!B', 'AB'];
        foreach ($names as $i => $name) {
            Order::create([
                'user_id' => $user->id, 'total' => 100, 'customer_name' => $name,
                'customer_email' => "order{$i}@example.test",
                'idempotency_key' => 'order-'.$i, 'payload_hash' => str_repeat('a', 64),
            ]);
        }

        $expectations = ['a_b' => 'a_b', '100%' => '100% Cotton', 'A!B' => 'A!B'];
        $this->actingAs($this->admin(), 'admin');
        foreach ($expectations as $term => $expected) {
            $this->get(route('admin.dashboard', ['oq' => $term]))
                ->assertOk()
                ->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1
                    && $rows->first()->customer_name === $expected);
        }
    }

    public function test_concierge_search_treats_wildcards_as_literals(): void
    {
        $names = ['a_b', 'axb', '100% Cotton', '1000 Cotton', 'A!B', 'AB'];
        foreach ($names as $i => $name) {
            ConciergeRequest::create([
                'name' => $name, 'email' => "concierge{$i}@example.test", 'phone' => '0811',
                'service' => 'Fitting', 'status' => 'new',
            ]);
        }

        $expectations = ['a_b' => 'a_b', '100%' => '100% Cotton', 'A!B' => 'A!B'];
        $this->actingAs($this->admin(), 'admin');
        foreach ($expectations as $term => $expected) {
            $this->get(route('admin.dashboard', ['q' => $term]))
                ->assertOk()
                ->assertViewHas('latestConcierge', fn ($rows) => $rows->total() === 1
                    && $rows->first()->name === $expected);
        }
    }

    private const LITERAL_NAMES = ['a_b', 'axb', '100% Cotton', '1000 Cotton', 'A!B', 'AB'];

    private const LITERAL_EXPECTATIONS = ['a_b' => 'a_b', '100%' => '100% Cotton', 'A!B' => 'A!B'];

    // Each term must hit exactly its literal row; 'axb', '1000 Cotton' and 'AB' only match if '_', '%' or '!' act as wildcards/escapes.
    private function assertLiteralSearch(string $key, string $view, callable $field): void
    {
        $this->actingAs($this->admin(), 'admin');
        foreach (self::LITERAL_EXPECTATIONS as $term => $expected) {
            $this->get(route('admin.dashboard', [$key => $term]))
                ->assertOk()
                ->assertViewHas($view, fn ($rows) => $rows->total() === 1
                    && $field($rows->first()) === $expected);
        }
    }

    public function test_category_search_treats_wildcards_as_literals(): void
    {
        foreach (self::LITERAL_NAMES as $name) {
            Category::create(['cat_title' => $name]);
        }

        $this->assertLiteralSearch('kq', 'categories', fn ($row) => $row->cat_title);
    }

    public function test_product_search_treats_wildcards_as_literals(): void
    {
        $category = Category::create(['cat_title' => 'Maison']);
        foreach (self::LITERAL_NAMES as $i => $name) {
            Product::create([
                'product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => $name,
                'product_slug' => 'literal-'.$i, 'product_price' => 1, 'stock' => 1,
            ]);
        }

        $this->assertLiteralSearch('pq', 'latestProducts', fn ($row) => $row->product_title);
    }

    public function test_backup_search_treats_wildcards_as_literals(): void
    {
        foreach (self::LITERAL_NAMES as $i => $name) {
            UserInfoBackup::create([
                'user_id' => User::factory()->create(['name' => 'Backup Owner'])->id,
                'first_name' => $name, 'last_name' => '', 'email' => "backup{$i}@example.test",
                'mobile' => '0811', 'address1' => 'Jl. Satu', 'address2' => '',
            ]);
        }

        $this->assertLiteralSearch('bq', 'latestBackups', fn ($row) => $row->first_name);
    }

    public function test_subscriber_search_treats_wildcards_as_literals(): void
    {
        foreach (self::LITERAL_NAMES as $name) {
            NewsletterSubscriber::create(['email' => $name.'@example.test']);
        }

        $this->assertLiteralSearch('sq', 'latestSubscribers', fn ($row) => str_replace('@example.test', '', $row->email));
    }

    public function test_concierge_sorts_by_allowlisted_column_and_marks_aria_sort(): void
    {
        foreach (['Celeste', 'Anna', 'Berta'] as $name) {
            $this->concierge(1, 'new', $name);
        }

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['csort' => 'name', 'cdir' => 'asc']))
            ->assertOk()
            ->assertViewHas('latestConcierge', fn ($rows) => $rows->pluck('name')->all() === ['Anna 1', 'Berta 1', 'Celeste 1'])
            ->assertSee('aria-sort="ascending"', false);

        $this->get(route('admin.dashboard', ['csort' => 'name', 'cdir' => 'desc']))
            ->assertViewHas('latestConcierge', fn ($rows) => $rows->first()->name === 'Celeste 1')
            ->assertSee('aria-sort="descending"', false);
    }

    public function test_sort_input_outside_allowlist_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['csort' => 'id; drop table users']))
            ->assertSessionHasErrors('csort');
        $this->get(route('admin.dashboard', ['usort' => 'password']))->assertSessionHasErrors('usort');
        $this->get(route('admin.dashboard', ['cdir' => 'sideways']))->assertSessionHasErrors('cdir');
    }

    public function test_concierge_paginates_and_keeps_filters_sort_and_other_table_state(): void
    {
        $this->concierge(25, 'new', 'Vivienne');
        $this->concierge(3, 'contacted', 'Celeste');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['status' => 'new', 'csort' => 'name', 'cdir' => 'asc', 'cpage' => 2, 'uq' => 'x']))
            ->assertOk()
            ->assertViewHas('latestConcierge', fn ($rows) => $rows->count() === 5 && $rows->total() === 25)
            ->assertSeeText('Menampilkan 5 dari 25 hasil')
            ->assertSee('data-table-link', false)
            ->assertSee('cpage=1', false)
            ->assertSee('status=new', false)
            ->assertSee('csort=name', false)
            ->assertSee('uq=x', false);
    }

    public function test_users_sort_by_allowlisted_name_in_both_directions(): void
    {
        foreach (['Celeste', 'Anna', 'Berta'] as $name) {
            User::factory()->create(['name' => $name, 'first_name' => null, 'last_name' => null]);
        }

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['usort' => 'name', 'udir' => 'asc']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->pluck('name')->all() === ['Anna', 'Berta', 'Celeste']);

        $this->get(route('admin.dashboard', ['usort' => 'name', 'udir' => 'desc']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->pluck('name')->all() === ['Celeste', 'Berta', 'Anna']);
    }

    public function test_users_sort_name_matches_displayed_profile_name(): void
    {
        User::factory()->create(['name' => 'Aaron Raw', 'first_name' => 'Zoe', 'last_name' => 'Adams']);
        User::factory()->create(['name' => 'Zed Raw', 'first_name' => 'Anna', 'last_name' => 'Baker']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['usort' => 'name', 'udir' => 'asc']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->pluck('first_name')->all() === ['Anna', 'Zoe']);

        $this->get(route('admin.dashboard', ['usort' => 'name', 'udir' => 'desc']))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->pluck('first_name')->all() === ['Zoe', 'Anna']);
    }

    public function test_users_sort_and_paginate_with_independent_page_name(): void
    {
        User::factory()->count(12)->create();
        User::factory()->create(['name' => 'Zed Last', 'first_name' => null, 'last_name' => null, 'created_at' => now()->subYear()]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['usort' => 'name', 'udir' => 'desc', 'upage' => 2, 'cpage' => 1]))
            ->assertOk()
            ->assertViewHas('latestUsers', fn ($rows) => $rows->count() === 3 && $rows->total() === 13)
            ->assertSee('upage=1', false);
    }

    public function test_tables_expose_progressive_enhancement_hooks(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-table="concierge"', false)
            ->assertSee('data-admin-table="user"', false)
            ->assertSee('data-admin-table-form="concierge"', false)
            ->assertSee('data-admin-table-form="user"', false)
            ->assertSee('data-table-error', false);
    }

    public function test_dashboard_without_filters_has_no_chips_and_shows_panel_counts(): void
    {
        Category::create(['cat_title' => 'Couture']);
        $this->concierge(3, 'new', 'Anna');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard', ['uq' => '', 'status' => '', 'q' => '']))
            ->assertOk()
            ->assertDontSee('data-active-filter=', false)
            ->assertDontSeeText('Filter aktif:')
            ->assertSee('data-panel-count="kategori"', false)
            ->assertSee('data-panel-count="produk"', false)
            ->assertSee('data-panel-count="pesanan"', false)
            ->assertSee('data-panel-count="audit"', false)
            ->assertSee('data-panel-count="subscriber"', false)
            ->assertSeeText('Menampilkan 3 dari 3 hasil');
    }
}
