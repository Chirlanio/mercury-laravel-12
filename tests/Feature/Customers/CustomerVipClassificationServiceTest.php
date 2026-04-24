<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Models\CustomerVipTierConfig;
use App\Services\CustomerVipClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CustomerVipClassificationServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CustomerVipClassificationService $service;

    /**
     * Ano da LISTA VIP. A apuração usa $year - 1 (regra MS Life).
     * Movements são gerados com data em $year - 1 nos helpers.
     */
    private int $year = 2026;

    private int $revenueYear = 2025;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->service = app(CustomerVipClassificationService::class);

        // Cache em memória do array de lojas MS Life — limpa entre tests
        // pra não vazar state do setUp anterior.
        Cache::store('array')->forget('vip.ms_life_store_codes');

        // Minimal movements schema for SQLite — real tenant migrations não rodam
        // em in-memory SQLite padrão, então criamos as colunas que o service usa.
        if (! Schema::hasTable('movements')) {
            Schema::create('movements', function ($table) {
                $table->id();
                $table->date('movement_date');
                $table->string('cpf_customer', 14)->nullable();
                $table->integer('movement_code');
                $table->char('entry_exit', 1);
                $table->decimal('net_value', 12, 2)->default(0);
                $table->decimal('quantity', 10, 3)->default(0);
                $table->string('invoice_number', 30)->nullable();
                $table->string('store_code', 10)->nullable();
                $table->timestamps();
            });
        }

        // Lojas da rede Meia Sola (network_id=3 via TestHelpers::createNetworks).
        // Z441 = e-commerce (memorizado no CLAUDE.md como da rede Meia Sola na prática),
        // Z800 = loja Meia Sola física hipotética pros tests.
        // Z421/Z422 são Arezzo (network_id=1) — usados em tests de exclusão.
        $this->createStoreIfMissing('Z441', 'MS E-COMMERCE', networkId: 3);
        $this->createStoreIfMissing('Z800', 'MS LOJA FISICA', networkId: 3);
        $this->createStoreIfMissing('Z801', 'MS LOJA SUL', networkId: 3);
        $this->createStoreIfMissing('Z421', 'AREZZO RIOMAR', networkId: 1);
    }

    private function createStoreIfMissing(string $code, string $name, int $networkId): void
    {
        if (DB::table('stores')->where('code', $code)->exists()) {
            return;
        }
        DB::table('stores')->insert([
            'code' => $code,
            'name' => $name,
            'cnpj' => (string) random_int(10000000000000, 99999999999999),
            'company_name' => $name,
            'state_registration' => '000',
            'address' => '—',
            'network_id' => $networkId,
            'manager_id' => 1,
            'store_order' => 1,
            'network_order' => 1,
            'supervisor_id' => 1,
            'status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCustomer(string $cpf, string $name = 'CLIENTE'): Customer
    {
        return Customer::create([
            'cigam_code' => '10001-'.substr($cpf, -4),
            'name' => $name,
            'cpf' => $cpf,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    /**
     * Insere movements no ano de APURAÇÃO ($revenueYear, decide o tier) E
     * espelha no ano da LISTA ($year, vai pro snapshot persistido). Quando o
     * test passa `movement_date` explícito, NÃO espelha — usado em cenários
     * que filtram por ano específico (ex: test_decision_uses_revenue_year).
     */
    private function makeMovement(array $overrides): void
    {
        $defaults = [
            'movement_date' => sprintf('%d-03-15', $this->revenueYear),
            'movement_code' => 2,
            'entry_exit' => 'S',
            'net_value' => 1000.00,
            'quantity' => 1,
            'invoice_number' => (string) random_int(1000, 99999),
            'store_code' => 'Z441',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('movements')->insert(array_merge($defaults, $overrides));

        // Espelha em $year (snapshot) só se o test não definiu data explícita
        if (! isset($overrides['movement_date'])) {
            $mirror = array_merge($defaults, $overrides);
            $mirror['movement_date'] = sprintf('%d-03-15', $this->year);
            DB::table('movements')->insert($mirror);
        }
    }

    private function setThresholds(float $black = 10000, float $gold = 5000): void
    {
        CustomerVipTierConfig::create(['year' => $this->year, 'tier' => 'black', 'min_revenue' => $black]);
        CustomerVipTierConfig::create(['year' => $this->year, 'tier' => 'gold', 'min_revenue' => $gold]);
    }

    // --------------------------------------------------------------
    // Cálculo de faturamento
    // --------------------------------------------------------------

    public function test_revenue_sums_code_2_and_subtracts_code_6_entry(): void
    {
        $customer = $this->makeCustomer('11111111111', 'JOAO');
        $this->setThresholds(black: 10000, gold: 5000);

        // 3 vendas de R$ 3000 + 1 devolução de R$ 500 = 8500 (Gold)
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1001']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1002']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1003']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 6, 'entry_exit' => 'E', 'net_value' => 500, 'invoice_number' => '1004']);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['suggested_gold']);
        $this->assertSame(0, $summary['suggested_black']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier);
        $this->assertEqualsWithDelta(8500.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals('gold', $tier->suggested_tier);
        $this->assertEquals('gold', $tier->final_tier);
        $this->assertEquals(4, $tier->total_orders);
    }

    public function test_revenue_ignores_code_6_exit_and_other_codes(): void
    {
        $customer = $this->makeCustomer('22222222222');
        $this->setThresholds(10000, 5000);

        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 6000]);
        // code 6 'S' (saída) — não é devolução, deve ser ignorado
        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 6, 'entry_exit' => 'S', 'net_value' => 500]);
        // code 1 (compra) — deve ser ignorado
        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 1, 'entry_exit' => 'E', 'net_value' => 100000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(6000.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals('gold', $tier->suggested_tier);
    }

    public function test_decision_uses_revenue_year_snapshot_uses_list_year(): void
    {
        // Lista = 2026, apuração = 2025.
        // Decisão de tier olha 2025; snapshot persistido reflete 2026.
        $customer = $this->makeCustomer('33333333333');
        $this->setThresholds(10000, 5000);

        // 2025 (apuração) → 6000, bate Gold
        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => $this->revenueYear.'-05-01', 'net_value' => 6000]);
        // 2026 (ano da lista) → 4500, snapshot persistido
        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => $this->year.'-05-01', 'net_value' => 4500]);
        // 2024 (fora) — não afeta
        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => ($this->revenueYear - 1).'-05-01', 'net_value' => 100000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('gold', $tier->suggested_tier, 'Tier decidido por 2025 (6000 → Gold)');
        $this->assertEqualsWithDelta(4500.0, (float) $tier->total_revenue, 0.01, 'Snapshot reflete vendas de 2026');
        $this->assertEquals($this->year, $tier->revenue_year, 'revenue_year armazena ano da lista');
    }

    public function test_persists_year_and_revenue_year_in_record(): void
    {
        $customer = $this->makeCustomer('44400440044');
        $this->setThresholds(10000, 5000);
        // makeMovement default cria em ambos: $revenueYear (decisão) + $year (snapshot)
        $this->makeMovement(['cpf_customer' => '44400440044', 'net_value' => 8000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertEquals($this->year, $summary['year']);
        $this->assertEquals($this->revenueYear, $summary['revenue_year']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals($this->year, $tier->revenue_year, 'revenue_year = ano da lista (snapshot)');
    }

    // -------------------------------------------------------------
    // refreshSnapshots — recalcula snapshots de uma lista existente
    // -------------------------------------------------------------

    public function test_refresh_snapshots_recomputes_from_list_year(): void
    {
        $customer = $this->makeCustomer('55500550055');

        // Cria tier 2026 manualmente sem snapshot (cenário: importou lista via XLSX)
        CustomerVipTier::create([
            'customer_id' => $customer->id,
            'year' => $this->year,
            'final_tier' => 'black',
            'source' => 'manual',
            'curated_at' => now(),
            'total_revenue' => 0,
            'total_orders' => 0,
        ]);

        // Vendas Meia Sola em 2026 — devem aparecer no snapshot
        $this->makeMovement(['cpf_customer' => '55500550055', 'movement_date' => $this->year.'-04-10', 'net_value' => 7500, 'invoice_number' => 'A1']);
        $this->makeMovement(['cpf_customer' => '55500550055', 'movement_date' => $this->year.'-08-20', 'net_value' => 2500, 'invoice_number' => 'A2']);

        $summary = $this->service->refreshSnapshots($this->year);

        $this->assertSame(1, $summary['updated']);
        $this->assertEquals($this->year, $summary['year']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(10000.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals(2, $tier->total_orders);
        $this->assertEquals('black', $tier->final_tier, 'final_tier preservado');
        $this->assertNotNull($tier->curated_at, 'curadoria preservada');
        $this->assertEquals($this->year, $tier->revenue_year);
    }

    public function test_refresh_snapshots_zera_quando_cliente_nao_comprou_no_ano(): void
    {
        $customer = $this->makeCustomer('66600660066');
        CustomerVipTier::create([
            'customer_id' => $customer->id,
            'year' => $this->year,
            'final_tier' => 'gold',
            'source' => 'manual',
            'curated_at' => now(),
            'total_revenue' => 5000, // valor antigo de outra rodada
            'total_orders' => 3,
        ]);
        // Sem movements em 2026

        $summary = $this->service->refreshSnapshots($this->year);

        $this->assertSame(1, $summary['updated']);
        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals(0, $tier->total_orders);
        $this->assertNull($tier->preferred_store_code);
    }

    // --------------------------------------------------------------
    // Thresholds e tiers
    // --------------------------------------------------------------

    public function test_assigns_black_when_revenue_above_black_threshold(): void
    {
        $customer = $this->makeCustomer('44444444444');
        $this->setThresholds(black: 10000, gold: 5000);

        $this->makeMovement(['cpf_customer' => '44444444444', 'net_value' => 15000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('black', $tier->suggested_tier);
        $this->assertEquals('black', $tier->final_tier);
    }

    public function test_no_record_created_when_below_gold_threshold(): void
    {
        $customer = $this->makeCustomer('55555555555');
        $this->setThresholds(10000, 5000);

        $this->makeMovement(['cpf_customer' => '55555555555', 'net_value' => 3000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['below_threshold']);
        $this->assertSame(0, $summary['suggested_black']);
        $this->assertSame(0, $summary['suggested_gold']);
        $this->assertNull(
            CustomerVipTier::where('customer_id', $customer->id)->first(),
            'Cliente abaixo do threshold não deve gerar registro VIP'
        );
    }

    public function test_returns_has_thresholds_false_when_year_unconfigured(): void
    {
        $this->makeCustomer('66600011111');
        // SEM setThresholds — ano sem config
        $this->makeMovement(['cpf_customer' => '66600011111', 'net_value' => 50000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertFalse($summary['has_thresholds']);
        $this->assertSame(0, $summary['processed']);
        $this->assertSame(0, CustomerVipTier::count());
    }

    public function test_preserves_manual_curation(): void
    {
        $customer = $this->makeCustomer('66666666666');
        $this->setThresholds(10000, 5000);

        // Curadoria manual prévia — promove pra Black
        $this->service->curate($customer, $this->year, 'black', 'Decidido pela diretoria', $this->adminUser);

        // Novos movements que indicariam apenas Gold
        $this->makeMovement(['cpf_customer' => '66666666666', 'net_value' => 7000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['preserved_curated']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('black', $tier->final_tier, 'final_tier não deve ser rebaixado');
        $this->assertEquals('gold', $tier->suggested_tier, 'suggested_tier deve refletir o cálculo atual');
        $this->assertEqualsWithDelta(7000.0, (float) $tier->total_revenue, 0.01);
    }

    // --------------------------------------------------------------
    // Curate / Remove
    // --------------------------------------------------------------

    public function test_curate_marks_source_manual(): void
    {
        $customer = $this->makeCustomer('77777777777');

        $record = $this->service->curate($customer, $this->year, 'gold', null, $this->adminUser);

        $this->assertEquals(CustomerVipTier::SOURCE_MANUAL, $record->source);
        $this->assertEquals($this->adminUser->id, $record->curated_by_user_id);
        $this->assertNotNull($record->curated_at);
    }

    public function test_remove_nullifies_final_tier_but_keeps_history(): void
    {
        $customer = $this->makeCustomer('88888888888');
        $this->setThresholds(10000, 5000);
        $this->makeMovement(['cpf_customer' => '88888888888', 'net_value' => 12000]);
        $this->service->generateSuggestions($this->year);

        $this->service->remove($customer, $this->year, $this->adminUser);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier, 'record preservado');
        $this->assertNull($tier->final_tier, 'final_tier zerado');
        $this->assertEquals('black', $tier->suggested_tier, 'histórico da sugestão preservado');
        $this->assertEqualsWithDelta(12000.0, (float) $tier->total_revenue, 0.01);
    }

    public function test_curate_rejects_invalid_tier(): void
    {
        $customer = $this->makeCustomer('99999999999');
        $this->expectException(\InvalidArgumentException::class);
        $this->service->curate($customer, $this->year, 'platinum', null, $this->adminUser);
    }

    // --------------------------------------------------------------
    // Matching por CPF
    // --------------------------------------------------------------

    public function test_ignores_cpfs_without_matching_customer(): void
    {
        $this->setThresholds(10000, 5000);

        // Movement com CPF que não existe em customers
        $this->makeMovement(['cpf_customer' => '00000000000', 'net_value' => 50000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(0, $summary['processed']);
        $this->assertSame(0, CustomerVipTier::count());
    }

    // --------------------------------------------------------------
    // MS Life — filtro por rede Meia Sola
    // --------------------------------------------------------------

    public function test_ignores_sales_outside_meia_sola_network(): void
    {
        $customer = $this->makeCustomer('12312312312');
        $this->setThresholds(10000, 5000);

        // Vendas em lojas Meia Sola (Z800 = rede 3) — contam
        $this->makeMovement(['cpf_customer' => '12312312312', 'store_code' => 'Z800', 'net_value' => 6000]);

        // Vendas em Arezzo (Z421 = rede 1) — NÃO devem contar no MS Life
        $this->makeMovement(['cpf_customer' => '12312312312', 'store_code' => 'Z421', 'net_value' => 50000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(6000.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals('gold', $tier->suggested_tier, 'deveria ser Gold (apenas Meia Sola contou)');
    }

    public function test_returns_empty_when_no_meia_sola_stores_exist(): void
    {
        // Remove todas as stores Meia Sola — nada a classificar
        DB::table('stores')->whereIn('code', ['Z441', 'Z800', 'Z801'])->delete();
        Cache::store('array')->forget('vip.ms_life_store_codes');

        $this->makeCustomer('55566677788');
        $this->setThresholds(10000, 5000);
        $this->makeMovement(['cpf_customer' => '55566677788', 'store_code' => 'Z421', 'net_value' => 50000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(0, $summary['processed']);
        $this->assertSame(0, CustomerVipTier::count());
    }

    // --------------------------------------------------------------
    // Loja preferida (tie-breaking: revenue > tickets > items)
    // --------------------------------------------------------------

    public function test_preferred_store_is_the_one_with_highest_revenue(): void
    {
        $customer = $this->makeCustomer('20202020200');
        $this->setThresholds(10000, 5000);

        // Z800: R$ 2000 em 2 NFs
        $this->makeMovement(['cpf_customer' => '20202020200', 'store_code' => 'Z800', 'net_value' => 1000, 'invoice_number' => 'A1']);
        $this->makeMovement(['cpf_customer' => '20202020200', 'store_code' => 'Z800', 'net_value' => 1000, 'invoice_number' => 'A2']);

        // Z801: R$ 5000 em 1 NF — maior faturamento
        $this->makeMovement(['cpf_customer' => '20202020200', 'store_code' => 'Z801', 'net_value' => 5000, 'invoice_number' => 'B1']);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('Z801', $tier->preferred_store_code);
    }

    public function test_preferred_store_tie_breaks_by_tickets_when_revenue_equal(): void
    {
        $customer = $this->makeCustomer('30303030300');
        $this->setThresholds(10000, 5000);

        // Z800: R$ 3000 em 1 NF
        $this->makeMovement(['cpf_customer' => '30303030300', 'store_code' => 'Z800', 'net_value' => 3000, 'invoice_number' => 'X1']);

        // Z801: R$ 3000 em 3 NFs — mesmo revenue, mais tickets
        $this->makeMovement(['cpf_customer' => '30303030300', 'store_code' => 'Z801', 'net_value' => 1000, 'invoice_number' => 'Y1']);
        $this->makeMovement(['cpf_customer' => '30303030300', 'store_code' => 'Z801', 'net_value' => 1000, 'invoice_number' => 'Y2']);
        $this->makeMovement(['cpf_customer' => '30303030300', 'store_code' => 'Z801', 'net_value' => 1000, 'invoice_number' => 'Y3']);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('Z801', $tier->preferred_store_code);
    }

    public function test_preferred_store_tie_breaks_by_items_when_revenue_and_tickets_equal(): void
    {
        $customer = $this->makeCustomer('40404040400');
        $this->setThresholds(10000, 5000);

        // Z800: R$ 3000 em 1 NF, 1 item
        $this->makeMovement(['cpf_customer' => '40404040400', 'store_code' => 'Z800', 'net_value' => 3000, 'quantity' => 1, 'invoice_number' => 'I1']);

        // Z801: R$ 3000 em 1 NF, 5 itens — mesmo revenue e NFs, mais itens
        // Total combinado = 6000, bate Gold (5000)
        $this->makeMovement(['cpf_customer' => '40404040400', 'store_code' => 'Z801', 'net_value' => 3000, 'quantity' => 5, 'invoice_number' => 'J1']);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier, 'Cliente deve qualificar como Gold (R$ 6000)');
        $this->assertEquals('Z801', $tier->preferred_store_code);
    }

    // --------------------------------------------------------------
    // Cleanup de registros auto obsoletos
    // --------------------------------------------------------------

    public function test_cleans_up_auto_records_that_no_longer_qualify(): void
    {
        $customer = $this->makeCustomer('70700700700');
        $this->setThresholds(10000, 5000);

        // 1ª rodada: cliente compra Meia Sola e qualifica como Gold
        $this->makeMovement(['cpf_customer' => '70700700700', 'store_code' => 'Z800', 'net_value' => 6000]);
        $this->service->generateSuggestions($this->year);

        $this->assertNotNull(
            CustomerVipTier::where('customer_id', $customer->id)->first(),
            'Após 1ª rodada deve existir registro Gold'
        );

        // 2ª rodada: as compras Meia Sola somem (foram cancel), cliente passa
        // a comprar só na Arezzo. Deve perder a sugestão.
        DB::table('movements')->where('cpf_customer', '70700700700')->delete();
        $this->makeMovement(['cpf_customer' => '70700700700', 'store_code' => 'Z421', 'net_value' => 50000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['removed_obsolete']);
        $this->assertNull(
            CustomerVipTier::where('customer_id', $customer->id)->first(),
            'Registro auto deve ser removido — cliente não qualifica mais'
        );
    }

    public function test_cleanup_preserves_curated_records_even_when_dropped(): void
    {
        $customer = $this->makeCustomer('80800800800');
        $this->setThresholds(10000, 5000);

        // 1ª rodada qualifica + Marketing cura como Black (decisão estratégica)
        $this->makeMovement(['cpf_customer' => '80800800800', 'store_code' => 'Z800', 'net_value' => 6000]);
        $this->service->generateSuggestions($this->year);
        $this->service->curate($customer, $this->year, 'black', 'Cliente histórico', $this->adminUser);

        // 2ª rodada: cliente para de comprar Meia Sola
        DB::table('movements')->where('cpf_customer', '80800800800')->delete();

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(0, $summary['removed_obsolete'], 'Curadoria nunca é deletada');

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier);
        $this->assertEquals('black', $tier->final_tier, 'final_tier curado preservado');
        $this->assertNotNull($tier->curated_at);
    }

    public function test_cleanup_runs_when_thresholds_missing(): void
    {
        $customer = $this->makeCustomer('90900900900');
        $this->setThresholds(10000, 5000);

        // 1ª rodada: cliente qualifica
        $this->makeMovement(['cpf_customer' => '90900900900', 'store_code' => 'Z800', 'net_value' => 12000]);
        $this->service->generateSuggestions($this->year);

        $this->assertNotNull(CustomerVipTier::where('customer_id', $customer->id)->first());

        // 2ª rodada: thresholds removidos (Marketing zerou a régua para revisar)
        \App\Models\CustomerVipTierConfig::truncate();

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertFalse($summary['has_thresholds']);
        $this->assertSame(1, $summary['removed_obsolete']);
        $this->assertNull(CustomerVipTier::where('customer_id', $customer->id)->first());
    }
}
