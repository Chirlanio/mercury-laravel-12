<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductPricesImport implements ToCollection, WithHeadingRow
{
    protected array $results = [
        'success' => 0,
        'unchanged' => 0,
        'skipped_locked' => 0,
        'not_found' => 0,
        'errors' => [],
        'rejected_rows' => [],
    ];

    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        // Pre-load all references for this batch to avoid N+1
        $references = $rows->map(fn ($row) => trim($this->getColumn($row, 'referencia')))->filter()->unique();
        $products = Product::whereIn('reference', $references)->get()->keyBy('reference');

        foreach ($rows as $index => $row) {
            $lineNum = $index + 2; // +2 for header row + 0-based index

            try {
                $reference = trim($this->getColumn($row, 'referencia'));

                if (empty($reference)) {
                    $this->addRejected($lineNum, '', null, null, 'Referência vazia');
                    continue;
                }

                $rawSalePrice = $this->getColumn($row, 'preco_venda');
                $rawCostPrice = $this->getColumn($row, 'custo');

                $salePrice = $this->parsePrice($rawSalePrice);
                $costPrice = $this->parsePrice($rawCostPrice);

                if ($salePrice === null && $costPrice === null) {
                    $this->addRejected($lineNum, $reference, $rawSalePrice, $rawCostPrice, 'Nenhum preço informado');
                    continue;
                }

                $product = $products[$reference] ?? null;

                if (! $product) {
                    $this->results['not_found']++;
                    $this->addRejected($lineNum, $reference, $rawSalePrice, $rawCostPrice, 'Produto não encontrado');
                    continue;
                }

                if ($product->sync_locked) {
                    $this->results['skipped_locked']++;
                    $this->addRejected($lineNum, $reference, $rawSalePrice, $rawCostPrice, 'Produto bloqueado para sincronização');
                    continue;
                }

                // Check if prices actually changed
                $newSale = $salePrice !== null ? round($salePrice, 2) : round((float) $product->sale_price, 2);
                $newCost = $costPrice !== null ? round($costPrice, 2) : round((float) $product->cost_price, 2);

                if ($newSale == round((float) $product->sale_price, 2) && $newCost == round((float) $product->cost_price, 2)) {
                    $this->results['unchanged']++;
                    continue;
                }

                $product->update([
                    'sale_price' => $newSale,
                    'cost_price' => $newCost,
                    'updated_by_user_id' => $this->userId,
                ]);

                $this->results['success']++;
            } catch (\Exception $e) {
                $this->results['errors'][] = "Linha {$lineNum}: {$e->getMessage()}";
            }
        }
    }

    protected function getColumn(Collection $row, string $key): mixed
    {
        // Try multiple column name variations
        $variations = match ($key) {
            'referencia' => ['referencia', 'reference', 'ref', 'codigo', 'código'],
            'preco_venda' => ['preco_venda', 'preco_de_venda', 'sale_price', 'venda', 'preco'],
            'custo' => ['custo', 'preco_custo', 'cost_price', 'cost', 'preco_de_custo'],
            default => [$key],
        };

        foreach ($variations as $v) {
            if ($row->has($v) && $row[$v] !== null) {
                return $row[$v];
            }
        }

        return null;
    }

    protected function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        $value = trim($value);
        $value = str_replace(['R$', 'r$', ' '], '', $value);

        if ($value === '') {
            return null;
        }

        // Brazilian format: 1.234,56
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $parts = explode(',', $value);
            if (strlen(end($parts)) <= 2) {
                $value = str_replace(',', '.', $value);
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function addRejected(int $line, string $ref, mixed $salePrice, mixed $costPrice, string $reason): void
    {
        $this->results['rejected_rows'][] = [
            $line,
            $ref,
            $salePrice ?? '',
            $costPrice ?? '',
            $reason,
        ];
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
