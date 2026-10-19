<?php

namespace Tests\Feature;

use App\Http\Middleware\NoStore;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_admin_route_disables_caching(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(
            fn ($route) => str_starts_with($route->getName() ?? '', 'admin.')
                && in_array('auth:admin', $route->gatherMiddleware(), true)
        );

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains(NoStore::class, $route->gatherMiddleware(), $route->getName());
        }
    }

    public function test_unauthenticated_admin_redirect_is_private(): void
    {
        // auth:admin throws before NoStore can post-process, so Laravel's default redirect header applies.
        $response = $this->get('/admin')->assertRedirect(route('admin.login'));

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function test_admin_dashboard_response_is_not_cached(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_orders_response_is_not_cached(): void
    {
        $this->actingAs(Admin::create(['name' => 'Operator', 'email' => 'ops@example.test', 'password' => 'Password123!']), 'admin');

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
