<?php

namespace Tests\Feature;

use App\Models\TrainingContentCategory;
use App\Models\TrainingQuiz;
use App\Models\TrainingQuizAttempt;
use App\Models\TrainingQuizOption;
use App\Models\TrainingQuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TrainingQuizControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TrainingContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->category = TrainingContentCategory::first()
            ?? TrainingContentCategory::create(['name' => 'Teste', 'is_active' => true]);
    }

    // ==========================================
    // Index
    // ==========================================

    public function test_admin_can_list_quizzes(): void
    {
        $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->get(route('training-quizzes.index'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['quizzes']);
    }

    // ==========================================
    // Store
    // ==========================================

    public function test_admin_can_create_quiz_with_questions(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-quizzes.store'), [
            'title' => 'Quiz de Produto',
            'passing_score' => 70,
            'questions' => [
                [
                    'question_text' => 'Qual a cor do produto X?',
                    'question_type' => 'single',
                    'points' => 2,
                    'options' => [
                        ['option_text' => 'Azul', 'is_correct' => true],
                        ['option_text' => 'Verde', 'is_correct' => false],
                        ['option_text' => 'Vermelho', 'is_correct' => false],
                    ],
                ],
                [
                    'question_text' => 'O produto Y e importado?',
                    'question_type' => 'boolean',
                    'points' => 1,
                    'options' => [
                        ['option_text' => 'Verdadeiro', 'is_correct' => true],
                        ['option_text' => 'Falso', 'is_correct' => false],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_quizzes', ['title' => 'Quiz de Produto']);

        $quiz = TrainingQuiz::where('title', 'Quiz de Produto')->first();
        $this->assertEquals(2, $quiz->questions()->count());
        $this->assertEquals(3, $quiz->questions()->first()->options()->count());
    }

    public function test_support_cannot_create_quiz(): void
    {
        $response = $this->actingAs($this->supportUser)->post(route('training-quizzes.store'), [
            'title' => 'Blocked',
            'questions' => [
                [
                    'question_text' => 'Test?',
                    'question_type' => 'single',
                    'options' => [
                        ['option_text' => 'A', 'is_correct' => true],
                        ['option_text' => 'B', 'is_correct' => false],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_validation_requires_questions(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('training-quizzes.store'), [
            'title' => 'Quiz sem perguntas',
        ]);

        $response->assertSessionHasErrors('questions');
    }

    // ==========================================
    // Show
    // ==========================================

    public function test_can_view_quiz_detail(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->get(route('training-quizzes.show', $quiz));
        $response->assertStatus(200);
        $response->assertJsonStructure(['quiz' => ['id', 'title', 'questions'], 'stats']);
    }

    // ==========================================
    // Update
    // ==========================================

    public function test_admin_can_update_quiz(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->put(route('training-quizzes.update', $quiz), [
            'title' => 'Quiz Atualizado',
            'passing_score' => 80,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('training_quizzes', ['id' => $quiz->id, 'title' => 'Quiz Atualizado', 'passing_score' => 80]);
    }

    // ==========================================
    // Delete
    // ==========================================

    public function test_admin_can_soft_delete_quiz(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->delete(route('training-quizzes.destroy', $quiz));
        $response->assertStatus(200);
        $this->assertNotNull($quiz->fresh()->deleted_at);
    }

    // ==========================================
    // Quiz Taking
    // ==========================================

    public function test_user_can_start_quiz(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'attempt_id', 'quiz', 'questions']);
        $response->assertJsonPath('success', true);
    }

    public function test_cannot_start_inactive_quiz(): void
    {
        $quiz = $this->createQuizWithQuestions(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $response->assertStatus(422);
    }

    public function test_can_submit_quiz_and_pass(): void
    {
        $quiz = $this->createQuizWithQuestions(['passing_score' => 50]);
        $questions = $quiz->questions()->with('options')->get();

        // Start attempt
        $startResponse = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $attemptId = $startResponse->json('attempt_id');

        // Build correct answers
        $answers = $questions->map(function ($q) {
            $correctIds = $q->options->where('is_correct', true)->pluck('id')->toArray();

            return ['question_id' => $q->id, 'selected_options' => $correctIds];
        })->toArray();

        // Submit
        $response = $this->actingAs($this->adminUser)->post(route('training-quiz-attempts.submit', $attemptId), [
            'answers' => $answers,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('result.passed', true);
        $this->assertEquals(100, $response->json('result.score'));
    }

    public function test_can_submit_quiz_and_fail(): void
    {
        $quiz = $this->createQuizWithQuestions(['passing_score' => 100]);
        $questions = $quiz->questions()->with('options')->get();

        // Start
        $startResponse = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $attemptId = $startResponse->json('attempt_id');

        // Submit wrong answers
        $answers = $questions->map(function ($q) {
            $wrongIds = $q->options->where('is_correct', false)->pluck('id')->take(1)->toArray();

            return ['question_id' => $q->id, 'selected_options' => $wrongIds];
        })->toArray();

        $response = $this->actingAs($this->adminUser)->post(route('training-quiz-attempts.submit', $attemptId), [
            'answers' => $answers,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('result.passed', false);
    }

    public function test_cannot_submit_twice(): void
    {
        $quiz = $this->createQuizWithQuestions();
        $questions = $quiz->questions()->with('options')->get();

        $startResponse = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $attemptId = $startResponse->json('attempt_id');

        $answers = $questions->map(fn ($q) => [
            'question_id' => $q->id,
            'selected_options' => $q->options->where('is_correct', true)->pluck('id')->toArray(),
        ])->toArray();

        // First submit
        $this->actingAs($this->adminUser)->post(route('training-quiz-attempts.submit', $attemptId), ['answers' => $answers]);

        // Second submit
        $response = $this->actingAs($this->adminUser)->post(route('training-quiz-attempts.submit', $attemptId), ['answers' => $answers]);
        $response->assertStatus(422);
    }

    public function test_max_attempts_enforced(): void
    {
        $quiz = $this->createQuizWithQuestions(['max_attempts' => 1]);

        // Create a completed attempt
        TrainingQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $this->adminUser->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'completed_at' => now(),
            'score' => 50,
            'passed' => false,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $response->assertStatus(422);
    }

    // ==========================================
    // Review
    // ==========================================

    public function test_can_review_completed_attempt(): void
    {
        $quiz = $this->createQuizWithQuestions(['show_answers' => true]);
        $questions = $quiz->questions()->with('options')->get();

        $startResponse = $this->actingAs($this->adminUser)->post(route('training-quizzes.start', $quiz));
        $attemptId = $startResponse->json('attempt_id');

        $answers = $questions->map(fn ($q) => [
            'question_id' => $q->id,
            'selected_options' => $q->options->where('is_correct', true)->pluck('id')->toArray(),
        ])->toArray();

        $this->actingAs($this->adminUser)->post(route('training-quiz-attempts.submit', $attemptId), ['answers' => $answers]);

        $response = $this->actingAs($this->adminUser)->get(route('training-quiz-attempts.review', $attemptId));
        $response->assertStatus(200);
        $response->assertJsonStructure(['attempt', 'quiz', 'responses']);
    }

    public function test_cannot_review_other_users_attempt(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $attempt = TrainingQuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $this->adminUser->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'completed_at' => now(),
            'score' => 100,
            'passed' => true,
        ]);

        $response = $this->actingAs($this->supportUser)->get(route('training-quiz-attempts.review', $attempt));
        $response->assertStatus(403);
    }

    // ==========================================
    // Take Quiz Page
    // ==========================================

    public function test_can_view_take_quiz_page(): void
    {
        $quiz = $this->createQuizWithQuestions();

        $response = $this->actingAs($this->adminUser)->get(route('training-quizzes.take', $quiz));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Trainings/TakeQuiz'));
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createQuizWithQuestions(array $overrides = []): TrainingQuiz
    {
        $quiz = TrainingQuiz::create(array_merge([
            'title' => 'Quiz Teste',
            'passing_score' => 70,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));

        $q1 = TrainingQuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Qual a resposta?',
            'question_type' => 'single',
            'sort_order' => 0,
            'points' => 1,
        ]);
        TrainingQuizOption::create(['question_id' => $q1->id, 'option_text' => 'Correto', 'is_correct' => true, 'sort_order' => 0]);
        TrainingQuizOption::create(['question_id' => $q1->id, 'option_text' => 'Errado', 'is_correct' => false, 'sort_order' => 1]);

        $q2 = TrainingQuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Verdadeiro ou falso?',
            'question_type' => 'boolean',
            'sort_order' => 1,
            'points' => 1,
        ]);
        TrainingQuizOption::create(['question_id' => $q2->id, 'option_text' => 'Verdadeiro', 'is_correct' => true, 'sort_order' => 0]);
        TrainingQuizOption::create(['question_id' => $q2->id, 'option_text' => 'Falso', 'is_correct' => false, 'sort_order' => 1]);

        return $quiz;
    }
}
