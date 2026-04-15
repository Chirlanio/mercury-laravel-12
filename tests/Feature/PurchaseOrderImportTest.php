<?php

namespace Tests\Feature;

use App\Models\ProductBrand;
use App\Models\ProductSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSizeMapping;
use App\Models\Store;
use App\Services\PurchaseOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Tests do import no formato v1 Mercury com a arquitetura corrigida:
 *  - Fornecedor NÃO vem da planilha (alinhado com v1)
 *  - Marca vem de product_brands (sincronizado do CIGAM) — rejeita se desconhecida
 *  - Tamanhos usam de-para via PurchaseOrderSizeMapping
 */
class PurchaseOrderImportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ProductBrand $brandLuizaBarcelos;

    protected ProductBrand $brandVicenza;

    protected ProductSize $size33;

    protected ProductSize $size34;

    protected ProductSize $size36;

    protected ProductSize $sizePP;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create([
            'code' => 'Z424',
            'name' => 'CD MEIA SOLA',
        ]);

        // product_brands — são as marcas sincronizadas do CIGAM
        $this->brandLuizaBarcelos = ProductBrand::create([
            'cigam_code' => 'LB01',
            'name' => 'LUIZA BARCELOS',
            'is_active' => true,
        ]);
        $this->brandVicenza = ProductBrand::create([
            'cigam_code' => 'VZ01',
            'name' => 'VICENZA',
            'is_active' => true,
        ]);

        // product_sizes — sincronizados do CIGAM
        $this->size33 = ProductSize::create(['cigam_code' => '1', 'name' => '33', 'is_active' => true]);
        $this->size34 = ProductSize::create(['cigam_code' => '2', 'name' => '34', 'is_active' => true]);
        $this->size36 = ProductSize::create(['cigam_code' => '4', 'name' => '36', 'is_active' => true]);
        $this->sizePP = ProductSize::create(['cigam_code' => '27', 'name' => 'PP', 'is_active' => true]);

        // Pré-popula de-para pros tamanhos que vão aparecer nos testes
        PurchaseOrderSizeMapping::create(['source_label' => '33', 'product_size_id' => $this->size33->id, 'is_active' => true, 'auto_detected' => true]);
        PurchaseOrderSizeMapping::create(['source_label' => '34', 'product_size_id' => $this->size34->id, 'is_active' => true, 'auto_detected' => true]);
        PurchaseOrderSizeMapping::create(['source_label' => '36', 'product_size_id' => $this->size36->id, 'is_active' => true, 'auto_detected' => true]);
        PurchaseOrderSizeMapping::create(['source_label' => 'PP', 'product_size_id' => $this->sizePP->id, 'is_active' => true, 'auto_detected' => true]);
    }

    protected function headerV1(): array
    {
        return [
            'Referência', 'Descrição', 'Material', 'Cor', 'Tipo', 'Grupo', 'Subgrupo',
            'Marca', 'Estação', 'Coleção', 'Custo Unit', 'Preço Venda', 'Precif',
            'Qtd Pedido', 'Custo total', 'Venda total', 'Nr Pedido', 'Status', 'Destino',
            'Dt Pedido', 'Previsão', 'Pagamento', 'Nota fiscal', 'Emissão Nf', 'Confirmação',
            // Tamanhos
            'PP', '33', '34', '36', '33/34',
        ];
    }

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------

    public function test_imports_v1_row_with_known_brand_and_mapped_sizes(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(
                ref: '00000819157',
                desc: 'I1 FSA COBRA TAIPAN OFF WHITE',
                marca: 'LUIZA BARCELOS',
                nrPedido: '132322',
                status: 'ENTREGUE',
                sizes: ['PP' => 0, '33' => 1, '34' => 2, '36' => 3, '33/34' => 0],
            ),
        ]);

        $service = app(PurchaseOrderImportService::class);
        $result = $service->import($file, $this->adminUser);

        $this->assertEquals(1, $result['orders_created']);
        $this->assertEquals(3, $result['items_created']); // 33, 34, 36 — PP e 33/34 com qty 0
        $this->assertEquals(0, $result['rows_rejected']);
        $this->assertEquals(0, $result['items_rejected']);

        $order = PurchaseOrder::where('order_number', '132322')->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->brandLuizaBarcelos->id, $order->brand_id);
        $this->assertEquals('Z424', $order->store_id);
        $this->assertNull($order->supplier_id); // alinhado com v1
        $this->assertEquals('delivered', $order->status->value);

        // Items com product_size_id resolvido
        foreach ($order->items as $item) {
            $this->assertNotNull($item->product_size_id);
        }
    }

    // ------------------------------------------------------------------
    // Marca desconhecida — REJEITA ordem inteira
    // ------------------------------------------------------------------

    public function test_rejects_order_with_unknown_brand(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(
                ref: 'REF-1',
                marca: 'MARCA QUE NAO EXISTE',
                nrPedido: 'PO-BAD',
                sizes: ['33' => 1],
            ),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertEquals(1, $result['rows_rejected']);
        $this->assertStringContainsString('Marca', $result['rejected'][0]['reason']);
        $this->assertStringContainsString('não cadastrada', $result['rejected'][0]['reason']);
        $this->assertDatabaseMissing('purchase_orders', ['order_number' => 'PO-BAD']);
    }

    public function test_rejects_order_with_empty_brand(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(
                ref: 'REF-1',
                marca: '',
                nrPedido: 'PO-EMPTY-MARCA',
                sizes: ['33' => 1],
            ),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertEquals(1, $result['rows_rejected']);
        $this->assertStringContainsString('Marca vazia', $result['rejected'][0]['reason']);
    }

    // ------------------------------------------------------------------
    // Tamanho sem mapeamento — rejeita o ITEM (não a ordem)
    // ------------------------------------------------------------------

    public function test_rejects_items_with_unmapped_size_but_keeps_order(): void
    {
        // 33/34 não tem product_size_id (pendente) — é criado pelo import
        PurchaseOrderSizeMapping::create([
            'source_label' => '33/34',
            'product_size_id' => null,
            'is_active' => true,
            'auto_detected' => true,
        ]);

        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(
                ref: 'REF-X',
                marca: 'LUIZA BARCELOS',
                nrPedido: 'PO-MIX',
                sizes: ['33' => 2, '34' => 3, '33/34' => 5], // 33/34 vai ser rejeitado
            ),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser);

        $this->assertEquals(1, $result['orders_created']);
        $this->assertEquals(2, $result['items_created']); // só 33 e 34
        $this->assertEquals(1, $result['items_rejected']); // 33/34

        $rejected = collect($result['rejected'])->first(
            fn ($r) => str_contains($r['reason'], '33/34')
        );
        $this->assertNotNull($rejected);
        $this->assertStringContainsString('sem mapeamento', $rejected['reason']);

        $order = PurchaseOrder::where('order_number', 'PO-MIX')->first();
        $this->assertEquals(2, $order->items()->count());
    }

    // ------------------------------------------------------------------
    // Múltiplas marcas numa mesma planilha
    // ------------------------------------------------------------------

    public function test_imports_multiple_brands_in_same_spreadsheet(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(ref: 'REF-LB', marca: 'LUIZA BARCELOS', nrPedido: 'PO-LB', sizes: ['33' => 2]),
            $this->rowV1(ref: 'REF-VZ', marca: 'VICENZA', nrPedido: 'PO-VZ', sizes: ['34' => 3]),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser);

        $this->assertEquals(2, $result['orders_created']);
        $this->assertEquals(2, $result['items_created']);

        $orderLB = PurchaseOrder::where('order_number', 'PO-LB')->first();
        $orderVZ = PurchaseOrder::where('order_number', 'PO-VZ')->first();

        $this->assertEquals($this->brandLuizaBarcelos->id, $orderLB->brand_id);
        $this->assertEquals($this->brandVicenza->id, $orderVZ->brand_id);
    }

    // ------------------------------------------------------------------
    // Preview detecta marcas e tamanhos pendentes
    // ------------------------------------------------------------------

    public function test_preview_flags_unknown_brands(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(ref: 'REF-1', marca: 'LUIZA BARCELOS', nrPedido: 'PO-1', sizes: ['33' => 1]),
            $this->rowV1(ref: 'REF-2', marca: 'MARCA FANTASMA', nrPedido: 'PO-2', sizes: ['34' => 1]),
        ]);

        $preview = app(PurchaseOrderImportService::class)->preview($file);

        $this->assertCount(2, $preview['brands_detected']);

        $known = collect($preview['brands_detected'])->firstWhere('name', 'LUIZA BARCELOS');
        $unknown = collect($preview['brands_detected'])->firstWhere('name', 'MARCA FANTASMA');

        $this->assertTrue($known['is_known']);
        $this->assertFalse($unknown['is_known']);
        $this->assertFalse($preview['can_import']); // bloqueia por causa da marca desconhecida
    }

    public function test_preview_flags_pending_sizes(): void
    {
        // 35/36 nem é criado previamente — vai ser auto-criado pelo preview como pendente
        $header = array_merge($this->headerV1(), ['35/36']);

        $row = $this->rowV1(ref: 'REF-1', marca: 'LUIZA BARCELOS', nrPedido: 'PO-PREV', sizes: ['33' => 1]);
        $row[] = '2'; // valor pra coluna 35/36 adicional

        $file = $this->makeCsv([$header, $row]);

        $preview = app(PurchaseOrderImportService::class)->preview($file);

        $this->assertContains('35/36', $preview['sizes_pending']);
        // "33" é conhecido, então import pode prosseguir (rejeita só o item 35/36)
        $this->assertTrue($preview['can_import']);
    }

    public function test_preview_auto_creates_size_mapping_row_as_pending(): void
    {
        $header = array_merge($this->headerV1(), ['99/100']);
        $row = $this->rowV1(ref: 'REF-1', marca: 'LUIZA BARCELOS', nrPedido: 'PO-1', sizes: ['33' => 1]);
        $row[] = '3';

        $file = $this->makeCsv([$header, $row]);

        $this->assertFalse(PurchaseOrderSizeMapping::where('source_label', '99/100')->exists());

        app(PurchaseOrderImportService::class)->preview($file);

        // Depois do preview, o label foi registrado como pendente pra aparecer no CRUD
        $mapping = PurchaseOrderSizeMapping::where('source_label', '99/100')->first();
        $this->assertNotNull($mapping);
        $this->assertNull($mapping->product_size_id);
    }

    public function test_preview_blocks_import_when_missing_required_columns(): void
    {
        $file = $this->makeCsv([
            ['Referência', 'Nr Pedido', '33'], // faltam obrigatórias
            ['REF-1', 'PO-X', '1'],
        ]);

        $preview = app(PurchaseOrderImportService::class)->preview($file);

        $this->assertNotEmpty($preview['missing_columns']);
        $this->assertFalse($preview['can_import']);
    }

    // ------------------------------------------------------------------
    // Dates / Money / Upsert
    // ------------------------------------------------------------------

    public function test_parses_brazilian_dates_and_money(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(
                ref: 'REF-BR',
                marca: 'LUIZA BARCELOS',
                nrPedido: 'PO-BR',
                sizes: ['33' => 1],
                unitCost: '1.234,56',
                sellingPrice: '2.500,00',
                dtPedido: '15/03/2024',
                previsao: '20/04/2024',
            ),
        ]);

        app(PurchaseOrderImportService::class)->import($file, $this->adminUser);

        $order = PurchaseOrder::where('order_number', 'PO-BR')->first();
        $this->assertEquals('2024-03-15', $order->order_date->toDateString());
        $this->assertEquals('2024-04-20', $order->predict_date->toDateString());

        $item = PurchaseOrderItem::where('reference', 'REF-BR')->first();
        $this->assertEquals(1234.56, (float) $item->unit_cost);
        $this->assertEquals(2500.00, (float) $item->selling_price);
    }

    public function test_upserts_pending_order_on_reimport(): void
    {
        $file1 = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(ref: 'REF-1', marca: 'LUIZA BARCELOS', nrPedido: 'PO-UPS', status: 'PENDENTE', unitCost: '100,00', sizes: ['33' => 5]),
        ]);
        app(PurchaseOrderImportService::class)->import($file1, $this->adminUser);

        $file2 = $this->makeCsv([
            $this->headerV1(),
            $this->rowV1(ref: 'REF-1', marca: 'LUIZA BARCELOS', nrPedido: 'PO-UPS', status: 'PENDENTE', unitCost: '150,00', sizes: ['33' => 8]),
        ]);
        $result = app(PurchaseOrderImportService::class)->import($file2, $this->adminUser);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertEquals(1, $result['orders_updated']);
        $this->assertEquals(1, $result['items_updated']);

        $item = PurchaseOrderItem::where('reference', 'REF-1')->first();
        $this->assertEquals(8, $item->quantity_ordered);
        $this->assertEquals(150.00, (float) $item->unit_cost);
    }

    // ------------------------------------------------------------------
    // Endpoint integration
    // ------------------------------------------------------------------

    public function test_endpoint_processes_file_without_supplier_param(): void
    {
        $file = $this->makeUploadedCsv([
            $this->headerV1(),
            $this->rowV1(ref: 'REF-EP', marca: 'LUIZA BARCELOS', nrPedido: 'PO-EP', sizes: ['33' => 2]),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.import.store'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', ['order_number' => 'PO-EP']);
    }

    public function test_endpoint_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Gera uma linha no formato v1 preservando a ordem das 25 colunas fixas
     * + 5 colunas de tamanho (PP, 33, 34, 36, 33/34).
     */
    protected function rowV1(
        string $ref,
        string $marca,
        string $nrPedido,
        array $sizes = [],
        string $desc = 'Produto teste',
        string $status = 'PENDENTE',
        string $destino = 'CD MEIA SOLA',
        string $unitCost = '100,00',
        string $sellingPrice = '200,00',
        string $dtPedido = '01/12/2024',
        string $previsao = '25/02/2025',
    ): array {
        $fixed = [
            $ref, $desc, 'Couro', 'Preto',
            'Sapatos', 'FECHADO SALTO ALTO', 'SCARPIN',
            $marca, 'INVERNO 2024', 'INVERNO 1',
            $unitCost, $sellingPrice, 'OK',
            '3', '', '', $nrPedido, $status, $destino,
            $dtPedido, $previsao, '120', '', '', '',
        ];

        // Size columns em order: PP, 33, 34, 36, 33/34
        $sizeLabels = ['PP', '33', '34', '36', '33/34'];
        $sizeValues = array_map(fn ($l) => (string) ($sizes[$l] ?? ''), $sizeLabels);

        return array_merge($fixed, $sizeValues);
    }

    protected function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'po_import_') . '.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        return $path;
    }

    protected function makeUploadedCsv(array $rows): UploadedFile
    {
        $path = $this->makeCsv($rows);
        return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
    }
}
