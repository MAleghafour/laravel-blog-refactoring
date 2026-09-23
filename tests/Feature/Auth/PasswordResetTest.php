<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Notifications\CustomResetPasswordNotification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('success');

        Notification::assertSentTo($user, CustomResetPasswordNotification::class);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->post('/auth/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Old@123',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New@1234',
            'password_confirmation' => 'New@1234',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(
            Hash::check('New@1234', $user->password)
        );

        $this->assertFalse(
            Hash::check('Old@123', $user->password)
        );
    }

    public function test_password_reset_requires_valid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Old@123',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');

        $user->refresh();

        $this->assertTrue(
            Hash::check('Old@123', $user->password)
        );
    }
}
