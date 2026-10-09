<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_accessible(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_new_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ratna Dewi',
            'email' => 'ratna@example.com',
            'password' => 'Saphir#2026',
            'password_confirmation' => 'Saphir#2026',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ratna@example.com']);
    }

    public function test_weak_password_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Suci',
            'email' => 'suci@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'suci@example.com']);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'password' => 'Saphir#2026',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Saphir#2026',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
