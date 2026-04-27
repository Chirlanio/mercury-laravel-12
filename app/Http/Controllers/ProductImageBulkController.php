<?php

namespace App\Http\Controllers;

use App\Services\ProductBulkImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageBulkController extends Controller
{
    public function __construct(private readonly ProductBulkImageService $service) {}

    /**
     * Recebe apenas a lista de nomes de arquivos (sem os bytes) e responde com matched / not_found / conflicts / invalid.
     * Usado pela UI antes do envio dos lotes.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filenames' => ['required', 'array', 'min:1', 'max:1000'],
            'filenames.*' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->service->previewFilenames($validated['filenames']);

        return response()->json([
            'matched' => $result['matched'],
            'not_found' => $result['not_found'],
            'conflicts' => $result['conflicts'],
            'invalid' => $result['invalid'],
            'counts' => [
                'matched' => count($result['matched']),
                'not_found' => count($result['not_found']),
                'conflicts' => count($result['conflicts']),
                'invalid' => count($result['invalid']),
            ],
        ]);
    }

    /**
     * Recebe um lote de arquivos + decisão de conflito e processa cada um.
     * `on_conflict` aplica-se a todos os arquivos do lote (a UI já resolveu a decisão antes do envio).
     */
    public function uploadBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:5120'], // 5MB
            'on_conflict' => ['required', 'string', 'in:replace,skip'],
        ]);

        $results = [];
        foreach ($validated['files'] as $file) {
            $results[] = $this->service->processFile(
                file: $file,
                originalName: $file->getClientOriginalName(),
                onConflict: $validated['on_conflict'],
                userId: auth()->id(),
            );
        }

        return response()->json([
            'results' => $results,
            'summary' => $this->service->summarize($results),
        ]);
    }
}
