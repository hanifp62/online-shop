<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** FR-FE-COMP-20261023: execute only in the integrator's isolated PHP harness. */
class InertiaAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['kategori', 'produk', 'pesanan', 'user', 'audit', 'concierge', 'subscriber'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true,
            'frontend.pages' => ['Admin/Dashboard'],
            'inertia.testing.page_paths' => [resource_path('js/pages')],
        ]);
    }

    private function login(string $role = Admin::ROLE_ADMIN): Admin
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'Password123!', 'role' => $role]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function snapshot(array $query = []): TestResponse
    {
        return $this->getJson(route('admin.dashboard', $query), ['X-Zafira-Dashboard' => 'snapshot']);
    }

    private function seedRows(): array
    {
        $category = Category::create(['cat_title' => 'Silk needle']);
        Category::create(['cat_title' => 'Other']);
        $user = User::factory()->create([
            'name' => 'Client fallback', 'first_name' => 'Needle', 'last_name' => 'Client',
            'email' => 'needle@example.test', 'mobile' => '12345', 'address1' => 'First line', 'address2' => 'Second line',
            'remember_token' => 'PRIVATE_SENTINEL',
        ]);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA', 'product_title' => 'Needle scarf',
            'product_slug' => 'needle-scarf', 'product_price' => 100, 'stock' => 2, 'is_active' => true,
            'product_description' => 'PRIVATE_SENTINEL',
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'customer_name' => 'Guest needle', 'status' => 'pending', 'total' => 100,
            'idempotency_key' => 'PRIVATE_SENTINEL', 'payload_hash' => str_repeat('a', 64),
            'shipping_address' => ['line1' => 'PRIVATE_SENTINEL'],
        ]);
        $concierge = ConciergeRequest::create([
            'user_id' => $user->id, 'name' => 'Needle request', 'email' => 'needle@example.test',
            'phone' => '12345', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new',
        ]);
        NewsletterSubscriber::create(['email' => 'needle@example.test']);

        return compact('category', 'user', 'product', 'order', 'concierge');
    }

    public function test_native_page_has_whitelisted_seven_region_snapshot_and_private_history(): void
    {
        $this->login();
        $rows = $this->seedRows();
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')
            ->where('dashboardSnapshot.contract', 'admin-dashboard.v1')
            ->where('dashboardSnapshot.tables.kategori.pagination.total', 2)
            ->where('dashboardSnapshot.tables.produk.rows.0.id', $rows['product']->product_id)
            ->where('dashboardSnapshot.tables.pesanan.rows.0.customer', 'Guest needle')
            ->where('dashboardSnapshot.tables.pesanan.rows.0.transitions', ['cancelled'])
            ->where('dashboardSnapshot.tables.user.rows.0.name', 'Needle Client')
            ->where('dashboardSnapshot.tables.concierge.rows.0.transitions', $rows['concierge']->availableTransitions())
            ->missing('latestUsers')->missing('latestOrders')->missing('admin')->missing('user')->missing('session'));
        $props = $response->viewData('page')['props'];
        $this->assertSame(self::TABLES, array_keys($props['dashboardSnapshot']['tables']));
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', json_encode($props));
        $this->assertTrue($response->viewData('page')['encryptHistory']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
    }

    public function test_json_snapshot_is_explicit_and_excludes_shell_session_and_non_display_fields(): void
    {
        $this->login();
        $this->seedRows();
        $response = $this->snapshot()->assertOk()->assertJsonPath('contract', 'admin-dashboard.v1')
            ->assertJsonCount(7, 'tables')->assertJsonPath('tables.pesanan.rows.0.transitions', ['cancelled']);
        $this->assertSame(['contract', 'url', 'tableState', 'filters', 'stats', 'categoryOptions', 'tables'], array_keys($response->json()));
        $this->assertStringNotContainsString('PRIVATE_SENTINEL', $response->getContent());
        $response->assertJsonMissingPath('csrfToken')->assertJsonMissingPath('admin')->assertJsonMissingPath('session')
            ->assertJsonMissingPath('tables.produk.rows.0.product_description')
            ->assertJsonMissingPath('tables.pesanan.rows.0.shipping_address');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson(route('admin.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
    }

    public function test_snapshot_preserves_peer_filters_sorts_and_clamps_only_page_presentation(): void
    {
        $this->login();
        $this->seedRows();
        $query = [
            'kq' => 'Silk', 'ksort' => 'cat_title', 'kdir' => 'desc', 'kpage' => 1000,
            'pq' => 'Needle', 'psort' => 'stock', 'pdir' => 'asc',
            'oq' => 'Guest needle', 'ostatus' => 'pending', 'osort' => 'total', 'odir' => 'asc',
            'uq' => 'needle', 'usort' => 'email', 'udir' => 'asc',
            'bq' => 'needle', 'bsort' => 'email', 'bdir' => 'asc',
            'q' => 'Needle request', 'status' => 'new', 'csort' => 'name', 'cdir' => 'asc',
            'sq' => 'needle', 'ssort' => 'email', 'sdir' => 'asc',
        ];
        $response = $this->snapshot($query)->assertOk()->assertJsonPath('tableState.kpage', 1)
            ->assertJsonPath('tables.kategori.pagination.total', 1)->assertJsonPath('tables.produk.pagination.total', 1)
            ->assertJsonPath('tables.pesanan.pagination.total', 1)->assertJsonPath('tables.user.pagination.total', 1)
            ->assertJsonPath('tables.concierge.pagination.total', 1)->assertJsonPath('tables.subscriber.pagination.total', 1);
        foreach ($query as $key => $value) {
            if ($key !== 'kpage') {
                $response->assertJsonPath('tableState.'.$key, $value)->assertJsonPath('filters.'.$key, $value);
            }
        }
        parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $canonical);
        $this->assertSame('1', $canonical['kpage']);
        $this->assertSame('pending', $canonical['ostatus']);
    }

    public function test_snapshot_reuses_query_validation_and_discards_malformed_pages(): void
    {
        $this->login();
        foreach (['ksort' => 'password', 'udir' => 'DROP', 'ostatus' => 'unknown', 'q' => str_repeat('a', 256), 'opage' => 1001] as $key => $value) {
            $this->snapshot([$key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $response = $this->snapshot(['upage' => 'abc', 'pq' => 'silk'])->assertOk()
            ->assertJsonMissingPath('tableState.upage')->assertJsonPath('tableState.pq', 'silk');
        $this->assertStringNotContainsString('upage=', $response->json('url'));
    }

    public function test_server_transition_options_follow_current_state_without_exposing_manual_paid(): void
    {
        $this->login();
        $rows = $this->seedRows();
        $rows['order']->update(['status' => 'paid']);
        $rows['concierge']->update(['status' => 'confirmed']);
        $this->snapshot()->assertOk()->assertJsonPath('tables.pesanan.rows.0.transitions', ['processing'])
            ->assertJsonPath('tables.concierge.rows.0.transitions', ['confirmed', 'completed', 'cancelled']);
        $rows['order']->update(['status' => 'completed']);
        $rows['concierge']->update(['status' => 'cancelled']);
        $this->snapshot()->assertOk()->assertJsonPath('tables.pesanan.rows.0.transitions', [])
            ->assertJsonPath('tables.concierge.rows.0.transitions', []);
    }

    public function test_blade_override_and_disabled_frontend_keep_original_forms(): void
    {
        $this->login();
        foreach (['query', 'flag', 'allowlist'] as $mode) {
            config(['frontend.enabled' => $mode !== 'flag', 'frontend.pages' => $mode === 'allowlist' ? [] : ['Admin/Dashboard']]);
            $this->get(route('admin.dashboard', $mode === 'query' ? ['frontend' => 'blade'] : []))
                ->assertOk()->assertDontSee('data-react-fallback', false)
                ->assertSee('data-admin-table="kategori"', false)->assertSee('data-admin-table="subscriber"', false)
                ->assertSee('name="current_password"', false);
        }
    }

    public function test_snapshot_authorization_remains_in_existing_admin_guard(): void
    {
        $this->snapshot()->assertUnauthorized();
        $this->login(Admin::ROLE_STAFF);
        $this->snapshot()->assertOk();
        $this->assertNull($this->get(route('admin.dashboard'))->viewData('page')['props']['urls']['operations']);
        auth('admin')->user()->forceFill(['role' => 'unknown'])->save();
        $this->snapshot()->assertForbidden();
    }
}
