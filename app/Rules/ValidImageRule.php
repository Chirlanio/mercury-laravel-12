<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidImageRule implements ValidationRule
{
    private int $maxSizeInMB;
    private array $allowedMimeTypes;
    private int $minWidth;
    private int $minHeight;
    private int $maxWidth;
    private int $maxHeight;

    public function __construct(
        int $maxSizeInMB = 5,
        array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        int $minWidth = 50,
        int $minHeight = 50,
        int $maxWidth = 4000,
        int $maxHeight = 4000
    ) {
        $this->maxSizeInMB = $maxSizeInMB;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->minWidth = $minWidth;
        $this->minHeight = $minHeight;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Verificar se é um arquivo válido
        if (!$value instanceof UploadedFile) {
            $fail('O arquivo não é válido.');
            return;
        }

        // Verificar se o upload foi bem-sucedido
        if (!$value->isValid()) {
            $fail('Erro no upload do arquivo.');
            return;
        }

        // Verificar tipo MIME
        if (!in_array($value->getMimeType(), $this->allowedMimeTypes)) {
            $allowedTypes = implode(', ', array_map(function($type) {
                return str_replace('image/', '', $type);
            }, $this->allowedMimeTypes));
            $fail("Tipo de arquivo não permitido. Use: {$allowedTypes}.");
            return;
        }

        // Verificar tamanho do arquivo
        $maxSizeInBytes = $this->maxSizeInMB * 1024 * 1024;
        if ($value->getSize() > $maxSizeInBytes) {
            $fail("Arquivo muito grande. Tamanho máximo: {$this->maxSizeInMB}MB.");
            return;
        }

        // Verificar se é realmente uma imagem válida
        $imageInfo = getimagesize($value->getRealPath());
        if ($imageInfo === false) {
            $fail('O arquivo não é uma imagem válida.');
            return;
        }

        [$width, $height] = $imageInfo;

        // Verificar dimensões mínimas
        if ($width < $this->minWidth || $height < $this->minHeight) {
            $fail("Imagem muito pequena. Tamanho mínimo: {$this->minWidth}x{$this->minHeight} pixels.");
            return;
        }

        // Verificar dimensões máximas
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $fail("Imagem muito grande. Tamanho máximo: {$this->maxWidth}x{$this->maxHeight} pixels.");
            return;
        }

        // Verificar ratio extremo (muito largo ou muito alto)
        $ratio = $width / $height;
        if ($ratio > 10 || $ratio < 0.1) {
            $fail('Proporção da imagem não permitida. Use imagens com proporções mais equilibradas.');
            return;
        }

        // Verificar assinatura do arquivo para prevenir uploads maliciosos
        if (!$this->isValidImageSignature($value)) {
            $fail('Arquivo não é uma imagem válida ou pode conter conteúdo malicioso.');
            return;
        }
    }

    /**
     * Verifica a assinatura do arquivo para validar se é realmente uma imagem
     */
    private function isValidImageSignature(UploadedFile $file): bool
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }

        $signature = fread($handle, 12);
        fclose($handle);

        // Assinaturas de arquivos de imagem conhecidos
        $validSignatures = [
            // JPEG
            "\xFF\xD8\xFF",
            // PNG
            "\x89PNG\r\n\x1a\n",
            // GIF
            "GIF87a",
            "GIF89a",
            // WebP
            "RIFF", // WebP usa RIFF container
        ];

        foreach ($validSignatures as $validSignature) {
            if (strpos($signature, $validSignature) === 0) {
                return true;
            }
        }

        // Verificação adicional para WebP
        if (strpos($signature, 'RIFF') === 0 && strpos($signature, 'WEBP') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Cria uma instância para validação de avatar
     */
    public static function avatar(): self
    {
        return new self(
            maxSizeInMB: 5,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            minWidth: 50,
            minHeight: 50,
            maxWidth: 2000,
            maxHeight: 2000
        );
    }

    /**
     * Cria uma instância para validação de imagem geral
     */
    public static function general(): self
    {
        return new self(
            maxSizeInMB: 10,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            minWidth: 100,
            minHeight: 100,
            maxWidth: 4000,
            maxHeight: 4000
        );
    }

    /**
     * Cria uma instância para validação de imagem pequena (ícones, thumbnails)
     */
    public static function small(): self
    {
        return new self(
            maxSizeInMB: 2,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            minWidth: 16,
            minHeight: 16,
            maxWidth: 500,
            maxHeight: 500
        );
    }
}