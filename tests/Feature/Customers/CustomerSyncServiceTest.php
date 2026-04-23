<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Services\CustomerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Testes de integração do CustomerSyncService focando no upsert e
 * sanitização de linhas vindas da view CIGAM. Não testamos o SELECT
 * do PostgreSQL (fora do escopo do teste unitário/integração local).
 */
class CustomerSyncServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected CustomerSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->service = app(CustomerSyncService::class);
    }

    /**
     * Constrói uma linha "como se viesse" da view msl_dcliente_.
     * Todos os campos são strings (como o driver pgsql retorna).
     */
    protected function makeRow(array $overrides = []): object
    {
        return (object) array_merge([
            'cigam_code' => '10001-1',
            'codigo_cliente' => '10001',
            'digito_cliente' => '12345678909',  // aqui é o CPF (nome confuso da view)
            'nome_completo' => 'maria silva',
            'ddd_telefone' => '85',
            'telefone' => '3212-3456',
            'ddd_celular' => '85',
            'celular' => '9 8765-4321',
            'email' => 'maria@exemplo.com',
            'endereco' => 'rua das flores',
            'numero' => '100',
            'complemento' => 'casa',
            'bairro' => 'centro',
            'uf' => 'ce',
            'cidade' => 'fortaleza',
            'cep' => '60000-000',
            'tip_pessoa' => 'f',
            'data_cadastramento' => '2026-01-15',
            'data_aniversario' => '1990-05-10',
            'sexo' => 'F',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // upsertCustomer
    // ------------------------------------------------------------------

    public function test_upsert_inserts_new_customer_with_sanitized_fields(): void
    {
        $result = $this->service->upsertCustomer($this->makeRow());

        $this->assertSame('inserted', $result);

        $customer = Customer::first();
        $this->assertSame('MARIA SILVA', $customer->name);
        $this->assertSame('12345678909', $customer->cpf);
        $this->assertSame('maria@exemplo.com', $customer->email);
        $this->assertSame('8532123456', $customer->phone);
        $this->assertSame('85987654321', $customer->mobile);
        $this->assertSame('CE', $customer->state);
        $this->assertSame('60000000', $customer->zipcode);
        $this->assertSame('FORTALEZA', $customer->city);
        $this->assertSame('F', $customer->person_type);
        $this->assertSame('F', $customer->gender);
        $this->assertSame('1990-05-10', $customer->birth_date->format('Y-m-d'));
        $this->assertSame('2026-01-15', $customer->registered_at->format('Y-m-d'));
    }

    public function test_upsert_updates_existing_by_cigam_code(): void
    {
        $this->service->upsertCustomer($this->makeRow(['nome_completo' => 'Maria A']));
        $result = $this->service->upsertCustomer($this->makeRow(['nome_completo' => 'Maria B']));

        $this->assertSame('updated', $result);
        $this->assertSame(1, Customer::count());
        $this->assertSame('MARIA B', Customer::first()->name);
    }

    public function test_upsert_returns_skipped_when_no_changes(): void
    {
        $this->service->upsertCustomer($this->makeRow());
        $result = $this->service->upsertCustomer($this->makeRow());

        $this->assertSame('skipped', $result);
    }

    public function test_upsert_skips_when_name_is_empty(): void
    {
        $result = $this->service->upsertCustomer($this->makeRow(['nome_completo' => '']));
        $this->assertSame('skipped', $result);
        $this->assertSame(0, Customer::count());
    }

    public function test_upsert_skips_when_cigam_code_empty(): void
    {
        $result = $this->service->upsertCustomer($this->makeRow(['cigam_code' => '-']));
        $this->assertSame('skipped', $result);
        $this->assertSame(0, Customer::count());
    }

    public function test_upsert_handles_dirty_data_gracefully(): void
    {
        $row = $this->makeRow([
            'email' => 'not-an-email',
            'uf' => 'XYZ',
            'cep' => '123',
            'ddd_telefone' => null,
            'telefone' => null,
            'sexo' => 'qwerty',
            'tip_pessoa' => '9',
            'data_aniversario' => 'not-a-date',
        ]);

        $result = $this->service->upsertCustomer($row);

        $this->assertSame('inserted', $result);
        $customer = Customer::first();
        $this->assertNull($customer->email);
        $this->assertNull($customer->state);
        $this->assertNull($customer->zipcode);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->gender);
        $this->assertNull($customer->person_type);
        $this->assertNull($customer->birth_date);
        // Nome + cigam_code ainda válidos — registro é salvo mesmo com sujeira
        $this->assertNotNull($customer->name);
    }

    public function test_upsert_sets_synced_at(): void
    {
        $this->service->upsertCustomer($this->makeRow());
        $this->assertNotNull(Customer::first()->synced_at);
    }

    // ------------------------------------------------------------------
    // cancel
    // ------------------------------------------------------------------

    public function test_cancel_marks_log_as_cancelled(): void
    {
        $log = $this->service->start('full', $this->adminUser->id);
        $this->service->cancel($log->id);

        $log->refresh();
        $this->assertSame('cancelled', $log->status);
        $this->assertNotNull($log->completed_at);
    }

    public function test_cancel_does_not_change_completed_logs(): void
    {
        $log = $this->service->start('full');
        $log->update(['status' => 'completed', 'completed_at' => now()]);

        $this->service->cancel($log->id);

        $log->refresh();
        $this->assertSame('completed', $log->status);
    }
}
