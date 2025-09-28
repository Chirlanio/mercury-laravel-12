<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;

class ImageUploadService
{
    private string $disk = 'public';
    private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxFileSize = 2 * 1024 * 1024; // 2MB (matching PHP config)

    /**
     * Upload de avatar de usuário
     */
    public function uploadUserAvatar(UploadedFile $file, ?string $oldAvatar = null): string
    {
        Log::info('ImageUploadService: Starting avatar upload', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'old_avatar' => $oldAvatar
        ]);

        $this->validateImage($file);
        Log::info('ImageUploadService: Image validation passed');

        // Remove avatar antigo se existir
        if ($oldAvatar) {
            $this->deleteFile($oldAvatar);
            Log::info('ImageUploadService: Old avatar deleted', ['path' => $oldAvatar]);
        }

        // Gera nome único para o arquivo
        $filename = $this->generateUniqueFilename($file, 'avatar');
        $path = "avatars/{$filename}";
        Log::info('ImageUploadService: Generated filename', ['path' => $path]);

        // Redimensiona e otimiza a imagem
        $processedImage = $this->processAvatarImage($file);
        Log::info('ImageUploadService: Image processed', ['size' => strlen($processedImage)]);

        // Salva no storage
        $result = Storage::disk($this->disk)->put($path, $processedImage);
        Log::info('ImageUploadService: Storage save result', ['success' => $result, 'path' => $path]);

        if (!$result) {
            throw new Exception('Failed to save image to storage');
        }

