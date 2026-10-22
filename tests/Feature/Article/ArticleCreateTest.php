<?php

namespace Tests\Feature\Article;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_view_create_form(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('article.create'))
            ->assertOk()
            ->assertViewIs('dashboard.articles.create')
            ->assertSee('name="slug"', false)
            ->assertSee('name="body"', false)
            ->assertSee('name="thumbnail"', false);
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('article.create'))
            ->assertOk();
    }

    public function test_subscriber_cannot_view_create_form(): void
    {
        $subscriber = User::factory()->create([
            'role' => 'subscriber',
        ]);

        $this->actingAs($subscriber)
            ->get(route('article.create'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('article.create'))
            ->assertRedirect(route('login'));
    }

    public function test_create_form_preserves_old_values(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->withSession([
                '_old_input' => [
                    'title' => 'Previous title',
                    'slug' => 'previous-slug',
                    'brief' => 'Previous description',
                    'tags' => 'Laravel, PHP',
                    'body' => '<p>Previous body</p>',
                ],
            ])
            ->get(route('article.create'))
            ->assertOk()
            ->assertSee('Previous title')
            ->assertSee('previous-slug')
            ->assertSee('Previous description')
            ->assertSee('Laravel, PHP')
            ->assertSee('&lt;p&gt;Previous body&lt;/p&gt;', false);
    }

    public function test_create_form_displays_validation_errors(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $errors = new \Illuminate\Support\ViewErrorBag();

        $errors->put(
            'default',
            new \Illuminate\Support\MessageBag([
                'slug' => ['نامک مقاله الزامی است.'],
            ])
        );

        $this->actingAs($author)
            ->withSession(['errors' => $errors])
            ->get(route('article.create'))
            ->assertOk()
            ->assertSee('نامک مقاله الزامی است.')
            ->assertSee('is-invalid');
    }
}
