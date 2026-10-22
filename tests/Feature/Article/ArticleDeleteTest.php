<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(
        User $author,
        string $slug = 'delete-test',
        string $status = 'draft'
    ): Article {
        return Article::create([
            'author_id' => $author->id,
            'title' => 'Delete Test',
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    public function test_admin_can_delete_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author);

        $this->actingAs($admin)
            ->delete(route('article.destroy', $article))
            ->assertRedirect(route('article.index'));

        $this->assertSoftDeleted($article);
    }

    public function test_author_can_delete_own_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->delete(route('article.destroy', $article))
            ->assertRedirect(route('article.index'));

        $this->assertSoftDeleted($article);
    }

    public function test_author_cannot_delete_another_authors_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($other);

        $this->actingAs($author)
            ->delete(route('article.destroy', $article))
            ->assertForbidden();

        $this->assertNotSoftDeleted($article);
    }

    public function test_subscriber_cannot_delete_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createArticle($author);

        $this->actingAs($subscriber)
            ->delete(route('article.destroy', $article))
            ->assertForbidden();

        $this->assertNotSoftDeleted($article);
    }

    public function test_guest_cannot_delete_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->delete(route('article.destroy', $article))
            ->assertRedirect(route('login'));

        $this->assertNotSoftDeleted($article);
    }

    public function test_deletion_preserves_article_status(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle(
            $author,
            'pending-delete',
            'pending'
        );

        $this->actingAs($author)
            ->delete(route('article.destroy', $article))
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'pending',
        ]);

        $this->assertSoftDeleted($article);
    }

    public function test_deletion_records_event(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle($author);

        $this->actingAs($author)
            ->delete(route('article.destroy', $article))
            ->assertRedirect();

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $author->id,
            'event_type' => 'trashed',
        ]);
    }

    public function test_deleted_article_is_excluded_from_active_queries(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createArticle(
            $author,
            'pending-article',
            'pending'
        );

        $this->actingAs($author)
            ->delete(route('article.destroy', $article))
            ->assertRedirect();

        $this->assertFalse(
            Article::query()->whereKey($article->id)->exists()
        );

        $this->assertFalse(
            Article::query()
                ->where('status', 'pending')
                ->whereKey($article->id)
                ->exists()
        );

        $this->assertTrue(
            Article::onlyTrashed()->whereKey($article->id)->exists()
        );
    }
}
