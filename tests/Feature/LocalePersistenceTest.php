<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LocalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_persists_and_invalid_values_reset_to_indonesian(): void
    {
        $this->get('/koleksi?locale=en')->assertOk()->assertSessionHas('locale', 'en')->assertSee('<html lang="en">', false);
        $this->get('/koleksi')->assertOk()->assertSee('<html lang="en">', false);
        foreach (['xx', '../en', ['en']] as $locale) {
            $this->get('/koleksi?'.http_build_query(['locale' => $locale]))->assertOk()->assertSessionHas('locale', 'id')->assertSee('<html lang="id">', false);
        }
    }

    public function test_profile_is_used_without_session_and_session_then_query_take_priority(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'fr']));
        $this->get('/koleksi')->assertOk()->assertSee('<html lang="fr">', false);
        $this->withSession(['locale' => 'it'])->get('/koleksi')->assertSee('<html lang="it">', false);
        $this->get('/koleksi?locale=en')->assertSee('<html lang="en">', false);
    }

    public function test_anonymous_browsing_does_not_pin_default_over_profile_after_login(): void
    {
        $this->get('/koleksi')->assertOk()->assertSessionMissing('locale');
        $this->actingAs(User::factory()->create(['locale' => 'fr']));
        $this->get('/koleksi')->assertOk()->assertSee('<html lang="fr">', false);
    }

    public function test_locale_post_saves_profile_and_export_includes_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/locale', ['locale' => 'fr'])->assertNoContent()->assertSessionHas('locale', 'fr');
        $this->assertSame('fr', $user->fresh()->locale);
        $this->getJson('/akun/ekspor')->assertOk()->assertJsonPath('profile.locale', 'fr');
        $this->postJson('/locale', ['locale' => '../en'])->assertUnprocessable();
        $this->assertSame('fr', $user->fresh()->locale);
    }

    public function test_profile_update_validates_locale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/koleksi')->assertOk();
        $this->patch('/akun', ['name' => $user->name, 'locale' => 'it'])->assertSessionHasNoErrors();
        $this->assertSame('it', $user->fresh()->locale);
        $this->assertSame('it', session('locale'));
        $this->get('/koleksi')->assertOk()->assertSee('<html lang="it">', false);
        $this->patch('/akun', ['name' => $user->name, 'locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->assertSame('it', $user->fresh()->locale);
    }

    public function test_locale_post_requires_csrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/locale', ['locale' => 'en'])->assertStatus(419);
        $this->withSession(['_token' => 'locale-test-token'])->post('/locale', ['locale' => 'en', '_token' => 'locale-test-token'], ['Accept' => 'application/json'])->assertNoContent();
    }

    public function test_locale_post_is_throttled(): void
    {
        Cache::flush();
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/locale', ['locale' => 'en'])->assertNoContent();
        }
        $this->postJson('/locale', ['locale' => 'en'])->assertStatus(429);
    }
}
