<?php

namespace Tests\Feature;

use App\Models\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FR-ADM-20261025 / NFR-THEME-20261025: dijalankan integrator dalam harness PHP terisolasi. */
class AdminWorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'frontend.enabled' => true,
            'frontend.pages' => ['Admin/Dashboard', 'Admin/AuthEvents', 'Admin/OperationsSummary'],
            'features.admin_ops_summary' => ['enabled' => true, 'rollout' => 100],
        ]);
    }

    private function login(string $role = Admin::ROLE_ADMIN): void
    {
        $this->actingAs(Admin::create([
            'name' => 'Operator <script>unsafe</script>',
            'email' => 'shell-operator@example.test',
            'password' => 'Password123!',
            'role' => $role,
        ]), 'admin');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function navigationLinks(DOMXPath $xpath): array
    {
        return array_map(
            fn ($node) => $node->nodeValue,
            iterator_to_array($xpath->query('//nav[@data-admin-active]//a/@href'))
        );
    }

    public function test_shared_shell_is_outside_the_native_root_on_all_read_pages_and_blade_fallback(): void
    {
        $this->login();
        foreach (['admin.dashboard', 'admin.auth-events.index', 'admin.reports.operations-summary'] as $route) {
            foreach ([[], ['frontend' => 'blade']] as $query) {
                $response = $this->get(route($route, $query))->assertOk();
                $html = $response->getContent();
                $xpath = $this->xpath($html);
                $this->assertSame(1, $xpath->query('//main[@id="main-content"]')->length);
                $this->assertSame(1, $xpath->query('//h1')->length);
                $this->assertSame(1, $xpath->query('//aside//details[@data-admin-navigation]/summary')->length);
                $this->assertSame(1, $xpath->query('//select[@data-theme-select]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="app"]//select[@data-theme-select]')->length);
                $this->assertSame(0, $xpath->query('//*[@id="app"]//nav[@data-admin-active]')->length);
                $this->assertSame($query ? 0 : 1, $xpath->query('//*[@data-react-fallback]')->length);
                $this->assertSame(0, $xpath->query('//*[@inert]')->length);
                $values = array_map(fn ($node) => $node->nodeValue, iterator_to_array($xpath->query('//select[@data-theme-select]/option/@value')));
                $this->assertSame(['light', 'dark', 'system'], $values);
                $response->assertSee('href="'.route('admin.profile.show').'"', false)
                    ->assertSee('Operator &lt;script&gt;unsafe&lt;/script&gt;', false)
                    ->assertDontSee('<script>unsafe</script>', false);
                $logout = $xpath->query('//form[@action="'.route('admin.logout').'"]')->item(0);
                $this->assertNotNull($logout);
                $this->assertSame('POST', $logout->getAttribute('method'));
                $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $logout)->length);
                $this->assertContains(route('admin.reports.operations-summary'), $this->navigationLinks($xpath));
            }
        }
    }

    public function test_staff_navigation_only_exposes_allowed_destinations_even_when_report_flag_is_on(): void
    {
        $this->login(Admin::ROLE_STAFF);
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $links = $this->navigationLinks($this->xpath($response->getContent()));
        foreach (['#panel-pesanan', '#panel-produk', '#panel-concierge', '#panel-user', route('admin.dashboard'), route('home')] as $url) {
            $this->assertContains($url, $links);
        }
        foreach (['#panel-kategori', '#panel-subscriber', '#panel-audit', route('admin.auth-events.index'), route('admin.reports.operations-summary')] as $url) {
            $this->assertNotContains($url, $links);
        }
        $response->assertSee('href="'.route('admin.profile.show').'"', false);
    }

    public function test_admin_does_not_get_an_optional_report_link_when_the_flag_is_off(): void
    {
        $this->login();
        config(['features.admin_ops_summary' => ['enabled' => false, 'rollout' => 0]]);
        $response = $this->get(route('admin.dashboard'))->assertOk();
        $links = $this->navigationLinks($this->xpath($response->getContent()));
        $this->assertContains(route('admin.auth-events.index'), $links);
        $this->assertContains('#panel-kategori', $links);
        $this->assertNotContains(route('admin.reports.operations-summary'), $links);
    }

    public function test_overview_displays_server_counts_and_keeps_all_seven_replace_regions(): void
    {
        $this->login();
        $response = $this->get(route('admin.dashboard', ['frontend' => 'blade']))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $stats = $response->viewData('stats');
        $this->assertSame(7, $xpath->query('//*[@data-dashboard-metric]')->length);
        foreach ($stats as $key => $count) {
            $this->assertSame((string) $count, $xpath->evaluate('string(//*[@data-dashboard-metric="'.$key.'"]/dd)'));
        }
        $regions = array_map(fn ($node) => $node->nodeValue, iterator_to_array($xpath->query('//*[@data-admin-table]/@data-admin-table')));
        $this->assertSame(['pesanan', 'kategori', 'produk', 'user', 'audit', 'concierge', 'subscriber'], $regions);
        foreach (['admin.categories.store', 'admin.products.store', 'admin.orders.report', 'admin.password.update'] as $route) {
            $form = $xpath->query('//form[@action="'.route($route).'"]')->item(0);
            $this->assertNotNull($form);
            $this->assertSame(0, $xpath->query('ancestor::*[@data-admin-table]', $form)->length);
        }
    }
}
