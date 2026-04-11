<?php

namespace App\Http\Controllers;

use App\Models\TrainingContent;
use App\Models\TrainingContentProgress;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseContent;
use App\Models\TrainingCourseEnrollment;
use App\Models\TrainingFacilitator;
use App\Models\TrainingSubject;
use App\Services\TrainingCourseCompletionService;
use App\Services\TrainingEnrollmentService;
use App\Services\TrainingProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TrainingCourseController extends Controller
{
    public function __construct(
        private TrainingEnrollmentService $enrollmentService,
        private TrainingProgressService $progressService,
        private TrainingCourseCompletionService $completionService,
    ) {}

    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = TrainingCourse::with(['subject', 'facilitator', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('visibility')) {
            $query->forVisibility($request->visibility);
        }
        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $courses = $query->paginate(15)->through(fn ($course) => $this->formatCourse($course));

        $statusCounts = [];
        foreach (array_keys(TrainingCourse::STATUS_LABELS) as $status) {
            $statusCounts[$status] = TrainingCourse::active()->forStatus($status)->count();
        }

        return response()->json([
            'courses' => $courses,
            'filters' => $request->only(['search', 'status', 'visibility', 'subject_id']),
            'statusOptions' => TrainingCourse::STATUS_LABELS,
            'statusCounts' => $statusCounts,
            'visibilityOptions' => TrainingCourse::VISIBILITY_LABELS,
            'facilitators' => TrainingFacilitator::active()->orderBy('name')->get(['id', 'name', 'external']),
            'subjects' => TrainingSubject::active()->orderBy('name')->get(['id', 'name']),
            'stores' => \App\Models\Store::orderBy('name')->get(['id', 'name', 'code']),
            'templates' => \App\Models\CertificateTemplate::active()->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject_id' => 'nullable|exists:training_subjects,id',
            'facilitator_id' => 'nullable|exists:training_facilitators,id',
            'visibility' => 'required|string|in:public,private',
            'requires_sequential' => 'boolean',
            'certificate_on_completion' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'thumbnail' => 'nullable|image|max:5120',
        ]);

        unset($validated['thumbnail']);
        $validated['status'] = TrainingCourse::STATUS_DRAFT;
        $validated['created_by_user_id'] = auth()->id();

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('training-courses/thumbnails', 'public');
        }

        $course = TrainingCourse::create($validated);

        return response()->json([
            'course' => $this->formatCourse($course->load(['subject', 'facilitator'])),
            'message' => 'Curso criado com sucesso.',
        ]);
    }

    public function show(TrainingCourse $trainingCourse)
    {
        $trainingCourse->load([
            'subject', 'facilitator', 'certificateTemplate',
            'courseContents.content.category',
            'enrollments.user', 'enrollments.employee',
            'visibilityRules',
            'createdBy', 'updatedBy',
        ]);

        return response()->json([
            'course' => $this->formatCourseDetail($trainingCourse),
            'templates' => \App\Models\CertificateTemplate::active()->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function update(Request $request, TrainingCourse $trainingCourse)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject_id' => 'nullable|exists:training_subjects,id',
            'facilitator_id' => 'nullable|exists:training_facilitators,id',
            'visibility' => 'required|string|in:public,private',
            'requires_sequential' => 'boolean',
            'certificate_on_completion' => 'boolean',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'thumbnail' => 'nullable|image|max:5120',
        ]);

        unset($validated['thumbnail']);
        $validated['updated_by_user_id'] = auth()->id();

        if ($request->hasFile('thumbnail')) {
            if ($trainingCourse->thumbnail_path) {
                Storage::disk('public')->delete($trainingCourse->thumbnail_path);
            }
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('training-courses/thumbnails', 'public');
        }

        $trainingCourse->update($validated);

        return response()->json([
            'course' => $this->formatCourse($trainingCourse->load(['subject', 'facilitator'])),
            'message' => 'Curso atualizado com sucesso.',
        ]);
    }

    public function destroy(TrainingCourse $trainingCourse)
    {
        $trainingCourse->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
            'deleted_reason' => request('reason'),
        ]);

        return response()->json(['message' => 'Curso excluido com sucesso.']);
    }

    // ==========================================
    // State machine
    // ==========================================

    public function transition(Request $request, TrainingCourse $trainingCourse)
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(TrainingCourse::STATUS_LABELS)),
        ]);

        $newStatus = $request->status;

        if (! $trainingCourse->canTransitionTo($newStatus)) {
            return response()->json([
                'error' => "Transição de '{$trainingCourse->status_label}' para '".TrainingCourse::STATUS_LABELS[$newStatus]."' não permitida.",
            ], 422);
        }

        $data = ['status' => $newStatus, 'updated_by_user_id' => auth()->id()];

        if ($newStatus === TrainingCourse::STATUS_PUBLISHED && ! $trainingCourse->published_at) {
            $data['published_at'] = now();
        }

        $trainingCourse->update($data);

        return response()->json([
            'message' => 'Status atualizado para '.TrainingCourse::STATUS_LABELS[$newStatus].'.',
        ]);
    }

    // ==========================================
    // Content management
    // ==========================================

    public function manageContents(Request $request, TrainingCourse $trainingCourse)
    {
        $validated = $request->validate([
            'contents' => 'required|array',
            'contents.*.content_id' => 'required|exists:training_contents,id',
            'contents.*.sort_order' => 'required|integer|min:0',
            'contents.*.is_required' => 'boolean',
        ]);

        // Sync contents
        TrainingCourseContent::where('course_id', $trainingCourse->id)->delete();

        foreach ($validated['contents'] as $item) {
            TrainingCourseContent::create([
                'course_id' => $trainingCourse->id,
                'content_id' => $item['content_id'],
                'sort_order' => $item['sort_order'],
                'is_required' => $item['is_required'] ?? true,
            ]);
        }

        return response()->json(['message' => 'Conteúdos atualizados com sucesso.']);
    }

    // ==========================================
    // Visibility management
    // ==========================================

    public function manageVisibility(Request $request, TrainingCourse $trainingCourse)
    {
        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.target_type' => 'required|string|in:store,role,user',
            'rules.*.target_id' => 'required|string',
        ]);

        $trainingCourse->visibilityRules()->delete();

        foreach ($validated['rules'] as $rule) {
            $trainingCourse->visibilityRules()->create($rule);
        }

        return response()->json(['message' => 'Visibilidade atualizada com sucesso.']);
    }

    // ==========================================
    // Enrollment
    // ==========================================

    public function enroll(TrainingCourse $trainingCourse)
    {
        $user = auth()->user();

        if (! $this->enrollmentService->hasAccess($trainingCourse, $user)) {
            return response()->json(['error' => 'Você não tem acesso a este curso.'], 403);
        }

        $enrollment = $this->enrollmentService->enroll($trainingCourse, $user);

        return response()->json([
            'enrollment' => $enrollment,
            'message' => 'Inscrição realizada com sucesso.',
        ]);
    }

    // ==========================================
    // My Trainings (student portal)
    // ==========================================

    public function myTrainings()
    {
        $user = auth()->user();
        $courses = $this->enrollmentService->getUserCourses($user);

        // Available published courses user is not enrolled in
        $enrolledCourseIds = TrainingCourseEnrollment::forUser($user->id)->pluck('course_id');
        $available = TrainingCourse::active()
            ->published()
            ->whereNotIn('id', $enrolledCourseIds)
            ->with(['subject', 'facilitator'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($c) => $this->formatCourse($c));

        return Inertia::render('Trainings/MyTrainings', [
            'inProgress' => $courses['in_progress']->map(fn ($e) => $this->formatEnrollment($e)),
            'completed' => $courses['completed']->map(fn ($e) => $this->formatEnrollment($e)),
            'available' => $available,
        ]);
    }

    // ==========================================
    // Watch content / Progress
    // ==========================================

    public function startCourse(TrainingCourse $trainingCourse)
    {
        $user = auth()->user();

        // Auto-enroll
        $this->enrollmentService->enroll($trainingCourse, $user);

        // Find the first content, respecting sort_order
        $firstCourseContent = $trainingCourse->courseContents()
            ->orderBy('sort_order')
            ->first();

        if (! $firstCourseContent) {
            return back()->with('error', 'Este curso ainda não possui conteúdos.');
        }

        // Find the last content in progress (to resume)
        $lastProgress = \App\Models\TrainingContentProgress::where('user_id', $user->id)
            ->where('course_id', $trainingCourse->id)
            ->whereIn('status', ['in_progress', 'not_started'])
            ->first();

        $contentId = $lastProgress?->content_id ?? $firstCourseContent->content_id;

        return redirect()->route('training-courses.watch', [
            'trainingCourse' => $trainingCourse->id,
            'content' => $contentId,
        ]);
    }

    public function watchContent(TrainingCourse $trainingCourse, TrainingContent $content)
    {
        $user = auth()->user();

        // Auto-enroll if needed
        $this->enrollmentService->enroll($trainingCourse, $user);

        // Check sequential unlock
        if (! $this->enrollmentService->isContentUnlocked($trainingCourse, $content->id, $user)) {
            return back()->with('error', 'Complete os conteudos anteriores primeiro.');
        }

        // Load course contents for navigation
        $courseContents = $trainingCourse->courseContents()
            ->with('content')
            ->get()
            ->map(function ($cc) use ($user, $trainingCourse) {
                $progress = TrainingContentProgress::where('user_id', $user->id)
                    ->where('content_id', $cc->content_id)
                    ->where('course_id', $trainingCourse->id)
                    ->first();

                return [
                    'id' => $cc->content_id,
                    'title' => $cc->content->title,
                    'content_type' => $cc->content->content_type,
                    'sort_order' => $cc->sort_order,
                    'is_required' => $cc->is_required,
                    'status' => $progress?->status ?? 'not_started',
                    'progress_percent' => $progress?->progress_percent ?? 0,
                ];
            });

        // Get or create progress for current content
        $progress = TrainingContentProgress::firstOrCreate(
            ['user_id' => $user->id, 'content_id' => $content->id, 'course_id' => $trainingCourse->id],
            ['status' => TrainingContentProgress::STATUS_NOT_STARTED]
        );
        $progress->increment('views_count');
        $progress->update(['last_accessed_at' => now()]);

        return Inertia::render('Trainings/WatchContent', [
            'course' => $this->formatCourse($trainingCourse->load(['subject', 'facilitator'])),
            'content' => [
                'id' => $content->id,
                'title' => $content->title,
                'description' => $content->description,
                'content_type' => $content->content_type,
                'file_url' => $content->file_path ? $this->getFileUrl($content->file_path) : null,
                'external_url' => $content->external_url,
                'text_content' => $content->text_content,
                'duration_seconds' => $content->duration_seconds,
            ],
            'progress' => [
                'status' => $progress->status,
                'progress_percent' => $progress->progress_percent,
                'last_position_seconds' => $progress->last_position_seconds,
            ],
            'courseContents' => $courseContents,
        ]);
    }

    public function saveProgress(Request $request, TrainingContent $content)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|exists:training_courses,id',
            'progress_percent' => 'required|numeric|min:0|max:100',
            'position_seconds' => 'nullable|integer|min:0',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $result = $this->progressService->updateProgress(
            $content->id,
            $validated['course_id'] ?? null,
            auth()->user(),
            $validated['progress_percent'],
            $validated['position_seconds'] ?? null,
            $validated['time_spent'] ?? 0,
        );

        return response()->json($result);
    }

    public function markComplete(Request $request, TrainingContent $content)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|exists:training_courses,id',
        ]);

        $result = $this->progressService->markComplete(
            $content->id,
            $validated['course_id'] ?? null,
            auth()->user(),
        );

        return response()->json($result);
    }

    // ==========================================
    // Course certificate download
    // ==========================================

    public function downloadCertificate(TrainingCourse $trainingCourse)
    {
        $user = auth()->user();

        $enrollment = TrainingCourseEnrollment::where('course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['error' => 'Inscrição não encontrada.'], 404);
        }

        $result = $this->completionService->download($enrollment);

        if (! $result) {
            return response()->json(['error' => 'Certificado não disponível.'], 404);
        }

        return $result;
    }

    public function regenerateCertificate(TrainingCourse $trainingCourse)
    {
        $user = auth()->user();

        $enrollment = TrainingCourseEnrollment::where('course_id', $trainingCourse->id)
            ->where('user_id', $user->id)
            ->where('status', TrainingCourseEnrollment::STATUS_COMPLETED)
            ->first();

        if (! $enrollment) {
            return response()->json(['error' => 'Inscrição concluída não encontrada.'], 404);
        }

        $success = $this->completionService->regenerateCertificate($enrollment);

        if (! $success) {
            return response()->json(['error' => 'Erro ao regenerar certificado.'], 500);
        }

        return response()->json(['message' => 'Certificado regenerado com sucesso.']);
    }

    // ==========================================
    // File streaming (fallback when storage:link unavailable)
    // ==========================================

    public function streamFile(string $path)
    {
        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mimeType = Storage::disk('public')->mimeType($path);

        return response()->file(
            Storage::disk('public')->path($path),
            ['Content-Type' => $mimeType]
        );
    }

    // ==========================================
    // Reports & Export
    // ==========================================

    public function exportReport(Request $request)
    {
        $type = $request->get('type', 'overview');

        return (new \App\Exports\TrainingReportExport($type))
            ->download('relatorio_treinamentos_'.$type.'_'.now()->format('Y-m-d').'.xlsx');
    }

    public function reports(Request $request)
    {
        $type = $request->get('type', 'overview');

        $data = match ($type) {
            'overview' => $this->reportOverview($request),
            'by-employee' => $this->reportByEmployee($request),
            'by-store' => $this->reportByStore($request),
            'by-course' => $this->reportByCourse($request),
            default => $this->reportOverview($request),
        };

        if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json($data);
        }

        return Inertia::render('Trainings/Reports', [
            'initialData' => $data,
            'initialType' => $type,
        ]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function getFileUrl(string $filePath): string
    {
        // Se o symlink public/storage existe, usa a URL padrão do Storage
        if (is_link(public_path('storage'))) {
            return Storage::disk('public')->url($filePath);
        }

        // Fallback: rota de streaming via controller
        return route('training-contents.stream', ['path' => $filePath]);
    }

    private function formatCourse(TrainingCourse $course): array
    {
        return [
            'id' => $course->id,
            'hash_id' => $course->hash_id,
            'title' => $course->title,
            'description' => $course->description,
            'thumbnail_path' => $course->thumbnail_path,
            'status' => $course->status,
            'status_label' => $course->status_label,
            'status_color' => $course->status_color,
            'visibility' => $course->visibility,
            'visibility_label' => $course->visibility_label,
            'requires_sequential' => $course->requires_sequential,
            'certificate_on_completion' => $course->certificate_on_completion,
            'estimated_duration_minutes' => $course->estimated_duration_minutes,
            'facilitator' => $course->facilitator ? [
                'id' => $course->facilitator->id,
                'name' => $course->facilitator->name,
            ] : null,
            'subject' => $course->subject ? [
                'id' => $course->subject->id,
                'name' => $course->subject->name,
            ] : null,
            'content_count' => $course->contents()->count(),
            'enrollment_count' => $course->enrollments()->count(),
            'created_by' => $course->createdBy?->name,
            'created_at' => $course->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatCourseDetail(TrainingCourse $course): array
    {
        return array_merge($this->formatCourse($course), [
            'contents' => $course->courseContents->map(fn ($cc) => [
                'id' => $cc->content->id,
                'title' => $cc->content->title,
                'content_type' => $cc->content->content_type,
                'type_label' => $cc->content->type_label,
                'duration_formatted' => $cc->content->duration_formatted,
                'sort_order' => $cc->sort_order,
                'is_required' => $cc->is_required,
            ]),
            'enrollments' => $course->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'user_name' => $e->user?->name ?? '-',
                'status' => $e->status,
                'status_label' => $e->status_label,
                'status_color' => $e->status_color,
                'completion_percent' => $e->completion_percent,
                'enrolled_at' => $e->enrolled_at?->format('d/m/Y'),
                'completed_at' => $e->completed_at?->format('d/m/Y'),
            ]),
            'visibility_rules' => $course->visibilityRules->map(fn ($r) => [
                'target_type' => $r->target_type,
                'target_id' => $r->target_id,
            ]),
            'valid_transitions' => TrainingCourse::VALID_TRANSITIONS[$course->status] ?? [],
            'transition_labels' => collect(TrainingCourse::VALID_TRANSITIONS[$course->status] ?? [])
                ->mapWithKeys(fn ($s) => [$s => TrainingCourse::STATUS_LABELS[$s]])
                ->toArray(),
            'certificate_template' => $course->certificateTemplate ? [
                'id' => $course->certificateTemplate->id,
                'name' => $course->certificateTemplate->name,
            ] : null,
            'updated_by' => $course->updatedBy?->name,
            'updated_at' => $course->updated_at?->format('d/m/Y H:i'),
        ]);
    }

    private function formatEnrollment(TrainingCourseEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'course' => $enrollment->course ? $this->formatCourse($enrollment->course) : null,
            'status' => $enrollment->status,
            'status_label' => $enrollment->status_label,
            'completion_percent' => $enrollment->completion_percent,
            'enrolled_at' => $enrollment->enrolled_at?->format('d/m/Y'),
            'completed_at' => $enrollment->completed_at?->format('d/m/Y'),
            'certificate_generated' => $enrollment->certificate_generated,
        ];
    }

    private function reportOverview(Request $request): array
    {
        $query = TrainingCourseEnrollment::query();

        if ($request->filled('date_from')) {
            $query->where('enrolled_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('enrolled_at', '<=', $request->date_to.' 23:59:59');
        }

        return [
            'total_courses' => TrainingCourse::active()->count(),
            'published_courses' => TrainingCourse::active()->published()->count(),
            'total_enrollments' => (clone $query)->count(),
            'total_completions' => (clone $query)->completed()->count(),
            'total_in_progress' => (clone $query)->inProgress()->count(),
            'completion_rate' => $query->count() > 0
                ? round(($query->completed()->count() / $query->count()) * 100, 1)
                : 0,
            'total_hours' => round(TrainingContentProgress::sum('total_time_spent_seconds') / 3600, 1),
            'active_contents' => TrainingContent::active()->count(),
        ];
    }

    private function reportByEmployee(Request $request): array
    {
        return TrainingCourseEnrollment::with(['user', 'course'])
            ->selectRaw('user_id, COUNT(*) as total_enrollments, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completions', ['completed'])
            ->groupBy('user_id')
            ->get()
            ->map(fn ($row) => [
                'user_name' => $row->user?->name ?? '-',
                'total_enrollments' => $row->total_enrollments,
                'completions' => $row->completions,
            ])
            ->toArray();
    }

    private function reportByStore(Request $request): array
    {
        return TrainingCourseEnrollment::join('users', 'users.id', '=', 'training_course_enrollments.user_id')
            ->leftJoin('stores', 'stores.code', '=', 'users.store_id')
            ->selectRaw(
                'users.store_id as store_code,
                 COALESCE(stores.name, CASE WHEN users.store_id IS NULL THEN ? ELSE users.store_id END) as store_name,
                 COUNT(DISTINCT users.id) as employees_trained,
                 COUNT(*) as total_enrollments,
                 SUM(CASE WHEN training_course_enrollments.status = ? THEN 1 ELSE 0 END) as completions',
                ['Sem loja', TrainingCourseEnrollment::STATUS_COMPLETED]
            )
            ->groupBy('users.store_id', 'stores.name')
            ->get()
            ->map(fn ($row) => [
                'store_code' => $row->store_code ?? '-',
                'store_name' => $row->store_name,
                'employees_trained' => (int) $row->employees_trained,
                'total_enrollments' => (int) $row->total_enrollments,
                'completions' => (int) $row->completions,
                'completion_rate' => $row->total_enrollments > 0
                    ? round(($row->completions / $row->total_enrollments) * 100, 1)
                    : 0,
            ])
            ->toArray();
    }

    private function reportByCourse(Request $request): array
    {
        return TrainingCourse::active()
            ->withCount([
                'enrollments',
                'enrollments as completed_count' => fn ($q) => $q->completed(),
                'enrollments as dropped_count' => fn ($q) => $q->forStatus(TrainingCourseEnrollment::STATUS_DROPPED),
            ])
            ->get()
            ->map(fn ($course) => [
                'title' => $course->title,
                'enrolled' => $course->enrollments_count,
                'completed' => $course->completed_count,
                'dropped' => $course->dropped_count,
                'completion_rate' => $course->enrollments_count > 0
                    ? round(($course->completed_count / $course->enrollments_count) * 100, 1)
                    : 0,
            ])
            ->toArray();
    }
}
