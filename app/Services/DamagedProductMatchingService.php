<?php

namespace App\Services;

use App\Enums\DamageMatchStatus;
use App\Enums\DamageMatchType;
use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Enums\Permission;
use App\Events\DamagedProductMatchAccepted;
use App\Events\DamagedProductMatchFound;
use App\Events\DamagedProductMatchRejected;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\NetworkBrandRule;
use App\Models\Store;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Engine de matching — núcleo de valor do módulo.
 *
 * Responsabilidades:
 *  1. Identificar candidatos complementares (mismatched_pair, damaged_complement)
 *     pra um produto novo OU pra a base inteira (full scan agendado)
 *  2. Validar compatibilidade de marca/rede (whitelist bidirecional)
 *  3. Calcular score real (não 100% fixo como na v1) — pondera idade,
 *     prioridade da loja e bonificação por marca homóloga
 *  4. Persistir o match (com convenção product_a_id < product_b_id pra unique
 *     constraint funcionar em qualquer ordem)
 *  5. Sugerir direção da transferência (destino = loja com menor store_order
 *     = prioridade maior; origem = a outra)
 *  6. Aceitar/rejeitar/expirar matches e propagar transições
 *
 * NÃO faz mutação de DamagedProduct::status diretamente — delega ao
 * DamagedProductTransitionService pra manter o ponto único de mutação.
 */
class DamagedProductMatchingService
{
    public function __construct(
        protected DamagedProductTransitionService $transitions,
    ) {}

    // ==================================================================
    // Discovery
    // ==================================================================

    /**
     * Cria todos os matches viáveis pra um produto recém-criado/editado.
     * Idempotente — não duplica matches já existentes (unique pair constraint).
     *
     * @return Collection<int,DamagedProductMatch>
     */
    public function findMatchesFor(DamagedProduct $product): Collection
    {
        if ($product->status !== DamagedProductStatus::OPEN) {
            return collect();
        }

        $created = collect();

        if ($product->is_mismatched) {
            $created = $created->merge($this->findMismatchedPairCandidates($product));
        }

        if ($product->is_damaged) {
            $created = $created->merge($this->findDamagedComplementCandidates($product));
        }

        return $created;
    }

    /**
     * Full scan — itera todos os produtos OPEN e tenta matchar.
     * Retorna estatísticas pro command agendado/admin.
     *
     * @return array{scanned:int,matches_created:int}
     */
    public function runFullMatching(): array
    {
        $matchesCreated = 0;
        $scanned = 0;

        DamagedProduct::query()
            ->open()
            ->orderBy('id') // estabilidade — A<B na convenção do match
            ->chunkById(200, function ($products) use (&$scanned, &$matchesCreated) {
                foreach ($products as $product) {
                    $scanned++;
                    $matchesCreated += $this->findMatchesFor($product)->count();
                }
            });

        return [
            'scanned' => $scanned,
            'matches_created' => $matchesCreated,
        ];
    }

    /**
     * Candidatos pra par trocado: mesma referência, lojas distintas, status=open,
     * pés cruzados. Ex: A=(left 38, right 39) ↔ B=(left 39, right 38).
     * Combinando o esquerdo de A com o direito de B (ambos 38) e o direito
     * de A com o esquerdo de B (ambos 39) → forma 2 pares íntegros.
     *
     * @return Collection<int,DamagedProductMatch>
     */
    public function findMismatchedPairCandidates(DamagedProduct $a): Collection
    {
        if (! $a->is_mismatched || ! $a->mismatched_left_size || ! $a->mismatched_right_size) {
            return collect();
        }

        $candidates = DamagedProduct::query()
            ->open()
            ->where('product_reference', $a->product_reference)
            ->where('store_id', '!=', $a->store_id)
            ->where('id', '!=', $a->id)
            ->where('is_mismatched', true)
            ->where('mismatched_left_size', $a->mismatched_right_size)
            ->where('mismatched_right_size', $a->mismatched_left_size)
            ->with(['store.network'])
            ->get();

        return $candidates
            ->map(fn ($b) => $this->createMatch($a, $b, DamageMatchType::MISMATCHED_PAIR))
            ->filter();
    }

