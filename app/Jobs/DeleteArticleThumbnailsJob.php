<?php

namespace App\Jobs;

use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteArticleThumbnailsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public array $thumbnails
    ) {
    }

    public function handle(FileUploadService $fileUploadService): void
    {
        $fileUploadService->deleteExclusiveThumbnails(
            $this->thumbnails
        );
    }
}
