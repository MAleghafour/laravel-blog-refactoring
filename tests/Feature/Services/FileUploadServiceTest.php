<?php

namespace Tests\Feature\Services;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function test_removes_previous_thumbnails_when_generation_fails(): void
    {
        $filename = 'cleanup-test-' . uniqid() . '.jpg';
        $smallPath = public_path(
            "uploads/thumbnails/small/{$filename}"
        );

        $service = new class($filename) extends FileUploadService {
            public function __construct(private string $testFilename)
            {
                parent::__construct();
            }

            protected function upload($file, $image, $type): string
            {
                if ($type === 'medium') {
                    throw new \RuntimeException(
                        'Simulated upload failure'
                    );
                }

                if ($type === 'small') {
                    $filename = $this->testFilename;

                    $image->save(
                        public_path("uploads/thumbnails/small/{$filename}")
                    );

                    return $filename;
                }

                return parent::upload($file, $image, $type);
            }
        };

        try {
            $image = UploadedFile::fake()->image('article.jpg', 1200, 900);

            try {
                $service->processThumbnail($image);

                $this->fail('Expected upload failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame(
                    'Simulated upload failure',
                    $exception->getMessage()
                );
            }

            $this->assertTrue(File::missing($smallPath));
        } finally {
            // Remove the test file if an assertion fails.
            File::delete($smallPath);
        }
    }
}
