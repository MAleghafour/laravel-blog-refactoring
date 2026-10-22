<?php

namespace Tests\Feature\Article;

use App\Jobs\DeleteArticleThumbnailsJob;
use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArticleForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function createTrashedArticle(
        User $author,
        string $slug = 'force-delete-test',
        ?string $thumbnail = null
    ): Article {
        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Force Delete Test',
            'slug' => $slug,
            'status' => 'draft',
            'thumbnail_small' => $thumbnail,
        ]);

        $article->events()->create([
            'actor_id' => $author->id,
            'event_type' => 'draft_created',
        ]);

        $article->delete();

        return $article;
    }

    public function test_admin_can_permanently_delete_trashed_article(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($author);

        $this->actingAs($admin)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertRedirect(route('article.trash'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    public function test_author_cannot_permanently_delete_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createTrashedArticle($author);

        $this->actingAs($author)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertForbidden();

        $this->assertSoftDeleted($article);
    }

    public function test_subscriber_cannot_permanently_delete_article(): void
    {
        $subscriber = User::factory()->create(['role' => 'subscriber']);
        $author = User::factory()->create(['role' => 'author']);

        $article = $this->createTrashedArticle($author);

        $this->actingAs($subscriber)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertForbidden();

        $this->assertSoftDeleted($article);
    }

    public function test_guest_cannot_permanently_delete_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = $this->createTrashedArticle($author);

        $this->delete(route('article.force-delete', $article->id), [
            'confirmation' => 'حذف دائمی',
        ])->assertRedirect(route('login'));

        $this->assertSoftDeleted($article);
    }

    public function test_confirmation_phrase_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createTrashedArticle($admin);

        $this->actingAs($admin)
            ->from(route('article.trash'))
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'delete',
            ])
            ->assertRedirect(route('article.trash'))
            ->assertSessionHasErrors('confirmation');

        $this->assertSoftDeleted($article);
    }

    public function test_permanent_delete_removes_article_events(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createTrashedArticle($admin);

        $articleId = $article->id;

        $this->actingAs($admin)
            ->delete(route('article.force-delete', $articleId), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('article_events', [
            'article_id' => $articleId,
        ]);
    }

    public function test_thumbnail_cleanup_job_is_dispatched(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createTrashedArticle(
            $admin,
            'article-with-thumbnail',
            'example-small.jpg'
        );

        $this->actingAs($admin)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertRedirect();

        Queue::assertPushed(
            DeleteArticleThumbnailsJob::class,
            function ($job) {
                return $job->thumbnails['thumbnail_small']
                    === 'example-small.jpg';
            }
        );
    }

    public function test_no_cleanup_job_without_thumbnails(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->createTrashedArticle($admin);

        $this->actingAs($admin)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertRedirect();

        Queue::assertNotPushed(DeleteArticleThumbnailsJob::class);
    }

    public function test_active_article_cannot_be_permanently_deleted(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Active Article',
            'slug' => 'active-force-delete',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
        ]);
    }
}
