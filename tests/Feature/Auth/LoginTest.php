<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Test@123'),
        ]);

        $response = $this->post('/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Test@123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('Test@123'),
        ]);

        $response = $this->post('/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Wrong@123',
        ]);

        $response->assertRedirect();

        $response->assertSessionHas('error', 'نام کاربری یا کلمه عبور شما اشتباه است!');

        $this->assertGuest();
    }

    public function test_login_requires_valid_email(): void
    {
        $response = $this->post('/auth/login', [
            'email' => 'invalid-email',
            'password' => 'Test@123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_regenerates_session(): void
    {
        $user = User::factory()->create([
            'email' => 'session@example.com',
            'password' => Hash::make('Test@123'),
        ]);

        $this->withSession([
            'test_session_key' => 'test_value',
        ]);

        $initialSessionId = session()->getId();

        $response = $this->post('/auth/login', [
            'email' => 'session@example.com',
            'password' => 'Test@123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $this->assertNotSame(
            $initialSessionId,
            session()->getId()
        );
    }
}
