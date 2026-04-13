<?php

namespace Tests\Feature\Helpdesk;

use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdIntakeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class IntakeTemplatesAdminTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected HdDepartment $department;
    protected HdCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $base = $this->createHelpdeskBaseData();
        $this->department = $base['department'];
        $this->category = $base['categories'][0];
    }

    public function test_guest_cannot_access(): void
    {
        $this->get(route('helpdesk.intake-templates.index'))->assertRedirect(route('login'));
    }

    public function test_regular_user_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('helpdesk.intake-templates.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_index(): void
    {
        HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Teste',
            'fields' => [],
            'active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('helpdesk.intake-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Helpdesk/IntakeTemplates')
                ->has('templates', 1)
                ->has('fieldTypes')
                ->has('departments')
                ->has('categories'),
            );
    }

    public function test_store_creates_template_with_fields(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.intake-templates.store'), [
                'name' => 'Férias',
                'department_id' => $this->department->id,
                'active' => true,
                'sort_order' => 10,
                'fields' => [
                    ['name' => 'start_date', 'label' => 'Início', 'type' => 'date', 'required' => true],
                    ['name' => 'days', 'label' => 'Dias', 'type' => 'text', 'required' => true],
                ],
            ])
            ->assertRedirect();

        $template = HdIntakeTemplate::where('name', 'Férias')->first();
        $this->assertNotNull($template);
        $this->assertSame($this->department->id, $template->department_id);
        $this->assertCount(2, $template->fields);
        $this->assertSame('start_date', $template->fields[0]['name']);
        $this->assertSame('date', $template->fields[0]['type']);
    }

    public function test_store_rejects_invalid_field_key(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.intake-templates.store'), [
                'name' => 'Invalid',
                'department_id' => $this->department->id,
                'fields' => [
                    // Uppercase is not allowed by the regex rule.
                    ['name' => 'StartDate', 'label' => 'Início', 'type' => 'date'],
                ],
            ])
            ->assertSessionHasErrors(['fields.0.name']);
    }

    public function test_store_rejects_unknown_field_type(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.intake-templates.store'), [
                'name' => 'Invalid',
                'department_id' => $this->department->id,
                'fields' => [
                    ['name' => 'field', 'label' => 'L', 'type' => 'unknown'],
                ],
            ])
            ->assertSessionHasErrors(['fields.0.type']);
    }

    public function test_store_rejects_category_from_different_department(): void
    {
        $otherDept = HdDepartment::factory()->create(['name' => 'Outro']);
        $foreignCategory = HdCategory::factory()->forDepartment($otherDept)->create();

        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.intake-templates.store'), [
                'name' => 'Cross',
                'department_id' => $this->department->id,
                'category_id' => $foreignCategory->id,
                'fields' => [],
            ])
            ->assertStatus(422);
    }

    public function test_store_accepts_select_field_with_options(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('helpdesk.intake-templates.store'), [
                'name' => 'Tipo de férias',
                'department_id' => $this->department->id,
                'fields' => [
                    [
                        'name' => 'vacation_type',
                        'label' => 'Tipo',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'full', 'label' => 'Integral'],
                            ['value' => 'partial', 'label' => 'Parcial'],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $template = HdIntakeTemplate::where('name', 'Tipo de férias')->first();
        $this->assertCount(2, $template->fields[0]['options']);
        $this->assertSame('full', $template->fields[0]['options'][0]['value']);
    }

    public function test_update_modifies_template(): void
    {
        $template = HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Original',
            'fields' => [['name' => 'a', 'label' => 'A', 'type' => 'text']],
            'active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->put(route('helpdesk.intake-templates.update', $template->id), [
                'name' => 'Atualizado',
                'department_id' => $this->department->id,
                'active' => true,
                'fields' => [
                    ['name' => 'new_field', 'label' => 'Novo', 'type' => 'textarea'],
                ],
            ])
            ->assertRedirect();

        $fresh = $template->fresh();
        $this->assertSame('Atualizado', $fresh->name);
        $this->assertCount(1, $fresh->fields);
        $this->assertSame('new_field', $fresh->fields[0]['name']);
    }

    public function test_destroy_removes_template(): void
    {
        $template = HdIntakeTemplate::create([
            'department_id' => $this->department->id,
            'name' => 'Descartável',
            'fields' => [],
            'active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->delete(route('helpdesk.intake-templates.destroy', $template->id))
            ->assertRedirect();

        $this->assertNull(HdIntakeTemplate::find($template->id));
    }
}
