<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminFilterBadgeCopyTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    private function seedMatchingRows(): void
    {
        $category = Category::create(['cat_title' => 'Sutra Match']);
        Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Sutra Dress', 'product_slug' => 'sutra-dress', 'product_price' => 100, 'stock' => 2]);
        $user = User::factory()->create(['name' => 'Vivienne Match', 'first_name' => 'Vivienne', 'last_name' => 'Match', 'email' => 'vivienne.match@example.test', 'mobile' => '08123456']);
        Order::create(['user_id' => $user->id, 'total' => 250000, 'status' => 'pending', 'idempotency_key' => 'ord-match', 'payload_hash' => str_repeat('a', 64)]);
        ConciergeRequest::create(['name' => 'Vivienne Concierge', 'email' => 'concierge.match@example.test', 'phone' => '08111', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        NewsletterSubscriber::create(['email' => 'subscriber.match@example.test']);
    }

    private function assertScopedEmptyState(string $html, string $panelKey, string $message): void
    {
        $xpath = $this->xpath($html);
        if ($panelKey === 'pesanan') {
            $cells = $xpath->query('//*[@data-admin-table="pesanan"]//table[@data-table-body="pesanan"]//tbody//td[@colspan="7"]');
            $this->assertSame(1, $cells->length, 'pesanan empty cell');
            $this->assertSame($message, trim($cells->item(0)->firstChild->textContent));

            return;
        }
        $panel = $xpath->query('//*[@data-admin-table="'.$panelKey.'"]')->item(0);
        $this->assertNotNull($panel, $panelKey.' panel');
        $this->assertSame(0, $xpath->query('.//table[@data-table-body="'.$panelKey.'"]', $panel)->length, $panelKey.' table hidden when empty');
        $empties = $xpath->query('.//p[contains(concat(" ",normalize-space(@class)," ")," admin-empty ")][not(@data-table-error)]', $panel);
        $this->assertSame(1, $empties->length, $panelKey.' empty paragraph');
        $this->assertSame($message, trim($empties->item(0)->textContent));
    }

    public static function filterLabels(): array
    {
        return [
            ['kq', 'Kategori', 'Sutra'],
            ['pq', 'Produk', 'Sutra'],
            ['uq', 'Nama/email/HP', 'Vivienne'],
            ['bq', 'Nama atau email', 'Vivienne'],
            ['sq', 'Email', 'example.test'],
            ['oq', 'Pencarian pesanan', 'Vivienne'],
            ['ostatus', 'Status pesanan', 'pending'],
            ['q', 'Pencarian concierge', 'Vivienne'],
            ['status', 'Status permintaan', 'new'],
            ['pq', 'Produk', '<b>Étoile & sutra</b>'],
        ];
    }

    #[DataProvider('filterLabels')]
    public function test_r44_filter_badge_uses_human_copy_and_preserves_escaped_attribute(string $key, string $label, string $value): void
    {
        $this->login();
        $response = $this->get(route('admin.dashboard', [$key => $value]));

        $response->assertOk()
            ->assertSee('data-active-filter="'.$key.': '.e($value).'">'.$label.': '.(match ($key) {
                'ostatus' => 'Menunggu', 'status' => 'Baru', default => e($value)
            }).'</span>', false)
            ->assertDontSeeText($key.': '.$value);

        if (str_contains($value, '<')) {
            $response->assertDontSee($value, false);
        }
    }

    public static function additionalEmptyTableCases(): array
    {
        return [
            ['pesanan default', 'pesanan', [], 'latestOrders', 'Belum ada pesanan.', false, null],
            ['pesanan filtered', 'pesanan', ['oq' => 'no-match'], 'latestOrders', 'Tidak ada pesanan yang cocok dengan filter ini.', true, 'Vivienne Match'],
            ['pesanan zero', 'pesanan', ['oq' => '0'], 'latestOrders', 'Tidak ada pesanan yang cocok dengan filter ini.', false, null],
            ['pesanan status', 'pesanan', ['ostatus' => 'shipped'], 'latestOrders', 'Tidak ada pesanan yang cocok dengan filter ini.', true, 'Vivienne Match'],
            ['pesanan unrelated', 'pesanan', ['bq' => 'missing'], 'latestOrders', 'Belum ada pesanan.', false, null],
            ['user default', 'user', [], 'latestUsers', 'Tidak ada user yang cocok.', false, null],
            ['user filtered', 'user', ['uq' => 'no-match'], 'latestUsers', 'Tidak ada user yang cocok.', true, 'vivienne.match@example.test'],
            ['audit default', 'audit', [], 'latestBackups', 'Belum ada arsip. Dibuat otomatis setiap klien mendaftar.', false, null],
            ['audit filtered', 'audit', ['bq' => 'no-match'], 'latestBackups', 'Tidak ada arsip yang cocok dengan pencarian ini.', true, 'vivienne.match@example.test'],
            ['audit zero', 'audit', ['bq' => '0'], 'latestBackups', 'Tidak ada arsip yang cocok dengan pencarian ini.', false, null],
            ['audit unrelated', 'audit', ['oq' => 'missing'], 'latestBackups', 'Belum ada arsip. Dibuat otomatis setiap klien mendaftar.', false, null],
            ['concierge default', 'concierge', [], 'latestConcierge', 'Tidak ada permintaan yang cocok dengan filter.', false, null],
            ['concierge filtered', 'concierge', ['q' => 'no-match'], 'latestConcierge', 'Tidak ada permintaan yang cocok dengan filter.', true, 'concierge.match@example.test'],
            ['subscriber default', 'subscriber', [], 'latestSubscribers', 'Belum ada subscriber.', false, null],
            ['subscriber filtered', 'subscriber', ['sq' => 'no-match'], 'latestSubscribers', 'Tidak ada subscriber yang cocok.', true, 'subscriber.match@example.test'],
        ];
    }

    #[DataProvider('additionalEmptyTableCases')]
    public function test_other_empty_tables_show_current_default_or_filtered_copy(string $case, string $panelKey, array $query, string $view, string $message, bool $seed, ?string $seededText): void
    {
        $this->login();
        if ($seed) {
            $this->seedMatchingRows();
            $unfiltered = $this->get(route('admin.dashboard'))->assertOk()->getContent();
            $panel = $this->xpath($unfiltered)->query('//*[@data-admin-table="'.$panelKey.'"]')->item(0);
            $this->assertStringContainsString((string) $seededText, $panel->textContent);
        }
        $response = $this->get(route('admin.dashboard', $query))
            ->assertOk()
            ->assertViewHas($view, fn ($rows) => $rows->total() === 0);

        $this->assertScopedEmptyState($response->getContent(), $panelKey, $message);
        if ($seed) {
            $filteredPanel = $this->xpath($response->getContent())->query('//*[@data-admin-table="'.$panelKey.'"]')->item(0);
            $this->assertStringNotContainsString((string) $seededText, $filteredPanel->textContent);
        }
        if ($view === 'latestSubscribers') {
            $panel = $this->xpath($response->getContent())->query('//*[@data-admin-table="subscriber"]')->item(0);
            $this->assertNotNull($panel);
            $this->assertStringNotContainsString($query === [] ? 'Tidak ada subscriber yang cocok.' : 'Belum ada subscriber.', $panel->textContent);
        }
    }

    public static function emptyCategorySearches(): array
    {
        return [
            ['', 'Belum ada kategori.', 'Tidak ada kategori yang cocok.', false],
            ['zzz', 'Tidak ada kategori yang cocok.', 'Belum ada kategori.', true],
            ['0', 'Tidak ada kategori yang cocok.', 'Belum ada kategori.', true],
        ];
    }

    #[DataProvider('emptyCategorySearches')]
    public function test_empty_categories_explain_whether_search_is_active(string $search, string $message, string $otherMessage, bool $seed): void
    {
        $this->login();
        if ($seed) {
            $this->seedMatchingRows();
            $this->get(route('admin.dashboard'))->assertOk()->assertSee('Sutra Match');
        }
        $response = $this->get(route('admin.dashboard', ['kq' => $search]))
            ->assertOk()
            ->assertViewHas('categories', fn ($rows) => $rows->total() === 0);

        $this->assertStringNotContainsString($otherMessage, $this->xpath($response->getContent())->evaluate('string(//*[@data-admin-table="kategori"])'));

        $this->assertScopedEmptyState($response->getContent(), 'kategori', $message);
    }

    public static function emptyProductSearches(): array
    {
        return [
            ['', 'Belum ada produk.', 'Tidak ada produk yang cocok dengan pencarian.', false],
            ['zzz', 'Tidak ada produk yang cocok dengan pencarian.', 'Belum ada produk.', true],
            ['0', 'Tidak ada produk yang cocok dengan pencarian.', 'Belum ada produk.', true],
        ];
    }

    #[DataProvider('emptyProductSearches')]
    public function test_r44_empty_products_explain_whether_search_is_active(string $search, string $message, string $otherMessage, bool $seed): void
    {
        $this->login();
        if ($seed) {
            $this->seedMatchingRows();
            $this->get(route('admin.dashboard'))->assertOk()->assertSee('Sutra Dress');
        }
        $response = $this->get(route('admin.dashboard', ['pq' => $search]))
            ->assertOk()
            ->assertViewHas('latestProducts', fn ($rows) => $rows->total() === 0);

        $this->assertStringNotContainsString($otherMessage, $this->xpath($response->getContent())->evaluate('string(//*[@data-admin-table="produk"])'));

        $this->assertScopedEmptyState($response->getContent(), 'produk', $message);
    }

    public function test_order_and_concierge_status_badges_render_real_badge_nodes_across_status_matrix(): void
    {
        $this->login();
        $user = User::factory()->create();
        $orderStatuses = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
        foreach ($orderStatuses as $index => $status) {
            Order::create(['user_id' => $user->id, 'total' => 100 + $index, 'status' => $status, 'idempotency_key' => 'ord-'.$status, 'payload_hash' => str_repeat((string) $index, 64)]);
        }
        $conciergeStatuses = ['new', 'contacted', 'confirmed', 'completed', 'cancelled'];
        foreach ($conciergeStatuses as $status) {
            ConciergeRequest::create(['name' => 'Tamu '.$status, 'email' => $status.'@example.test', 'phone' => '08123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => $status]);
        }

        $xpath = $this->xpath($this->get(route('admin.dashboard'))->assertOk()->getContent());
        $expectedLabels = ['pending' => 'Menunggu', 'paid' => 'Dibayar', 'processing' => 'Diproses', 'shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        foreach ($orderStatuses as $status) {
            $nodes = $xpath->query('//table[@data-table-body="pesanan"]//tbody//span[contains(concat(" ",normalize-space(@class)," ")," badge-status ")][contains(concat(" ",normalize-space(@class)," ")," badge-'.$status.' ")]');
            $this->assertSame(1, $nodes->length, 'order badge '.$status);
            $this->assertSame($expectedLabels[$status], trim($nodes->item(0)->textContent));
        }
        $conciergeLabels = ['new' => 'Baru', 'contacted' => 'Dihubungi', 'confirmed' => 'Dikonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        foreach ($conciergeStatuses as $status) {
            $nodes = $xpath->query('//table[@data-table-body="concierge"]//tbody//span[contains(concat(" ",normalize-space(@class)," ")," badge-status ")][contains(concat(" ",normalize-space(@class)," ")," badge-'.$status.' ")]');
            $this->assertSame(1, $nodes->length, 'concierge badge '.$status);
            $this->assertSame($conciergeLabels[$status], trim($nodes->item(0)->textContent));
        }
    }

    public function test_guest_order_renders_customer_name_fallback_in_orders_panel(): void
    {
        $this->login();
        Order::create([
            'user_id' => null,
            'customer_name' => 'Madame Vivienne Guest',
            'customer_email' => 'guest@example.test',
            'total' => 450000,
            'status' => 'pending',
            'idempotency_key' => 'ord-guest-test',
            'payload_hash' => str_repeat('b', 64),
        ]);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $klienCells = $xpath->query('//*[@data-admin-table="pesanan"]//table[@data-table-body="pesanan"]//tbody//tr/td[4]');
        $this->assertSame(1, $klienCells->length, 'exactly one order row');
        $this->assertSame('Madame Vivienne Guest', trim($klienCells->item(0)->textContent));
    }
}
