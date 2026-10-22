<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\ConciergeRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Refund;
use App\Models\Review;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserInfoBackup;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Tests\TestCase;

/** REM-20261021 / FR-REM-004 / DATA-08: streamed, owner-only export; integrator runs RED serially. */
class ProfileExportStreamingTest extends TestCase
{
    use RefreshDatabase;

    // Explicit query-page ceiling, not a measured memory/throughput claim.
    private const MAX_PAGE = 1000;

    private const TOP_LEVEL_KEYS = [
        'generated_at', 'profile', 'newsletter_subscribed', 'profile_snapshots',
        'social_accounts', 'addresses', 'orders', 'returns', 'refunds', 'reviews',
        'favorites', 'concierge_requests',
    ];

    private const PROFILE_KEYS = [
        'name', 'first_name', 'last_name', 'email', 'mobile', 'address1', 'address2',
        'email_verified_at', 'cart_reminder_opt_in', 'locale', 'created_at',
    ];

    private const PROJECTIONS = [
        'profile_snapshots' => [UserInfoBackup::class, ['first_name', 'last_name', 'email', 'mobile', 'address1', 'address2', 'created_at']],
        'social_accounts' => [SocialAccount::class, ['provider', 'created_at']],
        'addresses' => [Address::class, ['label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code', 'is_default']],
        'returns' => [OrderReturn::class, ['return_number', 'order_id', 'status', 'reason', 'notes', 'requested_refund_amount', 'created_at']],
        'refunds' => [Refund::class, ['return_id', 'status', 'amount', 'created_at']],
        'reviews' => [Review::class, ['product_id', 'order_id', 'rating', 'body', 'created_at']],
        'concierge_requests' => [ConciergeRequest::class, ['service', 'name', 'email', 'phone', 'preferred_date', 'preferred_time', 'notes', 'status', 'created_at']],
    ];