    /**
     * Candidatos pra avaria complementar: mesma referência, lojas distintas,
     * pés opostos avariados (não 'both' nem 'na') E **mesmo tamanho** —
     * só assim o pé bom de A casa anatomicamente com o pé bom de B
     * formando um par íntegro.
     *
     * @return Collection<int,DamagedProductMatch>
     */
    public function findDamagedComplementCandidates(DamagedProduct $a): Collection
    {
        if (
            ! $a->is_damaged
            || ! $a->damaged_foot
            || ! $a->damaged_foot->isSingleFoot()
            || empty($a->damaged_size)
        ) {
            return collect();
        }

        $oppositeFoot = $a->damaged_foot->opposite();

        $candidates = DamagedProduct::query()
            ->open()
            ->where('product_reference', $a->product_reference)
            ->where('store_id', '!=', $a->store_id)
            ->where('id', '!=', $a->id)
            ->where('is_damaged', true)
            ->where('damaged_foot', $oppositeFoot->value)
            ->where('damaged_size', $a->damaged_size)
            ->with(['store.network'])
            ->get();

        return $candidates
            ->map(fn ($b) => $this->createMatch($a, $b, DamageMatchType::DAMAGED_COMPLEMENT))
            ->filter();
    }

    // ==================================================================
    // Brand/Network compatibility
    // ==================================================================

    /**
     * Whitelist bidirecional: a marca do produto B precisa ser aceita pela
     * rede da loja A E vice-versa. Rede sem regras = aceita qualquer marca.
     */
    public function areStoresBrandCompatible(Store $a, Store $b, ?string $brandA, ?string $brandB): bool
    {
        return $this->networkAcceptsBrand($a->network_id, $brandB)
            && $this->networkAcceptsBrand($b->network_id, $brandA);
    }

    protected function networkAcceptsBrand(?int $networkId, ?string $brand): bool
    {
        if (! $networkId) {
            // Loja sem rede: comportamento permissivo
            return true;
        }

        $hasRules = NetworkBrandRule::query()
            ->forNetwork($networkId)
            ->active()
            ->exists();

        if (! $hasRules) {
            return true; // Default permissivo
        }

        if (! $brand) {
            // Rede com regras + produto sem marca: bloqueia (sinaliza ajuste de cadastro)
            return false;
        }

        return NetworkBrandRule::query()
            ->forNetwork($networkId)
            ->active()
            ->where('brand_cigam_code', $brand)
            ->exists();
    }

    // ==================================================================
    // Score (melhoria v2 — v1 era 100% fixo)
    // ==================================================================

    /**
     * Score 0-100 pondera:
     *  - 60 pontos base: match estrutural válido
     *  - até 30 pontos: idade do registro mais antigo (mais idade = mais prioridade)
     *  - até 10 pontos: bonificação por marca idêntica (mesma referência sempre é,
     *    mas FK pra brand pode estar inconsistente — bonifica quando bate)
     */
    public function computeMatchScore(DamagedProduct $a, DamagedProduct $b): float
    {
        $score = 60.0;

        $oldestAgeDays = max(
            now()->diffInDays($a->created_at ?? now()),
            now()->diffInDays($b->created_at ?? now())
        );
        $score += min(30.0, $oldestAgeDays * 0.5);

        if ($a->brand_cigam_code && $a->brand_cigam_code === $b->brand_cigam_code) {
            $score += 10.0;
        }

        return round(min(100.0, $score), 2);
    }

    // ==================================================================
    // Direção sugerida (destino = menor store_order)
    // ==================================================================

    /**
     * @return array{origin:Store,destination:Store}
     */
    public function determineSuggestedDirection(Store $a, Store $b): array
    {
        $aOrder = $a->store_order ?? PHP_INT_MAX;
        $bOrder = $b->store_order ?? PHP_INT_MAX;

        if ($aOrder <= $bOrder) {
            // A tem prioridade maior (menor order) → recebe
            return ['origin' => $b, 'destination' => $a];
        }

        return ['origin' => $a, 'destination' => $b];
    }

