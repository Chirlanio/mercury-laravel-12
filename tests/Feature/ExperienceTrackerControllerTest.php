<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ExperienceEvaluation;
use App\Models\ExperienceQuestion;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ExperienceTrackerControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->employee = Employee::factory()->create([
            'name' => 'Novo Colaborador',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 2,
            'area_id' => 1,
        ]);
    }

    // ==========================================
    // Index
    // ==========================================

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ExperienceTracker/Index'));
    }

    public function test_support_can_view_index(): void
    {
        $response = $this->actingAs($this->supportUser)->get(route('experience-tracker.index'));
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('experience-tracker.index'));
        $response->assertStatus(403);
    }

    public function test_index_shows_stats(): void
    {
        $this->createEvaluation();

        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('stats'));
    }

    // ==========================================
    // Store
    // ==========================================

    public function test_admin_can_create_evaluation(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('experience-tracker.store'), [
            'employee_id' => $this->employee->id,
            'manager_id' => $this->adminUser->id,
            'store_id' => $this->store->code,
            'milestone' => '45',
            'date_admission' => now()->subDays(40)->format('Y-m-d'),
            'milestone_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('experience-tracker.index'));
        $this->assertDatabaseHas('experience_evaluations', [
            'employee_id' => $this->employee->id,
            'milestone' => '45',
            'manager_status' => 'pending',
            'employee_status' => 'pending',
        ]);
    }

    public function test_support_cannot_create_evaluation(): void
    {
        $response = $this->actingAs($this->supportUser)->post(route('experience-tracker.store'), [
            'employee_id' => $this->employee->id,
            'manager_id' => $this->adminUser->id,
            'store_id' => $this->store->code,
            'milestone' => '45',
            'date_admission' => now()->subDays(40)->format('Y-m-d'),
            'milestone_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    public function test_validation_requires_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('experience-tracker.store'), []);
        $response->assertSessionHasErrors(['employee_id', 'manager_id', 'store_id', 'milestone', 'date_admission', 'milestone_date']);
    }

    // ==========================================
    // Show
    // ==========================================

    public function test_can_view_evaluation_detail(): void
    {
        $eval = $this->createEvaluation();

        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.show', $eval));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'evaluation', 'managerQuestions', 'employeeQuestions',
            'managerResponses', 'employeeResponses',
        ]);
    }

    // ==========================================
    // Manager Fill
    // ==========================================

    public function test_manager_can_fill_evaluation(): void
    {
        $eval = $this->createEvaluation();
        $questions = ExperienceQuestion::active()->forMilestone('45')->forFormType('manager')->get();

        $data = [];
        foreach ($questions as $q) {
            $key = "response_{$q->id}";
            $data[$key] = match ($q->question_type) {
                'rating' => 4,
                'text' => 'Resposta teste',
                'yes_no' => true,
            };
        }

        $response = $this->actingAs($this->adminUser)->post(
            route('experience-tracker.fill-manager', $eval),
            $data
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('experience_evaluations', [
            'id' => $eval->id,
            'manager_status' => 'completed',
        ]);
        $this->assertNotNull($eval->fresh()->manager_completed_at);
    }

    // ==========================================
    // Public Form
    // ==========================================

    public function test_public_form_accessible_with_valid_token(): void
    {
        $eval = $this->createEvaluation();

        $response = $this->get(route('experience-tracker.public-form', $eval->employee_token));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ExperienceTracker/PublicForm')
            ->where('alreadyCompleted', false)
        );
    }

    public function test_public_form_shows_completed_when_already_done(): void
    {
        $eval = $this->createEvaluation();
        $eval->update(['employee_status' => 'completed', 'employee_completed_at' => now()]);

        $response = $this->get(route('experience-tracker.public-form', $eval->employee_token));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('alreadyCompleted', true));
    }

    public function test_public_form_404_with_invalid_token(): void
    {
        $response = $this->get(route('experience-tracker.public-form', 'invalid-token'));
        $response->assertStatus(404);
    }

    public function test_employee_can_submit_public_form(): void
    {
        $eval = $this->createEvaluation();
        $questions = ExperienceQuestion::active()->forMilestone('45')->forFormType('employee')->get();

        $data = [];
        foreach ($questions as $q) {
            $key = "response_{$q->id}";
            $data[$key] = match ($q->question_type) {
                'rating' => 3,
                'text' => 'Feedback do colaborador',
                'yes_no' => true,
            };
        }

        $response = $this->post(route('experience-tracker.public-submit', $eval->employee_token), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('experience_evaluations', [
            'id' => $eval->id,
            'employee_status' => 'completed',
        ]);
    }

    public function test_cannot_submit_public_form_twice(): void
    {
        $eval = $this->createEvaluation();
        $eval->update(['employee_status' => 'completed', 'employee_completed_at' => now()]);

        $response = $this->post(route('experience-tracker.public-submit', $eval->employee_token), []);
        $response->assertStatus(422);
    }

    // ==========================================
    // Statistics
    // ==========================================

    public function test_can_view_statistics(): void
    {
        $this->createEvaluation();

        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.statistics'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['total_pending', 'near_deadline', 'overdue', 'completed_month', 'compliance', 'hiring']);
    }

    // ==========================================
    // Filter tests
    // ==========================================

    public function test_can_filter_by_milestone(): void
    {
        $this->createEvaluation(['milestone' => '45']);
        $this->createEvaluation(['milestone' => '90', 'employee_id' => Employee::factory()->create([
            'name' => 'Outro', 'store_id' => $this->store->code, 'position_id' => 1, 'status_id' => 2, 'area_id' => 1,
        ])->id]);

        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.index', ['milestone' => '45']));
        $response->assertStatus(200);
    }

    public function test_can_filter_by_status(): void
    {
        $this->createEvaluation();

        $response = $this->actingAs($this->adminUser)->get(route('experience-tracker.index', ['status' => 'pending']));
        $response->assertStatus(200);
    }

    // ==========================================
    // Seeded questions
    // ==========================================

    public function test_questions_are_seeded(): void
    {
        // 45 days: 7 manager + 6 employee = 13
        $this->assertEquals(7, ExperienceQuestion::forMilestone('45')->forFormType('manager')->count());
        $this->assertEquals(6, ExperienceQuestion::forMilestone('45')->forFormType('employee')->count());

        // 90 days: 7 manager + 6 employee = 13
        $this->assertEquals(7, ExperienceQuestion::forMilestone('90')->forFormType('manager')->count());
        $this->assertEquals(6, ExperienceQuestion::forMilestone('90')->forFormType('employee')->count());

        // Total: 26
        $this->assertEquals(26, ExperienceQuestion::count());
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createEvaluation(array $overrides = []): ExperienceEvaluation
    {
        return ExperienceEvaluation::create(array_merge([
            'employee_id' => $this->employee->id,
            'manager_id' => $this->adminUser->id,
            'store_id' => $this->store->code,
            'milestone' => '45',
            'date_admission' => now()->subDays(40),
            'milestone_date' => now()->addDays(5),
            'employee_token' => Str::random(64),
        ], $overrides));
    }
}
