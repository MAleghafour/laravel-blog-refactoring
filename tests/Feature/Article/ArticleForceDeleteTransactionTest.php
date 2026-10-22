<?php

namespace Tests\Feature\Article;

use App\Jobs\DeleteArticleThumbnailsJob;
use App\Models\User;
use App\Models\dashboard\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ArticleForceDeleteTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', null);
        config()->set('queue.connections.database.after_commit', false);

    }

    private function createTrashedArticle(): Article
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $article = Article::create([
            'author_id' => $admin->id,
            'title' => 'Transaction Test',
            'slug' => 'transaction-test',
            'status' => 'draft',
            'thumbnail_small' => 'test-small.jpg',
        ]);

        $article->delete();

        return $article;
    }

    public function test_permanent_delete_persists_real_database_job(): void
    {
        $article = $this->createTrashedArticle();

        $this->actingAs($article->author)
            ->delete(route('article.force-delete', $article->id), [
                'confirmation' => 'حذف دائمی',
            ])
            ->assertRedirect(route('article.trash'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);

        $this->assertDatabaseCount('jobs', 1);

        $payload = json_decode(
            DB::table('jobs')->first()->payload,
            true
        );

        $this->assertSame(
            DeleteArticleThumbnailsJob::class,
            $payload['displayName']
        );
    }

    public function test_transaction_rollback_preserves_article_and_removes_job(): void
    {
        $article = $this->createTrashedArticle();

        try {
            DB::transaction(function () use ($article) {
                $thumbnails = $article->only([
                    'thumbnail_small',
                    'thumbnail_medium',
                    'thumbnail_large',
                ]);

                $article->forceDelete();

                DeleteArticleThumbnailsJob::dispatch($thumbnails);

                throw new RuntimeException('Simulated failure');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated failure',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
        ]);

        $this->assertSoftDeleted($article);

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_controller_rolls_back_when_database_job_persistence_fails(): void
    {
        $article = $this->createTrashedArticle();

        $event = $article->events()->create([
            'actor_id' => $article->author_id,
            'event_type' => 'trashed',
        ]);

        DB::unprepared("
        CREATE TRIGGER fail_jobs_insert
        BEFORE INSERT ON jobs
        BEGIN
            SELECT RAISE(ABORT, 'Simulated queue persistence failure');
        END;
    ");

        try {
            $response = $this->actingAs($article->author)
                ->from(route('article.trash'))
                ->delete(route('article.force-delete', $article->id), [
                    'confirmation' => 'حذف دائمی',
                ]);
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_jobs_insert'
            );
        }

        $response
            ->assertRedirect(route('article.trash'))
            ->assertSessionHas(
                'error',
                'حذف دائمی مقاله انجام نشد. لطفاً دوباره تلاش کنید.'
            )
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
        ]);

        $this->assertSoftDeleted($article);

        $this->assertDatabaseHas('article_events', [
            'id' => $event->id,
            'article_id' => $article->id,
        ]);

        $this->assertDatabaseCount('jobs', 0);
    }
}
