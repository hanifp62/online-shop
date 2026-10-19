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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTablesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public static function tables(): array
    {
        return [
            'kategori' => ['categories', 'kpage', 'cat_id', 10],
            'produk' => ['latestProducts', 'ppage', 'product_id', 10],
            'pesanan' => ['latestOrders', 'opage', 'id', 15],
            'user' => ['latestUsers', 'upage', 'id', 10],
            'audit' => ['latestBackups', 'bpage', 'id', 10],
            'concierge' => ['latestConcierge', 'cpage', 'id', 20],
            'subscriber' => ['latestSubscribers', 'spage', 'id', 10],
        ];
    }

    #[DataProvider('tables')]
    public function test_each_table_paginates_rows_and_preserves_other_table_context(string $view, string $pageKey, string $idKey, int $perPage): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
        $category = Category::create(['cat_title' => 'Base']);
        $user = User::factory()->create(['email' => 'preserved-base@example.test', 'created_at' => '2026-01-01 00:00:00']);
        UserInfoBackup::query()->delete();
        $siblingView = $view === 'categories' ? 'latestProducts' : 'categories';
        $siblingKey = $view === 'categories' ? 'ppage' : 'kpage';
        $siblingIdKey = $view === 'categories' ? 'product_id' : 'cat_id';
        $siblingIds = [];
        for ($i = 0; $i < 11; $i++) {
            $sibling = $view === 'categories'
                ? Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Sibling', 'product_slug' => 'sibling-'.$i, 'product_price' => 100, 'stock' => 1])
                : Category::create(['cat_title' => 'Sibling '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            $siblingIds[] = $sibling->getKey();
        }
        $ids = [];
        for ($i = 0; $i <= $perPage; $i++) {
            $row = match ($view) {
                'categories' => Category::create(['cat_title' => 'Category '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]),
                'latestProducts' => Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Piece', 'product_slug' => 'piece-'.$i, 'product_price' => 100, 'stock' => 1]),
                'latestOrders' => Order::create(['user_id' => $user->id, 'total' => 100, 'idempotency_key' => 'order-'.$i, 'payload_hash' => str_repeat('a', 64)]),
                'latestUsers' => User::factory()->create(['email' => 'preserved-'.$i.'@example.test']),
                'latestBackups' => UserInfoBackup::create(['user_id' => $user->id, 'email' => 'backup'.$i.'@example.test']),
                'latestConcierge' => ConciergeRequest::create(['name' => 'Guest '.$i, 'email' => 'concierge'.$i.'@example.test', 'phone' => '08123456789', 'service' => 'consultation', 'preferred_date' => '2026-01-10', 'preferred_time' => '10:00', 'status' => 'new']),
                'latestSubscribers' => NewsletterSubscriber::create(['email' => 'subscriber'.$i.'@example.test']),
            };
            $row->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
            $ids[] = $row->getKey();
        }

        // Kategori diurut cat_id asc dan diawali kategori Base; tabel lain diurut id desc.
        $ordered = match ($view) {
            'categories' => array_merge([$category->getKey()], $ids),
            'latestUsers' => array_merge(array_reverse($ids), [$user->getKey()]),
            default => array_reverse($ids),
        };
        $expectedFirst = array_slice($ordered, 0, $perPage);
        $expectedSecond = array_slice($ordered, $perPage);
        // Sibling dipaku di halaman 2: kategori asc (Base + 11 → [9], [10]); produk desc (11 → [0]).
        $siblingSecond = $view === 'categories' ? [$siblingIds[0]] : array_slice($siblingIds, 9);

        $context = ['uq' => 'preserved', 'status' => 'new', $siblingKey => 2]
            + ($pageKey === 'cpage' ? ['upage' => 3] : ['cpage' => 3]);
        $this->actingAs($admin, 'admin');
        $first = $this->get(route('admin.dashboard', $context + [$pageKey => 1]))->assertOk();
        $second = $this->get(route('admin.dashboard', $context + [$pageKey => 2]))->assertOk();

        $first->assertViewHas($view);
        $firstRows = $first->viewData($view);
        $firstIds = $firstRows->pluck($idKey)->all();

        $this->assertSame(1, $firstRows->currentPage(), 'Paginator current page is one.');
        $this->assertSame($perPage, $firstRows->count(), 'Page one row count equals page size.');
        $this->assertSame($expectedFirst, $firstIds, 'Page one contains expected IDs in expected order.');
        $this->assertSame([], array_intersect($firstIds, $expectedSecond), 'Page one contains no IDs from page two.');

        $second->assertViewHas($view);
        $secondRows = $second->viewData($view);
        $secondIds = $secondRows->pluck($idKey)->all();
        $query = [];
        parse_str(parse_url($secondRows->url(1), PHP_URL_QUERY), $query);

        $this->assertSame($expectedSecond, $secondIds, 'Page two contains expected IDs in expected order.');
        $this->assertSame([], array_intersect($secondIds, $expectedFirst), 'Page two contains no IDs from page one.');
        $this->assertSame($perPage + count($expectedSecond), $secondRows->total(), 'Paginator total is correct.');
        $this->assertSame(2, $secondRows->currentPage(), 'Paginator current page is two.');
        $this->assertSame($perPage, $secondRows->perPage(), 'Paginator page size is correct.');
        $this->assertSame('preserved', $query['uq'], 'User search query is preserved.');
        $this->assertSame('3', $query[$pageKey === 'cpage' ? 'upage' : 'cpage'], 'Other table page is preserved.');
        $this->assertSame('new', $query['status'], 'Status filter is preserved.');
        $this->assertSame('2', $query[$siblingKey], 'Sibling table page is preserved.');
        $this->assertSame('1', $query[$secondRows->getPageName()], 'Generated link targets page one.');
        $panelFragments = [
            'categories' => 'panel-kategori',
            'latestProducts' => 'panel-produk',
            'latestOrders' => 'panel-pesanan',
            'latestUsers' => 'panel-user',
            'latestBackups' => 'panel-audit',
            'latestConcierge' => 'panel-concierge',
            'latestSubscribers' => 'panel-subscriber',
        ];
        $this->assertSame($panelFragments[$view], parse_url($secondRows->url(1), PHP_URL_FRAGMENT), 'Generated link targets correct panel.');

        // Paginator sibling tetap di halaman 2 pada kedua request dan membawa halaman target di URL-nya.
        foreach ([[$first, '1'], [$second, '2']] as [$response, $targetPage]) {
            $response->assertViewHas($siblingView);
            $siblingRows = $response->viewData($siblingView);
            $siblingPage = $siblingRows->pluck($siblingIdKey)->all();
            $siblingQuery = [];
            parse_str(parse_url($siblingRows->url(1), PHP_URL_QUERY), $siblingQuery);

            $this->assertSame($siblingKey, $siblingRows->getPageName(), "Sibling page name (target page {$targetPage}).");
            $this->assertSame(2, $siblingRows->currentPage(), "Sibling current page is two (target page {$targetPage}).");
            $this->assertSame($siblingSecond, $siblingPage, "Sibling page contains expected IDs in order (target page {$targetPage}).");
            $this->assertSame([], array_intersect($siblingPage, array_diff($siblingIds, $siblingSecond)), "Sibling page has no foreign IDs (target page {$targetPage}).");
            $this->assertSame($targetPage, $siblingQuery[$pageKey], "Sibling link preserves target page (target page {$targetPage}).");
            $this->assertSame('1', $siblingQuery[$siblingKey], "Sibling link targets page one (target page {$targetPage}).");
        }
    }

    #[DataProvider('tables')]
    public function test_out_of_range_page_clamps_to_last_page(string $view, string $pageKey, string $idKey, int $perPage): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
        $category = $view === 'latestProducts' ? Category::create(['cat_title' => 'Base']) : null;
        $user = in_array($view, ['latestOrders', 'latestBackups'], true) ? User::factory()->create() : null;
        UserInfoBackup::query()->delete();
        for ($i = 0; $i <= $perPage; $i++) {
            match ($view) {
                'categories' => Category::create(['cat_title' => 'Category '.$i]),
                'latestProducts' => Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Piece', 'product_slug' => 'piece-'.$i, 'product_price' => 100, 'stock' => 1]),
                'latestOrders' => Order::create(['user_id' => $user->id, 'total' => 100, 'idempotency_key' => 'order-'.$i, 'payload_hash' => str_repeat('a', 64)]),
                'latestUsers' => User::factory()->create(),
                'latestBackups' => UserInfoBackup::create(['user_id' => $user->id, 'email' => 'backup'.$i.'@example.test']),
                'latestConcierge' => ConciergeRequest::create(['name' => 'Guest '.$i, 'email' => 'concierge'.$i.'@example.test', 'phone' => '08123456789', 'service' => 'consultation', 'preferred_date' => '2026-01-10', 'preferred_time' => '10:00', 'status' => 'new']),
                'latestSubscribers' => NewsletterSubscriber::create(['email' => 'subscriber'.$i.'@example.test']),
            };
        }

        $this->actingAs($admin, 'admin');
        $this->get(route('admin.dashboard', [$pageKey => 1000]))->assertOk()
            ->assertViewHas($view, fn ($rows) => $rows->currentPage() === 2 && $rows->lastPage() === 2)
            ->assertDontSee('Halaman 1000');

        // Request JSON agar validasi Laravel mengembalikan 422, bukan redirect 302 milik request HTML.
        $this->getJson(route('admin.dashboard', [$pageKey => 1001]))->assertStatus(422)->assertJsonValidationErrors([$pageKey]);
    }

    #[DataProvider('tables')]
    public function test_empty_table_stays_on_page_one_with_genuine_empty_state(string $view, string $pageKey, string $idKey, int $perPage): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.dashboard', [$pageKey => 1000]))->assertOk()
            ->assertViewHas($view, fn ($rows) => $rows->currentPage() === 1 && $rows->isEmpty());
    }

    public function test_out_of_range_page_reuses_count_query(): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
        NewsletterSubscriber::create(['email' => 'subscriber@example.test']);
        $this->actingAs($admin, 'admin');

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->get(route('admin.dashboard', ['opage' => 1]))->assertOk();
        $firstCounts = count(array_filter(\Illuminate\Support\Facades\DB::getQueryLog(), fn ($query) => preg_match('/count\\(/i', $query['query'])));
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->get(route('admin.dashboard', ['opage' => 1000]))->assertOk();
        $lastCounts = count(array_filter(\Illuminate\Support\Facades\DB::getQueryLog(), fn ($query) => preg_match('/count\\(/i', $query['query'])));

        // Filter ostatus membedakan count paginasi (where status = ?) dari count kartu statistik (tanpa where).
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->get(route('admin.dashboard', ['opage' => 1000, 'ostatus' => 'pending']))->assertOk();
        $paginationOrderCounts = count(array_filter(
            \Illuminate\Support\Facades\DB::getQueryLog(),
            fn ($query) => preg_match('/count\\(/i', $query['query'])
                && preg_match('/from ["`]orders["`] where ["`]status["`] = \\?/i', $query['query'])
        ));
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertSame($firstCounts, $lastCounts);
        $this->assertSame(1, $paginationOrderCounts, 'Orders pagination count runs exactly once for opage=1000.');
    }

    public function test_deleting_last_subscriber_page_returns_to_valid_page(): void
    {
        $admin = Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']);
        $subscribers = [];
        for ($i = 0; $i < 11; $i++) {
            $subscribers[] = NewsletterSubscriber::create(['email' => 'subscriber'.$i.'@example.test']);
        }
        $this->actingAs($admin, 'admin');

        $response = $this->from(route('admin.dashboard', ['spage' => 2]))
            ->delete(route('admin.subscribers.destroy', $subscribers[10]))
            ->assertRedirect();
        $this->followRedirects($response)->assertOk()
            ->assertViewHas('latestSubscribers', fn ($rows) => $rows->currentPage() === 1 && $rows->count() === 10)
            ->assertDontSee('Belum ada subscriber');
    }
}
