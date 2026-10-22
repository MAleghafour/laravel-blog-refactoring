<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $status = 'draft'
    ): Article {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology-' . uniqid(),
        ]);

        return Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'original-' . uniqid(),
            'body' => '<p>' . str_repeat('Article content ', 5) . '</p>',
            'category_id' => $category->id,
            'status' => $status,
        ]);
    }

    private function validData(Article $article): array
    {
        return [
            'title' => 'Updated title',
            'slug' => $article->slug,
            'body' => '<p>' . str_repeat('Updated content ', 5) . '</p>',
            'category_id' => $article->category_id,
            'lock_version' => $article->lock_version,
        ];
    }

    public function test_author_can_validate_incomplete_draft(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->put(route('article.update', $article), [
                'slug' => $article->slug,
                'lock_version' => $article->lock_version,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_author_cannot_set_status_directly(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->put(route('article.update', $article), [
                ...$this->validData($article),
                'status' => 'published',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_published_article_requires_complete_content(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author, 'published');

        $data = $this->validData($article);
        $data['body'] = '<p>Short</p>';

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertSessionHasErrors('body');
    }

    public function test_current_slug_is_allowed_on_update(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->put(
                route('article.update', $article),
                $this->validData($article)
            )
            ->assertSessionHasNoErrors();
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);
        $other = $this->createArticle($author);

        $data = $this->validData($article);
        $data['slug'] = $other->slug;

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_select_published_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin);

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->validData($article),
                'status' => 'published',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_admin_must_explain_editing_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->validData($article),
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('explanation');
    }

    public function test_admin_can_validate_edit_with_explanation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                ...$this->validData($article),
                'status' => 'draft',
                'explanation' => 'Corrected article metadata.',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_other_author_cannot_update_article(): void
    {
        $owner = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($owner);

        $this->actingAs($other)
            ->put(route('article.update', $article), $this->validData($article))
            ->assertForbidden();
    }

    public function test_admin_can_validate_update_without_changing_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createArticle($admin, 'pending');

        $this->actingAs($admin)
            ->put(
                route('article.update', $article),
                $this->validData($article)
            )
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $article->fresh()->status);
    }
}
