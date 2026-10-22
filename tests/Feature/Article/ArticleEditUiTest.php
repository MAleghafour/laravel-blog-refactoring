<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleEditUiTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(User $author): Article
    {
        return Article::create([
            'author_id' => $author->id,
            'title' => 'Article title',
            'slug' => 'article-' . uniqid(),
            'status' => 'draft',
        ]);
    }

    public function test_admin_sees_status_selector(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $this->actingAs($admin)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('name="status"', false)
            ->assertSee('value="published"', false);
    }

    public function test_author_cannot_see_status_selector(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertDontSee('name="status"', false);
    }

    public function test_admin_sees_explanation_when_editing_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('name="explanation"', false);
    }

    public function test_admin_does_not_need_explanation_for_own_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $this->actingAs($admin)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertDontSee('name="explanation"', false);
    }

    public function test_author_sees_latest_correction_feedback(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($author);

        $article->events()->create([
            'actor_id' => $admin->id,
            'event_type' => 'returned_for_correction',
            'explanation' => 'Please correct the article introduction.',
        ]);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('Please correct the article introduction.');
    }

    public function test_old_feedback_is_hidden_after_resubmission(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($author);

        $article->events()->create([
            'actor_id' => $admin->id,
            'event_type' => 'returned_for_correction',
            'explanation' => 'Old correction feedback.',
        ]);

        $article->events()->create([
            'actor_id' => $author->id,
            'event_type' => 'submitted_for_review',
        ]);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertDontSee('Old correction feedback.');
    }

    public function test_edit_form_displays_current_thumbnail(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $article->update([
            'thumbnail_medium' => 'existing-medium.jpg',
        ]);

        $this->actingAs($author)
            ->get(route('article.edit', $article))
            ->assertOk()
            ->assertSee('existing-medium.jpg')
            ->assertSee('id="preview"', false);
    }
}
