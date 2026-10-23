<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admin;
use App\Models\User;
use App\Support\TotpEnrolPageProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** FE-COMP-20261023 SECURITY; run only via the guarded isolated harness. */
class InertiaSecurityEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['Account/TotpEnrol', 'Account/TotpRecovery', 'Admin/TotpEnrol', 'Admin/TotpRecovery', 'Account/CartReminderUnsubscribe'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['frontend.enabled' => true, 'frontend.pages' => self::PAGES]);
    }

    public static function guards(): array
    {
        return [[false], [true]];
    }

    private function identity(bool $admin): User|Admin
    {
        $identity = $admin ? Admin::create(['name' => 'Security test', 'email' => 'enrol@example.test', 'password' => 'Test-only-password!123']) : User::factory()->create();
        $this->actingAs($identity, $admin ? 'admin' : 'web');

        return $identity;
    }

    private function recoveryState(bool $admin, int $id): array
    {
        return [
            ($admin ? 'totp_enrol_codes' : 'customer_totp_codes') => Crypt::encryptString(json_encode(['test-only-recovery'])),
            ($admin ? 'totp_enrol_codes_state' : 'customer_totp_codes_state') => [($admin ? 'admin_id' : 'user_id') => $id, 'expires_at' => now()->addMinutes(5)->timestamp],
        ];
    }

    #[DataProvider('guards')]
    public function test_enrol_keeps_material_in_flash_and_real_blade_fallback(bool $admin): void
    {
        $this->identity($admin);
        $route = $admin ? 'admin.totp.enrol' : 'account.totp.enrol';
        $component = $admin ? 'Admin/TotpEnrol' : 'Account/TotpEnrol';
        $response = $this->withSession(['_old_input' => ['code' => 'private-old-marker', 'password' => 'private-old-marker']])->get(route($route));
        $this->assertSame(200, $response->status());
        $page = $response->viewData('page');
        $this->assertSame($component, $page['component']);
        $material = $page['flash']['totpMaterial'] ?? [];
        $this->assertTrue(is_string($material['secret'] ?? null));
        $this->assertTrue(str_starts_with($material['qr'] ?? '', 'data:image/svg+xml;base64,'));
        $serialized = json_encode($page['props']);
        $this->assertFalse(str_contains($serialized, $material['secret']));
        $this->assertFalse(str_contains($serialized, 'private-old-marker'));
        $this->assertArrayNotHasKey('old', $page['props']);
        $this->assertTrue($page['encryptHistory']);
        $this->assertTrue($page['clearHistory']);
        $this->assertTrue(str_contains($response->headers->get('Cache-Control'), 'no-store'));
        $this->assertTrue(str_contains($response->headers->get('Vary'), 'X-Inertia'));
        $html = $response->getContent();
        foreach (['data-react-fallback', 'data-page="app"', 'name="code"', 'name="_token"', 'zafira:private-leave'] as $marker) {
            $this->assertTrue(str_contains($html, $marker));
        }
        foreach (['disabled', 'allowlist', 'request'] as $mode) {
            config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'allowlist' ? [] : self::PAGES]);
            $fallback = $this->get(route($route).($mode === 'request' ? '?frontend=blade' : ''));
            $this->assertSame(200, $fallback->status());
            $this->assertFalse(str_contains($fallback->getContent(), 'data-page="app"'));
            $this->assertTrue(str_contains($fallback->getContent(), 'name="code"'));
            $this->assertTrue(str_contains($fallback->getContent(), 'zafira:private-leave'));
        }
    }

    #[DataProvider('guards')]
    public function test_recovery_is_flash_only_one_time_and_prefetch_does_not_consume(bool $admin): void
    {
        $identity = $this->identity($admin);
        $state = $this->recoveryState($admin, $identity->id);
        $url = route($admin ? 'admin.totp.enrol.recovery' : 'account.totp.recovery');
        $this->withSession($state)->get($url, ['Purpose' => 'prefetch'])->assertNoContent();
        $response = $this->get($url);
        $this->assertSame(200, $response->status());
        $page = $response->viewData('page');
        $this->assertTrue(($page['flash']['totpMaterial']['codes'] ?? []) === ['test-only-recovery']);
        $this->assertFalse(str_contains(json_encode($page['props']), 'test-only-recovery'));
        $this->assertTrue($page['encryptHistory']);
        $this->assertTrue(str_contains($response->getContent(), 'zafira:private-leave'));
        $this->get($url)->assertRedirect(route($admin ? 'admin.dashboard' : 'account.show'));
        $version = $page['version'];
        $json = $this->withSession($state)->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(200, $json->status());
        $this->assertTrue(($json->json('flash.totpMaterial.codes') ?? []) === ['test-only-recovery']);
        $this->assertFalse(str_contains(json_encode($json->json('props')), 'test-only-recovery'));
        $this->get($url)->assertRedirect(route($admin ? 'admin.dashboard' : 'account.show'));
        config(['frontend.pages' => []]);
        $fallback = $this->withSession($state)->get($url);
        $this->assertSame(200, $fallback->status());
        $this->assertFalse(str_contains($fallback->getContent(), 'data-page="app"'));
        $this->assertTrue(str_contains($fallback->getContent(), '<code>test-only-recovery</code>') || str_contains($fallback->getContent(), '>test-only-recovery</li>'));
        $this->get($url)->assertRedirect(route($admin ? 'admin.dashboard' : 'account.show'));
    }

    public static function recoveryDocumentModes(): array
    {
        $cases = [];
        foreach ([false, true] as $admin) {
            foreach (['stale-version', 'disabled', 'query-blade', 'not-allowlisted'] as $mode) {
                $cases[($admin ? 'admin ' : 'customer ').$mode] = [$admin, $mode];
            }
        }

        return $cases;
    }

    #[DataProvider('recoveryDocumentModes')]
    public function test_recovery_document_redirect_preserves_state_until_one_normal_get(bool $admin, string $mode): void
    {
        $identity = $this->identity($admin);
        $state = $this->recoveryState($admin, $identity->id);
        config(['frontend.enabled' => $mode !== 'disabled', 'frontend.pages' => $mode === 'not-allowlisted' ? [] : self::PAGES]);
        $url = route($admin ? 'admin.totp.enrol.recovery' : 'account.totp.recovery')
            .'?category=a&category=b&signature=synthetic%2Bmarker'.($mode === 'query-blade' ? '&frontend=blade' : '');
        $version = (string) app(HandleInertiaRequests::class)->version(Request::create($url));
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $mode === 'stale-version' ? $version.'-outdated' : $version,
            'X-Requested-With' => 'XMLHttpRequest'];
        $redirect = $this->withSession($state)->get($url, $headers);
        $this->assertSame(409, $redirect->status());
        $this->assertSame($url, $redirect->headers->get('X-Inertia-Location'));
        $this->assertTrue(str_contains($redirect->headers->get('Cache-Control'), 'no-store'));
        $this->assertTrue(str_contains($redirect->headers->get('Vary'), 'X-Inertia'));
        $this->assertFalse(str_contains($redirect->getContent(), 'test-only-recovery'));
        foreach ($state as $key => $expected) {
            $this->assertTrue(session()->get($key) === $expected);
        }

        $document = $this->get($url);
        $this->assertSame(200, $document->status());
        if ($mode === 'stale-version') {
            $this->assertTrue(($document->viewData('page')['flash']['totpMaterial']['codes'] ?? []) === ['test-only-recovery']);
        } else {
            $this->assertFalse(str_contains($document->getContent(), 'data-page="app"'));
            $this->assertTrue(str_contains($document->getContent(), 'test-only-recovery'));
        }
        foreach (array_keys($state) as $key) {
            $this->assertFalse(session()->has($key));
        }
        $this->get($url)->assertRedirect(route($admin ? 'admin.dashboard' : 'account.show'));
    }

    public function test_recovery_document_helper_does_not_redirect_post_or_non_inertia_requests(): void
    {
        config(['frontend.enabled' => false]);
        foreach ([false, true] as $admin) {
            $post = Request::create('/api/recovery', 'POST');
            $post->headers->set('X-Inertia', 'true');
            $this->assertNull(TotpEnrolPageProps::recoveryDocumentVisit($post, $admin));
            $api = Request::create('/api/recovery', 'GET');
            $api->headers->set('Accept', 'application/json');
            $this->assertNull(TotpEnrolPageProps::recoveryDocumentVisit($api, $admin));
        }
    }

    #[DataProvider('guards')]
    public function test_enrol_prefetch_does_not_create_or_rotate_pending_secret(bool $admin): void
    {
        $this->identity($admin);
        $key = $admin ? 'totp_enrol_secret' : 'customer_totp_secret';
        $url = route($admin ? 'admin.totp.enrol' : 'account.totp.enrol');
        $this->withSession([$key => 'pending-marker'])->get($url, ['Sec-Purpose' => 'prefetch'])->assertNoContent()->assertSessionHas($key, 'pending-marker');
    }

    public function test_signed_unsubscribe_confirmation_preserves_signature_and_get_does_not_mutate(): void
    {
        $user = User::factory()->create(['cart_reminder_opt_in' => true]);
        $url = URL::temporarySignedRoute('cart-reminder.unsubscribe', now()->addHour(), ['user' => $user->id]);
        $response = $this->get($url);
        $this->assertSame(200, $response->status());
        $page = $response->viewData('page');
        $this->assertSame('Account/CartReminderUnsubscribe', $page['component']);
        $this->assertTrue($page['props']['urls']['submit'] === $url);
        $this->assertTrue((bool) $user->fresh()->cart_reminder_opt_in);
        $this->assertArrayNotHasKey('user', $page['props']);
        $this->assertTrue(str_contains($response->getContent(), 'name="_token"'));
        $this->get(route('cart-reminder.unsubscribe', $user))->assertForbidden();
        config(['frontend.pages' => []]);
        $fallback = $this->get($url);
        $this->assertSame(200, $fallback->status());
        $this->assertFalse(str_contains($fallback->getContent(), 'data-page="app"'));
        $completedFallback = $this->post($url)->assertOk();
        $this->assertFalse((bool) $user->fresh()->cart_reminder_opt_in);
        $this->assertTrue(str_contains($completedFallback->getContent(), 'role="status"'));
        $this->assertFalse(str_contains($completedFallback->getContent(), '<form'));
    }

    public function test_signed_unsubscribe_post_completion_is_native_and_idempotent_with_blade_rollback(): void
    {
        $user = User::factory()->create(['cart_reminder_opt_in' => true]);
        $url = URL::temporarySignedRoute('cart-reminder.unsubscribe', now()->addHour(), ['user' => $user->id]);
        $unsigned = route('cart-reminder.unsubscribe.store', $user);
        $this->post($unsigned)->assertForbidden();
        $this->assertTrue((bool) $user->fresh()->cart_reminder_opt_in);
        $first = $this->post($url);
        $this->assertSame(200, $first->status());
        $page = $first->viewData('page');
        $this->assertSame('Account/CartReminderUnsubscribe', $page['component']);
        $this->assertTrue($page['props']['done']);
        $this->assertNull($page['props']['urls']['submit']);
        $this->assertSame(__('account_ui.unsubscribe.done_heading'), $page['props']['copy']['page']['heading']);
        $this->assertSame(__('account_ui.unsubscribe.done_intro'), $page['props']['copy']['page']['intro']);
        $this->assertArrayNotHasKey('user', $page['props']);
        $this->assertFalse((bool) $user->fresh()->cart_reminder_opt_in);
        $this->assertTrue(str_contains($first->getContent(), 'data-react-fallback'));
        $this->assertTrue(str_contains($first->getContent(), 'role="status"'));
        $this->assertFalse(str_contains($first->getContent(), '<form'));
        $this->assertTrue(str_contains($first->headers->get('Cache-Control'), 'no-store'));
        $this->assertTrue(str_contains($first->headers->get('Vary'), 'X-Inertia'));
        $second = $this->post($url)->assertOk();
        $this->assertTrue($second->viewData('page')['props']['done']);
        $this->assertFalse((bool) $user->fresh()->cart_reminder_opt_in);
        config(['frontend.enabled' => false]);
        $fallback = $this->post($url)->assertOk();
        $this->assertFalse(str_contains($fallback->getContent(), 'data-page="app"'));
        $this->assertTrue(str_contains($fallback->getContent(), 'role="status"'));
        $this->assertFalse(str_contains($fallback->getContent(), '<form'));
    }
}