    // ==================================================================
    // Persistência de match (com convenção A<B)
    // ==================================================================

    /**
     * Cria um match A↔B se viável (compatibilidade de marca + ainda não existe).
     * Não modifica os DamagedProduct::status — quem faz isso é
     * findMatchesFor/full scan ao final, via TransitionService como system actor.
     *
     * Retorna o DamagedProductMatch criado (ou já existente reativado), ou null
     * se brand compat falhar.
     */
    public function createMatch(DamagedProduct $a, DamagedProduct $b, DamageMatchType $type): ?DamagedProductMatch
    {
        if ($a->id === $b->id) {
            return null;
        }

        if (! $a->store || ! $b->store) {
            return null;
        }

        if (! $this->areStoresBrandCompatible($a->store, $b->store, $a->brand_cigam_code, $b->brand_cigam_code)) {
            return null;
        }

        // Convenção: product_a_id < product_b_id (unique pair constraint
        // funciona em qualquer ordem que a engine descubra)
        [$lower, $higher] = $a->id < $b->id ? [$a, $b] : [$b, $a];

        $direction = $this->determineSuggestedDirection($lower->store, $higher->store);
        $score = $this->computeMatchScore($lower, $higher);

        $payload = [
            'product_reference' => $lower->product_reference,
            'match_type' => $type->value,
            'product_a' => [
                'id' => $lower->id,
                'store_code' => $lower->store->code,
                'brand' => $lower->brand_cigam_code,
                'brand_name' => $lower->brand_name,
                'mismatched_left_size' => $lower->mismatched_left_size,
                'mismatched_right_size' => $lower->mismatched_right_size,
                'damaged_foot' => $lower->damaged_foot?->value,
                'damaged_size' => $lower->damaged_size,
            ],
            'product_b' => [
                'id' => $higher->id,
                'store_code' => $higher->store->code,
                'brand' => $higher->brand_cigam_code,
                'brand_name' => $higher->brand_name,
                'mismatched_left_size' => $higher->mismatched_left_size,
                'mismatched_right_size' => $higher->mismatched_right_size,
                'damaged_foot' => $higher->damaged_foot?->value,
                'damaged_size' => $higher->damaged_size,
            ],
        ];

        $existing = DamagedProductMatch::query()
            ->where('product_a_id', $lower->id)
            ->where('product_b_id', $higher->id)
            ->first();

        if ($existing) {
            // Reativa rejected/expired — match candidato voltou a ser viável.
            if (in_array($existing->status, [DamageMatchStatus::REJECTED, DamageMatchStatus::EXPIRED], true)) {
                $existing->update([
                    'status' => DamageMatchStatus::PENDING->value,
                    'match_score' => $score,
                    'match_payload' => $payload,
                    'suggested_origin_store_id' => $direction['origin']->id,
                    'suggested_destination_store_id' => $direction['destination']->id,
                    'reject_reason' => null,
                    'responded_by_user_id' => null,
                    'responded_at' => null,
                    'notified_at' => null,
                    'resolved_at' => null,
                ]);

                $this->markProductsMatched($lower, $higher);
                DamagedProductMatchFound::dispatch($existing->fresh());
            }

            return $existing;
        }

        $match = DamagedProductMatch::create([
            'product_a_id' => $lower->id,
            'product_b_id' => $higher->id,
            'match_type' => $type->value,
            'match_score' => $score,
            'match_payload' => $payload,
            'suggested_origin_store_id' => $direction['origin']->id,
            'suggested_destination_store_id' => $direction['destination']->id,
            'status' => DamageMatchStatus::PENDING->value,
        ]);

        $this->markProductsMatched($lower, $higher);

        DamagedProductMatchFound::dispatch($match->fresh());

        return $match;
    }

