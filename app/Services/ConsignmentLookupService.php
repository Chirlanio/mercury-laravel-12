<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Movement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Lookup de produtos (catálogo local) + notas fiscais de saída/retorno
 * no CIGAM (tabela `movements`).
 *
 * Movement codes relevantes:
 *  - 20: Remessa → saída de produtos consignados (outbound)
 *  - 21: Retorno → entrada de produtos consignados que voltaram (inbound)
 *
 * NF é chave composta (store_code + invoice_number + movement_date) —
 * número reseta por ano/loja. Sempre passar a data no lookup.
 */
class ConsignmentLookupService
{
    /** Movement code para NF de saída (Remessa) */
    public const MOVEMENT_CODE_OUTBOUND = 20;

    /** Movement code para NF de retorno */
    public const MOVEMENT_CODE_RETURN = 21;

    // ==================================================================
    // Consultores (employees) por loja — filtro do select no modal
    // ==================================================================

    /**
     * Colaboradores ativos de uma loja. Aceita store_id (bigint FK) ou
     * store_code (varchar 'Z421'). O modal envia store_id porque esse é
     * o valor que ele já tem no state do React.
     *
     * Observação: Employee.store_id na tabela é VARCHAR com o CODE da
     * loja (não é FK para stores.id — herança da v1). Por isso traduzimos.
     *
     * @return Collection<int, array{id:int, name:string}>
     */
    public function employeesByStore(int|string|null $storeIdOrCode): Collection
    {
        if (! $storeIdOrCode) {
            return collect();
        }

        // Se veio um int, resolve para o code via Store; se veio string, usa direto.
        $storeCode = is_int($storeIdOrCode) || ctype_digit((string) $storeIdOrCode)
            ? Store::query()->where('id', (int) $storeIdOrCode)->value('code')
            : (string) $storeIdOrCode;

        if (! $storeCode) {
            return collect();
        }

        return Employee::query()
            ->where('store_id', $storeCode)
            ->where('status_id', 2) // 2 = Ativo
            ->orderBy('name')
            ->get(['id', 'name', 'short_name'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'short_name' => $e->short_name,
            ]);
    }

    // ==================================================================
    // Produtos — autocomplete e EAN (regra M8)
    // ==================================================================

    /**
     * Busca produtos para autocomplete. Aceita query parcial em múltiplos
     * campos (reference, description, barcode, aux_reference). EAN-13
     * (13 dígitos) é tratado como busca exata pelo barcode.
     *
     * @return Collection<int, array{
     *   product_id: int,
     *   reference: string,
     *   description: ?string,
     *   sale_price: ?float,
     *   is_active: bool,
     *   variants: array<int, array{
     *     id: int,
     *     barcode: ?string,
     *     size_cigam_code: ?string,
     *     aux_reference: ?string,
     *   }>,
     * }>
     */
    public function searchProducts(string $query, int $limit = 20): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $digitsOnly = preg_replace('/\D/', '', $query);
        $isEan13 = strlen($digitsOnly) === 13;

        $builder = Product::query()
            ->where('is_active', true);

        if ($isEan13) {
            // EAN-13 exato — busca via variant
            $builder->whereHas('variants', fn ($q) => $q->where('barcode', $digitsOnly));
        } else {
            $like = '%'.$query.'%';
            $builder->where(function ($q) use ($query, $like) {
                $q->where('reference', 'like', $query.'%')
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas(
                        'variants',
                        fn ($sub) => $sub->where('barcode', 'like', $query.'%')
                            ->orWhere('aux_reference', 'like', $like)
                    );
            });
        }

