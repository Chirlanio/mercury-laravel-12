<?php

namespace App\Imports;

use App\Models\ProductVariant;
use App\Models\StockAuditItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockAuditCountImport implements ToCollection, WithHeadingRow
{
    private int $auditId;

    private int $round;

    private ?int $areaId;

    private int $userId;

    private array $results = [
        'success' => 0,
        'errors' => 0,
        'rejected_rows' => [],
    ];

    public function __construct(int $auditId, int $round, ?int $areaId, int $userId)
    {
        $this->auditId = $auditId;
        $this->round = $round;
        $this->areaId = $areaId;
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $barcode = trim($row['barcode'] ?? $row['codigo'] ?? $row['ean'] ?? $row['codigo_barras'] ?? '');
            $quantity = (float) ($row['quantidade'] ?? $row['qty'] ?? $row['qtd'] ?? 1);
            $lineNum = $index + 2;

            if (empty($barcode)) {
                $this->results['errors']++;
                $this->results['rejected_rows'][] = [$lineNum, $barcode, 'Codigo de barras vazio'];

                continue;
            }

            $variant = ProductVariant::with('product')
                ->where('aux_reference', $barcode)
                ->orWhere('barcode', $barcode)
                ->where('is_active', true)
                ->first();

            if (! $variant || ! $variant->product) {
                $this->results['errors']++;
                $this->results['rejected_rows'][] = [$lineNum, $barcode, 'Produto nao encontrado'];

                continue;
            }

            $roundField = "count_{$this->round}";

            $item = StockAuditItem::where('audit_id', $this->auditId)
                ->where(function ($q) use ($barcode, $variant) {
                    $q->where('product_barcode', $barcode)
                        ->orWhere('product_variant_id', $variant->id);
                })
                ->first();

            if (! $item) {
                $item = StockAuditItem::create([
                    'audit_id' => $this->auditId,
                    'area_id' => $this->areaId,
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
                $roundField => $currentCount + $quantity,
                "{$roundField}_by_user_id" => $this->userId,
                "{$roundField}_at" => now(),
            ]);

            $this->results['success']++;
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
