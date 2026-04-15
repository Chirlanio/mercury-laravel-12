<?php

namespace Database\Seeders;

use App\Models\ProductSize;
use App\Models\PurchaseOrderSizeMapping;
use Illuminate\Database\Seeder;

/**
 * Pré-popula mapeamentos óbvios entre labels conhecidos da planilha v1
 * e product_sizes sincronizados do CIGAM.
 *
 * Estratégia: pra cada label da lista de LABELS_CONHECIDOS, procura em
 * product_sizes por name exato (case-insensitive). Se acha, cria o
 * mapping com auto_detected=true. Se não acha, cria mapping pendente
 * (product_size_id=null) pra que o usuário configure depois via UI.
 *
 * Idempotente: usa updateOrCreate, pode ser re-executado sem efeito
 * colateral (mappings manuais criados pelo usuário não são sobrescritos
 * porque a condição de match inclui `auto_detected=true`).
 */
class PurchaseOrderSizeMappingSeeder extends Seeder
{
    /**
     * Labels comuns encontrados em planilhas v1 Mercury.
     * A lista completa pode crescer conforme o usuário importar novas
     * planilhas — os que aparecerem como "não mapeados" no preview
     * serão criados dinamicamente pelo SizeMappingService.
     */
    private const LABELS_CONHECIDOS = [
        // Vestuário
        'PP', 'P', 'M', 'G', 'GG',
        // Numéricos simples
        '01',
        // Sapatos adulto padrão
        '32', '33', '34', '35', '36', '37', '38', '39', '40',
        // Meio-tamanhos
        '33.5', '34.5', '35.5', '36.5', '37.5', '38.5', '39.5',
        // Tamanhos duplos (tipicamente sem match direto — ficam pendentes)
        '33/34', '35/36', '37/38', '39/40',
        // Tamanhos numéricos "jeans/cintos" (70-105)
        '70', '75', '80', '85', '90', '95', '100', '105',
    ];

    public function run(): void
    {
        // Cache dos product_sizes por name (case-insensitive)
        $sizesByName = ProductSize::active()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($s) => [mb_strtoupper(trim($s->name)) => $s->id])
            ->all();

        $created = 0;
        $resolved = 0;
        $pending = 0;

        foreach (self::LABELS_CONHECIDOS as $label) {
            $normalized = mb_strtoupper(trim($label));
            $productSizeId = $sizesByName[$normalized] ?? null;

            $mapping = PurchaseOrderSizeMapping::firstOrNew([
                'source_label' => $normalized,
            ]);

            // NÃO sobrescreve mappings criados manualmente
            if ($mapping->exists && ! $mapping->auto_detected) {
                continue;
            }

            $mapping->product_size_id = $productSizeId;
            $mapping->is_active = true;
            $mapping->auto_detected = true;
            $mapping->save();

            if ($mapping->wasRecentlyCreated) {
                $created++;
            }
            if ($productSizeId !== null) {
                $resolved++;
            } else {
                $pending++;
            }
        }

        $this->command?->info("PurchaseOrderSizeMappingSeeder: {$created} criados, {$resolved} resolvidos, {$pending} pendentes.");
    }
}
