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

class AdminTableCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');
    }

    public static function literalSearches(): array
    {
        return array_merge(
            array_map(fn ($key) => [$key, '%'], ['kq', 'pq', 'oq', 'uq', 'bq', 'q', 'sq']),
            array_map(fn ($key) => [$key, '_'], ['kq', 'pq', 'oq', 'uq', 'bq', 'q', 'sq']),
            // A backslash must stay literal too: it is the engine-level escape character in MySQL.
            array_map(fn ($key) => [$key, '\\'], ['kq', 'pq', 'oq', 'uq', 'bq', 'q', 'sq']),
            array_map(fn ($key) => [$key, '!'], ['kq', 'pq', 'oq', 'uq', 'bq', 'q', 'sq'])
        );
    }

    private function seedSearchPair(string $key, string $term): string
    {
        $match = 'mark'.$term.'value';
        $miss = 'markXvalue';
        $user = User::factory()->create(['name' => $match, 'first_name' => $match, 'last_name' => '']);
        $other = User::factory()->create(['name' => $miss, 'first_name' => $miss, 'last_name' => '']);

        match ($key) {
            'kq' => Category::create(['cat_title' => $match]) && Category::create(['cat_title' => $miss]),
            'pq' => $this->seedProducts($match, $miss),
            'oq' => $this->seedOrders($user, $other, $match, $miss),
            'uq' => null,
            'bq' => null,
            'q' => $this->seedConcierge($match, $miss),
            'sq' => NewsletterSubscriber::create(['email' => $match.'@example.test']) && NewsletterSubscriber::create(['email' => $miss.'@example.test']),
        };

        if ($key === 'bq') {
            UserInfoBackup::query()->where('user_id', $user->id)->update(['first_name' => $match]);
            UserInfoBackup::query()->where('user_id', $other->id)->update(['first_name' => $miss]);
        }

        return $match;
    }

    private function seedProducts(string $match, string $miss): void
    {
        $category = Category::create(['cat_title' => 'Products']);
        Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => $match, 'product_slug' => 'match', 'product_price' => 1, 'stock' => 1]);
        Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => $miss, 'product_slug' => 'miss', 'product_price' => 1, 'stock' => 1]);
    }

    private function seedOrders(User $user, User $other, string $match, string $miss): void
    {
        foreach ([[$user, $match], [$other, $miss]] as [$owner, $value]) {
            Order::create(['user_id' => $owner->id, 'customer_name' => $value, 'customer_email' => $value.'@example.test', 'total' => 1, 'idempotency_key' => $value, 'payload_hash' => str_repeat('a', 64)]);
        }
    }

    private function seedConcierge(string $match, string $miss): void
    {
        foreach ([$match, $miss] as $i => $value) {
            ConciergeRequest::create(['name' => $value, 'email' => 'concierge'.$i.'@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        }
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_wildcard_and_escape_characters_literally(string $key, string $term): void
    {
        $match = $this->seedSearchPair($key, $term);
        $this->login();
        $view = ['kq' => 'categories', 'pq' => 'latestProducts', 'oq' => 'latestOrders', 'uq' => 'latestUsers', 'bq' => 'latestBackups', 'q' => 'latestConcierge', 'sq' => 'latestSubscribers'][$key];
        $rows = $this->get(route('admin.dashboard', [$key => $term]))->assertOk()->viewData($view);
        $this->assertSame(1, $rows->total(), "{$key} search for '{$term}' must match exactly one row");
        // Encode the needle too: a literal backslash becomes \\ inside JSON.
        $this->assertStringContainsString(trim(json_encode($match), '"'), json_encode($rows->items()));
    }

    public function test_category_and_user_search_return_matching_records(): void
    {
        $category = Category::create(['cat_title' => 'Silk atelier']);
        User::factory()->create(['name' => 'Vivienne Client', 'first_name' => 'Vivienne', 'last_name' => 'Client']);
        User::factory()->create(['name' => 'Other', 'first_name' => 'Other']);
        $this->login();
        $this->get(route('admin.dashboard', ['kq' => 'Silk']))->assertOk()->assertViewHas('categories', fn ($rows) => $rows->total() === 1 && $rows->items()[0]->cat_id === $category->cat_id);
        $this->get(route('admin.dashboard', ['uq' => 'Vivienne']))->assertOk()->assertViewHas('latestUsers', fn ($rows) => $rows->total() === 1 && $rows->items()[0]->name === 'Vivienne Client');
    }

    public static function sortDirections(): array
    {
        return [['csort', 'name', 'cdir'], ['csort', 'email', 'cdir'], ['usort', 'name', 'udir'], ['usort', 'email', 'udir']];
    }

    #[DataProvider('sortDirections')]
    public function test_concierge_and_user_sort_ascending_and_descending(string $sortKey, string $column, string $dirKey): void
    {
        if ($sortKey === 'csort') {
            foreach (['Zed', 'Amy'] as $name) {
                ConciergeRequest::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
            }
            $view = 'latestConcierge';
            $values = ['name' => ['Amy', 'Zed'], 'email' => ['amy@example.test', 'zed@example.test']][$column];
        } else {
            foreach ([['Zed', 'Zed'], ['Amy', 'Amy']] as [$name, $first]) {
                User::factory()->create(['name' => $name, 'first_name' => $first, 'last_name' => '']);
            }
            $view = 'latestUsers';
            $values = ['name' => ['Amy', 'Zed'], 'email' => User::query()->orderBy('email')->pluck('email')->all()][$column];
        }
        $this->login();
        foreach (['asc' => $values, 'desc' => array_reverse($values)] as $direction => $expected) {
            $this->get(route('admin.dashboard', [$sortKey => $column, $dirKey => $direction]))->assertOk()->assertViewHas($view, fn ($rows) => array_map(fn ($row) => $column === 'name' && $sortKey === 'usort' ? ($row->first_name ?: $row->name) : $row->{$column}, $rows->items()) === $expected);
        }
    }

    public function test_concierge_status_filter_and_default_sort_tie_break(): void
    {
        foreach ([['Older', 'old@example.test', '2025-01-01'], ['Newest A', 'a@example.test', '2025-02-01'], ['Newest B', 'b@example.test', '2025-02-01']] as [$name, $email, $date]) {
            ConciergeRequest::create(['name' => $name, 'email' => $email, 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => $name === 'Older' ? 'new' : 'confirmed', 'created_at' => $date, 'updated_at' => $date]);
        }
        $this->login();
        $this->get(route('admin.dashboard', ['status' => 'confirmed']))->assertOk()->assertViewHas('latestConcierge', fn ($rows) => $rows->total() === 2 && collect($rows->items())->every(fn ($row) => $row->status === 'confirmed'));
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('latestConcierge', fn ($rows) => array_column($rows->items(), 'email') === ['b@example.test', 'a@example.test', 'old@example.test']);
    }

    public function test_user_default_sort_is_created_at_desc_with_id_tie_break(): void
    {
        $older = User::factory()->create(['created_at' => '2025-01-01']);
        $tieA = User::factory()->create(['created_at' => '2025-02-01']);
        $tieB = User::factory()->create(['created_at' => '2025-02-01']);
        $this->login();
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('latestUsers', fn ($rows) => array_column($rows->items(), 'id') === [$tieB->id, $tieA->id, $older->id]);
    }

    public function test_page_sizes_and_out_of_range_pages_are_clamped(): void
    {
        $category = Category::create(['cat_title' => 'Main']);
        for ($i = 0; $i < 22; $i++) {
            $user = User::factory()->create();
            Product::create(['product_cat' => $category->cat_id, 'product_brand' => 'Maison', 'product_title' => 'Item '.$i, 'product_slug' => 'item-'.$i, 'product_price' => $i, 'stock' => $i]);
            Order::create(['user_id' => $user->id, 'total' => $i, 'idempotency_key' => 'o'.$i, 'payload_hash' => str_repeat('b', 64)]);
            NewsletterSubscriber::create(['email' => 's'.$i.'@example.test']);
            UserInfoBackup::create(['user_id' => $user->id, 'first_name' => 'F'.$i, 'last_name' => 'L', 'email' => 'b'.$i.'@example.test']);
            ConciergeRequest::create(['name' => 'C'.$i, 'email' => 'c'.$i.'@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
            if ($i < 9) {
                Category::create(['cat_title' => 'Category '.$i]);
            }
        }
        $this->login();
        foreach ([['upage', 'latestUsers', 10], ['bpage', 'latestBackups', 10], ['cpage', 'latestConcierge', 20], ['kpage', 'categories', 10], ['ppage', 'latestProducts', 10], ['spage', 'latestSubscribers', 10], ['opage', 'latestOrders', 15]] as [$page, $view, $size]) {
            $this->get(route('admin.dashboard', [$page => 99]))->assertOk()->assertViewHas($view, fn ($rows) => $rows->currentPage() === $rows->lastPage() && $rows->perPage() === $size);
        }
        foreach ([['upage', 'latestUsers'], ['cpage', 'latestConcierge']] as [$page, $view]) {
            $one = null;
            $this->get(route('admin.dashboard', [$page => 1]))->assertViewHas($view, function ($rows) use (&$one) {
                $one = $rows->items()[0]->id;

                return true;
            });
            $this->get(route('admin.dashboard', [$page => 2]))->assertViewHas($view, fn ($rows) => $rows->items()[0]->id !== $one);
        }
    }
}
