<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Employee;
use App\Models\HdCategory;
use App\Models\HdChannel;
use App\Models\HdChatSession;
use App\Models\HdDepartment;
use App\Models\HdIdentityLookup;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use App\Services\HelpdeskIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Validates the CPF identity resolution flow wired into the WhatsApp driver.
 *
 * Scenarios covered:
 *   1. Silent phone match skips the CPF step entirely
 *   2. Non-identified department never asks for CPF
 *   3. Identified department asks for CPF and resolves on valid match
 *   4. Invalid CPF retries once then falls through to anonymous
 *   5. Ticket carries employee_id when resolved
 *   6. hd_identity_lookups row is created per attempt
 */
class WhatsappIdentityFlowTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $ti;
    protected HdDepartment $dp;
    protected HdCategory $dpPayroll;
    protected HdChannel $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Clean slate — driver relies on exactly known departments/categories.
        HdCategory::query()->delete();
        HdDepartment::query()->delete();

        // TI: does NOT require identification. Sort 1 so it's "1" in the menu.
        $this->ti = HdDepartment::create([
            'name' => 'TI Test',
            'is_active' => true,
            'sort_order' => 1,
            'requires_identification' => false,
        ]);
        HdCategory::create([
            'department_id' => $this->ti->id,
            'name' => 'Hardware',
            'is_active' => true,
            'default_priority' => HdTicket::PRIORITY_MEDIUM,
        ]);

        // DP: requires identification. Sort 2 so it's "2" in the menu.
        $this->dp = HdDepartment::create([
            'name' => 'DP Test',
            'is_active' => true,
            'sort_order' => 2,
            'requires_identification' => true,
        ]);
        $this->dpPayroll = HdCategory::create([
            'department_id' => $this->dp->id,
            'name' => 'Folha de Pagamento',
            'is_active' => true,
            'default_priority' => HdTicket::PRIORITY_HIGH,
        ]);

        $this->whatsapp = HdChannel::firstOrCreate(
            ['slug' => 'whatsapp'],
            [
                'name' => 'WhatsApp',
                'driver' => 'whatsapp',
                'config' => ['greeting' => 'Olá! Atendimento virtual.'],
                'is_active' => true,
            ],
        );
        $this->whatsapp->update(['is_active' => true]);

        Config::set('services.evolution.fake', true);
    }

    // -----------------------------
    // Scenario 1: silent phone match
    // -----------------------------

    public function test_phone_match_skips_cpf_even_for_dp(): void
    {
        // Given an employee already cadastrado with this phone
        $employee = $this->createEmployee(['phone_primary' => '(85) 98746-0451']);

        // First contact
        $step = $this->intake('5585987460451', 'oi');
        $this->assertStringContainsString($employee->first_name, $step->prompt);

        // Picks DP (which requires identification)
        $step = $this->intake('5585987460451', '2');
        // Should go straight to category menu — no CPF ask
        $this->assertStringContainsString('Folha de Pagamento', $step->prompt);
        $this->assertStringNotContainsString('CPF', $step->prompt);

        // Complete the flow
        $this->intake('5585987460451', '1');
        $step = $this->intake('5585987460451', 'Preciso do holerite do mês passado.');

        $this->assertTrue($step->isComplete);

        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame($employee->id, $ticket->employee_id);
        $this->assertSame($this->dp->id, $ticket->department_id);
    }

    // -----------------------------
    // Scenario 2: non-identified department never asks CPF
    // -----------------------------

    public function test_ti_never_asks_cpf(): void
    {
        // No phone match in employees table
        $this->intake('5585900000001', 'oi');
        $this->intake('5585900000001', '1'); // TI

        $step = $this->intake('5585900000001', 'Meu computador não liga, ajuda urgente');
        // TI has one category, so after depto we are in category menu
        $this->assertFalse($step->isComplete);
        $this->assertStringNotContainsString('CPF', $step->prompt);

        $step = $this->intake('5585900000001', '1'); // pick Hardware
        $step = $this->intake('5585900000001', 'Meu computador não liga, ajuda urgente');

        $this->assertTrue($step->isComplete);

        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame($this->ti->id, $ticket->department_id);
        $this->assertNull($ticket->employee_id);
    }

    // -----------------------------
    // Scenario 3: DP asks CPF, valid match proceeds
    // -----------------------------

    public function test_dp_asks_cpf_and_resolves_on_valid_match(): void
    {
        $employee = $this->createEmployee([
            'cpf' => '52998224725',
            'phone_primary' => null, // won't match silently
        ]);

        $this->intake('5585900000002', 'oi');
        $step = $this->intake('5585900000002', '2'); // DP

        $this->assertStringContainsString('CPF', $step->prompt);

        $session = HdChatSession::where('external_contact', '5585900000002')->first();
        $this->assertSame('awaiting_cpf', $session->step);

        // Send the CPF
        $step = $this->intake('5585900000002', '529.982.247-25');

        $this->assertStringContainsString($employee->first_name, $step->prompt);
        $this->assertStringContainsString('Folha de Pagamento', $step->prompt); // category menu now

        $session->refresh();
        $this->assertSame('awaiting_category', $session->step);
        $this->assertSame($employee->id, $session->context['employee_id']);

        // Pick category and describe
        $this->intake('5585900000002', '1');
        $step = $this->intake('5585900000002', 'Preciso do holerite do último mês, por favor.');

        $this->assertTrue($step->isComplete);
        $ticket = HdTicket::findOrFail($step->ticketId);
        $this->assertSame($employee->id, $ticket->employee_id);
    }

    // -----------------------------
    // Scenario 4: invalid CPF retries then anonymous fallback
    // -----------------------------

    public function test_invalid_cpf_retries_then_falls_back_to_anonymous(): void
    {
        // No employee created — all CPFs will fail to match.
        $this->intake('5585900000003', 'oi');
        $this->intake('5585900000003', '2'); // DP

        // First attempt — bad checksum
        $step = $this->intake('5585900000003', '12345678900');
        $this->assertStringContainsString('inválido', $step->prompt);
        $this->assertStringNotContainsString('manualmente', $step->prompt);

        $session = HdChatSession::where('external_contact', '5585900000003')->first();
        $this->assertSame('awaiting_cpf', $session->step);

        // Second attempt — still no match, should fall through to anonymous
        $step = $this->intake('5585900000003', '52998224725');
        $this->assertStringContainsString('manualmente', $step->prompt);

        $session->refresh();
        $this->assertSame('awaiting_category', $session->step);
        $this->assertTrue($session->context['identification_failed'] ?? false);

        // Complete the flow anonymously
        $this->intake('5585900000003', '1');
        $step = $this->intake('5585900000003', 'Preciso tirar uma dúvida sobre férias de 2024.');

        $this->assertTrue($step->isComplete);

        $ticket = HdTicket::with('interactions')->findOrFail($step->ticketId);
        $this->assertNull($ticket->employee_id);

        // An internal warning interaction was created
        $warning = $ticket->interactions->firstWhere('is_internal', true);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('manualmente', $warning->comment);
    }

    // -----------------------------
    // Scenario 5: hd_identity_lookups is populated
    // -----------------------------

    public function test_identity_lookups_are_recorded(): void
    {
        $employee = $this->createEmployee([
            'cpf' => '52998224725',
            'phone_primary' => '(85) 91234-5678', // not the one we simulate with
        ]);

        // Phone doesn't match, won't log phone lookup (passive).
        $this->intake('5585900000004', 'oi');
        $this->intake('5585900000004', '2'); // DP

        // Bad attempt
        $this->intake('5585900000004', '00000000000');

        // Good attempt
        $this->intake('5585900000004', '52998224725');

        $logs = HdIdentityLookup::orderBy('id')->get();

        // Expect exactly 2 CPF lookups (blacklisted + valid match).
        $cpfLogs = $logs->where('method', 'cpf');
        $this->assertCount(2, $cpfLogs);

        $this->assertFalse($cpfLogs->first()->matched);
        $this->assertSame(1, $cpfLogs->first()->attempt);

        $this->assertTrue($cpfLogs->last()->matched);
        $this->assertSame(2, $cpfLogs->last()->attempt);
        $this->assertSame($employee->id, $cpfLogs->last()->employee_id);
    }

    // -----------------------------
    // Scenario 6: phone match log
    // -----------------------------

    public function test_phone_match_creates_lookup_record(): void
    {
        $employee = $this->createEmployee(['phone_primary' => '85987460451']);

        $this->intake('5585987460451', 'oi');

        $phoneLogs = HdIdentityLookup::where('method', 'phone')->get();
        $this->assertCount(1, $phoneLogs);
        $this->assertTrue($phoneLogs->first()->matched);
        $this->assertSame($employee->id, $phoneLogs->first()->employee_id);
    }

    // -----------------------------
    // Helpers
    // -----------------------------

    protected function intake(string $contact, string $message): \App\Services\Intake\IntakeStep
    {
        return app(HelpdeskIntakeService::class)->handle('whatsapp', [
            'message' => $message,
        ], [
            'external_contact' => $contact,
            'push_name' => 'Teste',
            'instance' => 'test',
        ]);
    }

    protected function createEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'name' => 'Maria Silva Souza',
            'short_name' => 'Maria Silva',
            'cpf' => '52998224725',
            'admission_date' => '2023-01-15',
            'dismissal_date' => null,
            'position_id' => 1,
            'store_id' => 'Z421',
            'education_level_id' => 1,
            'gender_id' => 1,
            'birth_date' => '1990-05-20',
            'area_id' => 1,
            'is_pcd' => false,
            'is_apprentice' => false,
            'level' => 'Pleno',
            'status_id' => 2,
        ], $overrides));
    }
}
