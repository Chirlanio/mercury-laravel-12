<?php

namespace App\Http\Controllers;

use App\Events\PersonnelMovementCreated;
use App\Models\DismissalFollowUp;
use App\Models\DismissalReason;
use App\Models\Employee;
use App\Models\PersonnelMovement;
use App\Models\PersonnelMovementFile;
use App\Models\Position;
use App\Models\Sector;
use App\Models\Store;
use App\Services\PersonnelMovementIntegrationService;
use App\Services\PersonnelMovementTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PersonnelMovementController extends Controller
{
    public function __construct(
        private PersonnelMovementTransitionService $transitionService,
        private PersonnelMovementIntegrationService $integrationService,
    ) {}

    public function index(Request $request): Response
    {
        $query = PersonnelMovement::with(['employee', 'store', 'createdBy', 'newPosition'])
            ->active()
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('type')) {
            $query->forType($request->type);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('date_from')) {
            $query->where('effective_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('effective_date', '<=', $request->date_to);
        }

        $movements = $query->paginate(15)->through(fn ($m) => $this->formatMovement($m));

        $statusCounts = [];
        foreach (array_keys(PersonnelMovement::STATUS_LABELS) as $status) {
            $statusCounts[$status] = PersonnelMovement::active()->forStatus($status)->count();
        }

        $typeCounts = [];
        foreach (array_keys(PersonnelMovement::TYPE_LABELS) as $type) {
            $typeCounts[$type] = PersonnelMovement::active()->forType($type)->count();
        }

        return Inertia::render('PersonnelMovements/Index', [
            'movements' => $movements,
            'filters' => $request->only(['search', 'type', 'status', 'store_id', 'date_from', 'date_to']),
            'statusOptions' => PersonnelMovement::STATUS_LABELS,
            'statusCounts' => $statusCounts,
            'typeOptions' => PersonnelMovement::TYPE_LABELS,
            'typeCounts' => $typeCounts,
            'selects' => [
                'stores' => Store::orderBy('name')->get(['id', 'code', 'name']),
                'employees' => Employee::active()->orderBy('name')->get(['id', 'name', 'store_id', 'position_id']),
                'inactiveEmployees' => Employee::inactive()->orderBy('name')->get(['id', 'name', 'store_id']),
                'sectors' => Sector::where('is_active', true)->orderBy('sector_name')->get(['id', 'sector_name as name']),
                'positions' => Position::active()->orderBy('name')->get(['id', 'name']),
                'dismissalReasons' => DismissalReason::active()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baseRules = [
            'type' => 'required|in:dismissal,promotion,transfer,reactivation',
            'employee_id' => 'required|exists:employees,id',
            'store_id' => 'required|string|max:10',
            'observation' => 'nullable|string|max:5000',
            'requester_id' => 'nullable|exists:users,id',
            'request_area_id' => 'nullable|exists:sectors,id',
        ];

        $typeRules = $this->validationRulesForType($request->input('type', ''));
        $validated = $request->validate(array_merge($baseRules, $typeRules));

        $validated['status'] = PersonnelMovement::STATUS_PENDING;
        $validated['created_by_user_id'] = auth()->id();

        // Set effective_date based on type
        if ($validated['type'] === PersonnelMovement::TYPE_DISMISSAL) {
            $validated['effective_date'] = $validated['last_day_worked'] ?? null;

            // Pull integration data
            $integration = $this->integrationService->getEmployeeIntegrationData($validated['employee_id']);
            $validated['fouls'] = $integration['fouls'];
            $validated['days_off'] = $integration['days_off'];
            $validated['overtime_hours'] = $integration['overtime_hours'];
        } elseif ($validated['type'] === PersonnelMovement::TYPE_REACTIVATION) {
            $validated['effective_date'] = $validated['reactivation_date'] ?? null;
        }

        $movement = PersonnelMovement::create($validated);

        // Create dismissal follow-up with auto-calculated values
        if ($movement->type === PersonnelMovement::TYPE_DISMISSAL) {
            $this->createDismissalFollowUp($movement);

            // Sync reasons
            if ($request->has('reason_ids')) {
                $movement->reasons()->sync($request->input('reason_ids', []));
            }
        }

        // Record initial status history
        $this->transitionService->recordHistory(
            $movement->id,
            null,
            PersonnelMovement::STATUS_PENDING,
            auth()->id(),
            'Movimentação criada'
        );

        // Disparar evento (consumido por CreateSubstitutionVacancyFromDismissal:
        // cria automaticamente uma vaga de substituição quando for
        // desligamento com open_vacancy=true).
        PersonnelMovementCreated::dispatch($movement);

        return redirect()->route('personnel-movements.index')
            ->with('success', 'Movimentação de pessoal criada com sucesso.');
    }

    public function show(PersonnelMovement $personnelMovement): JsonResponse
    {
        $personnelMovement->load([
            'employee.position', 'employee.store', 'store',
            'requester', 'requestArea', 'newPosition',
            'originStore', 'destinationStore',
            'reasons', 'followUp', 'files.uploadedBy',
            'statusHistory.changedBy', 'createdBy', 'updatedBy',
        ]);

        return response()->json([
            'movement' => $this->formatMovementDetailed($personnelMovement),
        ]);
    }

    public function edit(PersonnelMovement $personnelMovement): JsonResponse
    {
        $personnelMovement->load(['employee', 'reasons', 'followUp']);

        return response()->json([
            'movement' => array_merge($personnelMovement->toArray(), [
                'reason_ids' => $personnelMovement->reasons->pluck('id')->toArray(),
            ]),
        ]);
    }

    public function update(Request $request, PersonnelMovement $personnelMovement): RedirectResponse
    {
        if ($personnelMovement->status !== PersonnelMovement::STATUS_PENDING) {
            return redirect()->back()->withErrors(['status' => 'Apenas movimentações pendentes podem ser editadas.']);
        }

        $typeRules = $this->validationRulesForType($personnelMovement->type);
        $validated = $request->validate(array_merge([
            'observation' => 'nullable|string|max:5000',
            'requester_id' => 'nullable|exists:users,id',
            'request_area_id' => 'nullable|exists:sectors,id',
        ], $typeRules));

        $validated['updated_by_user_id'] = auth()->id();

        $personnelMovement->update($validated);

        if ($personnelMovement->type === PersonnelMovement::TYPE_DISMISSAL && $request->has('reason_ids')) {
            $personnelMovement->reasons()->sync($request->input('reason_ids', []));
        }

        return redirect()->route('personnel-movements.index')
            ->with('success', 'Movimentação atualizada com sucesso.');
    }

    public function transition(Request $request, PersonnelMovement $personnelMovement): JsonResponse
    {
        $validated = $request->validate([
            'new_status' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validation = $this->transitionService->validateTransition(
            $personnelMovement,
            $validated['new_status'],
            $validated
        );

        if (! $validation['valid']) {
            return response()->json([
                'error' => true,
                'message' => implode(' ', $validation['errors']),
            ], 422);
        }

        $this->transitionService->executeTransition(
            $personnelMovement,
            $validated['new_status'],
            $validated,
            auth()->id()
        );

        return response()->json([
            'error' => false,
            'message' => 'Status atualizado com sucesso.',
        ]);
    }

    public function updateFollowUp(Request $request, PersonnelMovement $personnelMovement): RedirectResponse
    {
        if ($personnelMovement->type !== PersonnelMovement::TYPE_DISMISSAL) {
            return redirect()->back()->withErrors(['type' => 'Follow-up disponível apenas para desligamentos.']);
        }

        $validated = $request->validate([
            'uniform' => 'boolean',
            'phone_chip' => 'boolean',
            'original_card' => 'boolean',
            'aso' => 'boolean',
            'aso_resigns' => 'boolean',
            'send_aso_guide' => 'boolean',
            'signature_date_trct' => 'nullable|date',
            'termination_date' => 'nullable|date',
        ]);

        $personnelMovement->followUp->update($validated);

        return redirect()->back()->with('success', 'Checklist atualizado com sucesso.');
    }

    public function destroy(Request $request, PersonnelMovement $personnelMovement): RedirectResponse
    {
        $request->validate([
            'deleted_reason' => 'nullable|string|max:500',
        ]);

        $personnelMovement->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
            'deleted_reason' => $request->input('deleted_reason'),
        ]);

        return redirect()->route('personnel-movements.index')
            ->with('success', 'Movimentação excluída com sucesso.');
    }

    public function getEmployeeIntegrationData(Employee $employee): JsonResponse
    {
        return response()->json(
            $this->integrationService->getEmployeeIntegrationData($employee->id)
        );
    }

    public function uploadFile(Request $request, PersonnelMovement $personnelMovement): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        $file = $request->file('file');
        $path = $file->store("personnel-movements/{$personnelMovement->id}", 'public');

        $record = PersonnelMovementFile::create([
            'personnel_movement_id' => $personnelMovement->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by_user_id' => auth()->id(),
        ]);

        return response()->json(['file' => $record]);
    }

    public function deleteFile(PersonnelMovementFile $file): JsonResponse
    {
        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        return response()->json(['message' => 'Arquivo removido com sucesso.']);
    }

    // Private helpers

    private function validationRulesForType(string $type): array
    {
        return match ($type) {
            PersonnelMovement::TYPE_DISMISSAL => [
                'last_day_worked' => 'required|date',
                'contact' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'contract_type' => 'required|in:clt,trial,intern,apprentice',
                'dismissal_subtype' => 'required|in:company_initiative,employee_resignation,trial_end,just_cause',
                'early_warning' => 'required|in:worked,indemnified,dispensed',
                'fixed_fund' => 'nullable|numeric|min:0',
                'open_vacancy' => 'boolean',
                'access_power_bi' => 'boolean', 'access_zznet' => 'boolean',
                'access_cigam' => 'boolean', 'access_camera' => 'boolean',
                'access_deskfy' => 'boolean', 'access_meu_atendimento' => 'boolean',
                'access_dito' => 'boolean', 'access_notebook' => 'boolean',
                'access_email_corporate' => 'boolean', 'access_parking_card' => 'boolean',
                'access_parking_shopping' => 'boolean', 'access_key_office' => 'boolean',
                'access_key_store' => 'boolean', 'access_instagram' => 'boolean',
                'activate_it' => 'boolean', 'activate_operation' => 'boolean',
                'deactivate_instagram' => 'boolean', 'activate_hr' => 'boolean',
                'reason_ids' => 'nullable|array',
                'reason_ids.*' => 'exists:dismissal_reasons,id',
            ],
            PersonnelMovement::TYPE_PROMOTION => [
                'effective_date' => 'required|date',
                'new_position_id' => 'required|exists:positions,id',
            ],
            PersonnelMovement::TYPE_TRANSFER => [
                'effective_date' => 'required|date',
                'origin_store_id' => 'required|string|max:10',
                'destination_store_id' => 'required|string|max:10|different:origin_store_id',
            ],
            PersonnelMovement::TYPE_REACTIVATION => [
                'reactivation_date' => 'required|date',
                'new_position_id' => 'nullable|exists:positions,id',
            ],
            default => [],
        };
    }

    private function createDismissalFollowUp(PersonnelMovement $movement): void
    {
        $employee = $movement->employee()->with('position')->first();
        $isManager = $employee?->position?->name && str_contains(strtolower($employee->position->name), 'gerente');
        $isCompanyInitiative = $movement->dismissal_subtype === 'company_initiative';
        $isClt = $movement->contract_type === 'clt';

        DismissalFollowUp::create([
            'personnel_movement_id' => $movement->id,
            'employee_id' => $movement->employee_id,
            'uniform' => true,
            'phone_chip' => $isManager,
            'original_card' => $isCompanyInitiative,
            'aso' => true,
            'aso_resigns' => $isClt,
            'send_aso_guide' => false,
            'signature_date_trct' => $movement->last_day_worked?->addDays(10),
            'termination_date' => $movement->last_day_worked,
        ]);
    }

    private function formatMovement(PersonnelMovement $m): array
    {
        return [
            'id' => $m->id,
            'type' => $m->type,
            'type_label' => $m->type_label,
            'type_color' => $m->type_color,
            'status' => $m->status,
            'status_label' => $m->status_label,
            'status_color' => $m->status_color,
            'employee_name' => $m->employee?->name,
            'employee_id' => $m->employee_id,
            'store_name' => $m->store?->name,
            'store_code' => $m->store_id,
            'effective_date' => $m->effective_date?->format('d/m/Y'),
            'new_position' => $m->newPosition?->name,
            'destination_store' => $m->type === PersonnelMovement::TYPE_TRANSFER
                ? Store::where('code', $m->destination_store_id)->value('name')
                : null,
            'created_by' => $m->createdBy?->name,
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function formatMovementDetailed(PersonnelMovement $m): array
    {
        $base = $this->formatMovement($m);

        return array_merge($base, [
            'observation' => $m->observation,
            'requester_name' => $m->requester?->name,
            'request_area' => $m->requestArea?->name,
            // Dismissal
            'contact' => $m->contact,
            'email' => $m->email,
            'contract_type' => $m->contract_type,
            'contract_type_label' => PersonnelMovement::CONTRACT_TYPES[$m->contract_type] ?? null,
            'dismissal_subtype' => $m->dismissal_subtype,
            'dismissal_subtype_label' => PersonnelMovement::DISMISSAL_SUBTYPES[$m->dismissal_subtype] ?? null,
            'early_warning' => $m->early_warning,
            'early_warning_label' => PersonnelMovement::EARLY_WARNING_TYPES[$m->early_warning] ?? null,
            'last_day_worked' => $m->last_day_worked?->format('d/m/Y'),
            'fouls' => $m->fouls,
            'days_off' => $m->days_off,
            'overtime_hours' => $m->overtime_hours,
            'fixed_fund' => $m->fixed_fund,
            'open_vacancy' => $m->open_vacancy,
            // Access control
            'access_fields' => collect(PersonnelMovement::ACCESS_FIELDS)
                ->mapWithKeys(fn ($f) => [$f => $m->{$f}])->toArray(),
            'activation_fields' => collect(PersonnelMovement::ACTIVATION_FIELDS)
                ->mapWithKeys(fn ($f) => [$f => $m->{$f}])->toArray(),
            // Follow-up
            'follow_up' => $m->followUp ? [
                'id' => $m->followUp->id,
                'uniform' => $m->followUp->uniform,
                'phone_chip' => $m->followUp->phone_chip,
                'original_card' => $m->followUp->original_card,
                'aso' => $m->followUp->aso,
                'aso_resigns' => $m->followUp->aso_resigns,
                'send_aso_guide' => $m->followUp->send_aso_guide,
                'signature_date_trct' => $m->followUp->signature_date_trct?->format('d/m/Y'),
                'termination_date' => $m->followUp->termination_date?->format('d/m/Y'),
            ] : null,
            // Reasons
            'reasons' => $m->reasons->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->toArray(),
            // Files
            'files' => $m->files->map(fn ($f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_path' => asset('storage/'.$f->file_path),
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'uploaded_by' => $f->uploadedBy?->name,
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ])->toArray(),
            // Transfer
            'origin_store_id' => $m->origin_store_id,
            'origin_store_name' => $m->originStore?->name,
            'destination_store_id' => $m->destination_store_id,
            'destination_store_name' => $m->destinationStore?->name,
            // Reactivation
            'reactivation_date' => $m->reactivation_date?->format('d/m/Y'),
            // Promotion
            'new_position_id' => $m->new_position_id,
            'new_position_name' => $m->newPosition?->name,
            // Employee details
            'employee' => $m->employee ? [
                'id' => $m->employee->id,
                'name' => $m->employee->name,
                'cpf' => $m->employee->formatted_cpf,
                'position' => $m->employee->position?->name,
                'store' => $m->employee->store?->name,
                'admission_date' => $m->employee->admission_date?->format('d/m/Y'),
            ] : null,
            // Timeline
            'status_history' => $m->statusHistory->map(fn ($h) => [
                'old_status' => $h->old_status,
                'old_status_label' => $h->old_status_label,
                'new_status' => $h->new_status,
                'new_status_label' => $h->new_status_label,
                'changed_by' => $h->changedBy?->name,
                'notes' => $h->notes,
                'created_at' => $h->created_at?->format('d/m/Y H:i'),
            ])->toArray(),
            // Transitions
            'available_transitions' => PersonnelMovement::VALID_TRANSITIONS[$m->status] ?? [],
        ]);
    }
}
