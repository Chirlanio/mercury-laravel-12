<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductBulkImageService
{
    public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const ON_CONFLICT_REPLACE = 'replace';

    public const ON_CONFLICT_SKIP = 'skip';

    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
        $this->imageUploadService->setConfig([
            'max_file_size' => self::MAX_FILE_SIZE,
            'allowed_mime_types' => self::ALLOWED_MIME_TYPES,
        ]);
    }

    /**
     * Extrai a reference de um nome de arquivo. Aceita "REF-001.jpg", "REF-001.PNG", "ref-001.webp".
     * Retorna null se a extensão não for suportada.
     */
    public function extractReference(string $filename): ?string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $basename = trim($basename);

        return $basename === '' ? null : $basename;
    }

    /**
     * Avalia uma lista de filenames sem upload — retorna matched / not_found / conflicts.
     * Usado pelo passo "preview" da UI antes do envio dos arquivos.
     *
     * @param  array<int, string>  $filenames
     * @return array{matched: array, not_found: array, conflicts: array, invalid: array}
     */
    public function previewFilenames(array $filenames): array
    {
        $references = [];
        $invalid = [];

        foreach ($filenames as $filename) {
            $reference = $this->extractReference($filename);
            if ($reference === null) {
                $invalid[] = ['filename' => $filename, 'reason' => 'extensão não suportada'];
                continue;
            }
            $references[$reference][] = $filename;
        }

        $products = Product::whereIn('reference', array_keys($references))
            ->get(['id', 'reference', 'image'])
            ->keyBy('reference');

        $matched = [];
        $conflicts = [];
        $notFound = [];

        foreach ($references as $reference => $filenamesForRef) {
            $product = $products->get($reference);
            foreach ($filenamesForRef as $filename) {
                if (! $product) {
                    $notFound[] = ['filename' => $filename, 'reference' => $reference];
                    continue;
                }
                $entry = [
                    'filename' => $filename,
                    'reference' => $reference,
                    'product_id' => $product->id,
                    'has_existing_image' => ! empty($product->image),
                ];
                if (! empty($product->image)) {
                    $conflicts[] = $entry;
                } else {
                    $matched[] = $entry;
                }
            }
        }

        return [
            'matched' => $matched,
            'not_found' => $notFound,
            'conflicts' => $conflicts,
            'invalid' => $invalid,
        ];
    }

    /**
     * Processa um único arquivo. Retorna resultado granular pra que o caller (controller ou comando)
     * decida como agregar/reportar.
     *
     * Status possíveis:
     *  - 'uploaded'   — produto não tinha imagem, salvou
     *  - 'replaced'   — produto tinha imagem, substituiu (deletou a antiga)
     *  - 'skipped'    — produto tinha imagem e onConflict='skip'
     *  - 'not_found'  — nenhum produto com essa reference
     *  - 'invalid'    — extensão/mime/tamanho inválidos
     *  - 'error'      — falha inesperada (mensagem em 'message')
     *
     * @return array{filename: string, reference: ?string, status: string, message: ?string, product_id: ?int, image_path: ?string, image_url: ?string}
     */
    public function processFile(
        UploadedFile|string $file,
        string $originalName,
        string $onConflict = self::ON_CONFLICT_REPLACE,
        ?int $userId = null,
    ): array {
        $reference = $this->extractReference($originalName);
        $base = [
            'filename' => $originalName,
            'reference' => $reference,
            'product_id' => null,
            'image_path' => null,
            'image_url' => null,
            'message' => null,
        ];

        if ($reference === null) {
            return [...$base, 'status' => 'invalid', 'message' => 'Extensão não suportada (use jpg, jpeg, png ou webp).'];
        }

        $product = Product::where('reference', $reference)->first();
        if (! $product) {
            return [...$base, 'status' => 'not_found', 'message' => "Nenhum produto com referência '{$reference}'."];
        }
        $base['product_id'] = $product->id;
        $base['reference'] = $product->reference;

        $hadImage = ! empty($product->image);
        if ($hadImage && $onConflict === self::ON_CONFLICT_SKIP) {
            return [...$base, 'status' => 'skipped', 'message' => 'Produto já possui imagem.', 'image_path' => $product->image, 'image_url' => tenant_asset($product->image)];
        }

        if (is_string($file)) {
            $file = $this->makeUploadedFileFromPath($file, $originalName);
            if ($file === null) {
                return [...$base, 'status' => 'error', 'message' => 'Não foi possível ler o arquivo do disco.'];
            }
        }

        try {
            $path = $this->imageUploadService->uploadImage($file, 'products', $product->image);
        } catch (\InvalidArgumentException $e) {
            return [...$base, 'status' => 'invalid', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('ProductBulkImageService: upload falhou', [
                'reference' => $reference,
                'filename' => $originalName,
                'error' => $e->getMessage(),
            ]);

            return [...$base, 'status' => 'error', 'message' => 'Falha inesperada no upload.'];
        }

        $product->update([
            'image' => $path,
            'updated_by_user_id' => $userId,
        ]);

        return [
            ...$base,
            'status' => $hadImage ? 'replaced' : 'uploaded',
            'image_path' => $path,
            'image_url' => tenant_asset($path),
        ];
    }

    /**
     * Agrega resultados em um summary numérico — útil tanto pra response da API quanto pro comando CLI.
     *
     * @param  array<int, array>  $results
     * @return array<string, int>
     */
    public function summarize(array|Collection $results): array
    {
        $results = $results instanceof Collection ? $results : collect($results);

        return [
            'total' => $results->count(),
            'uploaded' => $results->where('status', 'uploaded')->count(),
            'replaced' => $results->where('status', 'replaced')->count(),
            'skipped' => $results->where('status', 'skipped')->count(),
            'not_found' => $results->where('status', 'not_found')->count(),
            'invalid' => $results->where('status', 'invalid')->count(),
            'errors' => $results->where('status', 'error')->count(),
        ];
    }

    private function makeUploadedFileFromPath(string $path, string $originalName): ?UploadedFile
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return new UploadedFile($path, $originalName, mime_content_type($path) ?: null, null, true);
    }
}
