<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminTotp;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/** Pagar berkelanjutan: route sensitif baru tanpa guard menggagalkan test (lihat THREAT_MODEL_STRIDE.md). */
class AuthorizationInventoryTest extends TestCase
{
    private const ADMIN_OPEN = ['admin.login', 'admin.login.process', 'admin.logout'];

    /** Route yang dipakai sebelum login admin penuh; harus dijaga auth:admin saja. */
    private const ADMIN_TOTP_PREFIX = 'admin.totp.';

    /** @return list<Route> */
    private function routes(string $prefix): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $r) => str_starts_with($r->uri(), $prefix)
        ));
    }

    public function test_every_admin_route_requires_totp_and_a_defined_gate(): void
    {
        $checked = 0;
        foreach ($this->routes('admin') as $route) {
            $name = (string) $route->getName();
            if (in_array($name, self::ADMIN_OPEN, true)) {
                continue;
            }
            $mw = $route->gatherMiddleware();
            $this->assertContains('auth:admin', $mw, "$name tanpa auth:admin");

            if (str_starts_with($name, self::ADMIN_TOTP_PREFIX)) {
                continue;
            }
            $this->assertContains(EnsureAdminTotp::class, $mw, "$name tanpa TOTP");
            $gates = array_values(array_filter($mw, fn ($m) => is_string($m) && str_starts_with($m, 'admin.can:')));
            $this->assertCount(1, $gates, "$name harus punya tepat satu admin.can:*");
            $ability = substr($gates[0], strlen('admin.can:'));
            $this->assertTrue(Gate::has($ability), "$name memakai Gate '$ability' yang tidak terdefinisi");
            $checked++;
        }
        $this->assertGreaterThan(10, $checked);
    }

    public function test_customer_money_routes_keep_their_guards(): void
    {
        $expect = [
            'checkout.store' => ['throttle:checkout'],
            'checkout.quote' => ['auth', 'throttle:cart'],
            'orders.show' => ['auth'],
            'orders.cancel' => ['auth'],
            'returns.store' => ['auth', 'throttle:cart'],
            'refunds.store' => ['auth'],
            'orders.track' => ['throttle:track'],
            'webhooks.midtrans' => ['throttle:60,1'],
        ];
        foreach ($expect as $name => $needed) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route $name hilang");
            foreach ($needed as $m) {
                $this->assertContains($m, $route->gatherMiddleware(), "$name kehilangan $m");
            }
        }
    }

    public function test_refund_approval_is_not_reachable_from_customer_routes(): void
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! in_array('PATCH', $route->methods(), true) || ! str_contains($route->uri(), 'refunds')) {
                continue;
            }
            $this->assertStringStartsWith('admin/', $route->uri(), 'transisi refund harus berada di bawah admin/');
        }
    }

    public function test_public_money_write_routes_keep_rate_limits(): void
    {
        foreach (['checkout.store', 'orders.track', 'webhooks.midtrans'] as $name) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertNotEmpty(array_filter(
                $route->gatherMiddleware(),
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:')
            ), "$name kehilangan rate limit");
        }
    }
}
