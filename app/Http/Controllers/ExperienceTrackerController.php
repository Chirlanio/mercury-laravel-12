<?php

namespace App\Http\Controllers;

use App\Jobs\SendExperienceNotificationJob;
use App\Models\ExperienceEvaluation;
use App\Models\ExperienceNotification;
use App\Models\ExperienceQuestion;
use App\Models\ExperienceResponse;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExperienceTrackerController extends Controller
{
    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = ExperienceEvaluation::with(['employee', 'manager', 'store'])
            ->latest();

        if ($request->filled('milestone')) {
            $query->forMilestone($request->milestone);
        }
        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'pending' => $query->pending(),
                'completed' => $query->fullyCompleted(),
                'overdue' => $query->overdue(),
                default => null,
            };
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $evaluations = $query->paginate(15)->through(fn ($eval) => $this->formatEvaluation($eval));

        return Inertia::render('ExperienceTracker/Index', [
            'evaluations' => $evaluations,
            'filters' => $request->only(['search', 'milestone', 'store_id', 'status']),
            'milestoneOptions' => ExperienceEvaluation::MILESTONE_LABELS,
            'stores' => Store::orderBy('name')->get(['id', 'name', 'code']),
            'stats' => $this->getQuickStats(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'manager_id' => 'required|exists:users,id',
            'store_id' => 'required|string|max:10',
            'milestone' => 'required|string|in:45,90',
            'date_admission' => 'required|date',
            'milestone_date' => 'required|date|after_or_equal:date_admission',
        ]);

        $validated['employee_token'] = Str::random(64);

        $evaluation = ExperienceEvaluation::create($validated);

        SendExperienceNotificationJob::dispatch($evaluation->id, ExperienceNotification::TYPE_CREATED);

        return redirect()->route('experience-tracker.index')
            ->with('success', 'Avaliacao criada com sucesso.');
    }

    public function show(ExperienceEvaluation $experienceTracker)
    {
        $experienceTracker->load([
            'employee', 'manager', 'store',
            'responses.question',
            'notifications',
        ]);

        $managerQuestions = ExperienceQuestion::active()
            ->forMilestone($experienceTracker->milestone)
            ->forFormType('manager')
            ->ordered()
            ->get();

        $employeeQuestions = ExperienceQuestion::active()
            ->forMilestone($experienceTracker->milestone)
            ->forFormType('employee')
            ->ordered()
            ->get();

        return response()->json([
            'evaluation' => $this->formatEvaluationDetail($experienceTracker),
            'managerQuestions' => $managerQuestions,
            'employeeQuestions' => $employeeQuestions,
            'managerResponses' => $experienceTracker->managerResponses->keyBy('question_id'),
            'employeeResponses' => $experienceTracker->employeeResponses->keyBy('question_id'),
        ]);
    }

    // ==========================================
    // Manager fill
    // ==========================================

    public function fillManager(Request $request, ExperienceEvaluation $experienceTracker)
    {
        $questions = ExperienceQuestion::active()
            ->forMilestone($experienceTracker->milestone)
            ->forFormType('manager')
            ->get();

        $validated = $request->validate(
            $questions->mapWithKeys(function ($q) {
                $rules = match ($q->question_type) {
                    'rating' => ['required', 'integer', 'min:1', 'max:5'],
                    'text' => ['required', 'string', 'max:2000'],
                    'yes_no' => ['required', 'boolean'],
                    default => ['nullable'],
                };

                return ["response_{$q->id}" => $rules];
            })->toArray()
        );

        foreach ($questions as $question) {
            $key = "response_{$question->id}";
            $value = $validated[$key] ?? null;

            ExperienceResponse::updateOrCreate(
                ['evaluation_id' => $experienceTracker->id, 'question_id' => $question->id, 'form_type' => 'manager'],
                [
                    'response_text' => $question->question_type === 'text' ? $value : null,
                    'rating_value' => $question->question_type === 'rating' ? $value : null,
                    'yes_no_value' => $question->question_type === 'yes_no' ? $value : null,
                ]
            );
        }

        // Handle recommendation (90-day milestone)
        if ($experienceTracker->milestone === ExperienceEvaluation::MILESTONE_90) {
            $recommendationQuestion = $questions->firstWhere('question_type', 'yes_no');
            if ($recommendationQuestion) {
                $experienceTracker->recommendation = $validated["response_{$recommendationQuestion->id}"]
                    ? ExperienceEvaluation::RECOMMENDATION_YES
                    : ExperienceEvaluation::RECOMMENDATION_NO;
            }
        }

        $experienceTracker->update([
            'manager_status' => ExperienceEvaluation::STATUS_COMPLETED,
            'manager_completed_at' => now(),
            'recommendation' => $experienceTracker->recommendation,
        ]);

        return response()->json(['message' => 'Avaliacao do gestor salva com sucesso.']);
    }

    // ==========================================
    // Public employee form (no auth)
    // ==========================================

    public function publicForm(string $token)
    {
        $evaluation = ExperienceEvaluation::where('employee_token', $token)->firstOrFail();

        if ($evaluation->employee_status === ExperienceEvaluation::STATUS_COMPLETED) {
            return Inertia::render('ExperienceTracker/PublicForm', [
                'evaluation' => null,
                'alreadyCompleted' => true,
            ]);
        }

        $questions = ExperienceQuestion::active()
            ->forMilestone($evaluation->milestone)
            ->forFormType('employee')
            ->ordered()
            ->get();

        $evaluation->load('employee');

        return Inertia::render('ExperienceTracker/PublicForm', [
            'evaluation' => [
                'id' => $evaluation->id,
                'token' => $evaluation->employee_token,
                'employee_name' => $evaluation->employee->name ?? '-',
                'milestone' => $evaluation->milestone,
                'milestone_label' => $evaluation->milestone_label,
            ],
            'questions' => $questions,
            'alreadyCompleted' => false,
        ]);
    }

    public function publicSubmit(Request $request, string $token)
    {
        $evaluation = ExperienceEvaluation::where('employee_token', $token)->firstOrFail();

        if ($evaluation->employee_status === ExperienceEvaluation::STATUS_COMPLETED) {
            return response()->json(['error' => 'Avaliacao ja respondida.'], 422);
        }

        $questions = ExperienceQuestion::active()
            ->forMilestone($evaluation->milestone)
            ->forFormType('employee')
            ->get();

        $validated = $request->validate(
            $questions->mapWithKeys(function ($q) {
                $rules = match ($q->question_type) {
                    'rating' => ['required', 'integer', 'min:1', 'max:5'],
                    'text' => ['required', 'string', 'max:2000'],
                    'yes_no' => ['required', 'boolean'],
                    default => ['nullable'],
                };

                return ["response_{$q->id}" => $rules];
            })->toArray()
        );

        foreach ($questions as $question) {
            $key = "response_{$question->id}";
            $value = $validated[$key] ?? null;

            ExperienceResponse::updateOrCreate(
                ['evaluation_id' => $evaluation->id, 'question_id' => $question->id, 'form_type' => 'employee'],
                [
                    'response_text' => $question->question_type === 'text' ? $value : null,
                    'rating_value' => $question->question_type === 'rating' ? $value : null,
                    'yes_no_value' => $question->question_type === 'yes_no' ? $value : null,
                ]
            );
        }

        $evaluation->update([
            'employee_status' => ExperienceEvaluation::STATUS_COMPLETED,
            'employee_completed_at' => now(),
        ]);

        return response()->json(['message' => 'Obrigado pela sua avaliacao!']);
    }

    // ==========================================
    // Statistics
    // ==========================================

    public function statistics()
    {
        return response()->json($this->getDetailedStats());
    }

    public function compliance()
    {
        $data = ExperienceEvaluation::with('store')
            ->selectRaw(
                'store_id, milestone,
                 SUM(CASE WHEN manager_status = ? AND employee_status = ? THEN 1 ELSE 0 END) as completed,
                 SUM(CASE WHEN milestone_date < CURDATE() AND (manager_status = ? OR employee_status = ?) THEN 1 ELSE 0 END) as overdue,
                 COUNT(*) as total',
                ['completed', 'completed', 'pending', 'pending']
            )
            ->groupBy('store_id', 'milestone')
            ->get()
            ->map(fn ($row) => [
                'store_id' => $row->store_id,
                'store_name' => $row->store?->name ?? $row->store_id,
                'milestone' => $row->milestone,
                'completed' => (int) $row->completed,
                'overdue' => (int) $row->overdue,
                'total' => (int) $row->total,
                'fill_rate' => $row->total > 0 ? round(($row->completed / $row->total) * 100, 1) : 0,
            ]);

        return response()->json(['compliance' => $data]);
    }

    public function evolution()
    {
        // Funcionários que têm AMBAS as avaliações (45 + 90 dias)
        $employees = ExperienceEvaluation::with(['employee', 'store', 'responses.question'])
            ->fullyCompleted()
            ->get()
            ->groupBy('employee_id')
            ->filter(fn ($evals) => $evals->where('milestone', '45')->isNotEmpty() && $evals->where('milestone', '90')->isNotEmpty())
            ->map(function ($evals) {
                $eval45 = $evals->firstWhere('milestone', '45');
                $eval90 = $evals->firstWhere('milestone', '90');

                $avgRating = fn ($eval) => $eval->responses
                    ->whereNotNull('rating_value')
                    ->avg('rating_value');

                $avg45 = round($avgRating($eval45) ?? 0, 1);
                $avg90 = round($avgRating($eval90) ?? 0, 1);

                return [
                    'employee_name' => $eval45->employee?->name ?? '-',
                    'store_name' => $eval45->store?->name ?? '-',
                    'avg_45' => $avg45,
                    'avg_90' => $avg90,
                    'variation' => $avg45 > 0 ? round($avg90 - $avg45, 1) : null,
                    'recommendation' => $eval90->recommendation,
                ];
            })
            ->values();

        return response()->json(['evolution' => $employees]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function getQuickStats(): array
    {
        return [
            'total_pending' => ExperienceEvaluation::pending()->count(),
            'near_deadline' => ExperienceEvaluation::nearDeadline()->count(),
            'overdue' => ExperienceEvaluation::overdue()->count(),
            'completed_month' => ExperienceEvaluation::fullyCompleted()
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
        ];
    }

    private function getDetailedStats(): array
    {
        $base = $this->getQuickStats();

        // Compliance by store
        $compliance = ExperienceEvaluation::selectRaw(
            'store_id, milestone,
             SUM(CASE WHEN manager_status = ? AND employee_status = ? THEN 1 ELSE 0 END) as completed,
             COUNT(*) as total',
            ['completed', 'completed']
        )
            ->groupBy('store_id', 'milestone')
            ->get()
            ->map(fn ($row) => [
                'store_id' => $row->store_id,
                'milestone' => $row->milestone,
                'completed' => $row->completed,
                'total' => $row->total,
                'fill_rate' => $row->total > 0 ? round(($row->completed / $row->total) * 100, 1) : 0,
            ]);

        // Hiring recommendations (90 days)
        $hiring = ExperienceEvaluation::forMilestone('90')
            ->whereNotNull('recommendation')
            ->selectRaw(
                'SUM(CASE WHEN recommendation = ? THEN 1 ELSE 0 END) as recommended,
                 SUM(CASE WHEN recommendation = ? THEN 1 ELSE 0 END) as not_recommended,
                 COUNT(*) as total',
                ['yes', 'no']
            )
            ->first();

        return array_merge($base, [
            'compliance' => $compliance,
            'hiring' => [
                'recommended' => $hiring->recommended ?? 0,
                'not_recommended' => $hiring->not_recommended ?? 0,
                'total' => $hiring->total ?? 0,
                'hire_rate' => ($hiring->total ?? 0) > 0
                    ? round((($hiring->recommended ?? 0) / $hiring->total) * 100, 1)
                    : 0,
            ],
        ]);
    }

    private function formatEvaluation(ExperienceEvaluation $eval): array
    {
        return [
            'id' => $eval->id,
            'employee' => $eval->employee ? [
                'id' => $eval->employee->id,
                'name' => $eval->employee->name,
            ] : null,
            'manager' => $eval->manager ? [
                'id' => $eval->manager->id,
                'name' => $eval->manager->name,
            ] : null,
            'store_id' => $eval->store_id,
            'store_name' => $eval->store?->name,
            'milestone' => $eval->milestone,
            'milestone_label' => $eval->milestone_label,
            'date_admission' => $eval->date_admission->format('d/m/Y'),
            'milestone_date' => $eval->milestone_date->format('d/m/Y'),
            'manager_status' => $eval->manager_status,
            'employee_status' => $eval->employee_status,
            'overall_status' => $eval->overall_status,
            'overall_status_label' => $eval->overall_status_label,
            'overall_status_color' => $eval->overall_status_color,
            'recommendation' => $eval->recommendation,
            'is_overdue' => $eval->is_overdue,
            'is_near_deadline' => $eval->is_near_deadline,
            'created_at' => $eval->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatEvaluationDetail(ExperienceEvaluation $eval): array
    {
        return array_merge($this->formatEvaluation($eval), [
            'employee_token' => $eval->employee_token,
            'manager_completed_at' => $eval->manager_completed_at?->format('d/m/Y H:i'),
            'employee_completed_at' => $eval->employee_completed_at?->format('d/m/Y H:i'),
            'responses' => $eval->responses->map(fn ($r) => [
                'question_id' => $r->question_id,
                'form_type' => $r->form_type,
                'display_value' => $r->display_value,
                'response_text' => $r->response_text,
                'rating_value' => $r->rating_value,
                'yes_no_value' => $r->yes_no_value,
            ]),
        ]);
    }
}