    private const HISTORY_TABLES = [
        'user_info_backups', 'social_accounts', 'addresses', 'orders', 'order_items',
        'returns', 'refunds', 'reviews', 'favorites', 'concierge_requests',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Notification::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_guest_cannot_download_a_profile_export(): void
    {
        $this->get(route('account.export'))->assertRedirect(route('login'));
    }

    public function test_stream_preserves_exact_export_values_and_allowlisted_keys_without_mutating_data(): void
    {
        [$owner, $foreign] = $this->fixture(2);
        $expected = $this->expectedExport($owner);
        $before = $this->databaseDigest();
        $response = $this->actingAs($owner->fresh())->get(route('account.export'))->assertOk();
        $this->assertInstanceOf(StreamedJsonResponse::class, $response->baseResponse);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Content-Disposition', 'attachment; filename="zafira-data-pribadi.json"');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $json = $response->streamedContent();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(self::TOP_LEVEL_KEYS, array_keys($data));
        $this->assertSame($expected, $data);
        $this->assertStringNotContainsString($foreign->email, $json);
        $this->assertNoPrivateFields($data);
        $this->assertSame($before, $this->databaseDigest());
    }

    public function test_unverified_owner_keeps_false_subscription_and_empty_lists_as_json_arrays(): void
    {
        $owner = User::factory()->unverified()->create()->fresh();
        NewsletterSubscriber::create(['email' => $owner->email]);
        $response = $this->actingAs($owner)->get(route('account.export'))->assertOk();
        $this->assertInstanceOf(StreamedJsonResponse::class, $response->baseResponse);
        $json = $response->streamedContent();
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $wire = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($this->expectedExport($owner), $data);
        $this->assertFalse($data['newsletter_subscribed']);
        foreach (['social_accounts', 'addresses', 'orders', 'returns', 'refunds', 'reviews', 'favorites', 'concierge_requests'] as $key) {
            $this->assertSame([], $data[$key], $key);
            $this->assertIsArray($wire->{$key}, $key.' must be a JSON array, not an object.');
            $this->assertSame([], $wire->{$key}, $key);
        }
        // The existing UserObserver creates an owned snapshot even for an otherwise empty account.
        $this->assertCount(1, $data['profile_snapshots']);
    }

    public function test_large_history_is_consumed_lazily_in_bounded_keyset_pages_and_complete_valid_json(): void
    {
        $count = self::MAX_PAGE + 3;
        [$owner, $foreign] = $this->fixture($count);
        $expected = $this->expectedExport($owner);
        $before = $this->databaseDigest();
        $body = '';
        $reads = [];
        $writes = [];
        $watch = true;
        $emitting = false;
        $firstRows = [
            'user_info_backups' => '/own-snapshot-0/',
            'addresses' => '/own-address-0/',
            'orders' => '/own-order-0/',
            'order_items' => '/own-item-0/',
            'returns' => '/own-return-0/',
            'refunds' => '/"amount"\s*:\s*50000/',
            'reviews' => '/own-review-0/',
            'favorites' => '/"favorites"\s*:\s*\[\s*'.$expected['favorites'][0].'(?:\s*,|\s*\])/',
            'concierge_requests' => '/own-concierge-0/',
        ];
        DB::listen(function (QueryExecuted $event) use (&$watch, &$emitting, &$body, &$reads, &$writes, $firstRows): void {
            if (! $watch) {
                return;
            }
            $sql = strtolower($event->sql);
            if (preg_match('/^\s*(insert|update|delete|replace)\b/', $sql)) {
                $writes[] = $sql;
            }
            if (! preg_match('/^\s*select\b.*?\bfrom\s+["`]?([a-z_]+)["`]?/s', $sql, $match)
                || ! in_array($match[1], self::HISTORY_TABLES, true)) {
                return;
            }
            $table = $match[1];
            $keyset = (bool) preg_match('/["`]?id["`]?\s*>\s*\?/', $sql);
            $reads[] = [
                'table' => $table, 'sql' => $sql, 'emitting' => $emitting, 'keyset' => $keyset,
                'first_row_already_sent' => $keyset && isset($firstRows[$table])
                    ? (bool) preg_match($firstRows[$table], $body) : null,
            ];
        });

        $response = $this->actingAs($owner->fresh())->get(route('account.export'))->assertOk();
        $this->assertSame([], $reads, 'History must not be loaded while constructing the response.');
        $this->assertInstanceOf(StreamedJsonResponse::class, $response->baseResponse);
        $emitting = true;
        // Test-only capture: the server must emit earlier rows before fetching later pages.
        // Passing LazyCollection directly to StreamedJsonResponse would materialize via JsonSerializable.
        ob_start(function (string $chunk) use (&$body): string {
            $body .= $chunk;

            return '';
        }, 1);
        try {
            $response->baseResponse->sendContent();
        } finally {
            ob_end_clean();
            $watch = false;
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($expected), array_keys($data));
        foreach ($expected as $key => $value) {
            // Strict nested equality is unchanged; do not ask PHPUnit to render a history-sized diff.
            $this->assertTrue($value === $data[$key], 'Strict JSON mismatch in section '.$key.' (values, types, keys or order).');
        }
        $this->assertCount($count, $data['orders']);
        $this->assertCount($count, $data['orders'][0]['items']);
        $this->assertCount($count + 1, $data['profile_snapshots']);
        foreach (['addresses', 'returns', 'refunds', 'reviews', 'favorites', 'concierge_requests'] as $key) {
            $this->assertCount($count, $data[$key], $key);
            $this->assertTrue(array_is_list($data[$key]), $key.' must remain a JSON list across page boundaries.');
        }
        $this->assertStringNotContainsString($foreign->email, $body);
        $this->assertStringNotContainsString('foreign-', $body);
        $this->assertNoPrivateFields($data);
        foreach ($reads as $read) {
            $this->assertTrue($read['emitting']);
            $this->assertSame(1, preg_match('/\blimit\s+(\d+)/', $read['sql'], $limit), $read['table'].' must have an explicit SQL page bound, not an unbounded cursor.');
            $this->assertGreaterThan(0, (int) $limit[1]);
            $this->assertLessThanOrEqual(self::MAX_PAGE, (int) $limit[1], $read['table']);
            $this->assertDoesNotMatchRegularExpression('/\boffset\s+[1-9]/', $read['sql']);
        }
        foreach (array_keys($firstRows) as $table) {
            $nextPages = array_values(array_filter($reads, fn ($read) => $read['table'] === $table && $read['keyset']));
            $this->assertNotEmpty($nextPages, $table.' must cross a keyset page boundary without truncation.');
            foreach ($nextPages as $read) {
                $this->assertTrue($read['first_row_already_sent'], $table.' was fully prefetched rather than streamed.');
            }
        }
        $this->assertSame([], $writes, 'Downloading must not mutate domain data.');
        $this->assertSame($before, $this->databaseDigest());
    }

    /** Explicit legacy wire contract, normalized with the same Eloquent scalar/date casts. */
    private function expectedExport(User $owner): array
    {
        $data = [
            'generated_at' => now()->toISOString(),
            'profile' => $owner->fresh()->only(self::PROFILE_KEYS),
            'newsletter_subscribed' => $owner->hasVerifiedEmail()
                && NewsletterSubscriber::where('email', $owner->email)->exists(),
        ];
        foreach (self::PROJECTIONS as $key => [$model, $fields]) {
            $data[$key] = $model::where('user_id', $owner->id)->orderBy('id')->get()
                ->map(fn ($row) => $row->only($fields))->all();
        }
        $data['orders'] = Order::where('user_id', $owner->id)->orderBy('id')->with('items')->get()->map(function (Order $order): array {
            return $order->only(['id', 'status', 'total', 'shipping_address', 'customer_name', 'customer_email', 'customer_phone', 'created_at']) + [
                'items' => $order->items->sortBy('id')->values()->map(fn ($item) => $item->only([
                    'product_title', 'unit_price', 'quantity', 'subtotal',
                ]))->all(),
            ];
        })->all();
        $data['favorites'] = DB::table('favorites')->where('user_id', $owner->id)->orderBy('id')->pluck('product_id')->all();
        $ordered = [];
        foreach (self::TOP_LEVEL_KEYS as $key) {
            $ordered[$key] = $data[$key];
        }

        return json_decode(json_encode($ordered, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertNoPrivateFields(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $this->assertDoesNotMatchRegularExpression('/password|token|hash|secret|provider_id|admin_notes|reviewed_by/', $key);
                $this->assertNotSame('user_id', $key);
            }
            if (is_array($value)) {
                $this->assertNoPrivateFields($value);
            }
        }
    }

    private function databaseDigest(): array
    {
        $digest = [];
        foreach ([...self::HISTORY_TABLES, 'users', 'newsletter_subscribers', 'products'] as $table) {
            $id = $table === 'products' ? 'product_id' : 'id';
            $digest[$table] = hash('sha256', json_encode(DB::table($table)->orderBy($id)->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $digest;
    }

    /** Bulk insertion is fixture setup only; does not call checkout/payment/provider services. */
    private function insertRows(string $table, array $rows): void
    {
        // Keep fixture INSERT bindings portable even on SQLite builds with a low variable ceiling.
        foreach (array_chunk($rows, 40) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    /** @return array{User, User} */
    private function fixture(int $count): array
    {
        $category = Category::create(['cat_title' => 'Export synthetic']);
        $owners = [];
        foreach (['own', 'foreign'] as $prefix) {
            $owner = User::factory()->create([
                'name' => $prefix.'-profile', 'first_name' => 'Zoë "Lumière" / 東京',
                'email' => $prefix.'-profile@example.test', 'locale' => 'fr',
                'address1' => 'https://example.test/atelier', 'address2' => null,
                'remember_token' => $prefix.'-private-token',
            ]);
            // Real HTTP authentication hydrates database defaults; a newly created factory model does not.
            $owners[] = $owner->fresh();
            NewsletterSubscriber::create(['email' => $owner->email]);
            $stamp = now()->toDateTimeString();
            $products = $orders = [];
            for ($i = 0; $i < $count; $i++) {
                $products[] = [
                    'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
                    'product_title' => $prefix.'-product-'.$i, 'product_slug' => $prefix.'-export-'.$i,
                    'product_price' => '125000.00', 'created_at' => $stamp, 'updated_at' => $stamp,
                ];
                $orders[] = [
                    'user_id' => $owner->id, 'status' => 'completed', 'subtotal' => $i === 0 ? $count * 125000 : 125000,
                    'discount' => 0, 'shipping_fee' => 0, 'total' => $i === 0 ? $count * 125000 : 125000,
                    'idempotency_key' => $prefix.'-export-'.$i, 'payload_hash' => hash('sha256', $prefix.'-payload-'.$i),
                    'shipping_address' => json_encode(['line1' => '東京 / "Atelier"', 'line2' => null], JSON_THROW_ON_ERROR),
                    'customer_name' => $prefix.'-order-'.$i, 'customer_email' => $owner->email,
                    'customer_phone' => null, 'created_at' => $stamp, 'updated_at' => $stamp,
                ];
            }
            $this->insertRows('products', $products);
            $this->insertRows('orders', $orders);
            $productIds = DB::table('products')->where('product_slug', 'like', $prefix.'-export-%')->orderBy('product_id')->pluck('product_id')->all();
            $orderIds = DB::table('orders')->where('user_id', $owner->id)->orderBy('id')->pluck('id')->all();
            $rows = array_fill_keys(['addresses', 'user_info_backups', 'order_items', 'returns', 'reviews', 'favorites', 'concierge_requests'], []);
            for ($i = 0; $i < $count; $i++) {
                $timestamps = ['created_at' => $stamp, 'updated_at' => $stamp];
                $rows['addresses'][] = $timestamps + [
                    'user_id' => $owner->id, 'label' => $prefix.'-address-'.$i, 'recipient_name' => $prefix.'-recipient',
                    'phone' => '081200000000', 'line1' => 'Jalan Uji / 東京', 'line2' => null,
                    'city' => 'Jakarta', 'province' => 'DKI', 'postal_code' => '10110', 'is_default' => $i === 0,
                ];
                $rows['user_info_backups'][] = $timestamps + [
                    'user_id' => $owner->id, 'first_name' => $prefix.'-snapshot-'.$i, 'last_name' => null,
                    'email' => $owner->email, 'mobile' => null, 'address1' => 'Atelier', 'address2' => null,
                ];
                $item = $timestamps + ['order_id' => $orderIds[0], 'product_id' => $productIds[$i],
                    'product_title' => $prefix.'-item-'.$i, 'unit_price' => 125000, 'quantity' => 1, 'subtotal' => 125000];
                $rows['order_items'][] = $item;
                if ($i > 0) {
                    $item['order_id'] = $orderIds[$i];
                    $rows['order_items'][] = $item;
                }
                $rows['returns'][] = $timestamps + [
                    'user_id' => $owner->id, 'order_id' => $orderIds[$i], 'return_number' => $prefix.'-return-'.$i,
                    'status' => 'completed', 'reason' => 'damaged', 'notes' => null,
                    'requested_refund_amount' => 125000, 'admin_notes' => $prefix.'-private-admin-note',
                ];
                $rows['reviews'][] = $timestamps + ['user_id' => $owner->id, 'order_id' => $orderIds[$i],
                    'product_id' => $productIds[$i], 'rating' => 4, 'body' => $prefix.'-review-'.$i];
                $rows['favorites'][] = $timestamps + ['user_id' => $owner->id, 'product_id' => $productIds[$i]];
                $rows['concierge_requests'][] = $timestamps + [
                    'user_id' => $owner->id, 'service' => 'atelier', 'name' => $prefix.'-concierge-'.$i,
                    'email' => $owner->email, 'phone' => '081200000000', 'preferred_date' => null,
                    'preferred_time' => null, 'notes' => 'Zoë / 東京', 'status' => 'completed',
                ];
            }
            foreach ($rows as $table => $entries) {
                $this->insertRows($table, $entries);
            }
            $returnIds = DB::table('returns')->where('user_id', $owner->id)->orderBy('id')->pluck('id')->all();
            $refunds = [];
            for ($i = 0; $i < $count; $i++) {
                $refunds[] = ['user_id' => $owner->id, 'order_id' => $orderIds[$i], 'return_id' => $returnIds[$i],
                    'refund_number' => $prefix.'-refund-'.$i, 'amount' => 50000, 'status' => 'diterima',
                    'reason' => 'Synthetic completed refund', 'created_at' => $stamp, 'updated_at' => $stamp];
            }
            $this->insertRows('refunds', $refunds);
            // Existing unique(user_id, provider) allows only the two supported provider identities.
            foreach (['google', 'apple'] as $provider) {
                SocialAccount::create(['user_id' => $owner->id, 'provider' => $provider, 'provider_id' => $prefix.'-private-'.$provider]);
            }
        }

        return $owners;
    }
}