    /**
     * Atualiza ambos os produtos pra status MATCHED via DB raw — não usa
     * TransitionService porque a engine roda em batch e não temos um actor
     * humano. O history é gravado manualmente como ação do system.
     */
    protected function markProductsMatched(DamagedProduct $a, DamagedProduct $b): void
    {
        foreach ([$a, $b] as $product) {
            if ($product->status !== DamagedProductStatus::OPEN) {
                continue;
            }

            $product->update(['status' => DamagedProductStatus::MATCHED->value]);

            \App\Models\DamagedProductStatusHistory::create([
                'damaged_product_id' => $product->id,
                'from_status' => DamagedProductStatus::OPEN->value,
                'to_status' => DamagedProductStatus::MATCHED->value,
                'note' => 'Match encontrado pela engine.',
                'actor_user_id' => null, // system
            ]);
        }
    }

    // ==================================================================
    // Lifecycle do match: accept / reject / resolve
    // ==================================================================

    /**
     * Aceita um match: cria Transfer (transfer_type=damage_match) entre as
     * lojas sugeridas e transiciona ambos os produtos para transfer_requested.
     *
     * @throws ValidationException
     */
    public function acceptMatch(DamagedProductMatch $match, User $actor, ?string $invoiceNumber = null): DamagedProductMatch
    {
        if ($match->status !== DamageMatchStatus::PENDING) {
            throw ValidationException::withMessages([
                'match' => 'Apenas matches pendentes podem ser aceitos. Status atual: '
                    . $match->status->label(),
            ]);
        }

        $this->ensureCanRespond($match, $actor);

        return DB::transaction(function () use ($match, $actor, $invoiceNumber) {
            $transfer = Transfer::create([
                'origin_store_id' => $match->suggested_origin_store_id,
                'destination_store_id' => $match->suggested_destination_store_id,
                'invoice_number' => $invoiceNumber,
                'volumes_qty' => 1,
                'products_qty' => 1,
                'transfer_type' => 'damage_match',
                'status' => 'pending',
                'observations' => $this->buildTransferObservations($match),
                'created_by_user_id' => $actor->id,
            ]);

            $match->update([
                'status' => DamageMatchStatus::ACCEPTED->value,
                'transfer_id' => $transfer->id,
                'responded_by_user_id' => $actor->id,
                'responded_at' => now(),
            ]);

            // Transiciona ambos os produtos para transfer_requested
            $this->transitions->transition(
                $match->productA,
                DamagedProductStatus::TRANSFER_REQUESTED,
                $actor,
                "Match #{$match->id} aceito — transferência #{$transfer->id} criada.",
                $match,
            );
            $this->transitions->transition(
                $match->productB,
                DamagedProductStatus::TRANSFER_REQUESTED,
                $actor,
                "Match #{$match->id} aceito — transferência #{$transfer->id} criada.",
                $match,
            );

            $fresh = $match->fresh(['productA.store', 'productB.store', 'transfer']);

            DamagedProductMatchAccepted::dispatch($fresh, $transfer, $actor);

            return $fresh;
        });
    }

    /**
     * Rejeita um match. Reason obrigatório. Se for o último match pendente
     * de um produto, ele volta pra OPEN (pode ser matchado de novo).
     *
     * @throws ValidationException
     */
    public function rejectMatch(DamagedProductMatch $match, User $actor, string $reason): DamagedProductMatch
    {
        if ($match->status !== DamageMatchStatus::PENDING) {
            throw ValidationException::withMessages([
                'match' => 'Apenas matches pendentes podem ser rejeitados.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Informe o motivo da rejeição.',
            ]);
        }

        $this->ensureCanRespond($match, $actor);

        return DB::transaction(function () use ($match, $actor, $reason) {
            $match->update([
                'status' => DamageMatchStatus::REJECTED->value,
                'reject_reason' => $reason,
                'responded_by_user_id' => $actor->id,
                'responded_at' => now(),
                'resolved_at' => now(),
            ]);

            $this->maybeRevertProductToOpen($match->productA, $actor, $match);
            $this->maybeRevertProductToOpen($match->productB, $actor, $match);

            $fresh = $match->fresh(['productA', 'productB']);
            DamagedProductMatchRejected::dispatch($fresh, $actor, $reason);

            return $fresh;
        });
    }

