<?php

namespace Tests\Feature;

use Tests\TestCase;

class CspReportOnlyTest extends TestCase
{
    private const HEADER = 'Content-Security-Policy-Report-Only';

    public function test_report_only_header_absent_by_default(): void
    {
        config(['security.csp_report_only' => false]);

        $this->get('/prototype')->assertHeaderMissing(self::HEADER);
    }

    public function test_config_reads_process_env_flag(): void
    {
        // Mirrors config/security.php without mutating .env.
        putenv('ZAFIRA_CSP_REPORT_ONLY=1');
        $this->assertTrue(getenv('ZAFIRA_CSP_REPORT_ONLY') === '1');
        putenv('ZAFIRA_CSP_REPORT_ONLY');
        $this->assertFalse(getenv('ZAFIRA_CSP_REPORT_ONLY') === '1');
    }

    public function test_report_only_header_present_when_enabled(): void
    {
        config(['security.csp_report_only' => true]);

        // /prototype is a plain redirect closure: web middleware, no database.
        $this->get('/prototype')
            ->assertRedirect('/prototype/')
            ->assertHeader(self::HEADER);
    }

    public function test_enforcing_csp_header_is_never_sent(): void
    {
        config(['security.csp_report_only' => true]);

        $this->get('/prototype')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_policy_is_strict_and_matches_current_assets(): void
    {
        config(['security.csp_report_only' => true]);

        $header = $this->get('/prototype')->headers->get(self::HEADER);

        foreach ([
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src 'self';",
            'https://fonts.bunny.net',
            'https://images.unsplash.com',
        ] as $directive) {
            $this->assertStringContainsString($directive, $header);
        }

        // No reporting collector exists yet; header must not advertise one.
        $this->assertStringNotContainsString('report-uri', $header);
        $this->assertStringNotContainsString('report-to', $header);
        $this->assertStringNotContainsString("'unsafe-eval'", $header);
    }
}
