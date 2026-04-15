<?php

namespace App\Services;

use App\Models\ProductBrand;
use App\Models\PurchaseOrderBrandAlias;
use Illuminate\Support\Facades\DB;

/**
 * Serviço central de resolução de nomes de marca da planilha de
 * importação vs product_brands oficiais (CIGAM).
 *
 * Estratégia de resolução em 3 camadas:
 *  1. Match direto em product_brands.name (case-insensitive, normalizado)
 *  2. Lookup em purchase_order_brand_aliases (convenções e variações)
 *  3. Falha (marca desconhecida — rejeita a ordem)
 */
class PurchaseOrderBrandAliasService
{
    /** @var array<string, int|null>|null cache em memória por request */
    protected ?array $brandCache = null;

    /** @var array<string, int|null>|null cache dos aliases */
    protected ?array $aliasCache = null;

    /**
     * Resolve um nome de marca pra product_brand_id.
     * Retorna null se não acha em nenhuma camada.
     */
    public function resolve(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $normalized = PurchaseOrderBrandAlias::normalizeName($name);

        // Camada 1: match direto em product_brands
        $this->loadBrandCache();
        if (isset($this->brandCache[$normalized])) {
            return $this->brandCache[$normalized];
        }

        // Camada 2: consulta alias ativo com product_brand_id preenchido
        $this->loadAliasCache();
        return $this->aliasCache[$normalized] ?? null;
    }

    /**
     * Dada uma lista de nomes, retorna 3 grupos: known (match direto),
     * aliased (resolvido via alias) e unknown (sem match nenhum).
     *
     * @param  array<int, string>  $names
     * @return array{
     *     known: array<int, array{name: string, product_brand_id: int, product_brand_name: ?string}>,
     *     aliased: array<int, array{name: string, product_brand_id: int, product_brand_name: ?string}>,
     *     unknown: array<int, string>
     * }
     */
    public function classify(array $names): array
    {
        $this->loadBrandCache();
        $this->loadAliasCache();

        $brandNames = ProductBrand::pluck('name', 'id')->all();

        $known = [];
        $aliased = [];
        $unknown = [];
        $seen = [];

        foreach ($names as $name) {
            $normalized = PurchaseOrderBrandAlias::normalizeName($name);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            if (isset($this->brandCache[$normalized])) {
                $id = $this->brandCache[$normalized];
                $known[] = [
                    'name' => $name,
                    'product_brand_id' => $id,
                    'product_brand_name' => $brandNames[$id] ?? null,
                ];
                continue;
            }

            if (isset($this->aliasCache[$normalized])) {
                $id = $this->aliasCache[$normalized];
                $aliased[] = [
                    'name' => $name,
                    'product_brand_id' => $id,
                    'product_brand_name' => $brandNames[$id] ?? null,
                ];
                continue;
            }

            $unknown[] = $name;
        }

        return compact('known', 'aliased', 'unknown');
    }

    /**
     * Auto-detect: pra cada source_name sem mapping, tenta encontrar
     * uma product_brand cujo nome seja "MS {source_name}" (convenção do
     * CIGAM para marcas próprias da Meia Sola).
     *
     * Pode ser chamado:
     *  - Do CRUD (botão "Auto-detectar")
     *  - Do import quando detecta nomes novos
     *  - Via seeder após sync CIGAM trazer marcas novas
     *
     * Não sobrescreve aliases manuais existentes.
     *
     * @return array{detected: int, skipped: int}
     */
    public function autoDetectMsPrefix(): array
    {
        $brandsByNormalized = ProductBrand::get(['id', 'name'])
            ->mapWithKeys(fn ($b) => [
                PurchaseOrderBrandAlias::normalizeName($b->name) => $b->id,
            ])
            ->all();

        $pending = PurchaseOrderBrandAlias::whereNull('product_brand_id')
            ->get();

        $detected = 0;
        $skipped = 0;

        foreach ($pending as $alias) {
            if (! $alias->auto_detected && $alias->notes) {
                // Usuário já mexeu manualmente — respeita
                $skipped++;
                continue;
            }

            $candidate = 'MS ' . $alias->source_name;
            $candidateNormalized = PurchaseOrderBrandAlias::normalizeName($candidate);

            if (isset($brandsByNormalized[$candidateNormalized])) {
                $alias->product_brand_id = $brandsByNormalized[$candidateNormalized];
                $alias->auto_detected = true;
                $alias->save();
                $detected++;
            } else {
                $skipped++;
            }
        }

        $this->brandCache = null;
        $this->aliasCache = null;

        return compact('detected', 'skipped');
    }

