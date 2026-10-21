<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleKernelRegressionTest extends TestCase
{
    public function test_unmatched_url_resolves_query_and_existing_session_locale(): void
    {
        $this->get('/__missing-locale-page?locale=it')->assertNotFound()
            ->assertSee('<html lang="it">', false);
        $this->withSession(['locale' => 'fr'])->get('/__missing-locale-page')->assertNotFound()
            ->assertSee('<html lang="fr">', false);
    }

    public function test_unmatched_url_reads_encrypted_session_cookie_and_preserves_json_errors(): void
    {
        $this->get('/__missing-seed-page?locale=fr')->assertNotFound();
        $sessionId = session()->getId();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->get('/__missing-cookie-page')->assertNotFound()
            ->assertSee('<html lang="fr">', false);
        $this->getJson('/__missing-json-page')->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_real_csrf_rejection_resolves_locale_without_accepting_post(): void
    {
        // Laravel bypasses CSRF in testing; change only the environment check, not middleware/token validation.
        $this->app->instance('env', 'production');
        $this->withSession(['locale' => 'fr'])->post('/locale', ['locale' => 'en'])
            ->assertStatus(419)->assertSee('<html lang="fr">', false);
        $this->post('/locale?locale=it', ['locale' => 'en'])
            ->assertStatus(419)->assertSee('<html lang="it">', false);
    }
}
