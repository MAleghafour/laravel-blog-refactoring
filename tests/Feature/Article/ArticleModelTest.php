<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\ArticleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleModelTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'author_id' => $author->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'status' => 'draft',
        ]);
    }

    public function test_article_can_be_saved_as_incomplete_draft(): void
    {
        $article = $this->createArticle();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'draft',
            'body' => null,
            'category_id' => null,
        ]);
    }

    public function test_article_uses_soft_deletes(): void
    {
        $article = $this->createArticle();

        $article->delete();

        $this->assertSoftDeleted($article);

        $this->assertNull(Article::find($article->id));

        $this->assertNotNull(
            Article::withTrashed()->find($article->id)
        );
    }

    public function test_soft_deleted_article_can_be_restored(): void
    {
        $article = $this->createArticle();

        $article->delete();
        $article->restore();

        $this->assertNull($article->fresh()->deleted_at);

        $this->assertNotNull(
            Article::find($article->id)
        );
    }

    public function test_article_has_many_events(): void
    {
        $article = $this->createArticle();

        $event = $article->events()->create([
            'actor_id' => $article->author_id,
            'event_type' => 'draft_created',
        ]);

        $this->assertTrue(
            $article->events->contains($event)
        );

        $this->assertTrue(
            $event->article->is($article)
        );

        $this->assertTrue(
            $event->actor->is($article->author)
        );
    }

    public function test_soft_delete_preserves_article_events(): void
    {
        $article = $this->createArticle();

        $event = $article->events()->create([
            'actor_id' => $article->author_id,
            'event_type' => 'draft_created',
        ]);

        $article->delete();

        $this->assertDatabaseHas('article_events', [
            'id' => $event->id,
            'article_id' => $article->id,
        ]);
    }

    public function test_force_delete_removes_article_events(): void
    {
        $article = $this->createArticle();

        $event = $article->events()->create([
            'actor_id' => $article->author_id,
            'event_type' => 'draft_created',
        ]);

        $article->forceDelete();

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);

        $this->assertDatabaseMissing('article_events', [
            'id' => $event->id,
        ]);
    }

    public function test_lifecycle_timestamps_are_cast_to_datetime(): void
    {
        $article = $this->createArticle();

        $article->update([
            'first_published_at' => now(),
            'submitted_for_review_at' => now(),
        ]);

        $article->refresh();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $article->first_published_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $article->submitted_for_review_at
        );
    }

    public function test_article_lock_version_defaults_to_zero(): void
    {
        $article = $this->createArticle();

        $article->refresh();

        $this->assertSame(0, (int) $article->lock_version);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'lock_version' => 0,
        ]);
    }

    public function test_lock_version_increments_when_article_is_updated(): void
    {
        $article = $this->createArticle();

        $article->refresh();

        $this->assertSame(0, $article->lock_version);

        $article->update([
            'title' => 'Updated Article',
        ]);

        $article->refresh();

        $this->assertSame(1, $article->lock_version);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'Updated Article',
            'lock_version' => 1,
        ]);

        $article->update([
            'title' => 'Updated Again',
        ]);

        $this->assertSame(2, $article->fresh()->lock_version);
    }

    public function test_lock_version_does_not_increment_without_changes(): void
    {
        $article = $this->createArticle();

        $article->refresh();

        $this->assertSame(0, $article->lock_version);

        $article->save();

        $this->assertSame(0, $article->fresh()->lock_version);

        $article->update([
            'title' => $article->title,
        ]);

        $this->assertSame(0, $article->fresh()->lock_version);
    }

    public function test_lock_version_increments_on_soft_delete(): void
    {
        $article = $this->createArticle();
        $article->refresh();

        $this->assertSame(0, $article->lock_version);

        $article->delete();

        $trashedArticle = Article::withTrashed()
            ->findOrFail($article->id);

        $this->assertSame(1, $trashedArticle->lock_version);

        $this->assertSoftDeleted($article);
    }

    public function test_lock_version_increments_on_restore(): void
    {
        $article = $this->createArticle();
        $article->refresh();

        $article->delete();

        $this->assertSame(1, $article->fresh() === null
            ? Article::withTrashed()->findOrFail($article->id)->lock_version
            : $article->fresh()->lock_version);

        $article->restore();

        $restored = $article->fresh();

        $this->assertNotNull($restored);
        $this->assertSame(2, $restored->lock_version);
        $this->assertNull($restored->deleted_at);
    }

    public function test_new_article_has_initial_lock_version(): void
    {
        $article = new Article();

        $this->assertSame(0, $article->lock_version);
    }
}
