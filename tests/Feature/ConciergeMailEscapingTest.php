<?php

namespace Tests\Feature;

use App\Mail\ConciergeRequested;
use App\Models\ConciergeRequest;
use Tests\TestCase;

class ConciergeMailEscapingTest extends TestCase
{
    public function test_concierge_confirmation_mail_escapes_customer_supplied_fields(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = (new ConciergeRequested(new ConciergeRequest([
            'name' => $payload,
            'phone' => $payload,
            'notes' => $payload,
            'email' => 'klien@example.test',
            'service' => 'private-styling',
        ])))->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        // name, notes and phone each render once, all escaped.
        $this->assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    }
}
