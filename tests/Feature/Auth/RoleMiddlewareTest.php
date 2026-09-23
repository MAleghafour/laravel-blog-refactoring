<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user)
            ->get('/dashboard/article/category')
            ->assertSuccessful();
    }

    public function test_author_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'author',
        ]);

        $this->actingAs($user)
            ->get('/dashboard/article/category')
            ->assertForbidden();
    }

    public function test_subscriber_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'subscriber',
        ]);

        $this->actingAs($user)
            ->get('/dashboard/article/category')
            ->assertForbidden();
    }

    public function test_author_can_access_author_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'author',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertSuccessful();
    }

    public function test_admin_can_access_author_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertSuccessful();
    }

    public function test_subscriber_cannot_access_author_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'subscriber',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/auth/login');
    }
}