    /**
     * Marca match como concluído manualmente (útil quando a transferência
     * foi feita fora do sistema). Propaga RESOLVED para os 2 produtos.
     *
     * @throws ValidationException
     */
    public function resolveMatch(DamagedProductMatch $match, User $actor, ?string $note = null): DamagedProductMatch
    {
        if ($match->status === DamageMatchStatus::REJECTED || $match->status === DamageMatchStatus::EXPIRED) {
            throw ValidationException::withMessages([
                'match' => 'Match em estado terminal não pode ser resolvido.',
            ]);
        }

        $this->ensureCanRespond($match, $actor);

        return DB::transaction(function () use ($match, $actor, $note) {
            $match->update([
                'status' => DamageMatchStatus::ACCEPTED->value,
                'resolved_at' => now(),
                'responded_by_user_id' => $match->responded_by_user_id ?? $actor->id,
                'responded_at' => $match->responded_at ?? now(),
            ]);

            $messageNote = $note ?: "Match #{$match->id} resolvido manualmente.";

            foreach ([$match->productA, $match->productB] as $product) {
                if ($product->isFinal()) {
                    continue;
                }
                $this->transitions->transition(
                    $product,
                    DamagedProductStatus::RESOLVED,
                    $actor,
                    $messageNote,
                    $match,
                );
            }

            return $match->fresh(['productA', 'productB']);
        });
    }

    // ==================================================================
    // Authorization helper
    // ==================================================================

    /**
     * Quem pode aceitar/rejeitar/resolver um match:
     *  - usuário com MANAGE_DAMAGED_PRODUCTS (admin/support — qualquer match)
     *  - usuário com APPROVE_DAMAGED_PRODUCT_MATCHES da loja origem OU destino
     *
     * @throws ValidationException
     */
    protected function ensureCanRespond(DamagedProductMatch $match, User $actor): void
    {
        if ($actor->hasPermissionTo(Permission::MANAGE_DAMAGED_PRODUCTS->value)) {
            return;
        }

        if (! $actor->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value)) {
            throw ValidationException::withMessages([
                'match' => 'Você não tem permissão para responder a matches.',
            ]);
        }

        // Sem MANAGE: só responde matches da própria loja.
        $userStoreId = $actor->store_id ?? null;
        $involvedStoreIds = [
            $match->productA?->store_id,
            $match->productB?->store_id,
        ];

        if (! $userStoreId || ! in_array($userStoreId, $involvedStoreIds, true)) {
            throw ValidationException::withMessages([
                'match' => 'Você só pode responder matches que envolvam a sua loja.',
            ]);
        }
    }

    /**
     * Se o produto não tem mais nenhum match pending, volta a OPEN. Senão
     * mantém em MATCHED (ainda há outros candidatos vivos).
     */
    protected function maybeRevertProductToOpen(DamagedProduct $product, User $actor, DamagedProductMatch $match): void
    {
        if ($product->status !== DamagedProductStatus::MATCHED) {
            return;
        }

        $hasOtherPending = DamagedProductMatch::query()
            ->forProduct($product->id)
            ->pending()
            ->where('id', '!=', $match->id)
            ->exists();

        if ($hasOtherPending) {
            return;
        }

        $this->transitions->transition(
            $product,
            DamagedProductStatus::OPEN,
            $actor,
            "Match #{$match->id} rejeitado — produto retornado pra fila.",
            $match,
        );
    }

    protected function buildTransferObservations(DamagedProductMatch $match): string
    {
        $type = $match->match_type->label();
        $ref = $match->productA->product_reference;

        return "Transferência gerada automaticamente pelo módulo de Produtos Avariados. "
            . "Tipo: {$type}. Referência: {$ref}. Match #{$match->id}.";
    }
}
