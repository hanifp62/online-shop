<?php

namespace Tests\Feature;

use App\Support\FeatureFlags;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    public function test_boundaries_kill_switch_and_invalid_configuration(): void
    {
        $flags = new FeatureFlags;
        $this->assertFalse($flags->enabled('missing', '1'));
        foreach ([[true, 100, true], [true, 0, false], [false, 100, false], [true, -1, false], [true, 101, false], [true, 'bad', false], ['false', 100, false]] as [$enabled, $rollout, $expected]) {
            config(['features.example' => compact('enabled', 'rollout')]);
            $this->assertSame($expected, $flags->enabled('example', '1'));
        }
    }

    public function test_cohorts_are_stable_and_nested(): void
    {
        $flags = new FeatureFlags;
        $included = 0;
        for ($id = 0; $id < 1000; $id++) {
            config(['features.example' => ['enabled' => true, 'rollout' => 25]]);
            $selected = $flags->enabled('example', (string) $id);
            $this->assertSame($selected, $flags->enabled('example', (string) $id));
            $included += (int) $selected;
            config(['features.example.rollout' => 50]);
            if ($selected) {
                $this->assertTrue($flags->enabled('example', (string) $id));
            }
        }
        $this->assertGreaterThan(200, $included);
        $this->assertLessThan(300, $included);
    }

    public function test_middleware_switches_without_route_rebuild(): void
    {
        Route::get('/flag-test', fn () => response('ok'))->middleware('feature:admin_sales_summary');
        $this->get('/flag-test')->assertOk();
        config(['features.admin_sales_summary.enabled' => false]);
        $this->getJson('/flag-test')->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        config(['features.admin_sales_summary.enabled' => true, 'features.admin_sales_summary.rollout' => 0]);
        $this->getJson('/flag-test')->assertNotFound();
    }

    public function test_real_report_route_uses_flag_and_existing_authorization(): void
    {
        $middleware = Route::getRoutes()->getByName('admin.reports.sales-summary')->gatherMiddleware();
        $this->assertContains('feature:admin_sales_summary', $middleware);
        $this->assertContains('auth:admin', $middleware);
        $this->assertContains('admin.can:view-reports', $middleware);
    }
}