        return $builder
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('reference')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'reference' => $p->reference,
                'description' => $p->description,
                'sale_price' => $p->sale_price !== null ? (float) $p->sale_price : null,
                'is_active' => (bool) $p->is_active,
                'variants' => $p->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'barcode' => $v->barcode,
                    'size_cigam_code' => $v->size_cigam_code,
                    'aux_reference' => $v->aux_reference,
                ])->values()->all(),
            ]);
    }

    /**
     * Resolve product + variant a partir de dados do CIGAM. Usado pelo
     * lookup de NF para popular itens automaticamente.
     *
     * Estratégia em ordem de tentativa:
     *  1. `ref_size` como barcode do variant (padrão Mercury — o sync
     *     de produtos grava `movements.ref_size` em
     *     `product_variants.barcode`).
     *  2. `barcode` do movement (EAN numérico).
     *  3. `reference` + `size_cigam_code` — fallback.
     *
     * Carrega sempre `product` + `size` (product_sizes via
     * size_cigam_code → cigam_code) para que o caller tenha o nome
     * humano do tamanho disponível (product_sizes.name = "35", "36"...).
     *
     * Retorna null quando nada resolve → item vira "órfão" (regra M8).
     *
     * @return array{product: Product, variant: ?ProductVariant}|null
     */
    public function resolveProductVariant(
        ?string $reference = null,
        ?string $barcode = null,
        ?string $sizeCigamCode = null,
        ?string $refSize = null,
    ): ?array {
        // 1. ref_size → product_variants.barcode (padrão Mercury/CIGAM)
        if ($refSize) {
            $variant = ProductVariant::query()
                ->with(['product', 'size'])
                ->where('barcode', $refSize)
                ->where('is_active', true)
                ->first();

            if ($variant && $variant->product) {
                return ['product' => $variant->product, 'variant' => $variant];
            }
        }

        // 2. EAN numérico (movements.barcode)
        if ($barcode && $barcode !== $refSize) {
            $variant = ProductVariant::query()
                ->with(['product', 'size'])
                ->where('barcode', $barcode)
                ->where('is_active', true)
                ->first();

            if ($variant && $variant->product) {
                return ['product' => $variant->product, 'variant' => $variant];
            }
        }

        // 3. Fallback por reference + size
        if ($reference) {
            $product = Product::query()
                ->where('reference', $reference)
                ->where('is_active', true)
                ->first();

            if ($product) {
                $variant = $sizeCigamCode
                    ? ProductVariant::query()
                        ->with('size')
                        ->where('product_id', $product->id)
                        ->where('size_cigam_code', $sizeCigamCode)
                        ->first()
                    : null;

                return ['product' => $product, 'variant' => $variant];
            }
        }

        return null;
    }

    // ==================================================================
    // NF de saída (movement_code = 20 — Remessa)
    // ==================================================================

    /**
     * Busca os movements de uma NF de saída (code=20) e monta o payload
     * para o modal de criação. Resolve cada item em products/variants
     * via `resolveProductVariant`. Itens órfãos (sem produto no catálogo)
     * são sinalizados em `orphan_items` para o front bloquear o cadastro
     * (regra M8).
     *
     * @return array{
     *   found: bool,
     *   invoice_number: string,
     *   store_code: ?string,
     *   movement_date: ?string,
     *   cpf_customer: ?string,
     *   total_value: float,
     *   items_count: int,
     *   available_dates: array<int, string>,
     *   items: array<int, array<string, mixed>>,
     *   orphan_items: array<int, array<string, mixed>>,
     * }
     */
    public function findOutboundInvoice(
        string $storeCode,
        string $invoiceNumber,
        ?string $movementDate = null,
    ): array {
        return $this->findInvoice(
            self::MOVEMENT_CODE_OUTBOUND,
            $storeCode,
            $invoiceNumber,
            $movementDate,
        );
    }

    // ==================================================================
    // NF de retorno (movement_code = 21 — Retorno)
    // ==================================================================

    /**
     * Busca os movements de uma NF de retorno (code=21) e monta o payload
     * para o modal de lançamento de retorno. O diff contra os itens de
     * saída é feito em ConsignmentReturnService::register (regra M1).
     */
    public function findReturnInvoice(
        string $storeCode,
        string $invoiceNumber,
        ?string $movementDate = null,
    ): array {
        return $this->findInvoice(
            self::MOVEMENT_CODE_RETURN,
            $storeCode,
            $invoiceNumber,
            $movementDate,
        );
    }

    /**
     * @return array{
     *   found: bool,
     *   invoice_number: string,
     *   store_code: ?string,
     *   movement_date: ?string,
     *   cpf_customer: ?string,
     *   total_value: float,
     *   items_count: int,
     *   available_dates: array<int, string>,
     *   items: array<int, array<string, mixed>>,
     *   orphan_items: array<int, array<string, mixed>>,
     * }
     */
    protected function findInvoice(
        int $movementCode,
        string $storeCode,
        string $invoiceNumber,
        ?string $movementDate,
    ): array {
        $invoiceNumber = trim($invoiceNumber);
        $storeCode = trim($storeCode);

        /** @var Collection<int, Movement> $all */
        $all = Movement::query()
            ->where('movement_code', $movementCode)
            ->where('store_code', $storeCode)
            ->where('invoice_number', $invoiceNumber)
            ->orderByDesc('movement_date')
            ->orderBy('id')
            ->get();

        if ($all->isEmpty()) {
            return [
                'found' => false,
                'invoice_number' => $invoiceNumber,
                'store_code' => $storeCode,
                'movement_date' => null,
                'cpf_customer' => null,
                'total_value' => 0.0,
                'items_count' => 0,
                'available_dates' => [],
                'items' => [],
                'orphan_items' => [],
            ];
        }

        $availableDates = $all->pluck('movement_date')
            ->filter()
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        $targetDate = $movementDate && in_array($movementDate, $availableDates, true)
            ? $movementDate
            : ($availableDates[0] ?? null);

        $movements = $targetDate
            ? $all->filter(fn (Movement $m) => $m->movement_date?->format('Y-m-d') === $targetDate)
            : $all;

        $header = $movements->first();

        $items = [];
        $orphans = [];

        foreach ($movements as $m) {
            // splitRefSize retorna fallback quando CIGAM usar formato
            // com pipe. No padrão Mercury (sem pipe), usamos refSize
            // direto no resolver para casar product_variants.barcode.
            [$fallbackRef, $fallbackSize] = $this->splitRefSize($m->ref_size);

            $resolved = $this->resolveProductVariant(
                reference: $fallbackRef,
                barcode: $m->barcode,
                refSize: $m->ref_size,
            );

            if ($resolved) {
                // Usa nomes REAIS via join:
                //   - reference ← products.reference ('A1340000010002')
                //   - size_label ← product_sizes.name ('35') via
                //     product_variants.size_cigam_code → product_sizes.cigam_code
                //   - size_cigam_code ← product_variants.size_cigam_code (código interno, ex: '3')
                //
                // Estrutura CIGAM: size_cigam_code é código numérico
                // compacto ('3'); o tamanho humano ('35') vive em
                // product_sizes.name com cigam_code=size_cigam_code.
                $product = $resolved['product'];
                $variant = $resolved['variant'];
                $sizeCigam = $variant?->size_cigam_code;
                $sizeLabel = $variant?->size?->name ?? $fallbackSize;

                $items[] = [
                    'movement_id' => $m->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'reference' => $product->reference,  // products.reference (real)
                    'description' => $product->description,
                    'barcode' => $variant?->barcode ?? $m->barcode,
                    'size_cigam_code' => $sizeCigam,
                    'size_label' => $sizeLabel,          // product_sizes.name (humano)
                    'quantity' => (int) $m->quantity,
                    'unit_value' => (float) $m->sale_price,
                    'total_value' => (float) $m->realized_value,
                ];
            } else {
                // Regra M8: produto inexistente no catálogo bloqueia o
                // cadastro. Front mostra os órfãos para o usuário resolver.
                $orphans[] = [
                    'movement_id' => $m->id,
                    'reference' => $fallbackRef,
                    'size_label' => $fallbackSize,
                    'barcode' => $m->barcode,
                    'quantity' => (int) $m->quantity,
                    'unit_value' => (float) $m->sale_price,
                    'total_value' => (float) $m->realized_value,
                ];
            }
        }

        return [
            'found' => true,
            'invoice_number' => $invoiceNumber,
            'store_code' => $storeCode,
            'movement_date' => $targetDate,
            'cpf_customer' => $header?->cpf_customer,
            'total_value' => (float) $movements->sum('realized_value'),
            'items_count' => $movements->count(),
            'available_dates' => $availableDates,
            'items' => $items,
            'orphan_items' => $orphans,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitRefSize(?string $refSize): array
    {
        if (! $refSize) {
            return [null, null];
        }

        $parts = explode('|', $refSize, 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
