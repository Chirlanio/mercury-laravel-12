<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdBusinessHour;
use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DepartmentSettingsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];

        // The settings page requires MANAGE_HD_PERMISSIONS — the admin user
        // fixture from TestHelpers is a SUPER_ADMIN which has every permission.
        $this->admin = $this->adminUser;
    }

    // ----------------------------------------------------------------
    // Access control
    // ----------------------------------------------------------------

    public function test_guest_cannot_view_department_settings(): void
    {
        $this->get(route('helpdesk.department-settings.index'))->assertRedirect(route('login'));
    }

    public function test_regular_user_without_permission_cannot_view(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('helpdesk.department-settings.index'))
            ->assertForbidden();
    }

    public function test_support_user_can_view_settings_page(): void
    {
        // Support holds MANAGE_HD_DEPARTMENTS and should be able to access
        // department configuration. Permissions management (separate route)
        // still requires MANAGE_HD_PERMISSIONS which Support does not have.
        $this->actingAs($this->supportUser)
            ->get(route('helpdesk.department-settings.index'))
            ->assertOk();
    }

    public function test_support_user_cannot_access_permissions_page(): void
    {
        $this->actingAs($this->supportUser)
            ->get(route('helpdesk.permissions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('helpdesk.department-settings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Helpdesk/DepartmentSettings')
            ->has('departments')
            ->has('weekdayLabels')
            ->has('promptPlaceholders')
        );
    }

    // ----------------------------------------------------------------
    // Business hours
    // ----------------------------------------------------------------

    public function test_update_business_hours_replaces_schedule(): void
    {
        // Seed some initial ranges
        HdBusinessHour::create([
            'department_id' => $this->department->id,
            'weekday' => 1,
            'start_time' => '07:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.business-hours.update', $this->department->id), [
                'ranges' => [
                    ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                    ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '18:00'],
                    ['weekday' => 2, 'start_time' => '09:00', 'end_time' => '17:00'],
                ],
            ]);

        $response->assertRedirect();

        $rows = HdBusinessHour::where('department_id', $this->department->id)
            ->orderBy('weekday')->orderBy('start_time')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame('09:00:00', $rows[0]->start_time);
        $this->assertSame('12:00:00', $rows[0]->end_time);
        $this->assertSame('13:00:00', $rows[1]->start_time);
        // The pre-existing 07:00-11:00 row should be gone
        $this->assertFalse($rows->contains(fn ($r) => $r->start_time === '07:00:00'));
    }

    public function test_update_business_hours_rejects_inverted_range(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.business-hours.update', $this->department->id), [
                'ranges' => [
                    ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '09:00'],
                ],
            ]);

        $response->assertSessionHasErrors(['ranges.0.end_time']);
        $this->assertSame(0, HdBusinessHour::where('department_id', $this->department->id)->count());
    }

    public function test_update_business_hours_empty_ranges_clears_schedule(): void
    {
        HdBusinessHour::create([
            'department_id' => $this->department->id,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.business-hours.update', $this->department->id), [
                'ranges' => [],
            ]);

        $this->assertSame(0, HdBusinessHour::where('department_id', $this->department->id)->count());
    }

    // ----------------------------------------------------------------
    // Holidays
    // ----------------------------------------------------------------

    public function test_store_holiday_creates_row(): void
    {
        $this->actingAs($this->admin)
            ->post(route('helpdesk.department-settings.holidays.store', $this->department->id), [
                'date' => '2026-12-25',
                'description' => 'Natal',
            ])
            ->assertRedirect();

        $holiday = HdHoliday::where('department_id', $this->department->id)->first();
        $this->assertNotNull($holiday);
        $this->assertSame('Natal', $holiday->description);
        $this->assertSame('2026-12-25', $holiday->date->format('Y-m-d'));
    }

    public function test_store_holiday_rejects_invalid_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('helpdesk.department-settings.holidays.store', $this->department->id), [
                'date' => 'not-a-date',
            ])
            ->assertSessionHasErrors(['date']);
    }

    public function test_destroy_holiday_removes_row(): void
    {
        $holiday = HdHoliday::create([
            'department_id' => $this->department->id,
            'date' => '2026-11-15',
            'description' => 'Proclamação',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('helpdesk.department-settings.holidays.destroy', [
                $this->department->id,
                $holiday->id,
            ]))
            ->assertRedirect();

        $this->assertNull(HdHoliday::find($holiday->id));
    }

    public function test_destroy_holiday_from_wrong_department_is_forbidden(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'Outro']);
        $holiday = HdHoliday::create([
            'department_id' => $otherDept->id,
            'date' => '2026-10-12',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('helpdesk.department-settings.holidays.destroy', [
                $this->department->id, // wrong dept
                $holiday->id,
            ]))
            ->assertNotFound();

        // Still exists
        $this->assertNotNull(HdHoliday::find($holiday->id));
    }

    // ----------------------------------------------------------------
    // AI config
    // ----------------------------------------------------------------

    public function test_update_ai_enables_and_saves_prompt(): void
    {
        $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.ai.update', $this->department->id), [
                'ai_classification_enabled' => true,
                'ai_classification_prompt' => 'Classifique este chamado de {{department_name}}.',
            ])
            ->assertRedirect();

        $fresh = $this->department->fresh();
        $this->assertTrue($fresh->ai_classification_enabled);
        $this->assertStringContainsString('{{department_name}}', $fresh->ai_classification_prompt);
    }

    public function test_update_ai_can_clear_prompt(): void
    {
        $this->department->update([
            'ai_classification_enabled' => true,
            'ai_classification_prompt' => 'Prompt antigo',
        ]);

        $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.ai.update', $this->department->id), [
                'ai_classification_enabled' => true,
                'ai_classification_prompt' => null,
            ])
            ->assertRedirect();

        $this->assertNull($this->department->fresh()->ai_classification_prompt);
    }

    public function test_update_ai_disables_classification(): void
    {
        $this->department->update(['ai_classification_enabled' => true]);

        $this->actingAs($this->admin)
            ->put(route('helpdesk.department-settings.ai.update', $this->department->id), [
                'ai_classification_enabled' => false,
                'ai_classification_prompt' => null,
            ]);

        $this->assertFalse($this->department->fresh()->ai_classification_enabled);
    }

    // ----------------------------------------------------------------
    // Index page returns correct data for the selected department
    // ----------------------------------------------------------------

    public function test_index_returns_business_hours_and_holidays_for_selected_department(): void
    {
        HdBusinessHour::create([
            'department_id' => $this->department->id,
            'weekday' => 3,
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);
        HdHoliday::create([
            'department_id' => $this->department->id,
            'date' => '2026-12-25',
            'description' => 'Natal',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('helpdesk.department-settings.index', ['department_id' => $this->department->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('selectedDepartmentId', $this->department->id)
            ->has('businessHours', 1)
            ->has('holidays', 1)
            ->where('holidays.0.description', 'Natal')
        );
    }
}
