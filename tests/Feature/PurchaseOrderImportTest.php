<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PurchaseOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Tests do import no formato v1 Mercury.
 *
 * Header:
 *   Referência, Descrição, Material, Cor, Tipo, Grupo, Subgrupo, Marca,
 *   Estação, Coleção, Custo Unit, Preço Venda, Precif, Qtd Pedido,
 *   Custo total, Venda total, Nr Pedido, Status, Destino, Dt Pedido,
 *   Previsão, Pagamento, Nota fiscal, Emissão Nf, Confirmação, <tamanhos...>
 */
class PurchaseOrderImportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create([
            'code' => 'Z424',
            'name' => 'CD MEIA SOLA',
        ]);
        $this->supplier = $this->createTestSupplier([
            'nome_fantasia' => 'Fornecedor Teste',
        ]);
    }

    protected function headerV1(): array
    {
        return [
            'Referência', 'Descrição', 'Material', 'Cor', 'Tipo', 'Grupo', 'Subgrupo',
            'Marca', 'Estação', 'Coleção', 'Custo Unit', 'Preço Venda', 'Precif',
            'Qtd Pedido', 'Custo total', 'Venda total', 'Nr Pedido', 'Status', 'Destino',
            'Dt Pedido', 'Previsão', 'Pagamento', 'Nota fiscal', 'Emissão Nf', 'Confirmação',
            // Tamanhos
            'PP', 'P', 'M', 'G', 'GG',
            '33', '34', '35', '36', '37', '38', '39', '40',
            '33/34', '35/36',
        ];
    }

    // ------------------------------------------------------------------
    // Parsing do formato v1
    // ------------------------------------------------------------------

    public function test_imports_v1_row_expanding_size_matrix(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            [
                '00000819157', 'I1 FSA COBRA TAIPAN OFF WHITE', 'COBRA TAIPAN', 'OFF WHITE',
                'Sapatos', 'FECHADO SALTO ALTO', 'SCARPIN',
                'LUIZA BARCELOS', 'INVERNO 2021', 'INVERNO 1',
                '172,90', '399,00', 'OK',
                '12', '2074,80', '4788,00', '132322', 'ENTREGUE', 'CD MEIA SOLA',
                '02/12/2020', '25/02/2021', '120', '223941', '19/02/2021', '02/03/2021',
                // Tamanhos — PP, P, M, G, GG (5)
                '', '', '', '', '',
                // 33, 34, 35, 36, 37, 38, 39, 40 (8)
                '1', '2', '3', '3', '2', '1', '', '',
                // 33/34, 35/36 (2)
                '', '',
            ],
        ]);

        $service = app(PurchaseOrderImportService::class);
        $result = $service->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals(1, $result['orders_created']);
        // 6 tamanhos com qtd > 0 = 6 items
        $this->assertEquals(6, $result['items_created']);
        $this->assertEquals(0, $result['rows_rejected']);

        $order = PurchaseOrder::where('order_number', '132322')->first();
        $this->assertNotNull($order);
        $this->assertEquals('INVERNO 2021', $order->season);
        $this->assertEquals('INVERNO 1', $order->collection);
        $this->assertEquals('Z424', $order->store_id);
        $this->assertEquals($this->supplier->id, $order->supplier_id);
        $this->assertEquals('2020-12-02', $order->order_date->toDateString());
        $this->assertEquals('2021-02-25', $order->predict_date->toDateString());
        $this->assertEquals('120', $order->payment_terms_raw);

        // Apenas 1 linha = status "ENTREGUE" → delivered
        $this->assertEquals('delivered', $order->status->value);

        // Itens com quantities corretas
        $items = $order->items()->orderBy('size')->get()->keyBy('size');
        $this->assertEquals(1, $items['33']->quantity_ordered);
        $this->assertEquals(2, $items['34']->quantity_ordered);
        $this->assertEquals(3, $items['35']->quantity_ordered);
        $this->assertEquals(3, $items['36']->quantity_ordered);
        $this->assertEquals(2, $items['37']->quantity_ordered);
        $this->assertEquals(1, $items['38']->quantity_ordered);

        // unit_cost e selling_price parseados do formato BR
        $this->assertEquals(172.90, (float) $items['33']->unit_cost);
        $this->assertEquals(399.00, (float) $items['33']->selling_price);
        $this->assertTrue($items['33']->pricing_locked); // Precif=OK
    }

    public function test_groups_multiple_references_under_same_order(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            [
                '00000819157', 'Produto A', 'COBRA TAIPAN', 'OFF WHITE',
                'Sapatos', 'G1', 'S1', 'LUIZA BARCELOS', 'INVERNO 2021', 'INVERNO 1',
                '172,90', '399,00', 'OK', '12', '', '', '132322', 'ENTREGUE', 'CD MEIA SOLA',
                '02/12/2020', '25/02/2021', '120', '', '', '',
                '','','','','','','1','2','3','3','2','1','','','',
            ],
            [
                '10330034-288', 'Produto B', 'COBRA TAIPAN', 'BIC',
                'Sapatos', 'G1', 'S1', 'LUIZA BARCELOS', 'INVERNO 2021', 'INVERNO 1',
                '172,90', '399,00', 'OK', '9', '', '', '132322', 'ENTREGUE', 'CD MEIA SOLA',
                '02/12/2020', '25/02/2021', '120', '', '', '',
                '','','','','','','','1','2','3','2','1','','','',
            ],
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals(1, $result['orders_created']);
        $this->assertEquals(11, $result['items_created']); // 6 + 5
        $this->assertEquals(1, PurchaseOrder::where('order_number', '132322')->count());

        $order = PurchaseOrder::where('order_number', '132322')->first();
        $this->assertEquals(11, $order->items()->count());
    }

    public function test_status_group_picks_most_frequent(): void
    {
        // 3 linhas: 2 ENTREGUE, 1 CANCELADO → delivered
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', '132322', 'ENTREGUE'),
            $this->rowWithStatus('REF-2', '132322', 'CANCELADO'),
            $this->rowWithStatus('REF-3', '132322', 'ENTREGUE'),
        ]);

        app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals('delivered', PurchaseOrder::where('order_number', '132322')->first()->status->value);
    }

    public function test_store_lookup_by_name_works(): void
    {
        // Store tem code=Z424 e name="CD MEIA SOLA"
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-NAME', 'PENDENTE', destino: 'CD MEIA SOLA'),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals(1, $result['orders_created']);
        $this->assertEquals('Z424', PurchaseOrder::where('order_number', 'PO-NAME')->first()->store_id);
    }

    public function test_store_lookup_by_code_works(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-CODE', 'PENDENTE', destino: 'Z424'),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals(1, $result['orders_created']);
        $this->assertEquals('Z424', PurchaseOrder::where('order_number', 'PO-CODE')->first()->store_id);
    }

    public function test_rejects_unknown_store(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-BAD', 'PENDENTE', destino: 'LOJA FANTASMA'),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertEquals(1, $result['rows_rejected']);
        $this->assertStringContainsString('Destino', $result['rejected'][0]['reason']);
    }

    public function test_rejects_everything_without_supplier_id(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-NS', 'PENDENTE'),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, null);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertStringContainsString('Fornecedor', $result['rejected'][0]['reason']);
    }

    public function test_parses_brazilian_dates(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus(
                'REF-1', 'PO-DATE', 'PENDENTE',
                dtPedido: '15/03/2024',
                previsao: '20/04/2024'
            ),
        ]);

        app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $order = PurchaseOrder::where('order_number', 'PO-DATE')->first();
        $this->assertEquals('2024-03-15', $order->order_date->toDateString());
        $this->assertEquals('2024-04-20', $order->predict_date->toDateString());
    }

    public function test_parses_brazilian_money_format(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-M', 'PO-MONEY', 'PENDENTE', unitCost: '1.234,56', sellingPrice: '2.500,00'),
        ]);

        app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        $item = PurchaseOrderItem::where('reference', 'REF-M')->first();
        $this->assertEquals(1234.56, (float) $item->unit_cost);
        $this->assertEquals(2500.00, (float) $item->selling_price);
    }

    public function test_upserts_existing_pending_order(): void
    {
        $file1 = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-UPS', 'PENDENTE', unitCost: '100,00', qty36: 5),
        ]);

        app(PurchaseOrderImportService::class)->import($file1, $this->adminUser, $this->supplier->id);

        // Re-import com mesmo order_number, mesma ref, mesmo tamanho, mas unit_cost e qty diferentes
        $file2 = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-UPS', 'PENDENTE', unitCost: '150,00', qty36: 8),
        ]);

        $result = app(PurchaseOrderImportService::class)->import($file2, $this->adminUser, $this->supplier->id);

        $this->assertEquals(0, $result['orders_created']);
        $this->assertEquals(1, $result['orders_updated']);
        $this->assertEquals(1, $result['items_updated']);

        $item = PurchaseOrderItem::where('reference', 'REF-1')->first();
        $this->assertEquals(150.00, (float) $item->unit_cost);
        $this->assertEquals(8, $item->quantity_ordered);
    }

    public function test_skips_zero_quantity_sizes(): void
    {
        // Row com vários tamanhos mas só 36 e 37 têm qty > 0
        $row = $this->rowWithStatus('REF-Z', 'PO-ZERO', 'PENDENTE', qty36: 3);
        $row[36] = '2'; // coluna 37 (35→idx25+11=idx36)
        $file = $this->makeCsv([$this->headerV1(), $row]);

        $result = app(PurchaseOrderImportService::class)->import($file, $this->adminUser, $this->supplier->id);

        // Só 2 tamanhos com qty > 0
        $this->assertEquals(2, $result['items_created']);
    }

    public function test_preview_returns_detected_size_columns(): void
    {
        $file = $this->makeCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-PREV', 'PENDENTE'),
        ]);

        $preview = app(PurchaseOrderImportService::class)->preview($file);

        $this->assertEquals(1, $preview['total']);
        $this->assertNotEmpty($preview['size_columns']);
        $this->assertContains('pp', $preview['size_columns']);
        $this->assertContains('33/34', $preview['size_columns']);
    }

    public function test_preview_detects_missing_required_columns(): void
    {
        $file = $this->makeCsv([
            ['Referência', 'Custo Unit', '33'], // faltam várias obrigatórias
            ['REF-1', '100', '5'],
        ]);

        $preview = app(PurchaseOrderImportService::class)->preview($file);

        $this->assertNotEmpty($preview['missing_columns']);
        $this->assertContains('nr_pedido', $preview['missing_columns']);
        $this->assertContains('destino', $preview['missing_columns']);
    }

    // ------------------------------------------------------------------
    // Endpoint integration
    // ------------------------------------------------------------------

    public function test_endpoint_requires_default_supplier_id(): void
    {
        $file = $this->makeUploadedCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-EP-1', 'PENDENTE'),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('default_supplier_id');
    }

    public function test_endpoint_processes_file_with_supplier(): void
    {
        $file = $this->makeUploadedCsv([
            $this->headerV1(),
            $this->rowWithStatus('REF-1', 'PO-EP-OK', 'PENDENTE'),
        ]);

        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.import.store'),
            ['file' => $file, 'default_supplier_id' => $this->supplier->id]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', ['order_number' => 'PO-EP-OK']);
    }

    public function test_endpoint_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $response = $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.import.store'), [
                'file' => $file,
                'default_supplier_id' => $this->supplier->id,
            ]);

        $response->assertSessionHasErrors('file');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Gera uma linha v1 com tamanho 36 preenchido por padrão.
     * Use kwargs-style pra customizar.
     */
    protected function rowWithStatus(
        string $referencia,
        string $nrPedido,
        string $status,
        string $destino = 'CD MEIA SOLA',
        string $unitCost = '100,00',
        string $sellingPrice = '200,00',
        string $dtPedido = '01/12/2024',
        string $previsao = '25/02/2025',
        int $qty36 = 3,
    ): array {
        // Ordem igual ao headerV1(): 25 colunas fixas + 15 tamanhos
        $row = [
            $referencia, 'Produto teste', 'Couro', 'Preto',
            'Sapatos', 'FECHADO SALTO ALTO', 'SCARPIN',
            'Marca X', 'INVERNO 2024', 'INVERNO 1',
            $unitCost, $sellingPrice, 'OK',
            '3', '', '', $nrPedido, $status, $destino,
            $dtPedido, $previsao, '120', '', '', '',
        ];
        // 15 colunas de tamanho (PP..33/34..35/36)
        // Índices a partir de 25: PP(25), P(26), M(27), G(28), GG(29),
        //   33(30), 34(31), 35(32), 36(33), 37(34), 38(35), 39(36), 40(37),
        //   33/34(38), 35/36(39)
        $sizes = array_fill(0, 15, '');
        $sizes[8] = (string) $qty36; // tamanho 36
        return array_merge($row, $sizes);
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
