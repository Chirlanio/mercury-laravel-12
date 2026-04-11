<?php

namespace App\Http\Controllers;

use App\Models\TrainingQuiz;
use App\Models\TrainingQuizAttempt;
use App\Models\TrainingQuizOption;
use App\Models\TrainingQuizQuestion;
use App\Models\TrainingQuizResponse;
use App\Services\TrainingQuizService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingQuizController extends Controller
{
    public function __construct(
        private TrainingQuizService $quizService,
    ) {}

    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = TrainingQuiz::with(['content', 'course', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $quizzes = $query->paginate(15)->through(fn ($quiz) => $this->formatQuiz($quiz));

        return response()->json([
            'quizzes' => $quizzes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content_id' => 'nullable|exists:training_contents,id',
            'course_id' => 'nullable|exists:training_courses,id',
            'passing_score' => 'integer|min:1|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'show_answers' => 'boolean',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|in:single,multiple,boolean,open_text',
            'questions.*.points' => 'integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'boolean',
        ]);

        $quiz = TrainingQuiz::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content_id' => $validated['content_id'] ?? null,
            'course_id' => $validated['course_id'] ?? null,
            'passing_score' => $validated['passing_score'] ?? 70,
            'max_attempts' => $validated['max_attempts'] ?? null,
            'show_answers' => $validated['show_answers'] ?? false,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->saveQuestions($quiz, $validated['questions']);

        return response()->json([
            'quiz' => $this->formatQuiz($quiz->load(['content', 'course'])),
            'message' => 'Quiz criado com sucesso.',
        ]);
    }

    public function show(TrainingQuiz $trainingQuiz)
    {
        $trainingQuiz->load(['content', 'course', 'questions.options', 'createdBy']);

        $attemptStats = [
            'total' => $trainingQuiz->attempts()->completed()->count(),
            'passed' => $trainingQuiz->attempts()->completed()->where('passed', true)->count(),
            'avg_score' => round($trainingQuiz->attempts()->completed()->avg('score') ?? 0, 1),
        ];

        return response()->json([
            'quiz' => $this->formatQuizDetail($trainingQuiz),
            'stats' => $attemptStats,
        ]);
    }

    public function update(Request $request, TrainingQuiz $trainingQuiz)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_score' => 'integer|min:1|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'show_answers' => 'boolean',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'questions' => 'sometimes|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|in:single,multiple,boolean,open_text',
            'questions.*.points' => 'integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'boolean',
        ]);

        $trainingQuiz->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'passing_score' => $validated['passing_score'] ?? $trainingQuiz->passing_score,
            'max_attempts' => $validated['max_attempts'] ?? null,
            'show_answers' => $validated['show_answers'] ?? $trainingQuiz->show_answers,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'is_active' => $validated['is_active'] ?? $trainingQuiz->is_active,
        ]);

        if (isset($validated['questions'])) {
            $this->saveQuestions($trainingQuiz, $validated['questions']);
        }

        return response()->json([
            'quiz' => $this->formatQuiz($trainingQuiz->load(['content', 'course'])),
            'message' => 'Quiz atualizado com sucesso.',
        ]);
    }

    public function destroy(TrainingQuiz $trainingQuiz)
    {
        $trainingQuiz->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
        ]);

        return response()->json(['message' => 'Quiz excluido com sucesso.']);
    }

    // ==========================================
    // Quiz taking
    // ==========================================

    public function start(TrainingQuiz $trainingQuiz)
    {
        $result = $this->quizService->startAttempt($trainingQuiz, auth()->user());

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function submit(Request $request, TrainingQuizAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.selected_options' => 'nullable|array',
            'answers.*.selected_options.*' => 'integer',
            'answers.*.response_text' => 'nullable|string|max:5000',
        ]);

        $result = $this->quizService->submitAttempt($attempt, $validated['answers']);

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result);
    }

    public function review(TrainingQuizAttempt $attempt)
    {
        if ($attempt->user_id !== auth()->id()) {
            return response()->json(['error' => 'Acesso negado.'], 403);
        }

        $review = $this->quizService->getAttemptReview($attempt);

        if (! $review) {
            return response()->json(['error' => 'Tentativa não finalizada.'], 422);
        }

        return response()->json($review);
    }

    // ==========================================
    // Grading (open_text responses)
    // ==========================================

    public function ungradedResponses(TrainingQuiz $trainingQuiz)
    {
        return response()->json([
            'attempts' => $this->quizService->getUngradedAttempts($trainingQuiz->id),
            'quiz_title' => $trainingQuiz->title,
        ]);
    }

    public function gradeResponse(Request $request, TrainingQuizResponse $response)
    {
        $validated = $request->validate([
            'points_earned' => 'required|integer|min:0',
            'feedback' => 'nullable|string|max:2000',
        ]);

        $result = $this->quizService->gradeResponse(
            $response,
            $validated['points_earned'],
            $validated['feedback'] ?? null,
            auth()->id(),
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json($result);
    }

    // ==========================================
    // Take Quiz page
    // ==========================================

    public function take(TrainingQuiz $trainingQuiz)
    {
        return Inertia::render('Trainings/TakeQuiz', [
            'quiz' => [
                'id' => $trainingQuiz->id,
                'title' => $trainingQuiz->title,
                'description' => $trainingQuiz->description,
                'passing_score' => $trainingQuiz->passing_score,
                'time_limit_minutes' => $trainingQuiz->time_limit_minutes,
                'show_answers' => $trainingQuiz->show_answers,
            ],
        ]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function saveQuestions(TrainingQuiz $quiz, array $questions): void
    {
        // Delete existing questions (cascade deletes options)
        $quiz->questions()->delete();

        foreach ($questions as $index => $q) {
            $question = TrainingQuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'],
                'sort_order' => $index,
                'points' => $q['points'] ?? 1,
                'explanation' => $q['explanation'] ?? null,
            ]);

            // Perguntas abertas não têm opções
            if (! empty($q['options'])) {
                foreach ($q['options'] as $optIndex => $opt) {
                    TrainingQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'is_correct' => $opt['is_correct'] ?? false,
                        'sort_order' => $optIndex,
                    ]);
                }
            }
        }
    }

    private function formatQuiz(TrainingQuiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'passing_score' => $quiz->passing_score,
            'max_attempts' => $quiz->max_attempts,
            'show_answers' => $quiz->show_answers,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'is_active' => $quiz->is_active,
            'question_count' => $quiz->question_count,
            'total_points' => $quiz->total_points,
            'content' => $quiz->content ? ['id' => $quiz->content->id, 'title' => $quiz->content->title] : null,
            'course' => $quiz->course ? ['id' => $quiz->course->id, 'title' => $quiz->course->title] : null,
            'created_by' => $quiz->createdBy?->name,
            'created_at' => $quiz->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatQuizDetail(TrainingQuiz $quiz): array
    {
        return array_merge($this->formatQuiz($quiz), [
            'questions' => $quiz->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
                'type_label' => TrainingQuizQuestion::TYPE_LABELS[$q->question_type] ?? $q->question_type,
                'sort_order' => $q->sort_order,
                'points' => $q->points,
                'explanation' => $q->explanation,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    'is_correct' => $o->is_correct,
                    'sort_order' => $o->sort_order,
                ]),
            ]),
        ]);
    }
}
