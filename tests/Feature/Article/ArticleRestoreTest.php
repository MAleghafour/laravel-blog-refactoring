<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function createTrashedArticle(
        User $author,
        string $status = 'published'
    ): Article {
        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Restore Test',
            'slug' => 'restore-test',
            'status' => $status,
            'first_published_at' => '2026-01-01 12:00:00',
        ]);

        $article->delete();

        return $article;
    }

    public function test_admin_can_restore_another_authors_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($author);

        $this->actingAs($admin)
            ->post(route('article.restore', $article->id))
            ->assertRedirect(route('article.trash'));

        $this->assertNotNull(Article::find($article->id));
    }

    public function test_author_can_restore_own_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createTrashedArticle($author);

        $this->actingAs($author)
            ->post(route('article.restore', $article->id))
            ->assertRedirect(route('article.trash'));

        $this->assertNotNull(Article::find($article->id));
    }

    public function test_author_cannot_restore_another_authors_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $other = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($other);

        $this->actingAs($author)
            ->post(route('article.restore', $article->id))
            ->assertForbidden();

        $this->assertSoftDeleted($article);
    }

    public function test_subscriber_cannot_restore_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($author);

        $this->actingAs($subscriber)
            ->post(route('article.restore', $article->id))
            ->assertForbidden();

        $this->assertSoftDeleted($article);
    }

    public function test_restored_article_always_becomes_draft(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createTrashedArticle($author);

        $this->actingAs($author)
            ->post(route('article.restore', $article->id))
            ->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'draft',
            'deleted_at' => null,
        ]);
    }

    public function test_restore_preserves_first_publication_date(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createTrashedArticle($author);

        $originalDate = $article->first_published_at;

        $this->actingAs($author)
            ->post(route('article.restore', $article->id))
            ->assertRedirect();

        $this->assertTrue(
            $article->fresh()->first_published_at->equalTo($originalDate)
        );
    }

    public function test_restore_records_event_with_correct_actor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($author);

        $this->actingAs($admin)
            ->post(route('article.restore', $article->id))
            ->assertRedirect();

        $this->assertDatabaseHas('article_events', [
            'article_id' => $article->id,
            'actor_id' => $admin->id,
            'event_type' => 'restored',
        ]);
    }

    public function test_active_article_cannot_be_restored(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Active Article',
            'slug' => 'active-article',
            'status' => 'draft',
        ]);

        $this->actingAs($author)
            ->post(route('article.restore', $article->id))
            ->assertForbidden();

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $article->id,
            'event_type' => 'restored',
        ]);
    }
}
