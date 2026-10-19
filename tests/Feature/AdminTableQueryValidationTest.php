<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ConciergeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTableQueryValidationTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');
    }

    public static function invalidDirections(): array
    {
        return array_map(fn ($prefix) => [$prefix.'dir'], ['k', 'p', 'o', 'b', 's', 'c', 'u']);
    }

    #[DataProvider('invalidDirections')]
    public function test_invalid_direction_returns_validation_error(string $key): void
    {
        $this->login();
        $this->getJson(route('admin.dashboard', [$key => 'DROP']))
            ->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function invalidSorts(): array
    {
        return array_map(fn ($prefix) => [$prefix.'sort'], ['k', 'p', 'o', 'b', 's', 'c', 'u']);
    }

    #[DataProvider('invalidSorts')]
    public function test_non_allowlisted_sort_is_rejected(string $key): void
    {
        $this->login();
        $this->getJson(route('admin.dashboard', [$key => 'password']))
            ->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function invalidPages(): array
    {
        return [[1001]];
    }

    #[DataProvider('invalidPages')]
    public function test_out_of_range_order_page_is_rejected(int $page): void
    {
        $this->login();
        $this->getJson(route('admin.dashboard', ['opage' => $page]))
            ->assertUnprocessable()->assertJsonValidationErrors('opage');
    }

    public function test_malformed_order_page_is_ignored_and_not_repeated(): void
    {
        $this->login();
        // 0 dan -1 gagal regex pra-validasi, jadi dibuang (bukan 422) seperti 'abc' dan array.
        foreach (['0', '-1', 'abc', [1]] as $page) {
            $this->get(route('admin.dashboard', ['opage' => $page]))
                ->assertOk()->assertViewHas('latestOrders', fn ($rows) => $rows->currentPage() === 1)
                ->assertViewHas('tableState', fn ($state) => ! array_key_exists('opage', $state))
                ->assertDontSee('opage=', false);
        }
    }

    public function test_overlong_concierge_query_is_rejected(): void
    {
        $this->login();
        $this->getJson(route('admin.dashboard', ['q' => str_repeat('a', 256)]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_unknown_status_filters_are_rejected(): void
    {
        $this->login();
        foreach (['status', 'ostatus'] as $key) {
            $this->getJson(route('admin.dashboard', [$key => 'not-a-status']))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_zero_query_filters_concierge_rows(): void
    {
        ConciergeRequest::create(['name' => '0', 'email' => 'zero@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        ConciergeRequest::create(['name' => 'Other', 'email' => 'other@example.test', 'phone' => '123', 'service' => 'consultation', 'preferred_date' => now()->addDay(), 'status' => 'new']);
        $this->login();
        $this->get(route('admin.dashboard', ['q' => '0']))->assertOk()
            ->assertViewHas('latestConcierge', fn ($rows) => $rows->total() === 1 && $rows->items()[0]->email === 'zero@example.test');
    }
}
