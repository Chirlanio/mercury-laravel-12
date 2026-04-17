<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TaneiaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $chatPath,
        private readonly string $uploadPath,
        private readonly int $timeout,
        private readonly int $uploadTimeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('services.taneia.base_url'),
            chatPath: (string) config('services.taneia.chat_path'),
            uploadPath: (string) config('services.taneia.upload_path'),
            timeout: (int) config('services.taneia.timeout'),
            uploadTimeout: (int) config('services.taneia.upload_timeout'),
        );
    }

    /**
     * Upload multipart do PDF para indexacao no ChromaDB.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function uploadDocument(UploadedFile $file): array
    {
        $endpoint = $this->url($this->uploadPath);
        $stream = fopen($file->getRealPath(), 'r');

        try {
            $response = Http::timeout($this->uploadTimeout)
                ->withHeaders($this->tenantHeaders())
                ->acceptJson()
                ->attach('file', $stream, $file->getClientOriginalName())
                ->post($endpoint);
        } catch (ConnectionException $e) {
            Log::warning('TaneIA upload unreachable', ['error' => $e->getMessage()]);
            throw new RuntimeException('TaneIA esta temporariamente indisponivel.', 0, $e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->failed()) {
            $detail = $response->json('detail') ?? 'Erro ao indexar documento.';
            Log::warning('TaneIA upload returned non-success', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(is_string($detail) ? $detail : 'Erro ao indexar documento.');
        }

        return $response->json() ?? [];
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function tenantHeaders(): array
    {
        $tenantId = tenant()?->id ?? 'default';

        return ['X-Tenant-Id' => (string) $tenantId];
    }
}
