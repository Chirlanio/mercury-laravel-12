<?php

namespace Tests\Feature\TravelExpenses;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\User;
use App\Services\TravelExpenseAccountabilityService;
use App\Services\TravelExpenseService;
use App\Services\TravelExpenseTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseAccountabilityServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TravelExpenseService $service;
    protected TravelExpenseTransitionService $transition;
    protected TravelExpenseAccountabilityService $accountability;
    protected int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z421');
        $this->employeeId = $this->createTestEmployee(['store_id' => 'Z421']);

        $this->service = app(TravelExpenseService::class);
        $this->transition = app(TravelExpenseTransitionService::class);
        $this->accountability = app(TravelExpenseAccountabilityService::class);

        config(['queue.default' => 'sync']);
        Storage::fake('public');
    }

    protected function makeApprovedExpense(): TravelExpense
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'Reunião',
            'pix_type_id' => 1,
            'pix_key' => '11122233344',
        ], $this->adminUser);

        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'approved', $this->adminUser);

        return $te;
    }

    // ==================================================================
    // addItem
    // ==================================================================

    public function test_add_item_creates_item_and_auto_transitions_to_in_progress(): void
    {
        $te = $this->makeApprovedExpense();
        $this->assertSame(AccountabilityStatus::PENDING, $te->accountability_status);

        $item = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50.00,
            'description' => 'Almoço',
        ], $this->adminUser);

        $this->assertSame(50.00, (float) $item->value);
        $this->assertSame(AccountabilityStatus::IN_PROGRESS, $te->fresh()->accountability_status);
    }

    public function test_add_item_blocked_when_expense_not_approved(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
        ], $this->adminUser);

        // ainda em DRAFT — bloqueado
        $this->expectException(ValidationException::class);
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);
    }

    public function test_add_item_blocked_when_accountability_already_submitted(): void
    {
        $te = $this->makeApprovedExpense();
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);
        $te = $this->transition->transitionAccountability($te->fresh(), 'submitted', $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-11',
            'value' => 30,
            'description' => 'Tentativa pós submit',
        ], $this->adminUser);
    }

    public function test_add_item_with_attachment_uploads_and_records_metadata(): void
    {
        $te = $this->makeApprovedExpense();
        $file = UploadedFile::fake()->image('comprovante.jpg', 800, 600);

        $item = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 75.00,
            'description' => 'Taxi com comprovante',
            'attachment' => $file,
        ], $this->adminUser);

        $this->assertNotNull($item->attachment_path);
        $this->assertSame('comprovante.jpg', $item->attachment_original_name);
        $this->assertStringStartsWith('image/', $item->attachment_mime);
        $this->assertGreaterThan(0, $item->attachment_size);
        Storage::disk('public')->assertExists($item->attachment_path);
    }

    public function test_add_item_rejects_unsupported_mime_type(): void
    {
        $te = $this->makeApprovedExpense();
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->expectException(ValidationException::class);
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
            'attachment' => $file,
        ], $this->adminUser);
    }

    public function test_add_item_rejects_oversized_file(): void
    {
        $te = $this->makeApprovedExpense();
        // 6MB > 5MB limit
        $file = UploadedFile::fake()->create('grande.pdf', 6 * 1024, 'application/pdf');

        $this->expectException(ValidationException::class);
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
            'attachment' => $file,
        ], $this->adminUser);
    }

    public function test_add_item_blocked_for_user_without_accountability_perm(): void
    {
        $te = $this->makeApprovedExpense();
        // user com role que NÃO tem MANAGE_ACCOUNTABILITY nem é dono nem manager
        $other = User::factory()->create([
            'role' => Role::DRIVER->value,
            'access_level_id' => 4,
        ]);

        $this->expectException(ValidationException::class);
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $other);
    }

    // ==================================================================
    // updateItem
    // ==================================================================

    public function test_update_item_replaces_attachment_and_deletes_old(): void
    {
        $te = $this->makeApprovedExpense();
        $file1 = UploadedFile::fake()->image('antigo.jpg');

        $item = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
            'attachment' => $file1,
        ], $this->adminUser);
        $oldPath = $item->attachment_path;

        $file2 = UploadedFile::fake()->image('novo.jpg');
        $item = $this->accountability->updateItem($item, [
            'value' => 80,
            'attachment' => $file2,
        ], $this->adminUser);

        $this->assertSame(80.00, (float) $item->value);
        $this->assertSame('novo.jpg', $item->attachment_original_name);
        Storage::disk('public')->assertMissing($oldPath); // arquivo antigo removido
        Storage::disk('public')->assertExists($item->attachment_path);
    }

    // ==================================================================
    // deleteItem (soft) + auto-transição
    // ==================================================================

    public function test_delete_last_item_reverts_to_pending(): void
    {
        $te = $this->makeApprovedExpense();
        $item = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);

        $te->refresh();
        $this->assertSame(AccountabilityStatus::IN_PROGRESS, $te->accountability_status);

        $this->accountability->deleteItem($item, $this->adminUser);

        $this->assertSame(AccountabilityStatus::PENDING, $te->fresh()->accountability_status);
    }

    public function test_delete_one_of_many_items_keeps_in_progress(): void
    {
        $te = $this->makeApprovedExpense();
        $item1 = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);
        $item2 = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-11',
            'value' => 30,
            'description' => 'Y',
        ], $this->adminUser);

        $this->accountability->deleteItem($item1, $this->adminUser);

        $this->assertSame(AccountabilityStatus::IN_PROGRESS, $te->fresh()->accountability_status);
        $this->assertSame(1, $te->items()->count());
    }

    public function test_delete_item_blocked_when_accountability_submitted(): void
    {
        $te = $this->makeApprovedExpense();
        $item = $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);
        $te = $this->transition->transitionAccountability($te->fresh(), 'submitted', $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->accountability->deleteItem($item, $this->adminUser);
    }

    // ==================================================================
    // submitAccountability
    // ==================================================================

    public function test_submit_accountability_transitions_to_submitted(): void
    {
        $te = $this->makeApprovedExpense();
        $this->accountability->addItem($te, [
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'X',
        ], $this->adminUser);

        $te = $this->accountability->submitAccountability($te->fresh(), $this->adminUser);

        $this->assertSame(AccountabilityStatus::SUBMITTED, $te->accountability_status);
        $this->assertNotNull($te->accountability_submitted_at);
    }
}
