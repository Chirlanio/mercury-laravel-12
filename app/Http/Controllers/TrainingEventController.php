<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\Training;
use App\Models\TrainingEvaluation;
use App\Models\TrainingFacilitator;
use App\Models\TrainingParticipant;
use App\Models\TrainingSubject;
use App\Services\TrainingCertificateService;
use App\Services\TrainingQRCodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingEventController extends Controller
{
    public function __construct(
        private TrainingCertificateService $certificateService,
        private TrainingQRCodeService $qrCodeService,
    ) {}

    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = Training::with(['facilitator', 'subject', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('facilitator_id')) {
            $query->forFacilitator($request->facilitator_id);
        }
        if ($request->filled('subject_id')) {
            $query->forSubject($request->subject_id);
        }
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('facilitator', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $trainings = $query->paginate(15)->through(fn ($training) => $this->formatTraining($training));

        $statusCounts = [];
        foreach (array_keys(Training::STATUS_LABELS) as $status) {
            $statusCounts[$status] = Training::active()->forStatus($status)->count();
        }

        return Inertia::render('Trainings/Index', [
            'trainings' => $trainings,
            'filters' => $request->only(['search', 'status', 'facilitator_id', 'subject_id', 'date_from', 'date_to']),
            'statusOptions' => Training::STATUS_LABELS,
            'statusCounts' => $statusCounts,
            'facilitators' => TrainingFacilitator::active()->orderBy('name')->get(['id', 'name', 'external']),
            'subjects' => TrainingSubject::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:start_time'],
            'location' => 'nullable|string|max:255',
            'max_participants' => 'nullable|integer|min:1',
            'facilitator_id' => 'required|exists:training_facilitators,id',
            'subject_id' => 'required|exists:training_subjects,id',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'allow_late_attendance' => 'boolean',
            'attendance_grace_minutes' => 'integer|min:0|max:120',
            'evaluation_enabled' => 'boolean',
        ]);

        // Normalizar para H:i
        $validated['start_time'] = substr($validated['start_time'], 0, 5);
        $validated['end_time'] = substr($validated['end_time'], 0, 5);
        $validated['status'] = Training::STATUS_DRAFT;
        $validated['created_by_user_id'] = auth()->id();

        $training = Training::create($validated);

        return redirect()->route('trainings.index')
            ->with('success', 'Treinamento criado com sucesso.');
    }

    public function show(Training $training)
    {
        $training->load([
            'facilitator',
            'subject',
            'certificateTemplate',
            'participants.employee',
            'participants.evaluation',
            'evaluations.participant',
            'createdBy',
            'updatedBy',
        ]);

        $qrData = $training->status !== Training::STATUS_DRAFT
            ? $this->qrCodeService->getQRCodeData($training)
            : null;

        return response()->json([
            'training' => $this->formatTrainingDetail($training),
            'qrCodes' => $qrData,
        ]);
    }

    public function edit(Training $training)
    {
        $training->load(['facilitator', 'subject', 'certificateTemplate']);

        return response()->json([
            'training' => $training,
            'facilitators' => TrainingFacilitator::active()->orderBy('name')->get(['id', 'name', 'external']),
            'subjects' => TrainingSubject::active()->orderBy('name')->get(['id', 'name']),
            'templates' => CertificateTemplate::active()->orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function update(Request $request, Training $training)
    {
        if ($training->status === Training::STATUS_COMPLETED || $training->status === Training::STATUS_CANCELLED) {
            return back()->with('error', 'Não é possível editar um treinamento concluído ou cancelado.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:start_time'],
            'location' => 'nullable|string|max:255',
            'max_participants' => 'nullable|integer|min:1',
            'facilitator_id' => 'required|exists:training_facilitators,id',
            'subject_id' => 'required|exists:training_subjects,id',
            'certificate_template_id' => 'nullable|exists:certificate_templates,id',
            'allow_late_attendance' => 'boolean',
            'attendance_grace_minutes' => 'integer|min:0|max:120',
            'evaluation_enabled' => 'boolean',
        ]);

        // Normalizar para H:i
        $validated['start_time'] = substr($validated['start_time'], 0, 5);
        $validated['end_time'] = substr($validated['end_time'], 0, 5);
        $validated['updated_by_user_id'] = auth()->id();

        $training->update($validated);

        return redirect()->route('trainings.index')
            ->with('success', 'Treinamento atualizado com sucesso.');
    }

    public function destroy(Training $training)
    {
        $training->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
            'deleted_reason' => request('reason'),
        ]);

        return redirect()->route('trainings.index')
            ->with('success', 'Treinamento excluído com sucesso.');
    }

    // ==========================================
    // State Machine
    // ==========================================

    public function transition(Request $request, Training $training)
    {
        $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(Training::STATUS_LABELS)),
        ]);

        $newStatus = $request->status;

        if (! $training->canTransitionTo($newStatus)) {
            return back()->with('error', "Transição de '{$training->status_label}' para '".Training::STATUS_LABELS[$newStatus]."' não é permitida.");
        }

        $training->update([
            'status' => $newStatus,
            'updated_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Status atualizado para '.Training::STATUS_LABELS[$newStatus].'.');
    }

    // ==========================================
    // QR Codes
    // ==========================================

    public function qrCodes(Training $training)
    {
        if ($training->status === Training::STATUS_DRAFT) {
            return response()->json(['error' => 'QR codes só ficam disponíveis após publicação.'], 422);
        }

        return response()->json($this->qrCodeService->getQRCodeData($training));
    }

    // ==========================================
    // Participants & Attendance
    // ==========================================

    public function addParticipant(Request $request, Training $training)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        if (! $training->has_vacancy) {
            return response()->json(['error' => 'Treinamento lotado.'], 422);
        }

        $existing = $training->participants()->where('employee_id', $validated['employee_id'])->first();
        if ($existing) {
            return response()->json(['error' => 'Participante já registrado.'], 422);
        }

        $employee = \App\Models\Employee::findOrFail($validated['employee_id']);

        $participant = $training->participants()->create([
            'employee_id' => $employee->id,
            'participant_name' => $employee->name,
            'participant_email' => $employee->email,
            'attendance_time' => now(),
        ]);

        return response()->json(['participant' => $participant->load('employee')]);
    }

    public function removeParticipant(Training $training, TrainingParticipant $participant)
    {
        if ($participant->training_id !== $training->id) {
            return response()->json(['error' => 'Participante não pertence a este treinamento.'], 422);
        }

        $participant->delete();

        return response()->json(['message' => 'Participante removido.']);
    }

    // ==========================================
    // Evaluations
    // ==========================================

    public function submitEvaluation(Request $request, Training $training, TrainingParticipant $participant)
    {
        if (! $training->evaluation_enabled) {
            return response()->json(['error' => 'Avaliação não habilitada para este treinamento.'], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $evaluation = TrainingEvaluation::updateOrCreate(
            ['training_id' => $training->id, 'participant_id' => $participant->id],
            $validated,
        );

        return response()->json(['evaluation' => $evaluation]);
    }

    // ==========================================
    // Certificates
    // ==========================================

    public function generateCertificates(Training $training)
    {
        if (! in_array($training->status, [Training::STATUS_IN_PROGRESS, Training::STATUS_COMPLETED])) {
            return response()->json(['error' => 'Certificados só podem ser gerados para treinamentos em andamento ou concluídos.'], 422);
        }

        $results = $this->certificateService->generateBulk($training);

        return response()->json([
            'message' => "Certificados gerados: {$results['generated']}. Erros: {$results['errors']}.",
            'results' => $results,
        ]);
    }

    public function downloadCertificate(Training $training, TrainingParticipant $participant)
    {
        return $this->certificateService->download($participant)
            ?? response()->json(['error' => 'Certificado não encontrado.'], 404);
    }

    // ==========================================
    // Statistics
    // ==========================================

    public function statistics()
    {
        $stats = [
            'total' => Training::active()->count(),
            'by_status' => [],
            'upcoming' => Training::active()->upcoming()->count(),
            'this_month' => Training::active()->whereMonth('event_date', now()->month)->whereYear('event_date', now()->year)->count(),
            'total_participants' => TrainingParticipant::count(),
            'avg_participants' => 0,
            'avg_rating' => round(TrainingEvaluation::avg('rating') ?? 0, 1),
            'active_facilitators' => TrainingFacilitator::active()->count(),
            'active_subjects' => TrainingSubject::active()->count(),
        ];

        foreach (Training::STATUS_LABELS as $status => $label) {
            $stats['by_status'][$status] = [
                'count' => Training::active()->forStatus($status)->count(),
                'label' => $label,
                'color' => Training::STATUS_COLORS[$status],
            ];
        }

        $totalTrainings = $stats['total'] ?: 1;
        $stats['avg_participants'] = round($stats['total_participants'] / $totalTrainings, 1);

        return response()->json($stats);
    }

    // ==========================================
    // Facilitators & Subjects CRUD
    // ==========================================

    public function storeFacilitator(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'external' => 'boolean',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $validated['created_by_user_id'] = auth()->id();

        $facilitator = TrainingFacilitator::create($validated);

        return response()->json(['facilitator' => $facilitator]);
    }

    public function updateFacilitator(Request $request, TrainingFacilitator $facilitator)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'external' => 'boolean',
            'employee_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by_user_id'] = auth()->id();

        $facilitator->update($validated);

        return response()->json(['facilitator' => $facilitator]);
    }

    public function storeSubject(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['created_by_user_id'] = auth()->id();

        $subject = TrainingSubject::create($validated);

        return response()->json(['subject' => $subject]);
    }

    public function updateSubject(Request $request, TrainingSubject $subject)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by_user_id'] = auth()->id();

        $subject->update($validated);

        return response()->json(['subject' => $subject]);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function formatTraining(Training $training): array
    {
        return [
            'id' => $training->id,
            'title' => $training->title,
            'event_date' => $training->event_date->format('Y-m-d'),
            'event_date_formatted' => $training->event_date->format('d/m/Y'),
            'start_time' => $training->start_time,
            'end_time' => $training->end_time,
            'duration_hours' => $training->duration_hours,
            'location' => $training->location,
            'status' => $training->status,
            'status_label' => $training->status_label,
            'status_color' => $training->status_color,
            'facilitator' => $training->facilitator ? [
                'id' => $training->facilitator->id,
                'name' => $training->facilitator->name,
                'external' => $training->facilitator->external,
            ] : null,
            'subject' => $training->subject ? [
                'id' => $training->subject->id,
                'name' => $training->subject->name,
            ] : null,
            'max_participants' => $training->max_participants,
            'participant_count' => $training->participants()->count(),
            'average_rating' => $training->average_rating,
            'created_by' => $training->createdBy?->name,
            'created_at' => $training->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatTrainingDetail(Training $training): array
    {
        $base = $this->formatTraining($training);

        return array_merge($base, [
            'hash_id' => $training->hash_id,
            'description' => $training->description,
            'allow_late_attendance' => $training->allow_late_attendance,
            'attendance_grace_minutes' => $training->attendance_grace_minutes,
            'evaluation_enabled' => $training->evaluation_enabled,
            'certificate_template' => $training->certificateTemplate ? [
                'id' => $training->certificateTemplate->id,
                'name' => $training->certificateTemplate->name,
            ] : null,
            'participants' => $training->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->display_name,
                'employee_id' => $p->employee_id,
                'attendance_time' => $p->attendance_time?->format('d/m/Y H:i'),
                'is_late' => $p->is_late,
                'certificate_generated' => $p->certificate_generated,
                'has_evaluated' => $p->has_evaluated,
                'evaluation' => $p->evaluation ? [
                    'rating' => $p->evaluation->rating,
                    'comment' => $p->evaluation->comment,
                ] : null,
            ]),
            'evaluation_summary' => [
                'count' => $training->evaluations->count(),
                'average' => $training->average_rating,
                'distribution' => collect(range(1, 5))->mapWithKeys(fn ($r) => [
                    $r => $training->evaluations->where('rating', $r)->count(),
                ])->toArray(),
            ],
            'valid_transitions' => Training::VALID_TRANSITIONS[$training->status] ?? [],
            'transition_labels' => collect(Training::VALID_TRANSITIONS[$training->status] ?? [])
                ->mapWithKeys(fn ($s) => [$s => Training::STATUS_LABELS[$s]])
                ->toArray(),
            'updated_by' => $training->updatedBy?->name,
            'updated_at' => $training->updated_at?->format('d/m/Y H:i'),
        ]);
    }
}
