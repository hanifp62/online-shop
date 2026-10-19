<?php

namespace Tests\Feature;

use App\Models\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthFormA11yTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    /** Field is invalid and its aria-describedby target holds the session error message. */
    private function assertFieldError(string $html, string $field): void
    {
        $xpath = $this->xpath($html);
        $input = $xpath->query("//input[@name='{$field}']")->item(0);
        $this->assertNotNull($input, "input {$field} rendered");
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame("{$field}-error", $input->getAttribute('aria-describedby'));

        $target = $xpath->query("//*[@id='{$field}-error']");
        $this->assertSame(1, $target->length, "exactly one #{$field}-error");
        $this->assertNotSame('', trim($target->item(0)->textContent), "#{$field}-error holds a message");
    }

    private function assertFieldClean(string $html, string $field): void
    {
        $xpath = $this->xpath($html);
        $input = $xpath->query("//input[@name='{$field}']")->item(0);
        $this->assertNotNull($input, "input {$field} rendered");
        $this->assertFalse($input->hasAttribute('aria-invalid'));
        $this->assertFalse($input->hasAttribute('aria-describedby'));
        $this->assertSame(0, $xpath->query("//*[@id='{$field}-error']")->length);
    }

    public function test_login_fields_reference_their_errors_and_omit_invalid_state_without_errors(): void
    {
        $clean = $this->get(route('admin.login'))->assertOk()->getContent();
        $this->assertFieldClean($clean, 'email');
        $this->assertFieldClean($clean, 'password');

        $this->from(route('admin.login'))
            ->post(route('admin.login.process'), ['email' => 'not-an-email', 'password' => ''])
            ->assertRedirect(route('admin.login'));

        $html = $this->get(route('admin.login'))->getContent();
        $this->assertFieldError($html, 'email');
        $this->assertFieldError($html, 'password');
    }

    public function test_login_password_stays_clean_when_only_email_fails(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login.process'), ['email' => 'not-an-email', 'password' => 'secret'])
            ->assertRedirect(route('admin.login'));

        $html = $this->get(route('admin.login'))->getContent();
        $this->assertFieldError($html, 'email');
        $this->assertFieldClean($html, 'password');
    }

    public function test_totp_fields_reference_their_errors_and_omit_invalid_state_without_errors(): void
    {
        $admin = Admin::create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => 'Password123!',
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_required_at' => now(),
        ]);

        $clean = $this->actingAs($admin, 'admin')->get(route('admin.totp.challenge'))->assertOk()->getContent();
        $this->assertFieldClean($clean, 'code');
        $this->assertFieldClean($clean, 'recovery_code');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.totp.challenge'))
            ->post(route('admin.totp.verify'), ['code' => '123'])
            ->assertRedirect(route('admin.totp.challenge'));

        $html = $this->get(route('admin.totp.challenge'))->getContent();
        $this->assertFieldError($html, 'code');
        $this->assertFieldClean($html, 'recovery_code');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.totp.challenge'))
            ->post(route('admin.totp.recovery'), ['recovery_code' => ''])
            ->assertRedirect(route('admin.totp.challenge'));

        $html = $this->get(route('admin.totp.challenge'))->getContent();
        $this->assertFieldError($html, 'recovery_code');
        $this->assertFieldClean($html, 'code');
    }
}
