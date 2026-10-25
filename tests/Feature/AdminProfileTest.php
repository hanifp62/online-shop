<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminTotp;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** FR-PROFILE-20261025 / NFR-SAFE-20261025: integrator's isolated PHP harness only. */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['frontend.enabled' => true, 'frontend.pages' => ['Admin/Profile'],
            'inertia.testing.page_paths' => [resource_path('js/pages')]]);
    }

    private function admin(string $role = Admin::ROLE_ADMIN): Admin
    {
        return Admin::create(['name' => 'Nadia Putri', 'email' => 'profile@example.test',
            'password' => 'Original123!', 'role' => $role]);
    }

    public function test_profile_is_get_only_and_rejects_guests_and_customer_guard(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.profile.show');
        $this->assertSame('admin/profil', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['', '?frontend=blade'] as $suffix) {
            $this->get('/admin/profil'.$suffix)->assertRedirect(route('admin.login'));
        }
        $this->actingAs(User::factory()->create())->get('/admin/profil')->assertRedirect(route('admin.login'));
        $this->post('/admin/profil')->assertStatus(405);
        $this->patch('/admin/profil')->assertStatus(405);
    }

    public function test_native_and_fallback_profile_whitelist_identity_and_never_expose_old_credentials(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['remember_token' => 'remember-sentinel'])->save();
        $this->actingAs($admin, 'admin')->withSession(['_old_input' => ['password' => 'credential-sentinel',
            'current_password' => 'credential-sentinel', 'password_confirmation' => 'credential-sentinel'],
            'private_state' => 'session-sentinel']);
        $response = $this->get('/admin/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Profile')->where('identity.name', 'Nadia Putri')->where('identity.initials', 'NP')
            ->where('identity.email', $admin->email)->where('security.totpEnabled', false)
            ->where('urls.password', route('admin.password.update'))->where('urls.authEvents', route('admin.auth-events.index'))
            ->missing('admin')->missing('user')->missing('old')->missing('session')->missing('csrfToken'));
        $this->assertSame(['name', 'email', 'initials', 'role'], array_keys($response->viewData('page')['props']['identity']));
        $this->assertTrue($response->viewData('page')['encryptHistory']);
        $response->assertSee('data-react-fallback', false)->assertSee('name="_method" value="PATCH"', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['credential-sentinel', 'session-sentinel', 'remember-sentinel', $admin->password] as $sentinel) {
            $response->assertDontSee($sentinel, false);
        }
        $this->get('/admin/profil?frontend=blade')->assertOk()->assertDontSee('data-react-fallback', false)
            ->assertSee('Nadia Putri')->assertSee('action="'.route('admin.password.update').'"', false)
            ->assertSee('zafira:private-leave', false)->assertDontSee('credential-sentinel', false);
        config(['frontend.enabled' => false]);
        $this->get('/admin/profil')->assertOk()->assertDontSee('data-react-fallback', false);
    }

    public function test_staff_has_no_audit_link_and_unknown_role_is_denied(): void
    {
        $admin = $this->admin(Admin::ROLE_STAFF);
        $this->actingAs($admin, 'admin')->get('/admin/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Profile')->where('urls.authEvents', null)->where('urls.password', route('admin.password.update')));
        $this->get('/admin/profil?frontend=blade')->assertOk()->assertDontSee('href="'.route('admin.auth-events.index').'"', false);
        $admin->forceFill(['role' => 'unknown'])->save();
        $this->get('/admin/profil')->assertForbidden();
    }

    public function test_totp_gate_is_required_for_both_renderers_and_valid_marker_reveals_only_status(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['totp_required_at' => now(), 'totp_secret' => 'JBSWY3DPEHPK3PXP', 'totp_last_used_at' => 123])->save();
        $this->actingAs($admin, 'admin');
        foreach (['', '?frontend=blade'] as $suffix) {
            $this->get('/admin/profil'.$suffix)->assertRedirect(route('admin.totp.challenge'));
        }
        $this->withSession([]);
        $sessionId = session()->getId();
        $marker = ['admin_id' => $admin->id, 'session_id' => $sessionId, 'last_used_at' => 123];
        // StartSession takes its ID from the request cookie, not from withSession().
        $this->withCookie(config('session.cookie'), $sessionId);
        $this->withSession([EnsureAdminTotp::SESSION_KEY => $marker])->get('/admin/profil')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Profile')
                ->where('security.totpEnabled', true)->where('urls.totpEnrol', null))
            ->assertDontSee('JBSWY3DPEHPK3PXP', false);
        $this->get('/admin/profil?frontend=blade')->assertOk()
            ->assertDontSee('data-react-fallback', false)->assertSee(__('profile_ui.totp_active'))
            ->assertDontSee('JBSWY3DPEHPK3PXP', false);

        foreach (['admin_id' => $admin->id + 1, 'session_id' => $sessionId.'-other', 'last_used_at' => 122] as $field => $invalid) {
            foreach (['', '?frontend=blade'] as $suffix) {
                $this->withSession([EnsureAdminTotp::SESSION_KEY => array_replace($marker, [$field => $invalid])]);
                // StartSession reloads the handler over in-memory attributes on each request.
                session()->save();
                $this->get('/admin/profil'.$suffix)->assertRedirect(route('admin.totp.challenge'))
                    ->assertSessionMissing(EnsureAdminTotp::SESSION_KEY);
            }
        }
    }

    public function test_existing_secret_without_required_flag_does_not_offer_an_unusable_enrolment_link(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['totp_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin, 'admin')->get('/admin/profil')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Profile')
                ->where('security.totpEnabled', false)->where('urls.totpEnrol', null))
            ->assertDontSee('JBSWY3DPEHPK3PXP', false);
    }

    public function test_existing_password_endpoint_validates_and_rotates_without_changing_identity_or_customer(): void
    {
        $admin = $this->admin(Admin::ROLE_STAFF);
        $customer = User::factory()->create();
        $customerHash = $customer->password;
        $this->actingAs($customer)->actingAs($admin, 'admin')->from('/admin/profil')
            ->patch(route('admin.password.update'), ['current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])
            ->assertRedirect('/admin/profil')->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Original123!', $admin->fresh()->password));
        $this->from('/admin/profil')->patch(route('admin.password.update'), ['current_password' => 'Original123!',
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!', 'role' => 'admin', 'email' => 'other@example.test'])
            ->assertRedirect('/admin/profil')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword123!', $admin->fresh()->password));
        $this->assertSame(Admin::ROLE_STAFF, $admin->fresh()->role);
        $this->assertSame('profile@example.test', $admin->fresh()->email);
        $this->assertSame($customerHash, $customer->fresh()->password);
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_profile_translations_have_five_locale_parity_and_render_in_both_modes(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $keys = array_keys(require lang_path('en/profile_ui.php'));
        foreach (['id', 'en', 'fr', 'it', 'zh'] as $locale) {
            $copy = require lang_path($locale.'/profile_ui.php');
            $this->assertSame($keys, array_keys($copy));
            $this->get('/admin/profil?locale='.$locale)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Profile')->where('copy.title', $copy['title']))
                ->assertSee($copy['theme'])->assertSee($copy['theme_light'])->assertSee($copy['theme_dark'])->assertSee($copy['theme_system']);
            $this->get('/admin/profil?locale='.$locale.'&frontend=blade')->assertOk()->assertSee($copy['identity'])
                ->assertSee($copy['theme'])->assertSee($copy['theme_light'])->assertSee($copy['theme_dark'])->assertSee($copy['theme_system']);
        }
    }
}
