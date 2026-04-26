<?php

namespace Tests\Feature\DamagedProducts;

use App\Enums\DamageMatchStatus;
use App\Enums\DamageMatchType;
use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\DamageType;
use App\Models\NetworkBrandRule;
use App\Models\Store;
use App\Models\User;
use App\Services\DamagedProductMatchingService;
use App\Services\DamagedProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DamagedProductMatchingServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected DamagedProductService $service;
    protected DamagedProductMatchingService $matching;

    protected int $storeAId;
    protected int $storeBId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // damage_types já vêm seedados pela migration (8 tipos default).
        $this->storeAId = $this->createTestStore('Z421', ['network_id' => 1, 'store_order' => 1]);
        $this->storeBId = $this->createTestStore('Z422', ['network_id' => 2, 'store_order' => 5]);

        $this->service = app(DamagedProductService::class);
        $this->matching = app(DamagedProductMatchingService::class);
    }

    protected function makeMismatched(int $storeId, string $reference, string $foot, string $actual, string $expected, ?string $brand = null): DamagedProduct
    {
        return $this->service->create([
            'store_id' => $storeId,
            'product_reference' => $reference,
            'brand_cigam_code' => $brand,
            'is_mismatched' => true,
            'mismatched_foot' => $foot,
            'mismatched_actual_size' => $actual,
            'mismatched_expected_size' => $expected,
        ], $this->adminUser);
    }

    protected function makeDamaged(int $storeId, string $reference, string $foot, ?string $brand = null): DamagedProduct
    {
        return $this->service->create([
            'store_id' => $storeId,
            'product_reference' => $reference,
            'brand_cigam_code' => $brand,
            'is_damaged' => true,
            'damage_type_id' => DamageType::first()->id,
            'damaged_foot' => $foot,
        ], $this->adminUser);
    }

    // ==================================================================
    // Discovery — mismatched_pair
    // ==================================================================

    public function test_finds_mismatched_pair_with_inverted_sizes(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $matches = $this->matching->findMatchesFor($b);

        $this->assertCount(1, $matches);
        $match = $matches->first();
        $this->assertSame(DamageMatchType::MISMATCHED_PAIR, $match->match_type);
        $this->assertSame($a->id, $match->product_a_id);
        $this->assertSame($b->id, $match->product_b_id);
    }

    public function test_does_not_match_same_store(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $this->makeMismatched($this->storeAId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    public function test_does_not_match_different_references(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $this->makeMismatched($this->storeBId, 'REF-OTHER', FootSide::RIGHT->value, '39', '38');

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    public function test_does_not_match_same_foot(): void
    {
        // Ambos têm pé esquerdo trocado — não combina
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $this->makeMismatched($this->storeBId, 'REF-001', FootSide::LEFT->value, '39', '38');

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    public function test_does_not_match_when_sizes_not_perfectly_inverted(): void
    {
        // A tem 38→39, B tem 38→40 (não bate perfeitamente)
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '38', '40');

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    // ==================================================================
    // Discovery — damaged_complement
    // ==================================================================

    public function test_finds_damaged_complement_with_opposite_feet(): void
    {
        $a = $this->makeDamaged($this->storeAId, 'REF-DMG', FootSide::LEFT->value);
        $b = $this->makeDamaged($this->storeBId, 'REF-DMG', FootSide::RIGHT->value);

        $matches = $this->matching->findMatchesFor($b);

        $this->assertCount(1, $matches);
        $this->assertSame(DamageMatchType::DAMAGED_COMPLEMENT, $matches->first()->match_type);
    }

    public function test_does_not_match_damaged_when_both_feet_damaged(): void
    {
        // Se A tem ambos os pés danificados, não há pé bom pra combinar
        $a = $this->makeDamaged($this->storeAId, 'REF-DMG', FootSide::BOTH->value);
        $this->makeDamaged($this->storeBId, 'REF-DMG', FootSide::RIGHT->value);

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    public function test_does_not_match_damaged_with_na_foot(): void
    {
        $a = $this->makeDamaged($this->storeAId, 'REF-DMG', FootSide::NA->value);
        $this->makeDamaged($this->storeBId, 'REF-DMG', FootSide::RIGHT->value);

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    // ==================================================================
    // Brand/Network compatibility
    // ==================================================================

    public function test_brand_compatibility_default_permissive_when_no_rules(): void
    {
        $a = Store::find($this->storeAId);
        $b = Store::find($this->storeBId);

        // Nenhuma regra cadastrada → aceita qualquer marca
        $this->assertTrue($this->matching->areStoresBrandCompatible($a, $b, 'AREZZO', 'SCHUTZ'));
    }

    public function test_brand_whitelist_blocks_mismatch(): void
    {
        // Rede A só aceita AREZZO
        NetworkBrandRule::create([
            'network_id' => 1,
            'brand_cigam_code' => 'AREZZO',
            'is_active' => true,
        ]);

        $a = Store::find($this->storeAId);
        $b = Store::find($this->storeBId);

        // B traz SCHUTZ — A não aceita
        $this->assertFalse($this->matching->areStoresBrandCompatible($a, $b, 'AREZZO', 'SCHUTZ'));
        // B traz AREZZO — A aceita; B não tem regras (permissivo)
        $this->assertTrue($this->matching->areStoresBrandCompatible($a, $b, 'SCHUTZ', 'AREZZO'));
    }

    public function test_brand_compatibility_is_bidirectional(): void
    {
        // Rede A aceita só AREZZO. Rede B aceita só SCHUTZ.
        NetworkBrandRule::create(['network_id' => 1, 'brand_cigam_code' => 'AREZZO', 'is_active' => true]);
        NetworkBrandRule::create(['network_id' => 2, 'brand_cigam_code' => 'SCHUTZ', 'is_active' => true]);

        $a = Store::find($this->storeAId);
        $b = Store::find($this->storeBId);

        // brandA=AREZZO → A entrega AREZZO pra B; B só aceita SCHUTZ → false
        $this->assertFalse($this->matching->areStoresBrandCompatible($a, $b, 'AREZZO', 'SCHUTZ'));

        // brandA=SCHUTZ, brandB=AREZZO: A entrega SCHUTZ pra B (B aceita SCHUTZ ✓);
        // B entrega AREZZO pra A (A aceita AREZZO ✓) → true
        $this->assertTrue($this->matching->areStoresBrandCompatible($a, $b, 'SCHUTZ', 'AREZZO'));

        // XYZ não está nas whitelists de nenhuma rede
        $this->assertFalse($this->matching->areStoresBrandCompatible($a, $b, 'XYZ', 'AREZZO'));
    }

    public function test_match_not_created_when_brand_incompatible(): void
    {
        NetworkBrandRule::create(['network_id' => 1, 'brand_cigam_code' => 'AREZZO', 'is_active' => true]);

        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39', brand: 'AREZZO');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38', brand: 'SCHUTZ');

        $matches = $this->matching->findMatchesFor($a);

        $this->assertCount(0, $matches);
    }

    // ==================================================================
    // Score (melhoria v2)
    // ==================================================================

    public function test_score_is_60_for_fresh_pair_without_brand_match(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $score = $this->matching->computeMatchScore($a, $b);

        // 60 base + 0 idade (recém criados) + 0 marca (null/null não pontua "iguais")
        $this->assertEquals(60.0, $score);
    }

    public function test_score_includes_brand_bonus_when_matching(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39', brand: 'AREZZO');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38', brand: 'AREZZO');

        $score = $this->matching->computeMatchScore($a, $b);

        $this->assertEquals(70.0, $score); // 60 + 10 marca
    }

    public function test_score_capped_at_100(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39', brand: 'AREZZO');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38', brand: 'AREZZO');

        // Backdate ambos pra forçar ageBonus máximo (60+ dias)
        $a->update(['created_at' => now()->subDays(80)]);
        $b->update(['created_at' => now()->subDays(80)]);
        $a->refresh();
        $b->refresh();

        $score = $this->matching->computeMatchScore($a, $b);

        $this->assertLessThanOrEqual(100.0, $score);
    }

    // ==================================================================
    // Suggested direction (destino = menor store_order)
    // ==================================================================

    public function test_suggests_destination_with_lower_store_order(): void
    {
        // A tem store_order=1 (prioridade alta = recebe), B tem store_order=5
        $a = Store::find($this->storeAId);
        $b = Store::find($this->storeBId);

        $direction = $this->matching->determineSuggestedDirection($a, $b);

        $this->assertSame($a->id, $direction['destination']->id);
        $this->assertSame($b->id, $direction['origin']->id);
    }

    public function test_suggested_direction_persisted_in_match(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();

        $this->assertSame($this->storeAId, $match->suggested_destination_store_id);
        $this->assertSame($this->storeBId, $match->suggested_origin_store_id);
    }

    // ==================================================================
    // Convenção A < B
    // ==================================================================

    public function test_match_persists_smaller_id_as_product_a(): void
    {
        // Cria B primeiro pra inverter ordem natural (b.id < a.id)
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');

        $match = $this->matching->findMatchesFor($a)->first();

        $this->assertLessThan($match->product_b_id, $match->product_a_id);
    }

    public function test_findMatchesFor_does_not_duplicate_matches(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        // Primeira chamada cria o match (e transiciona ambos pra matched)
        $first = $this->matching->findMatchesFor($a);
        $this->assertCount(1, $first);
        $this->assertEquals(1, DamagedProductMatch::count());

        // Reverte status pra OPEN simulando re-rodada do full scan diário
        DamagedProduct::query()->update(['status' => DamagedProductStatus::OPEN->value]);

        // Segunda chamada não cria duplicata (unique constraint A<B)
        $this->matching->findMatchesFor($a->fresh());
        $this->assertEquals(1, DamagedProductMatch::count());
    }

    // ==================================================================
    // Reativação de matches rejected/expired
    // ==================================================================

    public function test_reactivates_rejected_match_on_reattempt(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();
        $this->matching->rejectMatch($match, $this->adminUser, 'Teste de rejeição');

        $match->refresh();
        $this->assertSame(DamageMatchStatus::REJECTED, $match->status);

        // Re-roda matching — match deve ser reativado pra pending
        // Precisa que a engine encontre os candidatos de novo, mas eles estão
        // em open agora porque o reject reverteu A e B pra open
        $a->refresh();
        $b->refresh();
        $this->assertSame(DamagedProductStatus::OPEN, $a->status);

        $this->matching->findMatchesFor($a);
        $match->refresh();
        $this->assertSame(DamageMatchStatus::PENDING, $match->status);
        $this->assertNull($match->reject_reason);
    }

    // ==================================================================
    // Match status side effects on products
    // ==================================================================

    public function test_match_creation_transitions_both_products_to_matched(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $this->matching->findMatchesFor($a);

        $a->refresh();
        $b->refresh();
        $this->assertSame(DamagedProductStatus::MATCHED, $a->status);
        $this->assertSame(DamagedProductStatus::MATCHED, $b->status);
    }

    // ==================================================================
    // accept / reject / resolve lifecycle
    // ==================================================================

    public function test_accept_match_creates_transfer_with_damage_match_type(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();
        $accepted = $this->matching->acceptMatch($match, $this->adminUser, 'NF-12345');

        $this->assertSame(DamageMatchStatus::ACCEPTED, $accepted->status);
        $this->assertNotNull($accepted->transfer_id);

        $transfer = $accepted->transfer;
        $this->assertSame('damage_match', $transfer->transfer_type);
        $this->assertSame('pending', $transfer->status);
        $this->assertSame('NF-12345', $transfer->invoice_number);

        // Ambos os produtos transicionaram pra transfer_requested
        $a->refresh();
        $b->refresh();
        $this->assertSame(DamagedProductStatus::TRANSFER_REQUESTED, $a->status);
        $this->assertSame(DamagedProductStatus::TRANSFER_REQUESTED, $b->status);
    }

    public function test_accept_blocks_non_pending_match(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();
        $this->matching->acceptMatch($match, $this->adminUser, 'NF-1');

        $this->expectException(ValidationException::class);
        $match->refresh();
        $this->matching->acceptMatch($match, $this->adminUser, 'NF-2');
    }

    public function test_reject_reverts_product_to_open_when_no_other_pending(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();
        $this->matching->rejectMatch($match, $this->adminUser, 'Não me serve');

        $a->refresh();
        $b->refresh();
        $this->assertSame(DamagedProductStatus::OPEN, $a->status);
        $this->assertSame(DamagedProductStatus::OPEN, $b->status);
    }

    public function test_reject_requires_non_empty_reason(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');
        $match = $this->matching->findMatchesFor($a)->first();

        $this->expectException(ValidationException::class);
        $this->matching->rejectMatch($match, $this->adminUser, '');
    }

    public function test_resolve_cascades_to_both_products(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $match = $this->matching->findMatchesFor($a)->first();
        $accepted = $this->matching->acceptMatch($match, $this->adminUser, 'NF-1');

        $this->matching->resolveMatch($accepted, $this->adminUser, 'Confirmação manual');

        $a->refresh();
        $b->refresh();
        $this->assertSame(DamagedProductStatus::RESOLVED, $a->status);
        $this->assertSame(DamagedProductStatus::RESOLVED, $b->status);
    }

    // ==================================================================
    // runFullMatching
    // ==================================================================

    public function test_runFullMatching_reports_stats(): void
    {
        $a = $this->makeMismatched($this->storeAId, 'REF-001', FootSide::LEFT->value, '38', '39');
        $b = $this->makeMismatched($this->storeBId, 'REF-001', FootSide::RIGHT->value, '39', '38');

        $stats = $this->matching->runFullMatching();

        $this->assertGreaterThanOrEqual(2, $stats['scanned']);
        $this->assertGreaterThanOrEqual(1, $stats['matches_created']);
    }
}
