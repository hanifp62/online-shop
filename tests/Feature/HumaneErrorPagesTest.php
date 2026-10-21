<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HumaneErrorPagesTest extends TestCase
{
    public function test_404_page_is_indonesian_with_exit_links_and_no_internals(): void
    {
        $this->get('/tidak-ada-halaman')->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')->assertSee('Kembali ke beranda')
            ->assertDontSee('Symfony')->assertDontSee('vendor/');
    }

    public function test_every_status_page_renders_through_shared_layout(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $html = view("errors.$status")->render();
            $this->assertStringContainsString('Kembali ke beranda', $html, (string) $status);
            $this->assertStringContainsString("<p class=\"code\">$status</p>", $html);
        }
    }

    public function test_shop_request_helper_maps_statuses_to_humane_messages(): void
    {
        $js = file_get_contents(resource_path('views/shop/request.blade.php'));
        foreach ([403, 404, 409, 419, 429] as $status) {
            $this->assertMatchesRegularExpression("/\\b$status: copy\\[$status\\] \\|\\| '/", $js);
            foreach (config('app.supported_locales') as $locale) {
                $message = trans("shop_ui.request.$status", [], $locale);
                $this->assertNotSame("shop_ui.request.$status", $message, "$locale/$status");
                $this->assertNotSame('', trim($message));
            }
        }
    }

    public function test_error_page_shows_the_same_id_as_the_response_header(): void
    {
        $response = $this->get('/tidak-ada-halaman', ['X-Request-Id' => 'abc12345-trace'])->assertNotFound();

        $response->assertSee('Kode kejadian')->assertSee('abc12345-trace');
        $this->assertSame('abc12345-trace', $response->headers->get('X-Request-Id'));
    }

    public function test_unsafe_incoming_id_is_replaced_and_never_echoed_into_the_page(): void
    {
        $payload = '<script>alert(1)</script>';
        $response = $this->get('/tidak-ada-halaman', ['X-Request-Id' => $payload])->assertNotFound();

        $response->assertDontSee($payload, false);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
        $response->assertSee($response->headers->get('X-Request-Id'));
    }

    public function test_unhandled_exception_renders_500_with_id_and_without_internals(): void
    {
        config(['app.debug' => false]);
        Route::get('/__boom', fn () => throw new \RuntimeException('rahasia-internal'));

        $response = $this->get('/__boom', ['X-Request-Id' => 'boom-trace-0001'])->assertStatus(500);

        $response->assertSee('Ada kendala di sisi kami')->assertSee('boom-trace-0001')
            ->assertDontSee('rahasia-internal')->assertDontSee('RuntimeException');
    }
}
