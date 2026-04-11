<?php

namespace App\Services;

use App\Models\TrainingContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TrainingContentUploadService
{
    /**
     * Upload a content file (video, audio, document).
     *
     * @return array{path: string, name: string, size: int, mime: string}
     */
    public function upload(UploadedFile $file, string $contentType): array
    {
        $this->validateFile($file, $contentType);

        $directory = "training-contents/{$contentType}s";
        $path = $file->store($directory, 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Upload a thumbnail image.
     */
    public function uploadThumbnail(UploadedFile $file): string
    {
        return $file->store('training-contents/thumbnails', 'public');
    }

    /**
     * Delete a file from storage.
     */
    public function delete(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }

        return Storage::disk('public')->delete($filePath);
    }

    /**
     * Get max upload size in bytes for a content type.
     */
    public function getMaxSize(string $contentType): int
    {
        return match ($contentType) {
            TrainingContent::TYPE_VIDEO => TrainingContent::MAX_SIZE_VIDEO,
            TrainingContent::TYPE_AUDIO => TrainingContent::MAX_SIZE_AUDIO,
            TrainingContent::TYPE_DOCUMENT => TrainingContent::MAX_SIZE_DOCUMENT,
            default => 50 * 1024 * 1024,
        };
    }

    /**
     * Get allowed extensions for a content type.
     */
    public function getAllowedExtensions(string $contentType): array
    {
        return TrainingContent::EXTENSIONS[$contentType] ?? [];
    }

    /**
     * Check if a content type requires file upload.
     */
    public function isFileType(string $contentType): bool
    {
        return in_array($contentType, [
            TrainingContent::TYPE_VIDEO,
            TrainingContent::TYPE_AUDIO,
            TrainingContent::TYPE_DOCUMENT,
        ]);
    }

    /**
     * Validate an uploaded file against content type rules.
     */
    private function validateFile(UploadedFile $file, string $contentType): void
    {
        $maxSize = $this->getMaxSize($contentType);
        if ($file->getSize() > $maxSize) {
            $maxMB = $maxSize / 1024 / 1024;
            throw new \InvalidArgumentException("Arquivo excede o tamanho maximo de {$maxMB}MB.");
        }

        $allowedExtensions = $this->getAllowedExtensions($contentType);
        if (! empty($allowedExtensions)) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, $allowedExtensions)) {
                $allowed = implode(', ', $allowedExtensions);
                throw new \InvalidArgumentException("Extensao nao permitida. Permitidas: {$allowed}.");
            }
        }
    }
}
