<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\TrainingContent;
use App\Models\TrainingContentCategory;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseContent;
use App\Models\TrainingCourseEnrollment;
use App\Models\TrainingFacilitator;
use App\Models\TrainingSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TrainingCourseControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TrainingFacilitator $facilitator;

    protected TrainingSubject $subject;

    protected TrainingContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->facilitator = TrainingFacilitator::create([
            'name' => 'Carlos Mentor', 'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->subject = TrainingSubject::create([
            'name' => 'Produto', 'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->category = TrainingContentCategory::first()
            ?? TrainingContentCategory::create(['name' => 'Teste', 'is_active' => true]);
    }

    // ==========================================
    // Index
    // ==========================================

    public function test_admin_can_list_courses(): void
    {
        $this->createCourse();

        $response = $this->actingAs($this->adminUser)->get(route('training-courses.index'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['courses', 'statusOptions', 'statusCounts']);
    }

    public function test_user_can_list_courses(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('training-courses.index'));
        $response->assertStatus(200);
    }

    // ==========================================
    // Store
    // ==========================================

    public function test_admin_can_create_course(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-courses.store'), [
            'title' => 'Novo Curso',
            'description' => 'Descricao do curso',
            'visibility' => 'public',
            'subject_id' => $this->subject->id,
            'facilitator_id' => $this->facilitator->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_courses', [
            'title' => 'Novo Curso',
            'status' => 'draft',
            'visibility' => 'public',
        ]);
    }

    public function test_regular_user_cannot_create_course(): void
    {
        $response = $this->actingAs($this->regularUser)->post(route('training-courses.store'), [
            'title' => 'Blocked',
            'visibility' => 'public',
        ]);
        $response->assertStatus(403);
    }

    public function test_validation_requires_title_and_visibility(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-courses.store'), []);
        $response->assertSessionHasErrors(['title', 'visibility']);
    }

    // ==========================================
    // Show & Update
    // ==========================================

    public function test_can_view_course_detail(): void
    {
        $course = $this->createCourse();

        $response = $this->actingAs($this->adminUser)->get(route('training-courses.show', $course));
        $response->assertStatus(200);
        $response->assertJsonStructure(['course' => ['id', 'title', 'status', 'contents', 'enrollments']]);
    }

    public function test_admin_can_update_course(): void
    {
        $course = $this->createCourse();

        $response = $this->actingAs($this->adminUser)->put(route('training-courses.update', $course), [
            'title' => 'Titulo Atualizado',
            'visibility' => 'private',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_courses', ['id' => $course->id, 'title' => 'Titulo Atualizado']);
    }

    // ==========================================
    // Delete
    // ==========================================

    public function test_admin_can_soft_delete_course(): void
    {
        $course = $this->createCourse();

        $response = $this->actingAs($this->adminUser)->delete(route('training-courses.destroy', $course));
        $response->assertStatus(200);
        $this->assertNotNull($course->fresh()->deleted_at);
    }

    public function test_user_cannot_delete_course(): void
    {
        $course = $this->createCourse();

        $response = $this->actingAs($this->regularUser)->delete(route('training-courses.destroy', $course));
        $response->assertStatus(403);
    }

    // ==========================================
    // Transitions
    // ==========================================

    public function test_can_transition_draft_to_published(): void
    {
        $course = $this->createCourse(['status' => 'draft']);

        $response = $this->actingAs($this->adminUser)->post(route('training-courses.transition', $course), [
            'status' => 'published',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_courses', ['id' => $course->id, 'status' => 'published']);
        $this->assertNotNull($course->fresh()->published_at);
    }

    public function test_cannot_make_invalid_transition(): void
    {
        $course = $this->createCourse(['status' => 'draft']);

        $response = $this->actingAs($this->adminUser)->post(route('training-courses.transition', $course), [
            'status' => 'archived',
        ]);

        $response->assertStatus(422);
    }

    // ==========================================
    // Content management
    // ==========================================

    public function test_can_manage_course_contents(): void
    {
        $course = $this->createCourse();
        $content1 = $this->createContent('Video 1');
        $content2 = $this->createContent('Video 2');

        $response = $this->actingAs($this->adminUser)->post(route('training-courses.contents', $course), [
            'contents' => [
                ['content_id' => $content1->id, 'sort_order' => 0, 'is_required' => true],
                ['content_id' => $content2->id, 'sort_order' => 1, 'is_required' => false],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $course->courseContents()->count());
    }

    // ==========================================
    // Visibility management
    // ==========================================

    public function test_can_manage_visibility(): void
    {
        $course = $this->createCourse(['visibility' => 'private']);

        $response = $this->actingAs($this->adminUser)->post(route('training-courses.visibility', $course), [
            'rules' => [
                ['target_type' => 'role', 'target_id' => 'admin'],
                ['target_type' => 'role', 'target_id' => 'support'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $course->visibilityRules()->count());
    }

    // ==========================================
    // Enrollment
    // ==========================================

    public function test_user_can_enroll_in_public_course(): void
    {
        $course = $this->createCourse(['status' => 'published', 'visibility' => 'public']);

        $response = $this->actingAs($this->regularUser)->post(route('training-courses.enroll', $course));

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_course_enrollments', [
            'course_id' => $course->id,
            'user_id' => $this->regularUser->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_enrollment_is_idempotent(): void
    {
        $course = $this->createCourse(['status' => 'published', 'visibility' => 'public']);

        $this->actingAs($this->regularUser)->post(route('training-courses.enroll', $course));
        $this->actingAs($this->regularUser)->post(route('training-courses.enroll', $course));

        $this->assertEquals(1, TrainingCourseEnrollment::where('course_id', $course->id)->where('user_id', $this->regularUser->id)->count());
    }

    public function test_cannot_enroll_in_draft_course(): void
    {
        $course = $this->createCourse(['status' => 'draft', 'visibility' => 'public']);

        $response = $this->actingAs($this->regularUser)->post(route('training-courses.enroll', $course));
        $response->assertStatus(403);
    }

    // ==========================================
    // My Trainings
    // ==========================================

    public function test_can_view_my_trainings(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('my-trainings.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Trainings/MyTrainings'));
    }

    // ==========================================
    // Progress
    // ==========================================

    public function test_can_save_progress(): void
    {
        $content = $this->createContent('Test Content');
        $course = $this->createCourse(['status' => 'published', 'visibility' => 'public']);
        TrainingCourseContent::create(['course_id' => $course->id, 'content_id' => $content->id, 'sort_order' => 0]);
        TrainingCourseEnrollment::create(['course_id' => $course->id, 'user_id' => $this->adminUser->id, 'status' => 'in_progress']);

        $response = $this->actingAs($this->adminUser)->post(route('training-contents.progress', $content), [
            'course_id' => $course->id,
            'progress_percent' => 50,
            'position_seconds' => 120,
            'time_spent' => 30,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_content_progress', [
            'user_id' => $this->adminUser->id,
            'content_id' => $content->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_can_mark_complete(): void
    {
        $content = $this->createContent('Test');
        $course = $this->createCourse(['status' => 'published', 'visibility' => 'public']);
        TrainingCourseContent::create(['course_id' => $course->id, 'content_id' => $content->id, 'sort_order' => 0, 'is_required' => true]);
        TrainingCourseEnrollment::create(['course_id' => $course->id, 'user_id' => $this->adminUser->id, 'status' => 'in_progress']);

        $response = $this->actingAs($this->adminUser)->post(route('training-contents.complete', $content), [
            'course_id' => $course->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('completed', true);
    }

    // ==========================================
    // Reports
    // ==========================================

    public function test_can_view_reports_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('training-reports.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Trainings/Reports'));
    }

    public function test_can_fetch_reports_json(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('training-reports.index', ['type' => 'overview']));
        $response->assertStatus(200);
        $response->assertJsonStructure(['total_courses', 'published_courses', 'total_enrollments']);
    }

    public function test_can_fetch_report_by_course(): void
    {
        $this->createCourse();

        $response = $this->actingAs($this->adminUser)->getJson(route('training-reports.index', ['type' => 'by-course']));
        $response->assertStatus(200);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createCourse(array $overrides = []): TrainingCourse
    {
        return TrainingCourse::create(array_merge([
            'title' => 'Curso Teste',
            'visibility' => 'public',
            'status' => TrainingCourse::STATUS_DRAFT,
            'subject_id' => $this->subject->id,
            'facilitator_id' => $this->facilitator->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    private function createContent(string $title = 'Conteudo Teste'): TrainingContent
    {
        return TrainingContent::create([
            'title' => $title,
            'content_type' => 'text',
            'text_content' => '<p>Teste</p>',
            'category_id' => $this->category->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }
}
