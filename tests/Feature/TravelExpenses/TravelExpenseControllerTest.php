<?php

namespace Tests\Feature\TravelExpenses;

use App\Enums\AccountabilityStatus;
use App\Enums\Role;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\User;
use App\Services\TravelExpenseService;
use App\Services\TravelExpenseTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $employeeId;
    protected User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z421');
        $this->employeeId = $this->createTestEmployee(['store_id' => 'Z421']);

        $this->financeUser = User::factory()->create([
            'role' => Role::FINANCE->value,
            'access_level_id' => 1,
        ]);

        config(['queue.default' => 'sync']);
        Storage::fake('public');
    }

    protected function defaultPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'Teste',
            'pix_type_id' => 1,
            'pix_key' => '11122233344',
        ], $overrides);
    }

    protected function makeApprovedExpense(): TravelExpense
    {
        $service = app(TravelExpenseService::class);
        $transition = app(TravelExpenseTransitionService::class);

        $te = $service->create($this->defaultPayload(), $this->adminUser);
        $te = $transition->transitionExpense($te, 'submitted', $this->adminUser);
        $te = $transition->transitionExpense($te, 'approved', $this->adminUser);

        return $te;
    }

    // ==================================================================
    // Auth + Index
    // ==================================================================

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('travel-expenses.index'))->assertRedirect('/login');
    }

    public function test_index_renders_inertia(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('travel-expenses.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('TravelExpenses/Index'));
    }

    public function test_index_filters_by_status(): void
    {
        $service = app(TravelExpenseService::class);
        $service->create($this->defaultPayload(), $this->adminUser);
        $service->create($this->defaultPayload(['origin' => 'Outro']), $this->adminUser);

        $response = $this->actingAs($this->adminUser)->get(route('travel-expenses.index', ['status' => 'draft']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('expenses.data.0.status', 'draft')
            ->where('expenses.total', 2)
        );
    }

    // ==================================================================
    // Store
    // ==================================================================

    public function test_store_creates_draft_expense(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('travel-expenses.store'), $this->defaultPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('travel_expenses', [
            'employee_id' => $this->employeeId,
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'status' => 'draft',
        ]);
    }

    public function test_store_with_auto_submit_transitions_immediately(): void
    {
        $this->actingAs($this->adminUser)->post(
            route('travel-expenses.store'),
            $this->defaultPayload(['auto_submit' => '1'])
        )->assertRedirect();

        $te = TravelExpense::first();
        $this->assertSame(TravelExpenseStatus::SUBMITTED, $te->status);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('travel-expenses.store'), [
            // sem campos obrigatórios
        ]);
        $response->assertSessionHasErrors(['employee_id', 'store_code', 'origin', 'destination', 'initial_date', 'end_date', 'description']);
    }

    public function test_store_rejects_end_before_start(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('travel-expenses.store'), $this->defaultPayload([
            'initial_date' => '2026-05-15',
            'end_date' => '2026-05-10',
        ]));
        $response->assertSessionHasErrors('end_date');
    }

    public function test_store_blocked_for_user_without_create_permission(): void
    {
        $this->createTestStore('Z422');
        $this->createTestEmployee(['store_id' => 'Z422', 'cpf' => '99999999999']);
        $driver = User::factory()->create([
            'role' => Role::DRIVER->value,
            'access_level_id' => 4,
        ]);

        $this->actingAs($driver)
            ->post(route('travel-expenses.store'), $this->defaultPayload())
            ->assertForbidden();
    }

    // ==================================================================
    // Show
    // ==================================================================

    public function test_show_returns_detailed_json(): void
    {
        $te = $this->makeApprovedExpense();

        $response = $this->actingAs($this->adminUser)->get(route('travel-expenses.show', $te->ulid));
        $response->assertOk();
        $response->assertJson([
            'expense' => [
                'id' => $te->id,
                'ulid' => $te->ulid,
                'status' => 'approved',
                'accountability_status' => 'pending',
            ],
        ]);
    }

    public function test_show_blocks_access_for_other_store_user(): void
    {
        $te = $this->makeApprovedExpense(); // store Z421
        $this->createTestStore('Z999');

        // user em store diferente, sem MANAGE/APPROVE
        $other = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
            'store_id' => 'Z999',
        ]);

        $this->actingAs($other)
            ->get(route('travel-expenses.show', $te->ulid))
            ->assertForbidden();
    }

    // ==================================================================
    // Update
    // ==================================================================

    public function test_update_modifies_draft(): void
    {
        $service = app(TravelExpenseService::class);
        $te = $service->create($this->defaultPayload(), $this->adminUser);

        $response = $this->actingAs($this->adminUser)->put(route('travel-expenses.update', $te->ulid), [
            'origin' => 'Natal',
            'destination' => 'João Pessoa',
        ]);

        $response->assertRedirect();
        $te->refresh();
        $this->assertSame('Natal', $te->origin);
        $this->assertSame('João Pessoa', $te->destination);
    }

    // ==================================================================
    // Destroy
    // ==================================================================

    public function test_destroy_requires_reason(): void
    {
        $service = app(TravelExpenseService::class);
        $te = $service->create($this->defaultPayload(), $this->adminUser);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('travel-expenses.destroy', $te->ulid), []);

        $response->assertSessionHasErrors('deleted_reason');
    }

    public function test_destroy_soft_deletes_with_reason(): void
    {
        $service = app(TravelExpenseService::class);
        $te = $service->create($this->defaultPayload(), $this->adminUser);

        $this->actingAs($this->adminUser)
            ->delete(route('travel-expenses.destroy', $te->ulid), [
                'deleted_reason' => 'Criada por engano',
            ])
            ->assertRedirect();

        $te->refresh();
        $this->assertNotNull($te->deleted_at);
        $this->assertSame('Criada por engano', $te->deleted_reason);
    }

    // ==================================================================
    // Transition
    // ==================================================================

    public function test_transition_endpoint_updates_status(): void
    {
        $service = app(TravelExpenseService::class);
        $te = $service->create($this->defaultPayload(), $this->adminUser);

        $this->actingAs($this->adminUser)->post(route('travel-expenses.transition', $te->ulid), [
            'kind' => 'expense',
            'to_status' => 'submitted',
        ])->assertRedirect();

        $this->assertSame('submitted', $te->fresh()->status->value);
    }

    public function test_transition_validates_required_fields(): void
    {
        $te = $this->makeApprovedExpense();

        $this->actingAs($this->adminUser)
            ->post(route('travel-expenses.transition', $te->ulid), [])
            ->assertSessionHasErrors(['kind', 'to_status']);
    }

    // ==================================================================
    // Items
    // ==================================================================

    public function test_store_item_creates_with_attachment(): void
    {
        $te = $this->makeApprovedExpense();

        $this->actingAs($this->adminUser)
            ->post(route('travel-expenses.items.store', $te->ulid), [
                'type_expense_id' => 1,
                'expense_date' => '2026-05-10',
                'value' => 50.00,
                'description' => 'Almoço',
                'attachment' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertRedirect();

        $item = TravelExpenseItem::first();
        $this->assertNotNull($item);
        $this->assertNotNull($item->attachment_path);
        Storage::disk('public')->assertExists($item->attachment_path);
    }

    public function test_destroy_item_soft_deletes(): void
    {
        $te = $this->makeApprovedExpense();
        $accService = app(\App\Services\TravelExpenseAccountabilityService::class);
        $item = $accService->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);

        $this->actingAs($this->adminUser)
            ->delete(route('travel-expenses.items.destroy', [$te->ulid, $item->id]))
            ->assertRedirect();

        $this->assertNotNull($item->fresh()->deleted_at);
    }

    public function test_download_attachment_returns_file(): void
    {
        $te = $this->makeApprovedExpense();
        $accService = app(\App\Services\TravelExpenseAccountabilityService::class);
        $item = $accService->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
            'attachment' => UploadedFile::fake()->image('rec.jpg'),
        ], $this->adminUser);

        $response = $this->actingAs($this->adminUser)
            ->get(route('travel-expenses.items.download', [$te->ulid, $item->id]));
        $response->assertOk();
        $this->assertStringContainsString('rec.jpg', $response->headers->get('content-disposition'));
    }

    public function test_download_attachment_404_when_item_belongs_to_other_expense(): void
    {
        $te1 = $this->makeApprovedExpense();
        $te2 = $this->makeApprovedExpense();
        $accService = app(\App\Services\TravelExpenseAccountabilityService::class);
        $item = $accService->addItem($te1, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
            'attachment' => UploadedFile::fake()->image('x.jpg'),
        ], $this->adminUser);

        $this->actingAs($this->adminUser)
            ->get(route('travel-expenses.items.download', [$te2->ulid, $item->id]))
            ->assertNotFound();
    }
}
