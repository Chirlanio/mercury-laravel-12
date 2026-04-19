<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Armazena o xlsx original do orçamento para posterior re-download.
 *
 * Path: `{disk}/budgets/{year}/{yyyymmdd_hhmmss}_{uniqid}.xlsx`
 *
 * Usa o disk default do tenant (stancl/tenancy já scope por tenant no
 * storage path). Mantém o nome original em `original_filename` da tabela
 * para o usuário conseguir re-baixar com o nome familiar.
 */
class BudgetFileStorageService
{
    public function __construct(
        protected string $disk = 'local'
    ) {}

    /**
     * Armazena o arquivo e retorna o path relativo salvo em `stored_path`.
     */
    public function store(UploadedFile $file, int $year): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'xlsx';
        $filename = now()->format('Ymd_His').'_'.Str::random(8).'.'.$ext;
        $dir = "budgets/{$year}";

        return Storage::disk($this->disk)->putFileAs($dir, $file, $filename);
    }

    /**
     * Retorna o path absoluto para download/leitura.
     */
    public function absolutePath(string $storedPath): string
    {
        return Storage::disk($this->disk)->path($storedPath);
    }

    /**
     * Retorna stream/resource para download via HTTP response.
     */
    public function downloadResponse(string $storedPath, string $originalFilename)
    {
        return Storage::disk($this->disk)->download($storedPath, $originalFilename);
    }

    /**
     * Remove o arquivo físico. Usado no delete forte (hard delete).
     * Soft delete mantém o arquivo para auditoria.
     */
    public function remove(string $storedPath): bool
    {
        if (! Storage::disk($this->disk)->exists($storedPath)) {
            return false;
        }

        return Storage::disk($this->disk)->delete($storedPath);
    }

    public function exists(string $storedPath): bool
    {
        return Storage::disk($this->disk)->exists($storedPath);
    }
}
