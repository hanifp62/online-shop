<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\User;
use App\Models\UserInfoBackup;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('ADMIN_SEED_PASSWORD='.Str::password());
        $this->seed([AdminSeeder::class, CategorySeeder::class]);
    }

    public function test_admin_can_create_new_product(): void
    {
        $admin = Admin::first();
        $category = Category::first();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), [
                'product_title' => 'Bourse Royale',
                'product_cat' => $category->cat_id,
                'product_price' => 18500000,
                'product_image' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=800&q=88',
                'product_color' => 'Noir Ébène',
                'product_badge' => 'Nouveau',
                'product_description' => 'Tas kulit mewah buatan tangan artisan Florence.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'product_title' => 'Bourse Royale',
            'product_slug' => 'bourse-royale',
            'product_price' => 18500000,
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = Admin::first();
        $category = Category::first();

        $product = Product::create([
            'product_title' => 'Item to Delete',
            'product_slug' => 'item-to-delete',
            'product_cat' => $category->cat_id,
            'product_brand' => 1,
            'product_price' => 5000000,
            'product_image' => 'https://example.com/test.jpg',
            'product_alt' => 'Item alt',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.products.destroy', $product->product_id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('products', [
            'product_id' => $product->product_id,
        ]);
    }

    public function test_admin_can_delete_subscriber(): void
    {
        $admin = Admin::first();
        $subscriber = NewsletterSubscriber::create(['email' => 'removeme@zafira.maison']);

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.subscribers.destroy', $subscriber->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('newsletter_subscribers', [
            'id' => $subscriber->id,
        ]);
    }

    public function test_concierge_checkout_with_order_items_stores_and_displays_in_admin(): void
    {
        $admin = Admin::first();
        $this->seed(ProductSeeder::class);

        $cart = [
            ['id' => 'sac-seraphine', 'quantity' => 1],
            ['id' => 'echarpe-eclat', 'quantity' => 2],
        ];

        $response = $this->postJson(route('concierge.store'), [
            'name' => 'Madame Valerie',
            'email' => 'valerie@maison.id',
            'phone' => '+62 811 2233 4455',
            'service' => 'virtual-consultation',
            'notes' => 'Tolong kirimkan invoice ke kantor.',
            'order_items' => json_encode($cart),
        ]);

        $response->assertCreated();

        $concierge = ConciergeRequest::where('email', 'valerie@maison.id')->firstOrFail();
        $this->assertStringContainsString('Sac Séraphine (1x)', $concierge->notes);
        $this->assertStringContainsString('Écharpe Éclat (2x)', $concierge->notes);
        $this->assertStringContainsString('Total: Rp', $concierge->notes);

        $adminView = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();

        $adminView->assertSee('Madame Valerie');
        $adminView->assertSee('Sac Séraphine (1x)');
    }

    public function test_user_profile_update_creates_audit_backup(): void
    {
        $user = User::factory()->create([
            'email' => 'client@zafira.maison',
            'mobile' => '0811111111',
        ]);

        $initialCount = UserInfoBackup::where('user_id', $user->id)->count();

        $this->actingAs($user)
            ->from(route('account.show'))
            ->patch(route('account.update'), [
                'name' => 'Madame Client Updated',
                'first_name' => 'Madame',
                'last_name' => 'Client',
                'mobile' => '0822222222',
                'address1' => 'Rue de Rivoli No. 10',
                'address2' => 'Paris',
            ])->assertRedirect(route('account.show'));

        $this->assertDatabaseHas('user_info_backups', [
            'user_id' => $user->id,
            'mobile' => '0822222222',
            'address1' => 'Rue de Rivoli No. 10',
        ]);
        $this->assertGreaterThan($initialCount, UserInfoBackup::where('user_id', $user->id)->count());
    }
}
