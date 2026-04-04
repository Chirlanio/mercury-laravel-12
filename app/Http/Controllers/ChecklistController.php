<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Checklist;
use App\Models\ChecklistAnswer;
use App\Models\ChecklistArea;
use App\Models\ChecklistQuestion;
use App\Models\Employee;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ChecklistController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $storeId = $request->get('store_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $perPage = $request->get('per_page', 15);
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $query = Checklist::query()
            ->with(['store', 'applicator', 'createdBy']);

        // Store filtering for non-admin users
        $user = $request->user();
        if (!in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $query->forStore($store->id);
                }
            }
        } elseif ($storeId) {
            $query->forStore((int) $storeId);
        }

        if ($status) {
            $query->forStatus($status);
        }

        if ($dateFrom || $dateTo) {
            $query->forDateRange($dateFrom, $dateTo);
        }

        if ($search) {
            $query->whereHas('store', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'status', 'score_percentage'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $checklists = $query->paginate($perPage)->through(function ($checklist) {
            $totalAnswers = $checklist->answers()->count();
            $answeredCount = $checklist->answers()->where('answer_status', '!=', 'pending')->count();

            return [
                'id' => $checklist->id,
                'store' => $checklist->store ? [
                    'id' => $checklist->store->id,
                    'name' => $checklist->store->name,
                    'code' => $checklist->store->code,
                ] : null,
                'applicator' => $checklist->applicator ? [
                    'id' => $checklist->applicator->id,
                    'name' => $checklist->applicator->name,
                ] : null,
                'status' => $checklist->status,
                'score_percentage' => $checklist->score_percentage,
                'performance' => $checklist->score_percentage !== null
                    ? Checklist::getPerformanceLabel((float) $checklist->score_percentage)
                    : null,
                'progress' => [
                    'answered' => $answeredCount,
                    'total' => $totalAnswers,
                ],
                'started_at' => $checklist->started_at?->format('d/m/Y H:i'),
                'completed_at' => $checklist->completed_at?->format('d/m/Y H:i'),
                'created_at' => $checklist->created_at->format('d/m/Y H:i'),
                'created_by' => $checklist->createdBy?->name,
            ];
        });

        // Statistics summary
        $statsQuery = Checklist::query();
        if (!in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if ($user->employee && $user->employee->store_id) {
                $store = Store::where('code', $user->employee->store_id)->first();
                if ($store) {
                    $statsQuery->forStore($store->id);
                }
            }
        }

        $stats = [
            'total' => $statsQuery->count(),
            'pending' => (clone $statsQuery)->forStatus('pending')->count(),
            'in_progress' => (clone $statsQuery)->forStatus('in_progress')->count(),
            'completed' => (clone $statsQuery)->forStatus('completed')->count(),
            'avg_score' => round((clone $statsQuery)->whereNotNull('score_percentage')->avg('score_percentage') ?? 0, 1),
        ];

        $stores = in_array($user->role, [Role::ADMIN, Role::SUPER_ADMIN])
            ? Store::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return Inertia::render('Checklists/Index', [
            'checklists' => $checklists,
            'stats' => $stats,
            'stores' => $stores,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'store_id' => $storeId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $activeQuestions = ChecklistQuestion::active()
            ->with('area')
            ->whereHas('area', fn ($q) => $q->active())
            ->get();

        if ($activeQuestions->isEmpty()) {
            return back()->withErrors(['store_id' => 'Não há perguntas ativas cadastradas para criar um checklist.']);
        }

        $checklist = DB::transaction(function () use ($request, $activeQuestions) {
            $checklist = Checklist::create([
                'store_id' => $request->store_id,
                'applicator_user_id' => auth()->id(),
                'status' => 'pending',
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            $answers = $activeQuestions->map(fn ($question) => [
                'checklist_id' => $checklist->id,
                'checklist_question_id' => $question->id,
                'answer_status' => 'pending',
                'score' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            ChecklistAnswer::insert($answers);

            return $checklist;
        });

        return redirect()->route('checklists.index')
            ->with('success', 'Checklist criado com sucesso.');
    }

    public function show(Checklist $checklist)
    {
        $checklist->load(['store', 'applicator', 'createdBy', 'updatedBy']);

        $answers = $checklist->answers()
            ->with(['question.area', 'responsibleEmployee'])
            ->get()
            ->map(function ($answer) {
                return [
                    'id' => $answer->id,
                    'question' => [
                        'id' => $answer->question->id,
                        'description' => $answer->question->description,
                        'points' => $answer->question->points,
                        'area' => [
                            'id' => $answer->question->area->id,
                            'name' => $answer->question->area->name,
                        ],
                    ],
                    'answer_status' => $answer->answer_status,
                    'score' => $answer->score,
                    'justification' => $answer->justification,
                    'action_plan' => $answer->action_plan,
                    'responsible_employee' => $answer->responsibleEmployee ? [
                        'id' => $answer->responsibleEmployee->id,
                        'name' => $answer->responsibleEmployee->name,
                    ] : null,
                    'deadline_date' => $answer->deadline_date?->format('Y-m-d'),
                ];
            });

        // Group answers by area
        $answersByArea = $answers->groupBy('question.area.id')->map(function ($areaAnswers) {
            $area = $areaAnswers->first()['question']['area'];
            return [
                'area' => $area,
                'answers' => $areaAnswers->values()->all(),
            ];
        })->values()->all();

        $statistics = $checklist->calculateStatistics();

        return response()->json([
            'checklist' => [
                'id' => $checklist->id,
                'store' => $checklist->store ? [
                    'id' => $checklist->store->id,
                    'name' => $checklist->store->name,
                    'code' => $checklist->store->code,
                ] : null,
                'applicator' => $checklist->applicator ? [
                    'id' => $checklist->applicator->id,
                    'name' => $checklist->applicator->name,
                ] : null,
                'status' => $checklist->status,
                'score_percentage' => $checklist->score_percentage,
                'started_at' => $checklist->started_at?->format('d/m/Y H:i'),
                'completed_at' => $checklist->completed_at?->format('d/m/Y H:i'),
                'created_at' => $checklist->created_at->format('d/m/Y H:i'),
                'created_by' => $checklist->createdBy?->name,
            ],
            'answers_by_area' => $answersByArea,
            'statistics' => $statistics,
        ]);
    }

    public function destroy(Checklist $checklist)
    {
        if ($checklist->status !== 'pending') {
            return back()->withErrors(['general' => 'Apenas checklists pendentes podem ser excluídos.']);
        }

        $checklist->delete();

        return redirect()->route('checklists.index')
            ->with('success', 'Checklist excluído com sucesso.');
    }

    public function updateAnswer(Request $request, Checklist $checklist, ChecklistAnswer $answer)
    {
        if ($answer->checklist_id !== $checklist->id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'answer_status' => 'required|in:pending,compliant,partial,non_compliant',
            'justification' => 'nullable|string|max:2000',
            'action_plan' => 'nullable|string|max:2000',
            'responsible_employee_id' => 'nullable|exists:employees,id',
            'deadline_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $answer->update([
            'answer_status' => $request->answer_status,
            'justification' => $request->justification,
            'action_plan' => $request->action_plan,
            'responsible_employee_id' => $request->responsible_employee_id,
            'deadline_date' => $request->deadline_date,
        ]);

        // Calculate and store score
        $answer->update(['score' => $answer->calculateScore()]);

        // Update checklist status and score
        $checklist->updateStatusFromAnswers();
        $checklist->refresh();

        return response()->json([
            'answer' => [
                'id' => $answer->id,
                'answer_status' => $answer->answer_status,
                'score' => $answer->score,
            ],
            'checklist' => [
                'status' => $checklist->status,
                'score_percentage' => $checklist->score_percentage,
                'started_at' => $checklist->started_at?->format('d/m/Y H:i'),
                'completed_at' => $checklist->completed_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function statistics(Checklist $checklist)
    {
        return response()->json($checklist->calculateStatistics());
    }

    public function employees(Request $request)
    {
        $storeId = $request->get('store_id');

        $query = Employee::query()->select(['id', 'name', 'short_name']);

        if ($storeId) {
            $store = Store::find($storeId);
            if ($store) {
                $query->whereHas('contracts', function ($q) use ($store) {
                    $q->where('store_id', $store->code)->where('is_active', true);
                });
            }
        }

        return response()->json($query->orderBy('name')->get());
    }
}
