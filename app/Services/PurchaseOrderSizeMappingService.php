<?php

namespace App\Services;

use App\Models\ProductSize;
use App\Models\PurchaseOrderSizeMapping;
use Illuminate\Support\Collection;

/**
 * Serviço central de de-para de tamanhos da planilha v1 para product_sizes
 * oficiais (CIGAM).
 *
 * Usado pelo PurchaseOrderImportService durante o import e pela UI do
 * CRUD de mappings em Configurações.
 */
class PurchaseOrderSizeMappingService
{
    /** @var array<string, int|null>|null cache em memória por request */
    protected ?array $cache = null;

    /**
     * Resolve um label da planilha pro product_size_id correspondente.
     * Retorna null se:
     *  - Não existe mapping pro label
     *  - Existe mapping mas está inativo
     *  - Existe mapping mas product_size_id é null (pendente)
     *
     * Thread-safe por request (cache em memória).
     */
    public function resolve(?string $label): ?int
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $this->loadCache();

        $normalized = PurchaseOrderSizeMapping::normalizeLabel($label);
        return $this->cache[$normalized] ?? null;
    }

    /**
     * Dada uma lista de labels detectados na planilha, retorna duas
     * listas: os que têm mapping resolvido e os que estão pendentes
     * (sem mapping OU sem product_size_id).
     *
     * @param  array<int, string>  $labels
     * @return array{resolved: array<int, string>, pending: array<int, string>}
     */
    public function classify(array $labels): array
    {
        $this->loadCache();

        $resolved = [];
        $pending = [];

        foreach ($labels as $label) {
            $normalized = PurchaseOrderSizeMapping::normalizeLabel($label);
            if ($normalized === '') {
                continue;
            }

            if (isset($this->cache[$normalized]) && $this->cache[$normalized] !== null) {
                $resolved[] = $normalized;
            } else {
                $pending[] = $normalized;
            }
        }

        return [
            'resolved' => array_values(array_unique($resolved)),
            'pending' => array_values(array_unique($pending)),
        ];
    }

    /**
     * Varre product_sizes e cria/atualiza mappings auto-detectáveis por
     * name match exato (case-insensitive). Útil depois do sync CIGAM
     * trazer tamanhos novos.
     *
     * Não sobrescreve mappings manuais (auto_detected=false).
     *
     * @return array{created: int, updated: int, pending: int}
     */
    public function autoDetectFromProductSizes(): array
    {
        $productSizes = ProductSize::active()->get(['id', 'name']);

        $created = 0;
        $updated = 0;
        $pending = 0;

        foreach ($productSizes as $size) {
            $label = PurchaseOrderSizeMapping::normalizeLabel($size->name);
            if ($label === '') {
                continue;
            }

            $mapping = PurchaseOrderSizeMapping::firstOrNew(['source_label' => $label]);

            // Respeita mappings manuais existentes
            if ($mapping->exists && ! $mapping->auto_detected) {
                continue;
            }

            $wasNew = ! $mapping->exists;
            $mapping->product_size_id = $size->id;
            $mapping->is_active = true;
            $mapping->auto_detected = true;
            $mapping->save();

            if ($wasNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $pending = PurchaseOrderSizeMapping::whereNull('product_size_id')->count();

        $this->cache = null; // invalida

        return compact('created', 'updated', 'pending');
    }

    /**
     * Garante que existe pelo menos um registro (mesmo que pendente) pra
     * cada label passado. Usado pelo import quando detecta labels novos
     * pra deixá-los visíveis no CRUD mesmo antes do usuário resolver.
     *
     * @param  array<int, string>  $labels
     */
    public function ensureLabelsExist(array $labels): void
    {
        foreach ($labels as $label) {
            $normalized = PurchaseOrderSizeMapping::normalizeLabel($label);
            if ($normalized === '') {
                continue;
            }

            PurchaseOrderSizeMapping::firstOrCreate(
                ['source_label' => $normalized],
                [
                    'product_size_id' => null,
                    'is_active' => true,
                    'auto_detected' => true,
                    'notes' => 'Detectado automaticamente durante importação',
                ]
            );
        }

        $this->cache = null;
    }

    /**
     * Carrega todos os mappings ativos em cache de memória.
     */
    protected function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = PurchaseOrderSizeMapping::active()
            ->get(['source_label', 'product_size_id'])
            ->mapWithKeys(fn ($m) => [$m->source_label => $m->product_size_id])
            ->all();
    }
}
