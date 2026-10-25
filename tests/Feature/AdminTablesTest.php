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
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class AdminTablesTest extends TestCase
{
    use RefreshDatabase;

    public static function tables(): array
    {
        return [
            ['kategori', 'k', 'cat_title', 'categories', 'kq'],
            ['produk', 'p', 'product_title', 'latestProducts', 'pq'],
            ['pesanan', 'o', 'total', 'latestOrders', 'oq'],
            ['audit', 'b', 'email', 'latestBackups', 'bq'],
            ['subscriber', 's', 'email', 'latestSubscribers', 'sq'],
            ['user', 'u', 'email', 'latestUsers', 'uq'],
            ['concierge', 'c', 'email', 'latestConcierge', 'q'],
        ];
    }

    private function login(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');
    }

    private function seedRows(): void
    {
        $category = Category::create(['cat_title' => 'Needle category']);
        Category::create(['cat_title' => 'Other category']);
        for ($i = 0; $i < 17; $i++) {
            $name = $i === 0 ? 'Needle' : 'Other';
            $email = strtolower($name).$i.'@example.test';
            $user = User::factory()->create(['name' => $name, 'first_name' => $name, 'email' => $email]);
            Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => $name, 'product_slug' => 'piece-'.$i, 'product_price' => $i, 'stock' => $i]);
            Order::create(['user_id' => $user->id, 'total' => $i, 'idempotency_key' => 'order-'.$i, 'payload_hash' => str_repeat('a', 64)]);
            NewsletterSubscriber::create(['email' => $email]);
            ConciergeRequest::create(['name' => $name, 'email' => $email, 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        }
        for ($i = 0; $i < 10; $i++) {
            Category::create(['cat_title' => 'Other '.$i]);
        }
        for ($i = 17; $i < 21; $i++) {
            ConciergeRequest::create(['name' => 'Other', 'email' => 'other'.$i.'@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        }
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

    private function assertSortHeader(string $html, string $table, string $header, string $direction): void
    {
        $xpath = $this->xpath($html);
        $nodes = $xpath->query('//table[@data-table-body="'.$table.'"]//thead//th[@aria-sort="'.$direction.'"]');
        $this->assertSame(1, $nodes->length, $table.' exactly one sorted header');
        $this->assertSame($header, trim($nodes->item(0)->textContent), $table.' sorted header label');
    }

    #[DataProvider('tables')]
    public function test_table_contract_sort_search_and_pagination(string $key, string $prefix, string $sort, string $view, string $searchKey): void
    {
        $this->seedRows();
        $this->login();
        $response = $this->get(route('admin.dashboard'));
        $response->assertOk()->assertSee('data-admin-table="'.$key.'"', false);
        $sorted = $this->get(route('admin.dashboard', [$prefix.'sort' => $sort, $prefix.'dir' => 'asc']))->assertOk();
        $this->assertSortHeader($sorted->getContent(), $key, self::headerFor($key), 'ascending');
        $this->get(route('admin.dashboard', [$searchKey => 'Needle']))
            ->assertOk()->assertViewHas($view, fn ($rows) => $rows->total() === 1);
        $query = [$prefix.'page' => 2, 'ksort' => 'cat_title', 'kdir' => 'desc'];
        foreach (['uq' => 'preserved', 'csort' => 'email', 'cdir' => 'asc'] as $stateKey => $value) {
            if ($stateKey !== $searchKey && ! in_array($stateKey, [$prefix.'sort', $prefix.'dir'], true)) {
                $query[$stateKey] = $value;
            }
        }
        $page = $this->get(route('admin.dashboard', $query))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $links = $xpath->query('//*[@data-admin-table="'.$key.'"]//a[contains(@href, "'.$prefix.'page=")]');
        $pageOneQuery = null;
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $linkQuery);
            if (($linkQuery[$prefix.'page'] ?? null) === '1') {
                $pageOneQuery = $linkQuery;
                break;
            }
        }
        $this->assertNotNull($pageOneQuery, $key.' panel pagination link targets page 1');
        foreach ($query as $stateKey => $value) {
            $this->assertSame((string) ($stateKey === $prefix.'page' ? 1 : $value), $pageOneQuery[$stateKey] ?? null);
        }
        $this->get(route('admin.dashboard', [$prefix.'sort' => 'password; drop table users']))
            ->assertStatus(302)->assertSessionHasErrors($prefix.'sort');
    }

    private static function headerFor(string $key): string
    {
        return ['kategori' => 'Judul', 'produk' => 'Produk', 'pesanan' => 'Nilai tercatat', 'audit' => 'Email', 'subscriber' => 'Email', 'user' => 'Email', 'concierge' => 'Email'][$key];
    }

    public function test_admin_tables_expose_bulk_selection_density_and_visual_order_status(): void
    {
        $this->seedRows();
        $this->login();
        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//*[@data-table-select-all="pesanan"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@data-table-select="pesanan"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-admin-table="pesanan"]//*[@data-table-density="pesanan"]')->length);
        $this->assertSame(15, $xpath->query('//table[@data-table-body="pesanan"]//tbody//span[contains(concat(" ",normalize-space(@class)," ")," badge-status ")][contains(concat(" ",normalize-space(@class)," ")," badge-pending ")]')->length);
        $this->assertStringContainsString('.table-density-controls[hidden], .table-selection-toolbar[hidden] { display: none; }', $html);
    }

    public function test_only_orders_table_exposes_bulk_selection_markup(): void
    {
        $this->seedRows();
        $this->login();
        $xpath = $this->xpath($this->get(route('admin.dashboard'))->assertOk()->getContent());

        $this->assertGreaterThan(0, $xpath->query('//*[@data-admin-table="pesanan"]//*[@data-table-select]')->length);
        foreach (['kategori', 'produk', 'audit', 'subscriber', 'user', 'concierge'] as $key) {
            $this->assertSame(0, $xpath->query('//*[@data-admin-table="'.$key.'"]//*[@data-table-select]')->length, $key.' has no bulk selection');
        }
    }

    public function test_r44_accessible_controls_and_distinct_sort_headers(): void
    {
        $this->seedRows();
        $this->login();
        $response = $this->get(route('admin.dashboard', ['psort' => 'product_id', 'osort' => 'created_at']));
        $response->assertOk()->assertSee('data-table-announcement role="status"', false)
            ->assertDontSee('Produk / ID')
            ->assertSee('aria-label="Simpan kategori #', false)
            ->assertSee('aria-label="Hapus kategori ', false)
            ->assertSee('aria-label="Simpan produk ', false)
            ->assertSee('aria-label="Hapus produk ', false)
            ->assertSee('aria-label="Simpan status pesanan #', false)
            ->assertSee('aria-label="Hapus subscriber ', false);
        $this->assertSortHeader($response->getContent(), 'produk', 'ID', 'descending');
        $this->assertSortHeader($response->getContent(), 'pesanan', 'Tanggal', 'descending');
        $xpath = $this->xpath($response->getContent());
        $headers = $xpath->query('//table[@data-table-body="produk"]//thead//th');
        $headers->item(1)->removeAttribute('aria-sort');
        $headers->item(0)->setAttribute('aria-sort', 'descending');
        $mutatedHtml = $xpath->document->saveHTML();
        $sortPosition = strpos($mutatedHtml, 'aria-sort="descending"');
        $idPosition = strpos($mutatedHtml, '>ID</th>', $sortPosition);
        $this->assertNotFalse($idPosition, 'Old substring-order check accepts the wrong header');
        $this->assertNotFalse(strpos($mutatedHtml, 'Harga', $idPosition));
        $this->expectException(ExpectationFailedException::class);
        $this->assertSortHeader($mutatedHtml, 'produk', 'ID', 'descending');
    }

    public function test_r44_all_tables_expose_density_captions_column_scope_and_contrast_safe_badges(): void
    {
        $this->seedRows();
        Product::query()->first()->update(['is_active' => false]);
        ConciergeRequest::query()->latest('id')->first()->update(['status' => 'confirmed']);
        $this->login();
        $html = $this->get(route('admin.dashboard', ['psort' => 'product_id', 'pdir' => 'asc']))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $expectedCaptions = ['kategori' => 'Kategori', 'produk' => 'Produk', 'pesanan' => 'Pesanan', 'user' => 'User', 'audit' => 'Arsip audit', 'concierge' => 'Concierge', 'subscriber' => 'Subscriber'];
        foreach ($expectedCaptions as $key => $caption) {
            $table = $xpath->query('//table[@data-table-body="'.$key.'"]');
            $this->assertSame(1, $table->length, $key.' table');
            $this->assertSame('compact', $table->item(0)->getAttribute('data-density'), $key.' default density');
            $panel = $xpath->query('//*[@data-admin-table="'.$key.'"]')->item(0);
            $densityButton = $xpath->query('.//*[@data-table-density="'.$key.'"]', $panel)->item(0);
            $this->assertNotNull($densityButton, $key.' density button in panel');
            $densityTable = $xpath->query('ancestor::table[@data-table-body="'.$key.'"]', $densityButton);
            $this->assertSame(0, $densityTable->length, $key.' density control is not inside table');
            $this->assertSame($key, $panel->getAttribute('data-admin-table'), $key.' panel owner');
            $this->assertSame($caption, trim($xpath->query('.//caption', $table->item(0))->item(0)->textContent), $key.' caption');
            $this->assertSame(0, $xpath->query('.//th[not(@scope="col")]', $table->item(0))->length, $key.' header scope');
        }
        $this->assertSame($xpath->query('//th')->length, $xpath->query('//th[@scope="col"]')->length, 'every document th has col scope');
        $this->assertSame(1, $xpath->query('//*[@data-table-announcement][@role="status"]')->length);
        $this->assertSame(1, $xpath->query('//table[@data-table-body="produk"]//span[contains(concat(" ",normalize-space(@class)," ")," badge-cancelled ")]')->length);
        $this->assertSame(1, $xpath->query('//table[@data-table-body="concierge"]//span[contains(concat(" ",normalize-space(@class)," ")," badge-confirmed ")]')->length);
        foreach (['.badge-new { background: #e0f2fe; color: #075985; }', '.badge-contacted { background: #fef3c7; color: #92400e; }', '.badge-confirmed { background: #dcfce7; color: #166534; }', '.badge-completed { background: #f3e8ff; color: #7e22ce; }', '.badge-cancelled { background: #fee2e2; color: #991b1b; }'] as $rule) {
            $this->assertStringContainsString($rule, file_get_contents(resource_path('css/app.css')));
        }
    }

    public function test_r44_empty_orders_span_all_columns(): void
    {
        $this->login();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('colspan="7">Belum ada pesanan.', false);
    }

    public function test_search_string_zero_is_not_treated_as_empty(): void
    {
        $this->login();
        User::factory()->create(['name' => 'Other', 'first_name' => 'Other', 'last_name' => 'Member', 'email' => 'member@example.test', 'mobile' => '811111111']);
        User::factory()->create(['name' => '0', 'first_name' => 'Zero', 'last_name' => 'Member', 'email' => 'zero-user@example.test', 'mobile' => '822222222']);
        ConciergeRequest::create(['name' => '0', 'email' => 'zero@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        ConciergeRequest::create(['name' => 'Other', 'email' => 'other-concierge@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        $this->get(route('admin.dashboard', ['q' => '0', 'uq' => '0']))->assertOk()->assertViewHas('latestConcierge', fn ($rows) => $rows->total() === 1)->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1)->assertSee('zero@example.test')->assertSee('zero-user@example.test');
    }

    public function test_ostatus_filters_orders_with_mixed_statuses(): void
    {
        $this->seedRows();
        $shipped = Order::query()->first();
        $shipped->update(['status' => 'shipped']);
        $pending = Order::query()->whereKeyNot($shipped->id)->first();
        $this->login();
        $this->get(route('admin.dashboard', ['ostatus' => 'shipped']))->assertOk()->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 1 && collect($rows->items())->pluck('id')->all() === [$shipped->id])->assertSee('value="'.$shipped->id.'" aria-label="Pilih pesanan #'.$shipped->id.'"', false)->assertDontSee('aria-label="Pilih pesanan #'.$pending->id.'"', false)->assertDontSee('class="badge-status badge-pending"', false);
        $this->get(route('admin.dashboard', ['ostatus' => 'pending']))->assertOk()->assertViewHas('latestOrders', fn ($rows) => $rows->total() === 16 && collect($rows->items())->every(fn ($row) => $row->status === 'pending'));
    }

    public function test_r44_malformed_page_is_ignored_without_killing_dashboard_or_repeating_in_links(): void
    {
        $this->seedRows();
        $this->login();
        $this->get(route('admin.dashboard', ['kpage' => 'abc', 'opage' => 2]))->assertOk()->assertDontSee('kpage=abc', false)->assertSee('opage=2', false);
    }

    public function test_r44_rejects_unbounded_page_numbers(): void
    {
        $this->login();
        foreach (['kpage', 'ppage', 'opage', 'upage', 'bpage', 'cpage', 'spage'] as $key) {
            $this->getJson(route('admin.dashboard', [$key => 1001]))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_malformed_page_parameters_default_to_page_one_without_echoing_bad_input(): void
    {
        $this->seedRows();
        $this->login();
        $paginators = ['kpage' => 'categories', 'ppage' => 'latestProducts', 'upage' => 'latestUsers', 'bpage' => 'latestBackups', 'cpage' => 'latestConcierge', 'spage' => 'latestSubscribers'];
        foreach ($paginators as $key => $view) {
            foreach (['0', '-1', 'abc'] as $value) {
                $response = $this->get(route('admin.dashboard', [$key => $value]))->assertOk()->assertDontSee($key.'='.$value, false);
                $this->assertSame(1, $response->viewData($view)->currentPage(), $key.' defaults to page one');
            }

            $response = $this->get(route('admin.dashboard', [$key => ['1']]))->assertOk()->assertDontSee($key.'%5B0%5D=1', false)->assertDontSee($key.'[]=1', false);
            $this->assertSame(1, $response->viewData($view)->currentPage(), $key.' array input defaults to page one');
        }
    }

    public function test_stat_cards_report_totals_not_the_filtered_page_slice(): void
    {
        $this->seedRows();
        $this->login();
        $html = $this->get(route('admin.dashboard', ['kq' => 'Needle']))->assertOk()->getContent();
        $metric = $this->xpath($html)->query('//*[@data-dashboard-metric="categories"]');
        $this->assertSame(1, $metric->length);
        $this->assertSame('Kategori12', preg_replace('/\s+/', '', $metric->item(0)->textContent));
    }

    #[DataProvider('tables')]
    public function test_ascending_sort_orders_the_rows_not_only_the_header(string $key, string $prefix, string $sort, string $view, string $searchKey): void
    {
        $this->seedRows();
        $this->login();
        $rows = $this->get(route('admin.dashboard', [$prefix.'sort' => $sort, $prefix.'dir' => 'asc']))->assertOk()->viewData($view);
        $values = collect($rows->items())->map(fn ($row) => $row->{$sort})->all();
        $this->assertSame(collect($values)->sort()->values()->all(), $values, $key.' harus urut menaik');
    }

    public function test_all_forms_preserve_other_tables_and_category_choices_are_not_paginated(): void
    {
        $this->seedRows();
        $this->login();
        $this->get(route('admin.dashboard', ['kq' => 'missing', 'pq' => 'Needle', 'oq' => 'Needle', 'bq' => 'Needle', 'sq' => 'Needle', 'uq' => 'Needle', 'q' => 'Needle']))->assertOk()->assertViewHas('categoryOptions', fn ($rows) => $rows->count() === 12);
    }
}
