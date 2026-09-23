<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Test@123'),
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('home'));

        $this->assertGuest();

        $response->assertSessionHas(
            'errors',
            fn ($errors) => $errors->first() === 'به امید دیدار مجدد شما'
        );
    }
}
