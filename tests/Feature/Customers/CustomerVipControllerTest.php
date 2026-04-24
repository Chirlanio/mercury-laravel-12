<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CustomerVipControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();
        Cache::store('array')->forget('vip.ms_life_store_codes');

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
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'cigam_code' => '10001-'.rand(100, 999),
            'name' => 'CLIENTE TESTE',
            'cpf' => str_pad((string) rand(1, 99999999999), 11, '0', STR_PAD_LEFT),
            'is_active' => true,
            'synced_at' => now(),
        ], $overrides));
    }

    private function makeTier(Customer $customer, array $overrides = []): CustomerVipTier
    {
        return CustomerVipTier::create(array_merge([
            'customer_id' => $customer->id,
            'year' => 2025,
            'suggested_tier' => 'gold',
            'final_tier' => 'gold',
            'total_revenue' => 8000,
            'total_orders' => 4,
            'suggested_at' => now(),
            'source' => 'auto',
        ], $overrides));
    }

    // --------------------------------------------------------------
    // index
    // --------------------------------------------------------------

    public function test_admin_can_view_vip_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('customers.vip.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Customers/VipIndex'));
    }

    public function test_regular_user_cannot_view_vip_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('customers.vip.index'))
            ->assertForbidden();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('customers.vip.index'))->assertRedirect('/login');
    }

    public function test_index_filters_by_tier(): void
    {
        $c1 = $this->makeCustomer(['name' => 'CLIENTE BLACK']);
        $c2 = $this->makeCustomer(['name' => 'CLIENTE GOLD']);
        $this->makeTier($c1, ['final_tier' => 'black', 'suggested_tier' => 'black', 'total_revenue' => 20000]);
        $this->makeTier($c2, ['final_tier' => 'gold']);

        $this->actingAs($this->adminUser)
            ->get(route('customers.vip.index', ['year' => 2025, 'final_tier' => 'black']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customers/VipIndex')
                ->where('tiers.total', 1)
            );
    }

    public function test_index_filters_pending_curation(): void
    {
        $c1 = $this->makeCustomer(['name' => 'PENDENTE']);
        $c2 = $this->makeCustomer(['name' => 'CURADO']);
        $this->makeTier($c1); // curated_at null
        $this->makeTier($c2, ['curated_at' => now(), 'source' => 'manual']);

        $this->actingAs($this->adminUser)
            ->get(route('customers.vip.index', ['year' => 2025, 'final_tier' => 'pending']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tiers.total', 1));
    }

    // --------------------------------------------------------------
    // curate
    // --------------------------------------------------------------

    public function test_admin_can_curate_tier(): void
    {
        $customer = $this->makeCustomer();
        $tier = $this->makeTier($customer);

        $this->actingAs($this->adminUser)
            ->patch(route('customers.vip.curate', $tier->id), [
                'final_tier' => 'black',
                'notes' => 'Aprovado pela diretoria',
            ])
            ->assertRedirect();

        $tier->refresh();
        $this->assertEquals('black', $tier->final_tier);
        $this->assertEquals('Aprovado pela diretoria', $tier->notes);
        $this->assertEquals('manual', $tier->source);
        $this->assertEquals($this->adminUser->id, $tier->curated_by_user_id);
    }

    public function test_support_user_cannot_curate_tier(): void
    {
        $customer = $this->makeCustomer();
        $tier = $this->makeTier($customer);

        // SUPPORT não tem CURATE_VIP_CUSTOMERS
        $this->actingAs($this->supportUser)
            ->patch(route('customers.vip.curate', $tier->id), ['final_tier' => 'black'])
            ->assertForbidden();
    }

    public function test_curate_rejects_invalid_tier_via_validation(): void
    {
        $customer = $this->makeCustomer();
        $tier = $this->makeTier($customer);

        $this->actingAs($this->adminUser)
            ->patch(route('customers.vip.curate', $tier->id), ['final_tier' => 'platinum'])
            ->assertSessionHasErrors('final_tier');
    }

    // --------------------------------------------------------------
    // destroy
    // --------------------------------------------------------------

    public function test_destroy_nullifies_final_tier(): void
    {
        $customer = $this->makeCustomer();
        $tier = $this->makeTier($customer, ['final_tier' => 'black']);

        $this->actingAs($this->adminUser)
            ->delete(route('customers.vip.destroy', $tier->id))
            ->assertRedirect();

        $tier->refresh();
        $this->assertNull($tier->final_tier);
        $this->assertEqualsWithDelta(8000.0, (float) $tier->total_revenue, 0.01, 'histórico preservado');
    }

    // --------------------------------------------------------------
    // runSuggestions
    // --------------------------------------------------------------

    public function test_admin_can_run_suggestions(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('customers.vip.suggestions'), ['year' => 2025])
            ->assertRedirect(route('customers.vip.index', ['year' => 2025]))
            ->assertSessionHas('success');
    }

    public function test_support_user_cannot_run_suggestions(): void
    {
        $this->actingAs($this->supportUser)
            ->post(route('customers.vip.suggestions'), ['year' => 2025])
            ->assertForbidden();
    }

    // --------------------------------------------------------------
    // config (thresholds)
    // --------------------------------------------------------------

    public function test_admin_can_create_threshold(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('customers.vip.config.store'), [
                'year' => 2025,
                'tier' => 'black',
                'min_revenue' => 15000,
                'notes' => 'Threshold aprovado',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_vip_tier_configs', [
            'year' => 2025,
            'tier' => 'black',
            'min_revenue' => 15000,
        ]);
    }

    public function test_store_threshold_upserts_on_conflict(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('customers.vip.config.store'), [
                'year' => 2025, 'tier' => 'gold', 'min_revenue' => 5000,
            ]);

        $this->actingAs($this->adminUser)
            ->post(route('customers.vip.config.store'), [
                'year' => 2025, 'tier' => 'gold', 'min_revenue' => 7000,
            ]);

        $this->assertEquals(1, \App\Models\CustomerVipTierConfig::count());
        $this->assertEquals(
            7000.0,
            (float) \App\Models\CustomerVipTierConfig::first()->min_revenue,
        );
    }
}
