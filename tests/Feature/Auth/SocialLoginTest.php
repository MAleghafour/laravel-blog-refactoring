<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_login_rejects_unsupported_driver(): void
    {
        $response = $this->get('/auth/facebook/redirect');

        $response->assertNotFound();
    }

    public function test_social_login_callback_rejects_unsupported_driver(): void
    {
        $response = $this->get('/auth/facebook/callback');

        $response->assertNotFound();
    }

    public function test_existing_user_can_login_with_social_provider(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ]);

        $socialUser = \Mockery::mock();

        $socialUser->shouldReceive('getEmail')
            ->once()
            ->andReturn('existing@example.com');

        $socialUser->shouldReceive('getName')
            ->once()
            ->andReturn('Existing User');

        $socialiteDriver = \Mockery::mock();

        $socialiteDriver->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($socialiteDriver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_new_user_can_register_with_social_provider(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $socialUser = \Mockery::mock();

        $socialUser->shouldReceive('getEmail')
            ->andReturn('new@example.com');

        $socialUser->shouldReceive('getName')
            ->andReturn('New User');

        $socialUser->shouldReceive('getId')
            ->andReturn('google-123');

        $socialiteDriver = \Mockery::mock();

        $socialiteDriver->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($socialiteDriver);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');

        $user = User::where('email', 'new@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('New User', $user->name);
        $this->assertSame('google', $user->driver_name);
        $this->assertSame('google-123', $user->driver_id);

        $this->assertAuthenticatedAs($user);

        \Illuminate\Support\Facades\Mail::assertSent(
            \App\Mail\WelcomeMail::class
        );
    }
}
