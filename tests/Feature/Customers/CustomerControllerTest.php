<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura HTTP do CustomerController — index, show, lookup, sync.
 */
class CustomerControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        config(['queue.default' => 'sync']);
    }

    protected function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'cigam_code' => '10001-'.rand(100, 999),
            'name' => 'MARIA SILVA',
            'cpf' => '12345678909',
            'email' => 'maria@exemplo.com',
            'mobile' => '85987654321',
            'city' => 'FORTALEZA',
            'state' => 'CE',
            'is_active' => true,
            'synced_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // index
    // ------------------------------------------------------------------

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Customers/Index'));
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('customers.index'))->assertRedirect('/login');
    }

    public function test_index_search_filters_by_name(): void
    {
        $this->makeCustomer(['name' => 'JOÃO DA SILVA']);
        $this->makeCustomer(['name' => 'MARIA ANTONIETA']);

        $this->actingAs($this->adminUser)
            ->get(route('customers.index', ['search' => 'JOÃO']))
            ->assertInertia(fn ($page) => $page->where('customers.total', 1));
    }

    public function test_index_search_filters_by_cpf_digits(): void
    {
        $this->makeCustomer(['cpf' => '12345678909']);
        $this->makeCustomer(['cpf' => '99988877766']);

        $this->actingAs($this->adminUser)
            ->get(route('customers.index', ['search' => '123.456']))
            ->assertInertia(fn ($page) => $page->where('customers.total', 1));
    }

    public function test_index_filter_by_state(): void
    {
        $this->makeCustomer(['state' => 'CE']);
        $this->makeCustomer(['state' => 'SP']);

        $this->actingAs($this->adminUser)
            ->get(route('customers.index', ['state' => 'CE']))
            ->assertInertia(fn ($page) => $page->where('customers.total', 1));
    }

    public function test_index_only_active_default_true(): void
    {
        $this->makeCustomer(['is_active' => true]);
        $this->makeCustomer(['is_active' => false]);

        $this->actingAs($this->adminUser)
            ->get(route('customers.index'))
            ->assertInertia(fn ($page) => $page->where('customers.total', 1));
    }

    public function test_index_includes_inactive_when_filter_off(): void
    {
        $this->makeCustomer(['is_active' => true]);
        $this->makeCustomer(['is_active' => false]);

        $this->actingAs($this->adminUser)
            ->get(route('customers.index', ['only_active' => '0']))
            ->assertInertia(fn ($page) => $page->where('customers.total', 2));
    }

    // ------------------------------------------------------------------
    // show
    // ------------------------------------------------------------------

    public function test_show_returns_json_with_detail(): void
    {
        $c = $this->makeCustomer();

        $this->actingAs($this->adminUser)
            ->getJson(route('customers.show', $c->id))
            ->assertOk()
            ->assertJsonStructure([
                'customer' => [
                    'id', 'cigam_code', 'name', 'formatted_cpf',
                    'consignments',
                ],
            ]);
    }

    // ------------------------------------------------------------------
    // lookup
    // ------------------------------------------------------------------

    public function test_lookup_returns_matches_by_name(): void
    {
        $this->makeCustomer(['name' => 'MARIA ANTONIETA']);

        $this->actingAs($this->adminUser)
            ->getJson(route('customers.lookup', ['q' => 'MARIA']))
            ->assertOk()
            ->assertJsonStructure(['results' => [['id', 'name', 'formatted_cpf']]]);
    }

    public function test_lookup_excludes_inactive(): void
    {
        $this->makeCustomer(['name' => 'ATIVO', 'is_active' => true]);
        $this->makeCustomer(['name' => 'INATIVO TESTE', 'is_active' => false]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('customers.lookup', ['q' => 'ATIV']))
            ->assertOk();

        $names = collect($response->json('results'))->pluck('name')->all();
        $this->assertContains('ATIVO', $names);
        $this->assertNotContains('INATIVO TESTE', $names);
    }

    public function test_lookup_rejects_short_query(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('customers.lookup', ['q' => 'A']))
            ->assertStatus(422);
    }

    public function test_lookup_matches_by_cpf_digits(): void
    {
        $this->makeCustomer(['cpf' => '12345678909', 'name' => 'POR CPF']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('customers.lookup', ['q' => '123456']))
            ->assertOk();

        $this->assertSame('POR CPF', $response->json('results.0.name'));
    }

    // ------------------------------------------------------------------
    // sync
    // ------------------------------------------------------------------

    public function test_regular_user_cannot_trigger_sync(): void
    {
        // USER role não tem SYNC_CUSTOMERS
        $this->actingAs($this->regularUser)
            ->post(route('customers.sync'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // syncHistory
    // ------------------------------------------------------------------

    public function test_sync_history_returns_recent_logs(): void
    {
        \App\Models\CustomerSyncLog::create([
            'sync_type' => 'full',
            'status' => 'completed',
            'total_records' => 100,
            'processed_records' => 100,
            'inserted_records' => 30,
            'updated_records' => 70,
            'skipped_records' => 0,
            'error_count' => 0,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'started_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('customers.sync-history'))
            ->assertOk()
            ->assertJsonStructure([
                'logs' => [
                    ['id', 'sync_type', 'status', 'inserted_records', 'triggered'],
                ],
            ]);

        $log = $response->json('logs.0');
        $this->assertSame('completed', $log['status']);
        $this->assertSame(30, $log['inserted_records']);
        $this->assertSame('manual', $log['triggered']);
        $this->assertSame($this->adminUser->name, $log['started_by']);
    }

    public function test_sync_history_marks_scheduled_runs(): void
    {
        \App\Models\CustomerSyncLog::create([
            'sync_type' => 'full',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
            'started_by_user_id' => null, // scheduled run
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('customers.sync-history'))
            ->assertOk();

        $this->assertSame('schedule', $response->json('logs.0.triggered'));
    }

    // ------------------------------------------------------------------
    // cancelSync
    // ------------------------------------------------------------------

    public function test_cancel_sync_marks_log_as_cancelled(): void
    {
        $log = \App\Models\CustomerSyncLog::create([
            'sync_type' => 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('customers.sync.cancel', $log->id))
            ->assertRedirect();

        $log->refresh();
        $this->assertSame('cancelled', $log->status);
        $this->assertNotNull($log->completed_at);
    }

    public function test_cancel_sync_requires_permission(): void
    {
        $log = \App\Models\CustomerSyncLog::create([
            'sync_type' => 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        // regularUser não tem SYNC_CUSTOMERS
        $this->actingAs($this->regularUser)
            ->post(route('customers.sync.cancel', $log->id))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // M20 — atualização dos limites de consignação
    // ------------------------------------------------------------------

    public function test_update_consignment_limits_persists_values(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->adminUser)
            ->patch(route('customers.consignment-limits.update', $customer->id), [
                'consignment_max_items' => 5,
                'consignment_max_value' => 1500.00,
            ])
            ->assertRedirect();

        $customer->refresh();
        $this->assertSame(5, $customer->consignment_max_items);
        $this->assertEquals(1500.00, (float) $customer->consignment_max_value);
    }

    public function test_update_consignment_limits_clears_when_null(): void
    {
        $customer = $this->makeCustomer([
            'consignment_max_items' => 3,
            'consignment_max_value' => 500.00,
        ]);

        $this->actingAs($this->adminUser)
            ->patch(route('customers.consignment-limits.update', $customer->id), [
                'consignment_max_items' => null,
                'consignment_max_value' => null,
            ])
            ->assertRedirect();

        $customer->refresh();
        $this->assertNull($customer->consignment_max_items);
        $this->assertNull($customer->consignment_max_value);
    }

    public function test_update_consignment_limits_forbidden_for_regular_user(): void
    {
        $customer = $this->makeCustomer();

        // regularUser não tem MANAGE_CONSIGNMENTS
        $this->actingAs($this->regularUser)
            ->patch(route('customers.consignment-limits.update', $customer->id), [
                'consignment_max_items' => 5,
            ])
            ->assertForbidden();
    }
}
