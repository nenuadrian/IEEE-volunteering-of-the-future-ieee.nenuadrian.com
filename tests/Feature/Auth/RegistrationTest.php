<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ]);

        $this->assertAuthenticated();
        // New accounts go straight to profile set-up, as regular users with a profile.
        $response->assertRedirect(route('profile.volunteer.edit', absolute: false));
        $user = \App\Models\User::where('email', 'test@example.com')->first();
        $this->assertSame('user', $user->role);
        $this->assertNotNull($user->profile);
    }
}
