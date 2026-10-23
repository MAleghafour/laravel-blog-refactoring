<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Models\dashboard\ArticleEvent;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendArticleNotificationJob;
use Mockery;
use Tests\TestCase;

class ArticleUpdateTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_failure_rolls_back_update_and_cleans_new_thumbnails(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'original-' . uniqid(),
            'status' => 'draft',
            'thumbnail_small' => 'old-small.jpg',
        ]);

        $newThumbnails = [
            'thumbnail_small' => 'new-small.jpg',
            'thumbnail_medium' => 'new-medium.jpg',
            'thumbnail_large' => 'new-large.jpg',
        ];

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('fileUpload')
            ->once()
            ->andReturn($newThumbnails);

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($newThumbnails);

        $this->app->instance(FileUploadService::class, $service);

        ArticleEvent::creating(function () {
            throw new \RuntimeException('Simulated event failure');
        });

        $this->actingAs($admin)
            ->put(route('article.update', $article), [
                'title' => 'Updated title',
                'slug' => $article->slug,
                'lock_version' => $article->lock_version,
                'explanation' => 'Corrected article content.',
                'thumbnail' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect(route('article.edit', $article))
            ->assertSessionHas('error');

        $article->refresh();

        $this->assertSame('Original title', $article->title);
        $this->assertSame('old-small.jpg', $article->thumbnail_small);
        $this->assertSame(0, $article->lock_version);

        $this->assertDatabaseCount('article_events', 0);

        Queue::assertNotPushed(SendArticleNotificationJob::class);
    }
}
