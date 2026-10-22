<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ArticleUpdateThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(): array
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'article-' . uniqid(),
            'status' => 'draft',
            'thumbnail_small' => 'old-small.jpg',
            'thumbnail_medium' => 'old-medium.jpg',
            'thumbnail_large' => 'old-large.jpg',
        ]);

        return [$author, $article];
    }

    private function data(Article $article): array
    {
        return [
            'title' => 'Updated title',
            'slug' => $article->slug,
            'lock_version' => $article->lock_version,
            'thumbnail' => UploadedFile::fake()->image('new.jpg'),
        ];
    }

    private function newThumbnails(): array
    {
        return [
            'thumbnail_small' => 'new-small.jpg',
            'thumbnail_medium' => 'new-medium.jpg',
            'thumbnail_large' => 'new-large.jpg',
        ];
    }

    private function oldThumbnails(): array
    {
        return [
            'thumbnail_small' => 'old-small.jpg',
            'thumbnail_medium' => 'old-medium.jpg',
            'thumbnail_large' => 'old-large.jpg',
        ];
    }

    public function test_replacement_saves_new_thumbnails_and_cleans_old_ones(): void
    {
        [$author, $article] = $this->createArticle();

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('fileUpload')
            ->once()
            ->andReturn($this->newThumbnails());

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($this->oldThumbnails());

        $this->app->instance(FileUploadService::class, $service);

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->data($article))
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $article->refresh();

        $this->assertSame('new-small.jpg', $article->thumbnail_small);
        $this->assertSame('new-medium.jpg', $article->thumbnail_medium);
        $this->assertSame('new-large.jpg', $article->thumbnail_large);
    }

    public function test_stale_update_cleans_new_thumbnails_and_keeps_old_ones(): void
    {
        [$author, $article] = $this->createArticle();

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('fileUpload')
            ->once()
            ->andReturn($this->newThumbnails());

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($this->newThumbnails());

        $this->app->instance(FileUploadService::class, $service);

        $data = $this->data($article);
        $data['lock_version']++;

        $this->actingAs($author)
            ->put(route('article.update', $article), $data)
            ->assertRedirect(route('article.edit', $article))
            ->assertSessionHas('error');

        $this->assertSame(
            'old-small.jpg',
            $article->fresh()->thumbnail_small
        );
    }

    public function test_upload_failure_preserves_existing_thumbnails(): void
    {
        [$author, $article] = $this->createArticle();

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('fileUpload')
            ->once()
            ->andThrow(new \RuntimeException('Upload failed'));

        $service->shouldNotReceive('deleteExclusiveThumbnails');

        $this->app->instance(FileUploadService::class, $service);

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->data($article))
            ->assertSessionHas('error');

        $article->refresh();

        $this->assertSame('Original title', $article->title);
        $this->assertSame('old-small.jpg', $article->thumbnail_small);
    }

    public function test_old_thumbnail_cleanup_failure_does_not_rollback_update(): void
    {
        [$author, $article] = $this->createArticle();

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('fileUpload')
            ->once()
            ->andReturn($this->newThumbnails());

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($this->oldThumbnails())
            ->andThrow(new \RuntimeException('Cleanup failed'));

        $this->app->instance(FileUploadService::class, $service);

        $this->actingAs($author)
            ->put(route('article.update', $article), $this->data($article))
            ->assertRedirect(route('article.show', $article))
            ->assertSessionHas('success');

        $this->assertSame(
            'new-small.jpg',
            $article->fresh()->thumbnail_small
        );
    }
}
