<?php

namespace Tests\Feature;

use App\Console\Commands\BudgetsAlertCommand;
use App\Enums\Permission;
use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Notifications\BudgetAlertNotification;
use App\Services\BudgetAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BudgetAlertTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected ManagementClass $mc;

    protected CostCenter $cc1;

    protected CostCenter $cc2;

    protected BudgetUpload $budget;

    protected BudgetItem $item1;

    protected BudgetItem $item2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Limpa cache file do CentralRoleResolver — sem isso, permissões
        // adicionadas recentemente ao Role enum podem ficar ocultas se o
        // cache (TTL 5min) ainda tem a lista antiga.
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia

        $this->cc1 = CostCenter::create([
            'code' => 'CC-ALERT-1', 'name' => 'CC Warning',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->cc2 = CostCenter::create([
            'code' => 'CC-ALERT-2', 'name' => 'CC OK',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mc = ManagementClass::create([
            'code' => 'MC-ALERT', 'name' => 'Gerencial Alert',
            'accepts_entries' => true, 'accounting_class_id' => $this->ac->id,
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $year = (int) now()->year;

        $this->budget = BudgetUpload::create([
            'year' => $year,
            'scope_label' => 'TestAlert',
            'version_label' => '1.0',
            'major_version' => 1,
            'minor_version' => 0,
            'upload_type' => 'novo',
            'original_filename' => 'test.xlsx',
            'stored_path' => 'budgets/test.xlsx',
            'file_size_bytes' => 1000,
            'is_active' => true,
            'total_year' => 24000,
            'items_count' => 2,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $base = [
            'budget_upload_id' => $this->budget->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $this->mc->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ];

        $this->item1 = BudgetItem::create($base + ['cost_center_id' => $this->cc1->id]);
        $this->item2 = BudgetItem::create($base + ['cost_center_id' => $this->cc2->id]);
    }

    protected function createOp(int $itemId, float $value, string $status = 'waiting'): void
    {
        DB::table('order_payments')->insert([
            'description' => 'OP alert test',
            'total_value' => $value,
            'date_payment' => now()->format('Y-m-d'),
            'payment_type' => 'pix',
            'status' => $status,
            'installments' => 1,
            'advance' => false, 'advance_amount' => 0, 'advance_paid' => false,
            'proof' => false, 'payment_prepared' => false, 'has_allocation' => false,
            'budget_item_id' => $itemId,
            'created_by_user_id' => $this->adminUser->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_service_returns_no_alerts_when_all_below_warning(): void
    {
        // 50% do cc1, 10% do cc2 — nenhum em warning
        $this->createOp($this->item1->id, 6000);
        $this->createOp($this->item2->id, 1200);

        $scan = app(BudgetAlertService::class)->scanAlerts();

        $this->assertCount(0, $scan['alerts']);
        $this->assertEquals(0, $scan['summary']['warning_count']);
        $this->assertEquals(0, $scan['summary']['exceeded_count']);
    }

    public function test_service_detects_warning(): void
    {
        // 85% do cc1 — warning
        $this->createOp($this->item1->id, 10200);

        $scan = app(BudgetAlertService::class)->scanAlerts();

        $this->assertCount(1, $scan['alerts']);
        $alert = $scan['alerts'][0];
        $this->assertEquals('warning', $alert['status']);
        $this->assertCount(1, $alert['warning_ccs']);
        $this->assertEquals('CC-ALERT-1', $alert['warning_ccs'][0]['code']);
        $this->assertEquals(1, $scan['summary']['warning_count']);
    }

    public function test_service_detects_exceeded(): void
    {
        // 125% do cc1 — exceeded
        $this->createOp($this->item1->id, 15000);

        $scan = app(BudgetAlertService::class)->scanAlerts();

        $this->assertCount(1, $scan['alerts']);
        $alert = $scan['alerts'][0];
        $this->assertEquals('exceeded', $alert['status']); // exceeded manda sobre warning
        $this->assertCount(1, $alert['exceeded_ccs']);
        $this->assertEquals(1, $scan['summary']['exceeded_count']);
    }

    public function test_service_skips_budgets_from_other_years(): void
    {
        // 125% mas em ano diferente → não deve alertar
        $this->createOp($this->item1->id, 15000);

        $scan = app(BudgetAlertService::class)->scanAlerts((int) now()->year + 1);

        $this->assertCount(0, $scan['alerts']);
    }

    public function test_service_skips_inactive_budgets(): void
    {
        $this->createOp($this->item1->id, 15000);
        $this->budget->update(['is_active' => false]);

        $scan = app(BudgetAlertService::class)->scanAlerts();

        $this->assertCount(0, $scan['alerts']);
    }

    public function test_command_sends_notifications_to_users_with_permission(): void
    {
        Notification::fake();

        $this->createOp($this->item1->id, 15000); // exceeded

        $command = new BudgetsAlertCommand();
        $count = $command->scanTenant((int) now()->year);

        // adminUser (Role::ADMIN tem VIEW_BUDGET_CONSUMPTION) recebe
        $this->assertGreaterThanOrEqual(1, $count);
        Notification::assertSentTo($this->adminUser, BudgetAlertNotification::class);
    }

    public function test_command_does_not_send_when_no_alerts(): void
    {
        Notification::fake();

        $this->createOp($this->item1->id, 1000); // 8% — abaixo de warning

        $command = new BudgetsAlertCommand();
        $count = $command->scanTenant((int) now()->year);

        $this->assertEquals(0, $count);
        Notification::assertNothingSent();
    }

    public function test_command_dry_run_does_not_send(): void
    {
        Notification::fake();

        $this->createOp($this->item1->id, 15000);

        $command = new BudgetsAlertCommand();
        $count = $command->scanTenant((int) now()->year, dryRun: true);

        $this->assertEquals(0, $count);
        Notification::assertNothingSent();
    }

    public function test_notification_payload_structure(): void
    {
        $this->createOp($this->item1->id, 15000); // exceeded

        $scan = app(BudgetAlertService::class)->scanAlerts();
        $notification = new BudgetAlertNotification($scan);

        $channels = $notification->via($this->adminUser);
        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);

        $data = $notification->toArray($this->adminUser);
        $this->assertEquals('budget_alert', $data['type']);
        $this->assertStringContainsString('consumo', strtolower($data['message']));
        $this->assertContains('TestAlert', $data['affected_scopes']);
        // exceeded_total = count(exceeded_ccs) + exceeded_items
        // = 1 CC excedido + 1 item excedido = 2
        $this->assertEquals(2, $data['exceeded_total']);
    }

    public function test_regular_user_without_permission_not_in_recipients(): void
    {
        Notification::fake();

        $this->createOp($this->item1->id, 15000);

        (new BudgetsAlertCommand())->scanTenant((int) now()->year);

        Notification::assertSentTo($this->adminUser, BudgetAlertNotification::class);
        Notification::assertNotSentTo($this->regularUser, BudgetAlertNotification::class);
    }
}
