<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAudit;
use App\Models\StockAuditImportLog;
use App\Models\StockAuditItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StockAuditImportService
{
    /**
     * Process a CSV file import for counting.
     */
    public function processImport(StockAudit $audit, UploadedFile $file, int $round, ?int $areaId, int $userId): array
    {
        $finalizedField = "count_{$round}_finalized";
        if ($audit->$finalizedField) {
            return ['error' => true, 'message' => "Rodada {$round} ja esta finalizada."];
        }

        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(explode("\n", str_replace("\r", '', $content)));

        if (empty($lines)) {
            return ['error' => true, 'message' => 'Arquivo vazio.'];
        }

        // Detect format
        $format = $this->detectFormat($lines);

        $results = match ($format) {
            'tabular' => $this->parseTabular($audit, $lines, $round, $areaId, $userId),
            'collector' => $this->parseCollector($audit, $lines, $round, $areaId, $userId),
            default => ['error' => true, 'message' => 'Formato de arquivo nao reconhecido.'],
        };

        if (isset($results['error']) && $results['error']) {
            return $results;
        }

        // Save import log
        $rejectedPath = null;
        if (! empty($results['rejected_rows'])) {
            $rejectedPath = $this->generateRejectedCsv($results['rejected_rows'], $audit->id, $round);
        }

        $storedPath = $file->store('stock-audit-imports', 'local');

        StockAuditImportLog::create([
            'audit_id' => $audit->id,
            'count_round' => $round,
            'area_id' => $areaId,
            'file_name' => $file->getClientOriginalName(),
            'format_type' => $format,
            'uploaded_by_user_id' => $userId,
            'total_rows' => $results['total_rows'],
            'success_rows' => $results['success_rows'],
            'error_rows' => $results['error_rows'],
            'rejected_csv_path' => $rejectedPath,
        ]);

        return [
            'error' => false,
            'format' => $format,
            'total_rows' => $results['total_rows'],
            'success_rows' => $results['success_rows'],
            'error_rows' => $results['error_rows'],
            'rejected_rows' => count($results['rejected_rows'] ?? []),
        ];
    }

    /**
     * Detect CSV format based on content.
     * Collector: single column (barcode per line, quantity by repetition)
     * Tabular: multiple columns with header (barcode, quantity, area, etc.)
     */
    private function detectFormat(array $lines): string
    {
        $firstLine = trim($lines[0] ?? '');
        $separators = [';', ',', "\t"];

        foreach ($separators as $sep) {
            if (substr_count($firstLine, $sep) >= 1) {
                return 'tabular';
            }
        }

        return 'collector';
    }

    /**
     * Parse collector format: one barcode per line. Quantity = count of occurrences.
     */
    private function parseCollector(StockAudit $audit, array $lines, int $round, ?int $areaId, int $userId): array
    {
        $barcodeCounts = [];
        $rejected = [];
        $totalRows = 0;

        foreach ($lines as $lineNum => $line) {
            $barcode = trim($line);
            if (empty($barcode)) {
                continue;
            }
            $totalRows++;

            if (! isset($barcodeCounts[$barcode])) {
                $barcodeCounts[$barcode] = 0;
            }
            $barcodeCounts[$barcode]++;
        }

        $successRows = 0;
        $errorRows = 0;

        foreach ($barcodeCounts as $barcode => $qty) {
            $result = $this->applyCount($audit, $barcode, $round, $qty, $areaId, $userId);
            if ($result) {
                $successRows += $qty;
            } else {
                $errorRows += $qty;
                $rejected[] = [$barcode, $qty, 'Produto nao encontrado'];
            }
        }

        return [
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'error_rows' => $errorRows,
            'rejected_rows' => $rejected,
        ];
    }

    /**
     * Parse tabular format: CSV with columns (barcode, quantity, [area], [obs]).
     */
    private function parseTabular(StockAudit $audit, array $lines, int $round, ?int $areaId, int $userId): array
    {
        $separator = $this->detectSeparator($lines[0]);
        $header = array_map('trim', array_map('strtolower', explode($separator, array_shift($lines))));

        // Map column names (Portuguese + English)
        $barcodeCol = $this->findColumn($header, ['barcode', 'codigo', 'codigo_barras', 'ean', 'aux_reference', 'referencia_auxiliar']);
        $qtyCol = $this->findColumn($header, ['quantidade', 'qty', 'quantity', 'qtd', 'qtde']);

        if ($barcodeCol === null) {
            return ['error' => true, 'message' => 'Coluna de codigo de barras nao encontrada no cabecalho.'];
        }

        $totalRows = 0;
        $successRows = 0;
        $errorRows = 0;
        $rejected = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $totalRows++;

            $cols = array_map('trim', explode($separator, $line));
            $barcode = $cols[$barcodeCol] ?? '';
            $qty = $qtyCol !== null ? (float) ($cols[$qtyCol] ?? 1) : 1;

            if (empty($barcode)) {
                $errorRows++;
                $rejected[] = [$barcode, $qty, 'Codigo de barras vazio (linha '.($lineNum + 2).')'];

                continue;
            }

            $result = $this->applyCount($audit, $barcode, $round, $qty, $areaId, $userId);
            if ($result) {
                $successRows++;
            } else {
                $errorRows++;
                $rejected[] = [$barcode, $qty, 'Produto nao encontrado'];
            }
        }

        return [
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'error_rows' => $errorRows,
            'rejected_rows' => $rejected,
        ];
    }

    private function applyCount(StockAudit $audit, string $barcode, int $round, float $qty, ?int $areaId, int $userId): bool
    {
        // Find variant by aux_reference or barcode
        $variant = ProductVariant::with('product')
            ->where('aux_reference', $barcode)
            ->orWhere('barcode', $barcode)
            ->where('is_active', true)
            ->first();

        if (! $variant || ! $variant->product) {
            return false;
        }

        $roundField = "count_{$round}";

        // Find or create audit item
        $item = StockAuditItem::where('audit_id', $audit->id)
            ->where(function ($q) use ($barcode, $variant) {
                $q->where('product_barcode', $barcode)
                    ->orWhere('product_variant_id', $variant->id);
            })
            ->first();

        if (! $item) {
            $item = StockAuditItem::create([
                'audit_id' => $audit->id,
                'area_id' => $areaId,
                'product_variant_id' => $variant->id,
                'product_reference' => $variant->product->reference ?? '',
                'product_description' => $variant->product->description ?? '',
                'product_barcode' => $variant->aux_reference ?? $barcode,
                'product_size' => $variant->size_cigam_code,
                'system_quantity' => 0,
                'unit_price' => (float) ($variant->product->sale_price ?? 0),
                'cost_price' => (float) ($variant->product->cost_price ?? 0),
            ]);
        }

        $currentCount = (float) ($item->$roundField ?? 0);
        $item->update([
            $roundField => $currentCount + $qty,
            "{$roundField}_by_user_id" => $userId,
            "{$roundField}_at" => now(),
        ]);

        return true;
    }

    private function detectSeparator(string $headerLine): string
    {
        $counts = [
            ';' => substr_count($headerLine, ';'),
            ',' => substr_count($headerLine, ','),
            "\t" => substr_count($headerLine, "\t"),
        ];

        return array_keys($counts, max($counts))[0];
    }

    private function findColumn(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $header);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function generateRejectedCsv(array $rejectedRows, int $auditId, int $round): string
    {
        $filename = "stock-audit-rejected/{$auditId}_round{$round}_".now()->format('Ymd_His').'.csv';
        $content = "Barcode;Quantidade;Motivo\n";

        foreach ($rejectedRows as $row) {
            $content .= implode(';', $row)."\n";
        }

        Storage::disk('local')->put($filename, $content);

        return $filename;
    }
}
