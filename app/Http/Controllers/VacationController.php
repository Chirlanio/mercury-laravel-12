<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Store;
use App\Models\Vacation;
use App\Models\VacationPeriod;
use App\Services\VacationCalculationService;
use App\Services\VacationPeriodGeneratorService;
use App\Services\VacationTransitionService;
use App\Services\VacationValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VacationController extends Controller
{
    public function __construct(
        private VacationValidatorService $validator,
        private VacationCalculationService $calculator,
        private VacationTransitionService $transitionService,
        private VacationPeriodGeneratorService $periodGenerator,
    ) {}

    /**
     * Listagem de férias com filtros.
     */
    public function index(Request $request)
    {
        $query = Vacation::with([
            'employee:id,name,short_name,store_id,position_id',
            'employee.store:id,code,name',
            'employee.position:id,name',
            'vacationPeriod:id,date_start_acq,date_end_acq,days_entitled,days_taken,status',
            'requestedBy:id,name',
        ])->active()->latest('date_start');

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('short_name', 'like', "%{$search}%"));
        }

        $vacations = $query->paginate(20)->through(fn ($v) => $this->formatVacation($v));

        // Estatísticas por status
        $statusCounts = [];
        foreach (Vacation::STATUS_LABELS as $status => $label) {
            $statusCounts[$status] = [
                'label' => $label,
                'color' => Vacation::STATUS_COLORS[$status],
                'count' => Vacation::active()->forStatus($status)->count(),
            ];
        }

        $selects = [
            'stores' => Store::active()->orderedByStore()->get(['id', 'code', 'name']),
            'employees' => Employee::where('status_id', 2)->orderBy('name')->get(['id', 'name', 'short_name', 'store_id']),
        ];

        return Inertia::render('Vacations/Index', [
            'vacations' => $vacations,
            'selects' => $selects,
            'filters' => $request->only(['search', 'status', 'store_id', 'employee_id']),
            'statusOptions' => Vacation::STATUS_LABELS,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Criar solicitação de férias.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'vacation_period_id' => 'required|exists:vacation_periods,id',
            'date_start' => 'required|date',
            'days_quantity' => 'required|integer|min:5|max:30',
            'installment' => 'required|integer|min:1|max:3',
            'sell_allowance' => 'boolean',
            'sell_days' => 'nullable|integer|min:0',
            'advance_13th' => 'boolean',
            'override_reason' => 'nullable|string|max:255',
            'is_retroactive' => 'boolean',
            'retroactive_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $sellDays = ($validated['sell_allowance'] ?? false) ? ($validated['sell_days'] ?? 0) : 0;

        // Calcular datas
        $dateEnd = $this->calculator->calculateEndDate($validated['date_start'], $validated['days_quantity']);
        $dateReturn = $this->calculator->calculateReturnDate($dateEnd);
        $paymentDeadline = ($validated['is_retroactive'] ?? false) ? null : $this->calculator->calculatePaymentDeadline($validated['date_start']);
        $defaultDays = $this->calculator->getDefaultDaysByPosition($employee->position_id ?? 0);
        $isOverride = $validated['days_quantity'] != $defaultDays;

        // Preparar dados para validação
        $vacationData = [
            ...$validated,
            'date_end' => $dateEnd,
            'sell_days' => $sellDays,
            'default_days_override' => $isOverride,
        ];

        // Validar regras CLT
        $isRetroactive = $validated['is_retroactive'] ?? false;
        $validation = $isRetroactive
            ? $this->validator->validateRetroactive($vacationData)
            : $this->validator->validateAll($vacationData);

        if (! $validation['valid']) {
            return back()->withErrors(['vacation' => implode(' ', $validation['errors'])]);
        }

        $vacation = DB::transaction(function () use ($validated, $employee, $dateEnd, $dateReturn, $paymentDeadline, $sellDays, $isOverride, $isRetroactive) {
            $vacation = Vacation::create([
                'vacation_period_id' => $validated['vacation_period_id'],
                'employee_id' => $validated['employee_id'],
                'store_id' => $employee->store_id,
                'date_start' => $validated['date_start'],
                'date_end' => $dateEnd,
                'date_return' => $dateReturn,
                'days_quantity' => $validated['days_quantity'],
                'installment' => $validated['installment'],
                'sell_allowance' => $validated['sell_allowance'] ?? false,
                'sell_days' => $sellDays,
                'advance_13th' => $validated['advance_13th'] ?? false,
                'payment_deadline' => $paymentDeadline,
                'default_days_override' => $isOverride,
                'override_reason' => $validated['override_reason'] ?? null,
                'status' => $isRetroactive ? Vacation::STATUS_COMPLETED : Vacation::STATUS_DRAFT,
                'is_retroactive' => $isRetroactive,
                'retroactive_reason' => $validated['retroactive_reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_by_user_id' => auth()->id(),
                'created_by_user_id' => auth()->id(),
            ]);

            if ($isRetroactive) {
                // Retroativa: criar como finalizada e atualizar período
                $vacation->update([
                    'hr_approved_by_user_id' => auth()->id(),
                    'hr_approved_at' => now(),
                    'finalized_at' => now(),
                ]);

                $period = $vacation->vacationPeriod;
                $period->increment('days_taken', $vacation->days_quantity);
                if ($sellDays > 0) {
                    $period->increment('sell_days', $sellDays);
                }

                $this->transitionService->logAction(
                    $vacation->id,
                    'RETROACTIVE_CREATED',
                    null,
                    Vacation::STATUS_COMPLETED,
                    auth()->id(),
                    $validated['retroactive_reason'] ?? null
                );
            } else {
                $this->transitionService->logAction(
                    $vacation->id,
                    'CREATED',
                    null,
                    Vacation::STATUS_DRAFT,
                    auth()->id()
                );
            }

            return $vacation;
        });

        $msg = $isRetroactive ? 'Férias retroativas registradas com sucesso.' : 'Solicitação de férias criada com sucesso.';

        return redirect()->route('vacations.index')->with('success', $msg);
    }

    /**
     * Detalhes da solicitação.
     */
    public function show(Vacation $vacation)
    {
        $vacation->load([
            'employee:id,name,short_name,cpf,store_id,position_id,admission_date,birth_date,status_id',
            'employee.store:id,code,name',
            'employee.position:id,name',
            'employee.employeeStatus:id,description_name',
            'vacationPeriod',
            'requestedBy:id,name',
            'managerApprovedBy:id,name',
            'hrApprovedBy:id,name',
            'rejectedBy:id,name',
            'cancelledBy:id,name',
            'createdBy:id,name',
            'logs.changedBy:id,name',
        ]);

        return response()->json([
            'vacation' => $this->formatVacationDetailed($vacation),
            'period' => [
                'id' => $vacation->vacationPeriod->id,
                'label' => $vacation->vacationPeriod->period_label,
                'status' => $vacation->vacationPeriod->status_label,
                'days_entitled' => $vacation->vacationPeriod->days_entitled,
                'days_taken' => $vacation->vacationPeriod->days_taken,
                'balance' => $vacation->vacationPeriod->days_balance,
            ],
            'logs' => $vacation->logs->map(fn ($l) => [
                'id' => $l->id,
                'action_type' => $l->action_type,
                'old_status' => $l->old_status_label,
                'new_status' => $l->new_status_label,
                'changed_by' => $l->changedBy?->name,
                'notes' => $l->notes,
                'created_at' => $l->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    /**
     * Atualizar rascunho de férias.
     */
    public function update(Request $request, Vacation $vacation)
    {
        if ($vacation->status !== Vacation::STATUS_DRAFT) {
            return back()->withErrors(['vacation' => 'Somente rascunhos podem ser editados.']);
        }

        $validated = $request->validate([
            'date_start' => 'required|date',
            'days_quantity' => 'required|integer|min:5|max:30',
            'installment' => 'required|integer|min:1|max:3',
            'sell_allowance' => 'boolean',
            'sell_days' => 'nullable|integer|min:0',
            'advance_13th' => 'boolean',
            'override_reason' => 'nullable|string|max:255',
        ]);

        $employee = $vacation->employee;
        $sellDays = ($validated['sell_allowance'] ?? false) ? ($validated['sell_days'] ?? 0) : 0;
        $dateEnd = $this->calculator->calculateEndDate($validated['date_start'], $validated['days_quantity']);
        $dateReturn = $this->calculator->calculateReturnDate($dateEnd);
        $paymentDeadline = $this->calculator->calculatePaymentDeadline($validated['date_start']);
        $defaultDays = $this->calculator->getDefaultDaysByPosition($employee->position_id ?? 0);

        $vacationData = [
            ...$validated,
            'employee_id' => $vacation->employee_id,
            'vacation_period_id' => $vacation->vacation_period_id,
            'date_end' => $dateEnd,
            'sell_days' => $sellDays,
            'default_days_override' => $validated['days_quantity'] != $defaultDays,
        ];

        $validation = $this->validator->validateAll($vacationData, $vacation->id);
        if (! $validation['valid']) {
            return back()->withErrors(['vacation' => implode(' ', $validation['errors'])]);
        }

        $vacation->update([
            ...$validated,
            'date_end' => $dateEnd,
            'date_return' => $dateReturn,
            'payment_deadline' => $paymentDeadline,
            'sell_days' => $sellDays,
            'default_days_override' => $validated['days_quantity'] != $defaultDays,
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('vacations.index')->with('success', 'Solicitação atualizada com sucesso.');
    }

    /**
     * Transição de status.
     */
    public function transition(Request $request, Vacation $vacation)
    {
        $validated = $request->validate([
            'new_status' => 'required|string',
            'notes' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
        ]);

        $validation = $this->transitionService->validateTransition($vacation, $validated['new_status'], $validated);

        if (! $validation['valid']) {
            return response()->json(['error' => true, 'message' => implode(' ', $validation['errors'])], 422);
        }

        $this->transitionService->executeTransition($vacation, $validated['new_status'], $validated, auth()->id());

        return response()->json([
            'error' => false,
            'message' => 'Status atualizado para: '.(Vacation::STATUS_LABELS[$validated['new_status']] ?? $validated['new_status']),
        ]);
    }

    /**
     * Excluir rascunho.
     */
    public function destroy(Vacation $vacation)
    {
        if ($vacation->status !== Vacation::STATUS_DRAFT) {
            return response()->json(['error' => true, 'message' => 'Somente rascunhos podem ser excluídos. Use cancelamento para outros status.'], 422);
        }

        $vacation->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('vacations.index')->with('success', 'Solicitação excluída.');
    }

    /**
     * Saldo de férias de um funcionário.
     */
    public function balance(Request $request, Employee $employee)
    {
        // Gerar períodos se necessário
        $this->periodGenerator->generateForEmployee($employee->id);

        $periods = VacationPeriod::forEmployee($employee->id)
            ->withBalance()
            ->orderBy('date_start_acq')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => $p->period_label,
                'status' => $p->status_label,
                'status_color' => $p->status_color,
                'days_entitled' => $p->days_entitled,
                'days_taken' => $p->days_taken,
                'sell_days' => $p->sell_days,
                'balance' => $p->days_balance,
                'date_limit' => $p->date_limit_concessive->format('d/m/Y'),
                'is_expired' => $p->is_expired,
            ]);

        $defaultDays = $this->calculator->getDefaultDaysByPosition($employee->position_id ?? 0);

        return response()->json([
            'periods' => $periods,
            'default_days' => $defaultDays,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'admission_date' => $employee->admission_date?->format('d/m/Y'),
                'position' => $employee->position?->name,
            ],
        ]);
    }

    /**
     * Verificar data contra blackout rules.
     */
    public function checkDate(Request $request)
    {
        $date = $request->input('date');
        if (! $date) {
            return response()->json(['valid' => false, 'errors' => ['Data é obrigatória.']]);
        }

        $valid = $this->calculator->isValidStartDate($date);
        $suggested = ! $valid ? $this->calculator->suggestNextValidDate($date) : null;

        return response()->json([
            'valid' => $valid,
            'suggested' => $suggested,
            'suggested_formatted' => $suggested ? date('d/m/Y', strtotime($suggested)) : null,
        ]);
    }

    /**
     * Gerar períodos aquisitivos.
     */
    public function generatePeriods(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if ($employeeId) {
            $result = $this->periodGenerator->generateForEmployee($employeeId);
        } else {
            $storeId = $request->input('store_id');
            $result = $this->periodGenerator->generateForAllEmployees($storeId);
        }

        return response()->json([
            'error' => false,
            'message' => "Períodos gerados: {$result['created']} criados, {$result['skipped']} já existiam.",
            ...$result,
        ]);
    }

    // ==========================================
    // Holidays CRUD
    // ==========================================

    public function holidays()
    {
        $holidays = Holiday::active()->orderBy('date')->get();

        return response()->json(['holidays' => $holidays]);
    }

    public function storeHoliday(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date',
            'type' => 'required|in:nacional,estadual,municipal',
            'is_recurring' => 'boolean',
        ]);

        Holiday::create($validated);

        return response()->json(['error' => false, 'message' => 'Feriado cadastrado.']);
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date',
            'type' => 'required|in:nacional,estadual,municipal',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $holiday->update($validated);

        return response()->json(['error' => false, 'message' => 'Feriado atualizado.']);
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->update(['is_active' => false]);

        return response()->json(['error' => false, 'message' => 'Feriado desativado.']);
    }

    // ==========================================
    // Private helpers
    // ==========================================

    private function formatVacation(Vacation $v): array
    {
        return [
            'id' => $v->id,
            'employee_name' => $v->employee?->name,
            'employee_short_name' => $v->employee?->short_name,
            'store' => $v->employee?->store ? ['id' => $v->employee->store->id, 'name' => $v->employee->store->display_name ?? $v->employee->store->name] : null,
            'position' => $v->employee?->position?->name,
            'date_start' => $v->date_start->format('d/m/Y'),
            'date_end' => $v->date_end->format('d/m/Y'),
            'date_return' => $v->date_return->format('d/m/Y'),
            'days_quantity' => $v->days_quantity,
            'installment' => $v->installment,
            'status' => $v->status,
            'status_label' => $v->status_label,
            'status_color' => $v->status_color,
            'sell_allowance' => $v->sell_allowance,
            'sell_days' => $v->sell_days,
            'advance_13th' => $v->advance_13th,
            'is_retroactive' => $v->is_retroactive,
            'period_label' => $v->vacationPeriod?->period_label,
            'payment_deadline' => $v->payment_deadline?->format('d/m/Y'),
            'requested_by' => $v->requestedBy?->name,
            'created_at' => $v->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatVacationDetailed(Vacation $v): array
    {
        return array_merge($this->formatVacation($v), [
            'employee' => [
                'id' => $v->employee->id,
                'name' => $v->employee->name,
                'cpf' => $v->employee->formatted_cpf ?? $v->employee->cpf,
                'store' => $v->employee->store?->name,
                'position' => $v->employee->position?->name,
                'admission_date' => $v->employee->admission_date?->format('d/m/Y'),
                'birth_date' => $v->employee->birth_date?->format('d/m/Y'),
                'status' => $v->employee->employeeStatus?->description_name,
            ],
            'override_reason' => $v->override_reason,
            'manager_approved_by' => $v->managerApprovedBy?->name,
            'manager_approved_at' => $v->manager_approved_at?->format('d/m/Y H:i'),
            'manager_notes' => $v->manager_notes,
            'hr_approved_by' => $v->hrApprovedBy?->name,
            'hr_approved_at' => $v->hr_approved_at?->format('d/m/Y H:i'),
            'hr_notes' => $v->hr_notes,
            'rejected_by' => $v->rejectedBy?->name,
            'rejected_at' => $v->rejected_at?->format('d/m/Y H:i'),
            'rejection_reason' => $v->rejection_reason,
            'cancelled_by' => $v->cancelledBy?->name,
            'cancelled_at' => $v->cancelled_at?->format('d/m/Y H:i'),
            'cancellation_reason' => $v->cancellation_reason,
            'retroactive_reason' => $v->retroactive_reason,
            'employee_acknowledged_at' => $v->employee_acknowledged_at?->format('d/m/Y H:i'),
        ]);
    }
}
