<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\Employee;
use App\Models\Store;
use App\Models\Training;
use App\Models\TrainingFacilitator;
use App\Models\TrainingParticipant;
use App\Models\TrainingSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TrainingEventControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Employee $employee;

    protected TrainingFacilitator $facilitator;

    protected TrainingSubject $subject;

    protected CertificateTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);

        $this->employee = Employee::factory()->create([
            'name' => 'João Silva',
            'store_id' => $this->store->code,
            'position_id' => 1,
            'status_id' => 2,
            'area_id' => 1,
        ]);

        $this->facilitator = TrainingFacilitator::create([
            'name' => 'Carlos Mentor',
            'email' => 'carlos@test.com',
            'external' => false,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->subject = TrainingSubject::create([
            'name' => 'Atendimento ao Cliente',
            'description' => 'Treinamento de atendimento',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->template = CertificateTemplate::create([
            'name' => 'Template Teste',
            'html_template' => '<h1>Certificado para {{participant_name}}</h1>',
            'is_default' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    // ==========================================
    // Index
    // ==========================================

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('trainings.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Trainings/Index'));
    }

    public function test_support_can_view_index(): void
    {
        $response = $this->actingAs($this->supportUser)->get(route('trainings.index'));
        $response->assertStatus(200);
    }

    public function test_regular_user_can_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('trainings.index'));
        $response->assertStatus(200);
    }

    public function test_index_returns_trainings_with_filters(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->adminUser)->get(route('trainings.index', [
            'status' => 'draft',
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Trainings/Index')
            ->has('trainings.data', 1)
        );
    }

    // ==========================================
    // Store
    // ==========================================

    public function test_admin_can_create_training(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('trainings.store'), [
            'title' => 'Novo Treinamento',
            'event_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location' => 'Sala 1',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
            'evaluation_enabled' => true,
        ]);

        $response->assertRedirect(route('trainings.index'));
        $this->assertDatabaseHas('trainings', [
            'title' => 'Novo Treinamento',
            'status' => 'draft',
            'facilitator_id' => $this->facilitator->id,
        ]);
    }

    public function test_regular_user_cannot_create_training(): void
    {
        $response = $this->actingAs($this->regularUser)->post(route('trainings.store'), [
            'title' => 'Blocked',
            'event_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_validation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('trainings.store'), []);

        $response->assertSessionHasErrors(['title', 'event_date', 'start_time', 'end_time', 'facilitator_id', 'subject_id']);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('trainings.store'), [
            'title' => 'Teste',
            'event_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '10:00',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    // ==========================================
    // Show & Edit
    // ==========================================

    public function test_can_view_training_detail(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->adminUser)->get(route('trainings.show', $training));
        $response->assertStatus(200);
        $response->assertJsonStructure(['training', 'qrCodes']);
    }

    public function test_can_get_edit_data(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->adminUser)->get(route('trainings.edit', $training));
        $response->assertStatus(200);
        $response->assertJsonStructure(['training', 'facilitators', 'subjects', 'templates']);
    }

    // ==========================================
    // Update
    // ==========================================

    public function test_admin_can_update_training(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->adminUser)->put(route('trainings.update', $training), [
            'title' => 'Titulo Atualizado',
            'event_date' => $training->event_date->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertRedirect(route('trainings.index'));
        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'title' => 'Titulo Atualizado',
        ]);
    }

    public function test_cannot_update_completed_training(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_COMPLETED]);

        $response = $this->actingAs($this->adminUser)->put(route('trainings.update', $training), [
            'title' => 'Should Fail',
            'event_date' => $training->event_date->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ==========================================
    // Transitions
    // ==========================================

    public function test_can_transition_draft_to_published(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_DRAFT]);

        $response = $this->actingAs($this->adminUser)->post(route('trainings.transition', $training), [
            'status' => Training::STATUS_PUBLISHED,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => Training::STATUS_PUBLISHED,
        ]);
    }

    public function test_cannot_transition_from_completed(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_COMPLETED]);

        $response = $this->actingAs($this->adminUser)->post(route('trainings.transition', $training), [
            'status' => Training::STATUS_DRAFT,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_can_transition_published_to_in_progress(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->adminUser)->post(route('trainings.transition', $training), [
            'status' => Training::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => Training::STATUS_IN_PROGRESS,
        ]);
    }

    // ==========================================
    // Delete (soft)
    // ==========================================

    public function test_admin_can_soft_delete_training(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->adminUser)->delete(route('trainings.destroy', $training));

        $response->assertRedirect(route('trainings.index'));
        $this->assertNotNull($training->fresh()->deleted_at);
    }

    public function test_regular_user_cannot_delete_training(): void
    {
        $training = $this->createTraining();

        $response = $this->actingAs($this->regularUser)->delete(route('trainings.destroy', $training));
        $response->assertStatus(403);
    }

    // ==========================================
    // Participants
    // ==========================================

    public function test_can_add_participant(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->supportUser)->post(route('trainings.participants.store', $training), [
            'employee_id' => $this->employee->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_participants', [
            'training_id' => $training->id,
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_cannot_add_duplicate_participant(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_PUBLISHED]);

        TrainingParticipant::create([
            'training_id' => $training->id,
            'employee_id' => $this->employee->id,
            'participant_name' => $this->employee->name,
            'attendance_time' => now(),
        ]);

        $response = $this->actingAs($this->supportUser)->post(route('trainings.participants.store', $training), [
            'employee_id' => $this->employee->id,
        ]);

        $response->assertStatus(422);
    }

    // ==========================================
    // Evaluations
    // ==========================================

    public function test_can_submit_evaluation(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_IN_PROGRESS]);
        $participant = TrainingParticipant::create([
            'training_id' => $training->id,
            'employee_id' => $this->employee->id,
            'participant_name' => $this->employee->name,
            'attendance_time' => now(),
        ]);

        $response = $this->actingAs($this->supportUser)->post(
            route('trainings.evaluations.store', [$training, $participant]),
            ['rating' => 4, 'comment' => 'Muito bom']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_evaluations', [
            'training_id' => $training->id,
            'participant_id' => $participant->id,
            'rating' => 4,
        ]);
    }

    // ==========================================
    // Statistics
    // ==========================================

    public function test_can_get_statistics(): void
    {
        $this->createTraining();
        $this->createTraining(['status' => Training::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->adminUser)->get(route('trainings.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['total', 'by_status', 'upcoming', 'total_participants', 'avg_rating']);
    }

    // ==========================================
    // Facilitators CRUD
    // ==========================================

    public function test_admin_can_create_facilitator(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('trainings.facilitators.store'), [
            'name' => 'Novo Facilitador',
            'email' => 'novo@test.com',
            'external' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_facilitators', ['name' => 'Novo Facilitador', 'external' => true]);
    }

    // ==========================================
    // Subjects CRUD
    // ==========================================

    public function test_admin_can_create_subject(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('trainings.subjects.store'), [
            'name' => 'Novo Assunto',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_subjects', ['name' => 'Novo Assunto']);
    }

    // ==========================================
    // QR Codes
    // ==========================================

    public function test_qr_codes_not_available_for_draft(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_DRAFT]);

        $response = $this->actingAs($this->adminUser)->get(route('trainings.qr-codes', $training));

        $response->assertStatus(422);
    }

    public function test_qr_codes_available_for_published(): void
    {
        $training = $this->createTraining(['status' => Training::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->adminUser)->get(route('trainings.qr-codes', $training));

        $response->assertStatus(200);
        $response->assertJsonStructure(['attendance', 'evaluation']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createTraining(array $overrides = []): Training
    {
        return Training::create(array_merge([
            'title' => 'Treinamento Teste',
            'event_date' => now()->addWeek(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'location' => 'Sala 1',
            'facilitator_id' => $this->facilitator->id,
            'subject_id' => $this->subject->id,
            'status' => Training::STATUS_DRAFT,
            'certificate_template_id' => $this->template->id,
            'evaluation_enabled' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }
}