    /**
     * Garante que existem registros (mesmo pendentes) pra cada nome
     * passado. Usado pelo ImportService quando detecta nomes novos,
     * pra que apareçam no CRUD mesmo antes do usuário resolver.
     *
     * @param  array<int, string>  $names
     */
    public function ensureNamesExist(array $names): void
    {
        $this->loadBrandCache();

        foreach ($names as $name) {
            $normalized = PurchaseOrderBrandAlias::normalizeName($name);
            if ($normalized === '') {
                continue;
            }

            // Se a marca já existe em product_brands, não cria alias
            // (não tem sentido alias apontar pra match direto)
            if (isset($this->brandCache[$normalized])) {
                continue;
            }

            PurchaseOrderBrandAlias::firstOrCreate(
                ['source_name' => $normalized],
                [
                    'product_brand_id' => null,
                    'is_active' => true,
                    'auto_detected' => true,
                    'notes' => 'Detectado automaticamente durante importação',
                ]
            );
        }

        $this->aliasCache = null;
    }

    /**
     * Cria uma ProductBrand manualmente (pra casos onde a marca não existe
     * no CIGAM) e registra um alias apontando pra ela, em transaction.
     *
     * @return array{product_brand: ProductBrand, alias: PurchaseOrderBrandAlias}
     */
    public function createManualBrandWithAlias(
        string $sourceName,
        string $brandName,
        ?int $userId = null,
    ): array {
        return DB::transaction(function () use ($sourceName, $brandName, $userId) {
            // Cria product_brand com cigam_code prefixado MANUAL- pra distinguir
            // de marcas sincronizadas. O timestamp garante unicidade mesmo se
            // duas marcas forem criadas no mesmo segundo.
            $brand = ProductBrand::create([
                'cigam_code' => 'MANUAL-' . now()->format('YmdHis') . '-' . substr(md5(uniqid()), 0, 6),
                'name' => trim($brandName),
                'is_active' => true,
            ]);

            $alias = PurchaseOrderBrandAlias::updateOrCreate(
                ['source_name' => PurchaseOrderBrandAlias::normalizeName($sourceName)],
                [
                    'product_brand_id' => $brand->id,
                    'is_active' => true,
                    'auto_detected' => false,
                    'notes' => "Marca criada manualmente em {$brand->cigam_code}",
                    'created_by_user_id' => $userId,
                    'updated_by_user_id' => $userId,
                ]
            );

            $this->brandCache = null;
            $this->aliasCache = null;

            return ['product_brand' => $brand, 'alias' => $alias];
        });
    }

    // ------------------------------------------------------------------

    protected function loadBrandCache(): void
    {
        if ($this->brandCache !== null) {
            return;
        }

        $this->brandCache = ProductBrand::get(['id', 'name'])
            ->mapWithKeys(fn ($b) => [
                PurchaseOrderBrandAlias::normalizeName($b->name) => $b->id,
            ])
            ->all();
    }

    protected function loadAliasCache(): void
    {
        if ($this->aliasCache !== null) {
            return;
        }

        $this->aliasCache = PurchaseOrderBrandAlias::active()
            ->resolved()
            ->get(['source_name', 'product_brand_id'])
            ->mapWithKeys(fn ($a) => [$a->source_name => $a->product_brand_id])
            ->all();
    }
}
