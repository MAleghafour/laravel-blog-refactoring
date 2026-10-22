<?php

namespace Tests\Feature\Article;

use App\Jobs\DeleteArticleThumbnailsJob;
use App\Services\FileUploadService;
use Mockery;
use Tests\TestCase;

class DeleteArticleThumbnailsJobTest extends TestCase
{
    public function test_job_passes_thumbnails_to_upload_service(): void
    {
        $thumbnails = [
            'thumbnail_small' => 'small.jpg',
            'thumbnail_medium' => 'medium.jpg',
            'thumbnail_large' => 'large.jpg',
        ];

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($thumbnails);

        $job = new DeleteArticleThumbnailsJob($thumbnails);

        $job->handle($service);
    }

    public function test_job_propagates_cleanup_failure(): void
    {
        $thumbnails = [
            'thumbnail_small' => 'small.jpg',
        ];

        $service = Mockery::mock(FileUploadService::class);

        $service->shouldReceive('deleteExclusiveThumbnails')
            ->once()
            ->with($thumbnails)
            ->andThrow(new \RuntimeException('Deletion failed'));

        $job = new DeleteArticleThumbnailsJob($thumbnails);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deletion failed');

        $job->handle($service);
    }

    public function test_job_has_expected_retry_configuration(): void
    {
        $job = new DeleteArticleThumbnailsJob([]);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([10, 30], $job->backoff());
    }
}
