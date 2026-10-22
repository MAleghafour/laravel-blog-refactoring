<?php

namespace Tests\Feature\Article;

use App\Models\User;
use App\Models\dashboard\Article;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThumbnailDeletionTest extends TestCase
{
    use RefreshDatabase;

    private array $createdFiles = [];

    private function createTestThumbnail(string $size): string
    {
        $filename = Str::uuid() . '-' . $size . '.jpg';

        $directory = public_path("uploads/thumbnails/{$size}");

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, 'test-image-content');

        $this->createdFiles[] = $path;

        return $filename;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_exclusive_thumbnails_are_deleted(): void
    {
        $thumbnails = [
            'thumbnail_small' => $this->createTestThumbnail('small'),
            'thumbnail_medium' => $this->createTestThumbnail('medium'),
            'thumbnail_large' => $this->createTestThumbnail('large'),
        ];

        app(FileUploadService::class)
            ->deleteExclusiveThumbnails($thumbnails);

        foreach ($thumbnails as $column => $filename) {
            $size = str_replace('thumbnail_', '', $column);

            $this->assertFileDoesNotExist(
                public_path("uploads/thumbnails/{$size}/{$filename}")
            );
        }
    }

    public function test_shared_thumbnail_is_preserved(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $filename = $this->createTestThumbnail('small');

        Article::create([
            'author_id' => $author->id,
            'slug' => 'shared-image-article',
            'status' => 'draft',
            'thumbnail_small' => $filename,
        ]);

        app(FileUploadService::class)
            ->deleteExclusiveThumbnails([
                'thumbnail_small' => $filename,
            ]);

        $this->assertFileExists(
            public_path("uploads/thumbnails/small/{$filename}")
        );
    }

    public function test_soft_deleted_article_reference_prevents_file_deletion(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $filename = $this->createTestThumbnail('small');

        $article = Article::create([
            'author_id' => $author->id,
            'slug' => 'trashed-image-article',
            'status' => 'draft',
            'thumbnail_small' => $filename,
        ]);

        $article->delete();

        app(FileUploadService::class)
            ->deleteExclusiveThumbnails([
                'thumbnail_small' => $filename,
            ]);

        $this->assertFileExists(
            public_path("uploads/thumbnails/small/{$filename}")
        );
    }

    public function test_directory_traversal_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FileUploadService::class)
            ->deleteExclusiveThumbnails([
                'thumbnail_small' => '../important.jpg',
            ]);
    }

    public function test_windows_path_traversal_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FileUploadService::class)
            ->deleteExclusiveThumbnails([
                'thumbnail_small' => '..\\important.jpg',
            ]);
    }

    public function test_missing_thumbnail_does_not_cause_failure(): void
    {
        app(FileUploadService::class)
            ->deleteExclusiveThumbnails([
                'thumbnail_small' => 'missing-' . Str::uuid() . '.jpg',
            ]);

        $this->assertTrue(true);
    }
}
