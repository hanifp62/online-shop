<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_with_legacy_profile_fields_creates_backup(): void
    {
        $response = $this->post('/register', [
            'name' => 'Punee Red',
            'first_name' => 'punee',
            'last_name' => 'Red',
            'email' => 'pune@gmail.com',
            'password' => 'Saphir#2026',
            'password_confirmation' => 'Saphir#2026',
            'mobile' => '9448121558',
            'address1' => '123456789',
            'address2' => 'sdcjns,djc',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', [
            'email' => 'pune@gmail.com',
            'first_name' => 'punee',
            'mobile' => '9448121558',
        ]);
        // Pengganti trigger `after_user_info_insert` via UserObserver.
        $this->assertDatabaseHas('user_info_backups', [
            'email' => 'pune@gmail.com',
            'first_name' => 'punee',
        ]);
    }

    public function test_admin_seeder_preserves_dump_data(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('admins', ['email' => 'admin@gmail.com']);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'admin@gmail.com']);
    }

    public function test_category_get_by_id_matches_getcat_procedure(): void
    {
        $category = Category::create(['cat_title' => 'Busana Wanita']);

        $this->assertEquals(
            $category->cat_id,
            Category::getById($category->cat_id)?->cat_id
        );
        $this->assertNull(Category::getById(999999));
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create();

        $this->get('/akun')->assertRedirect('/login');

        $this->actingAs($user)->get('/akun')->assertOk()->assertSee('Bonjour');

        $this->actingAs($user)->patch('/akun', [
            'name' => 'Punee Red Jr',
            'first_name' => 'punee',
            'last_name' => 'Red',
            'mobile' => '081234567890',
            'address1' => 'Jl. Safir No. 1',
            'address2' => 'Jakarta',
        ])->assertRedirect('/akun');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'mobile' => '081234567890',
            'address1' => 'Jl. Safir No. 1',
        ]);
    }

    public function test_checkout_form_prefills_logged_in_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'mobile' => '9448121558',
        ]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('value="Punee Red"', false)
            ->assertSee('value="pune@gmail.com"', false)
            ->assertSee('value="9448121558"', false);
    }

    public function test_admin_can_login_and_view_dashboard(): void
    {
        $this->seed(AdminSeeder::class);

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Masuk admin');

        $this->post('/admin/login', [
            'email' => 'admin@gmail.com',
            'password' => '12345678',
        ])->assertRedirect('/admin');

        $this->get('/admin')->assertOk()->assertSee('Dashboard Maison');
    }

    public function test_admin_can_update_concierge_status(): void
    {
        $this->seed(AdminSeeder::class);
        $concierge = ConciergeRequest::create([
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'phone' => '9448121558',
            'service' => 'virtual-consultation',
            'status' => 'new',
        ]);

        $this->actingAs(Admin::first(), 'admin')
            ->patch(route('admin.concierge.update', $concierge), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertDatabaseHas('concierge_requests', ['id' => $concierge->id, 'status' => 'confirmed']);
    }

    public function test_admin_can_change_password(): void
    {
        $this->seed(AdminSeeder::class);
        $admin = Admin::first();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.password.update'), [
                'current_password' => '12345678',
                'password' => 'Saphir#2026',
                'password_confirmation' => 'Saphir#2026',
            ])->assertRedirect();

        $this->post('/admin/logout')->assertRedirect();
        $this->post('/admin/login', [
            'email' => 'admin@gmail.com',
            'password' => 'Saphir#2026',
        ])->assertRedirect('/admin');
    }

    public function test_account_page_shows_concierge_history(): void
    {
        $user = User::factory()->create(['email' => 'pune@gmail.com']);
        ConciergeRequest::create([
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'phone' => '9448121558',
            'service' => 'gifting',
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('Riwayat Concierge')
            ->assertSee('gifting')
            ->assertSee('confirmed');
    }

    public function test_concierge_links_to_logged_in_user(): void
    {
        $user = User::factory()->create(['email' => 'pune@gmail.com']);

        $this->actingAs($user)->post('/concierge', [
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'phone' => '9448121558',
            'service' => 'virtual-consultation',
        ])->assertRedirect();

        $this->assertDatabaseHas('concierge_requests', [
            'email' => 'pune@gmail.com',
            'user_id' => $user->id,
        ]);
    }

    public function test_account_history_prefers_user_id_link(): void
    {
        $user = User::factory()->create(['email' => 'pune@gmail.com']);
        ConciergeRequest::create([
            'user_id' => $user->id,
            'name' => 'Punee Red',
            'email' => 'lain@example.com',
            'phone' => '9448121558',
            'service' => 'atelier',
            'status' => 'new',
        ]);

        $this->actingAs($user)->get('/akun')
            ->assertOk()
            ->assertSee('atelier');
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->seed(AdminSeeder::class);
        $admin = Admin::first();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.categories.store'), ['cat_title' => 'Kategori Uji'])
            ->assertRedirect();
        $this->assertDatabaseHas('categories', ['cat_title' => 'Kategori Uji']);

        $category = Category::where('cat_title', 'Kategori Uji')->firstOrFail();
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.categories.update', $category->cat_id), ['cat_title' => 'Kategori Revisi'])
            ->assertRedirect();
        $this->assertDatabaseHas('categories', ['cat_id' => $category->cat_id, 'cat_title' => 'Kategori Revisi']);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Kategori Revisi');

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.categories.destroy', $category->cat_id))
            ->assertRedirect();
        $this->assertDatabaseMissing('categories', ['cat_id' => $category->cat_id]);
    }

    public function test_admin_cannot_delete_category_in_use(): void
    {
        $this->seed(AdminSeeder::class);
        $category = Category::create(['cat_title' => 'Dipakai']);
        Product::create([
            'product_cat' => $category->cat_id,
            'product_brand' => 'ZAFIRA',
            'product_title' => 'Produk Uji',
            'product_slug' => 'produk-uji-'.$category->cat_id,
            'product_price' => 100000,
        ]);

        $this->actingAs(Admin::first(), 'admin')
            ->delete(route('admin.categories.destroy', $category->cat_id))
            ->assertSessionHasErrors('cat_title');

        $this->assertDatabaseHas('categories', ['cat_id' => $category->cat_id]);
    }

    public function test_home_renders_dynamic_category_pills(): void
    {
        Category::create(['cat_title' => 'Kategori Dinamis']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-filter="Kategori Dinamis"', false);
    }

    public function test_category_mutation_flushes_storefront_cache(): void
    {
        $this->seed(AdminSeeder::class);

        $this->get('/')->assertOk()->assertSee('Semua');

        $this->actingAs(Admin::first(), 'admin')
            ->post(route('admin.categories.store'), ['cat_title' => 'Kategori Cache'])
            ->assertRedirect();

        $this->get('/')->assertOk()->assertSee('data-filter="Kategori Cache"', false);
    }

    public function test_sitemap_only_lists_public_urls(): void
    {
        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('/login', $content);
        $this->assertStringNotContainsString('/register', $content);
    }

    public function test_admin_dashboard_filters_concierge(): void
    {
        $this->seed(AdminSeeder::class);
        ConciergeRequest::create([
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'phone' => '9448121558',
            'service' => 'gifting',
            'status' => 'confirmed',
        ]);
        ConciergeRequest::create([
            'name' => 'Tamu Baru',
            'email' => 'tamu@example.com',
            'phone' => '0800000000',
            'service' => 'atelier',
            'status' => 'new',
        ]);

        $this->actingAs(Admin::first(), 'admin')
            ->get('/admin?status=confirmed')
            ->assertOk()
            ->assertSee('pune@gmail.com')
            ->assertDontSee('tamu@example.com');

        $this->actingAs(Admin::first(), 'admin')
            ->get('/admin?q=tamu@example.com')
            ->assertOk()
            ->assertSee('tamu@example.com')
            ->assertDontSee('pune@gmail.com');
    }

    public function test_checkout_email_is_normalized_to_lowercase(): void
    {
        $this->postJson('/concierge', [
            'name' => 'Punee Red',
            'email' => 'PUNE@GMAIL.COM',
            'phone' => '9448121558',
            'service' => 'virtual-consultation',
        ])->assertCreated();

        $this->assertDatabaseHas('concierge_requests', ['email' => 'pune@gmail.com']);
    }

    public function test_user_logout_keeps_admin_session(): void
    {
        $this->seed(AdminSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->actingAs(Admin::first(), 'admin');
        $this->post('/logout')->assertRedirect('/');
        $this->assertAuthenticated('admin');
        $this->assertGuest('web');

        $this->get('/admin')->assertOk();
    }

    public function test_admin_dashboard_shows_user_compatible_panels(): void
    {
        $this->seed(AdminSeeder::class);
        $user = User::factory()->create(['email' => 'pune@gmail.com']);
        ConciergeRequest::create([
            'user_id' => $user->id,
            'name' => 'Punee Red',
            'email' => 'pune@gmail.com',
            'phone' => '9448121558',
            'service' => 'gifting',
            'status' => 'confirmed',
        ]);

        $this->actingAs(Admin::first(), 'admin')->get('/admin')
            ->assertOk()
            ->assertSee('pill filter')
            ->assertSee('riwayat /akun')
            ->assertSee('pune@gmail.com')
            ->assertSee('La Lettre');
    }

    public function test_admin_can_move_product_category_visible_to_user(): void
    {
        $this->seed([CategorySeeder::class, ProductSeeder::class, AdminSeeder::class]);
        $product = Product::where('product_slug', 'sac-seraphine')->firstOrFail();
        $target = Category::where('cat_title', 'Aksesori')->firstOrFail();

        $this->actingAs(Admin::first(), 'admin')
            ->patch(route('admin.products.update', $product->product_id), ['product_cat' => $target->cat_id])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'product_id' => $product->product_id,
            'product_cat' => $target->cat_id,
        ]);

        $this->get(route('products.show', $product->product_slug))
            ->assertOk()
            ->assertSee('Aksesori');
    }

    public function test_admin_can_search_users(): void
    {
        $this->seed(AdminSeeder::class);
        User::factory()->create(['email' => 'pune@gmail.com']);
        User::factory()->create(['email' => 'tamu@example.com']);

        $this->actingAs(Admin::first(), 'admin')
            ->get('/admin?uq=pune@gmail.com')
            ->assertOk()
            ->assertSee('pune@gmail.com');
    }
}