        Log::info('ImageUploadService: Avatar upload completed', ['final_path' => $path]);
        return $path;
    }

    /**
     * Upload genérico de imagem
     */
    public function uploadImage(UploadedFile $file, string $directory = 'images', ?string $oldPath = null): string
    {
        $this->validateImage($file);

        // Remove arquivo antigo se existir
        if ($oldPath) {
            $this->deleteFile($oldPath);
        }

        // Gera nome único para o arquivo
        $filename = $this->generateUniqueFilename($file);
        $path = "{$directory}/{$filename}";

        // Processa e salva a imagem
        $processedImage = $this->processImage($file);
        Storage::disk($this->disk)->put($path, $processedImage);

        return $path;
    }

    /**
     * Processa imagem de avatar (redimensiona para 200x200)
     */
    private function processAvatarImage(UploadedFile $file): string
    {
        try {
            Log::info('ImageUploadService: Starting image processing', ['file_path' => $file->getRealPath()]);

            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());
            Log::info('ImageUploadService: Image read successfully', ['width' => $image->width(), 'height' => $image->height()]);

            // Redimensiona e faz crop para quadrado 200x200
            $image = $image->cover(200, 200);
            Log::info('ImageUploadService: Image resized to 200x200');

            // Aplica qualidade de compressão e converte para JPEG
            $result = $image->toJpeg(85)->toString();
            Log::info('ImageUploadService: Image converted to JPEG', ['size' => strlen($result)]);

            return $result;
        } catch (Exception $e) {
            Log::error('ImageUploadService: Image processing failed', ['error' => $e->getMessage()]);
            // Se falhar no processamento, retorna o arquivo original
            $fallback = file_get_contents($file->getRealPath());
            Log::info('ImageUploadService: Using fallback original file', ['size' => strlen($fallback)]);
            return $fallback;
        }
    }

    /**
     * Processa imagem genérica (redimensiona se muito grande)
     */
    private function processImage(UploadedFile $file, int $maxWidth = 1200, int $maxHeight = 1200): string
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            // Redimensiona apenas se for maior que o máximo
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image = $image->scaleDown($maxWidth, $maxHeight);
            }

            // Determina formato de saída baseado no tipo original
            $format = $this->getOptimalFormat($file->getMimeType());
            $quality = $format === 'jpg' ? 85 : 90;

            if ($format === 'jpg') {
                return $image->toJpeg($quality)->toString();
            } elseif ($format === 'png') {
                return $image->toPng()->toString();
            } elseif ($format === 'webp') {
                return $image->toWebp($quality)->toString();
            }

            return $image->toJpeg($quality)->toString();
        } catch (Exception $e) {
            // Se falhar no processamento, retorna o arquivo original
            return file_get_contents($file->getRealPath());
        }
    }

    /**
     * Valida se a imagem atende aos critérios
     */
    private function validateImage(UploadedFile $file): void
    {
        // Verifica se é um arquivo válido
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Arquivo inválido.');
        }

        // Verifica tipo MIME
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Tipo de arquivo não permitido. Use: JPEG, PNG, GIF ou WebP.');
        }

        // Verifica tamanho do arquivo
        if ($file->getSize() > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / (1024 * 1024);
            throw new \InvalidArgumentException("Arquivo muito grande. Tamanho máximo: {$maxSizeMB}MB.");
        }

        // Verifica se é realmente uma imagem
        $imageInfo = getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Arquivo não é uma imagem válida.');
        }

        // Verifica dimensões mínimas
        [$width, $height] = $imageInfo;
        if ($width < 50 || $height < 50) {
            throw new \InvalidArgumentException('Imagem muito pequena. Mínimo: 50x50 pixels.');
        }

        // Verifica dimensões máximas
        if ($width > 4000 || $height > 4000) {
            throw new \InvalidArgumentException('Imagem muito grande. Máximo: 4000x4000 pixels.');
        }
    }

    /**
     * Gera nome único para o arquivo
     */
    private function generateUniqueFilename(UploadedFile $file, string $prefix = ''): string
    {
        $extension = $this->getOptimalExtension($file->getMimeType());
        $hash = Str::random(32);
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($prefix) {
            return "{$prefix}_{$timestamp}_{$hash}.{$extension}";
        }

        return "{$timestamp}_{$hash}.{$extension}";
    }

    /**
     * Obtém extensão otimizada baseada no MIME type
     */
    private function getOptimalExtension(string $mimeType): string
    {
        return match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Obtém formato otimizado para encoding
     */
    private function getOptimalFormat(string $mimeType): string
    {
        return match($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Remove arquivo do storage
     */
    public function deleteFile(string $path): bool
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    /**
     * Verifica se arquivo existe
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Obtém URL pública do arquivo
     */
    public function getPublicUrl(string $path): string
    {
        return Storage::url($path);
    }

    /**
     * Obtém informações da imagem
     */
    public function getImageInfo(string $path): ?array
    {
        if (!$this->fileExists($path)) {
            return null;
        }

        $fullPath = Storage::disk($this->disk)->path($path);
        $imageInfo = getimagesize($fullPath);

        if ($imageInfo === false) {
            return null;
        }

        [$width, $height, $type] = $imageInfo;

        return [
            'width' => $width,
            'height' => $height,
            'type' => image_type_to_mime_type($type),
            'size' => Storage::disk($this->disk)->size($path),
            'url' => $this->getPublicUrl($path),
        ];
    }

    /**
     * Cria thumbnail da imagem
     */
    public function createThumbnail(string $imagePath, int $width = 150, int $height = 150): ?string
    {
        if (!$this->fileExists($imagePath)) {
            return null;
        }

        try {
            $fullPath = Storage::disk($this->disk)->path($imagePath);
            $manager = new ImageManager(new Driver());
            $image = $manager->read($fullPath);

            // Cria thumbnail
            $image = $image->cover($width, $height);

            // Gera nome do thumbnail
            $pathInfo = pathinfo($imagePath);
            $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];

            // Salva thumbnail
            $encodedImage = $image->toJpeg(80)->toString();
            Storage::disk($this->disk)->put($thumbnailPath, $encodedImage);

            return $thumbnailPath;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Define configurações personalizadas
     */
    public function setConfig(array $config): self
    {
        if (isset($config['disk'])) {
            $this->disk = $config['disk'];
        }

        if (isset($config['allowed_mime_types'])) {
            $this->allowedMimeTypes = $config['allowed_mime_types'];
        }

        if (isset($config['max_file_size'])) {
            $this->maxFileSize = $config['max_file_size'];
        }

        return $this;
    }
}
