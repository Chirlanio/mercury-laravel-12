<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItemNf;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockAdjustmentNfService
{
    /**
     * Registra um lançamento de NF (entrada e/ou saída) para um item de ajuste.
     *
     * @param  array<string,mixed>  $data
     */
    public function store(StockAdjustment $adjustment, array $data, User $user): StockAdjustmentItemNf
    {
        return DB::transaction(function () use ($adjustment, $data, $user) {
            return StockAdjustmentItemNf::create([
                'stock_adjustment_id' => $adjustment->id,
                'stock_adjustment_item_id' => $data['stock_adjustment_item_id'] ?? null,
                'nf_entrada' => $data['nf_entrada'] ?? null,
                'nf_saida' => $data['nf_saida'] ?? null,
                'nf_entrada_serie' => $data['nf_entrada_serie'] ?? null,
                'nf_saida_serie' => $data['nf_saida_serie'] ?? null,
                'nf_entrada_date' => $data['nf_entrada_date'] ?? null,
                'nf_saida_date' => $data['nf_saida_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $user->id,
            ]);
        });
    }

    public function update(StockAdjustmentItemNf $nf, array $data): StockAdjustmentItemNf
    {
        $nf->update(array_filter([
            'nf_entrada' => $data['nf_entrada'] ?? $nf->nf_entrada,
            'nf_saida' => $data['nf_saida'] ?? $nf->nf_saida,
            'nf_entrada_serie' => $data['nf_entrada_serie'] ?? $nf->nf_entrada_serie,
            'nf_saida_serie' => $data['nf_saida_serie'] ?? $nf->nf_saida_serie,
            'nf_entrada_date' => $data['nf_entrada_date'] ?? $nf->nf_entrada_date,
            'nf_saida_date' => $data['nf_saida_date'] ?? $nf->nf_saida_date,
            'notes' => $data['notes'] ?? $nf->notes,
        ], fn ($v) => $v !== null));

        return $nf->fresh();
    }

    public function delete(StockAdjustmentItemNf $nf): void
    {
        $nf->delete();
    }
}
