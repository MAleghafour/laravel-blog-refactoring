<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post('/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Test@123',
            'password_confirmation' => 'Test@123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'subscriber',
        ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->post('/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'Test@123',
            'password_confirmation' => 'Test@123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-email',
        ]);
    }

    public function test_registration_does_not_allow_role_escalation(): void
    {
        $response = $this->post('/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Test@123',
            'password_confirmation' => 'Test@123',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('home'));

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('subscriber', $user->role);
    }


    public function test_registration_regenerates_session(): void
    {
        $this->withSession([
            'test_session_key' => 'test_value',
        ]);

        $initialSessionId = session()->getId();

        $response = $this->post('/auth/register', [
            'name' => 'Test User',
            'email' => 'session@example.com',
            'password' => 'Test@123',
            'password_confirmation' => 'Test@123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertNotSame(
            $initialSessionId,
            session()->getId()
        );
    }
}
