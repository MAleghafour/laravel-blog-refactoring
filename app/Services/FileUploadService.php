<?php

namespace App\Services;

use Intervention\Image\Laravel\Facades\Image;
use App\Models\dashboard\Article;
use Illuminate\Support\Facades\File;

class FileUploadService
{
    private array $thumbnailDimension = [
        'small' => [300, 225],
        'medium' => [500, 500],
        'large' => [1000, 750],
    ];
        public function __construct()
        {
            $baseDirectory=public_path('uploads');
            if(!is_dir($baseDirectory)){
                mkdir($baseDirectory,0755,true);
            }
            $subDirectories = [
                 'avatars',
                'thumbnails/small',
                'thumbnails/medium',
                'thumbnails/large',
                'file_manager'
            ]   ;
            foreach($subDirectories as $directory){
                $fullDirectory = $baseDirectory.'/'.$directory;
                if(!is_dir($fullDirectory)){
                    mkdir($fullDirectory,0755,true);
                }
            }
        }

    public function fileUpload($file, $avatar = false, $thumbnail = false, $fileManager = false)
    {
        $processMethods = [
            $avatar => 'processAvatar',
            $thumbnail => 'processThumbnail',
            $fileManager => 'processFileManager',
        ];
        foreach ($processMethods as $condition => $method) {
            if ($condition) {
                return $this->$method($file);
            }
        }
        return false;
    }


    /**
     * Generate article thumbnails and clean up completed files on failure.
     *
     * Prevents orphaned thumbnails when a later size fails.
     */
    public function processThumbnail($file): array
    {
        $names = [];

        try {
            foreach ($this->thumbnailDimension as $size => $dimensions) {
                $image = $this->resize(
                    $file,
                    $dimensions[0],
                    $dimensions[1]
                );

                $image = $this->addWaterMark($image);

                $names["thumbnail_{$size}"] = $this->upload(
                    $file,
                    $image,
                    $size
                );
            }

            return $names;

        } catch (\Throwable $exception) {

            // Remove thumbnails successfully created by this upload.
            foreach ($names as $column => $filename) {
                $size = substr($column, strlen('thumbnail_'));
                $path = $this->findUploadPath($size) . $filename;

                if (File::exists($path) && ! File::delete($path)) {
                    throw new \RuntimeException(
                        'Failed to clean up an uploaded thumbnail.',
                        0,
                        $exception
                    );
                }
            }

            throw $exception;
        }
    }

    private function resize($file, $width, $height)
    {
        $image = Image::read($file);
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        $aspectRatio = $originalWidth / $originalHeight;
        if ($width === $height) {
            return $image->cover($width, $height, 'center');
        }
        if ($aspectRatio > ($width / $height)) {
            $newWidth = $width;
            $newHeight = intval($width / $aspectRatio);
        } else {
            $newHeight = $height;
            $newWidth = intval($newHeight * $aspectRatio);
        }
        return $image->resize($newWidth, $newHeight, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        //$image->save(public_path().'/thumbnails/b.jpg');
    }

    private function addWaterMark($image): \Intervention\Image\Interfaces\ImageInterface
    {
        $watermarkPath = public_path('watermark/logo.png');
        if (file_exists($watermarkPath)) {
            $image = Image::read($image);
            $image->place($watermarkPath, 'bottom-left', 5, 5);
        } else {
            $image = Image::read($image);
        }
        return $image;
    }

    protected function upload($file, $image,$type): string
    {
        $imageOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $imageExtenstion = $file->getClientOriginalExtension();
        $newFileName = $imageOriginalName . '-' . uniqid() . '-' . $type . '.' . $imageExtenstion;
        $findUploadPath = $this->findUploadPath($type);
        $uploadPath = $findUploadPath . $newFileName;
        $image->save($uploadPath);
        return $newFileName;

    }

    private function findUploadPath($type): string
    {
        return match ($type) {
            'avatars' => public_path("uploads/{$type}") . DIRECTORY_SEPARATOR,
            'small', 'medium', 'large' => public_path("uploads/thumbnails/{$type}") . DIRECTORY_SEPARATOR,
            default => public_path('uploads/file_manager') . DIRECTORY_SEPARATOR,
        };

    }

    public function deleteExclusiveThumbnails(array $thumbnails): void
    {
        $sizes = ['small', 'medium', 'large'];

        foreach ($sizes as $size) {
            $column = "thumbnail_{$size}";
            $filename = $thumbnails[$column] ?? null;

            if (! is_string($filename) || $filename === '') {
                continue;
            }

            // Reject directory traversal and nested paths.
            if (basename($filename) !== $filename ||
                str_contains($filename, '\\')) {
                throw new \InvalidArgumentException(
                    'Invalid thumbnail filename.'
                );
            }

            // Do not delete files referenced by another article.
            if (Article::withTrashed()
                ->where($column, $filename)
                ->exists()) {
                continue;
            }

            $path = public_path("uploads/thumbnails/{$size}/{$filename}");

            if (File::exists($path) && ! File::delete($path)) {
                throw new \RuntimeException(
                    "Failed to delete thumbnail: {$filename}"
                );
            }
        }
    }
}


