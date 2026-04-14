<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Store;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\WorkSchedule;
use App\Services\VacancyIntegrationService;
use App\Services\VacancyService;
use App\Services\VacancyTransitionService;
// SLA constants used in the Inertia payload so the frontend can preview
// "qual SLA será aplicado" conforme o cargo escolhido, sem precisar de
// um endpoint dedicado.
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VacancyController extends Controller
{
    public function __construct(
        private VacancyService $vacancyService,
        private VacancyTransitionService $transitionService,
        private VacancyIntegrationService $integrationService,
    ) {}

    /**
     * Lista paginada de vagas com filtros e estatísticas.
     * Aplica hard-scoping por store se o usuário não tiver MANAGE_VACANCIES.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = Vacancy::with([
            'store', 'position', 'workSchedule', 'recruiter',
            'replacedEmployee', 'hiredEmployee', 'originMovement',
        ])
            ->notDeleted()
            ->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            // Default: mostra apenas vagas ativas (não-terminais).
            $query->active();
        }

        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }

        if ($request->filled('recruiter_id')) {
            $query->where('recruiter_id', $request->recruiter_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $vacancies = $query->paginate(15)->through(fn ($v) => $this->formatVacancy($v));

        $statistics = $this->vacancyService->getStatistics($scopedStoreCode);

        return Inertia::render('Vacancies/Index', [
            'vacancies' => $vacancies,
            'filters' => $request->only([
                'store_id', 'status', 'request_type', 'recruiter_id',
                'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'statusOptions' => VacancyStatus::labels(),
            'statusColors' => VacancyStatus::colors(),
            'statusTransitions' => VacancyStatus::transitionMap(),
            'requestTypeOptions' => VacancyRequestType::labels(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                // Inclui level_category_id para o frontend calcular o SLA
                // que será aplicado (40d Gerencial / 20d operacional) antes
                // mesmo de submeter o form.
                'positions' => Position::orderBy('name')->get(['id', 'name', 'level_category_id']),
                'workSchedules' => WorkSchedule::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'slaDefaults' => [
                'managerial_days' => VacancyService::SLA_MANAGERIAL_DAYS,
                'operational_days' => VacancyService::SLA_OPERATIONAL_DAYS,
                'managerial_category_id' => 1,
            ],
        ]);
    }

    /**
     * Estatísticas JSON (para polling/atualização dos cards sem reload).
     */
    public function statistics(Request $request): JsonResponse
    {
        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return response()->json([
            'statistics' => $this->vacancyService->getStatistics($scopedStoreCode),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        // predicted_sla_days e delivery_forecast NÃO são aceitos aqui — o
        // SLA é resolvido automaticamente do nível da position (Gerencial
        // = 40d, Operacional = 20d). Só o recrutador pode ajustar depois,
        // via update() que exige permissão EDIT_VACANCIES.
        $data = $request->validate([
            'store_id' => 'required|string|max:10|exists:stores,code',
            'position_id' => 'required|exists:positions,id',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'request_type' => 'required|in:substitution,headcount_increase,floater',
            'replaced_employee_id' => 'nullable|exists:employees,id',
            'comments' => 'nullable|string|max:5000',
        ]);

        if ($scopedStoreCode && $data['store_id'] !== $scopedStoreCode) {
            return redirect()->back()->withErrors([
                'store_id' => 'Você só pode abrir vagas para a sua própria loja.',
            ])->withInput();
        }

        $this->vacancyService->create($data, $user);

        return redirect()->route('vacancies.index')
            ->with('success', 'Vaga criada com sucesso.');
    }

    public function show(Vacancy $vacancy, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $vacancy);

        $vacancy->load([
            'store', 'position', 'workSchedule', 'recruiter',
            'replacedEmployee.store', 'replacedEmployee.position',
            'hiredEmployee.store', 'hiredEmployee.position',
            'originMovement', 'createdBy', 'updatedBy',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'vacancy' => $this->formatVacancyDetailed($vacancy),
        ]);
    }

    public function update(Vacancy $vacancy, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $vacancy);

        $data = $request->validate([
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'recruiter_id' => 'nullable|exists:users,id',
            'predicted_sla_days' => 'nullable|integer|min:1|max:365',
            'delivery_forecast' => 'nullable|date',
            'interview_hr' => 'nullable|string|max:10000',
            'evaluators_hr' => 'nullable|string|max:500',
            'interview_leader' => 'nullable|string|max:10000',
            'evaluators_leader' => 'nullable|string|max:500',
            'comments' => 'nullable|string|max:5000',
        ]);

        $this->vacancyService->update($vacancy, $data, $request->user());

        return redirect()->route('vacancies.index')
            ->with('success', 'Vaga atualizada com sucesso.');
    }

    public function destroy(Vacancy $vacancy, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $vacancy);

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        $this->vacancyService->delete($vacancy, $request->user(), $data['deleted_reason']);

        return redirect()->route('vacancies.index')
            ->with('success', 'Vaga excluída com sucesso.');
    }

    /**
     * Transição de status. Se o target for finalized, dispara o fluxo de
     * pré-cadastro de funcionário via VacancyIntegrationService.
     */
    public function transition(Vacancy $vacancy, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $vacancy);

        $data = $request->validate([
            'to_status' => 'required|in:open,processing,in_admission,finalized,cancelled',
            'note' => 'nullable|string|max:2000',
            'recruiter_id' => 'nullable|exists:users,id',
            // Usados quando to_status=finalized (pré-cadastro de funcionário)
            'name' => 'nullable|string|max:100',
            'cpf' => 'nullable|string|max:14',
            'date_admission' => 'nullable|date',
        ]);

        $user = $request->user();

        if ($data['to_status'] === VacancyStatus::FINALIZED->value) {
            // Fluxo de finalização cria pré-cadastro de employee
            $cpf = preg_replace('/\D/', '', (string) ($data['cpf'] ?? ''));

            $employee = $this->integrationService->preRegisterEmployeeFromVacancy(
                $vacancy,
                [
                    'name' => $data['name'] ?? '',
                    'cpf' => $cpf,
                    'date_admission' => $data['date_admission'] ?? '',
                    'note' => $data['note'] ?? null,
                ],
                $user
            );

            return redirect()->route('vacancies.index')->with(
                'success',
                "Vaga finalizada. Pré-cadastro criado para {$employee->name}. Complete os dados em Funcionários."
            );
        }

        $this->transitionService->transition(
            $vacancy,
            $data['to_status'],
            $user,
            $data['note'] ?? null,
            ['recruiter_id' => $data['recruiter_id'] ?? null]
        );

        return redirect()->route('vacancies.index')
            ->with('success', 'Status da vaga atualizado.');
    }

    /**
     * Lista de funcionários ativos da loja (para o select de "colaborador a
     * substituir" no modal de criação quando request_type=substitution).
     */
    public function eligibleEmployeesForSubstitution(Request $request): JsonResponse
    {
        $storeCode = $request->input('store_id');
        if (! $storeCode) {
            return response()->json(['employees' => []]);
        }

        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());
        if ($scopedStoreCode && $scopedStoreCode !== $storeCode) {
            return response()->json(['employees' => []]);
        }

        $employees = Employee::where('store_id', $storeCode)
            ->whereIn('status_id', [2]) // Ativo
            ->orderBy('name')
            ->get(['id', 'name', 'cpf', 'position_id', 'store_id']);

        return response()->json(['employees' => $employees]);
    }

    /**
     * Lista de usuários que podem atuar como recrutadores.
     * Users com MANAGE_VACANCIES via Role/CentralRoleResolver.
     */
    public function availableRecruiters(Request $request): JsonResponse
    {
        $recruiters = User::orderBy('name')->get(['id', 'name', 'email', 'role'])
            ->filter(fn (User $u) => $u->hasPermissionTo(Permission::MANAGE_VACANCIES))
            ->values();

        return response()->json(['recruiters' => $recruiters]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Retorna o store_code ao qual o usuário está "preso" (store scoping),
     * ou null se ele puder ver/operar em todas as lojas.
     *
     * Regra: quem não tem MANAGE_VACANCIES é considerado gestor de loja
     * e só pode ver/criar vagas da própria loja (user.store_id).
     */
    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_VACANCIES)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function ensureCanView(?User $user, Vacancy $vacancy): void
    {
        $scoped = $this->resolveScopedStoreCode($user);
        if ($scoped && $vacancy->store_id !== $scoped) {
            abort(403, 'Você não tem acesso a vagas de outras lojas.');
        }
    }

    protected function formatVacancy(Vacancy $v): array
    {
        return [
            'id' => $v->id,
            'store_id' => $v->store_id,
            'store_name' => $v->store?->name,
            'position_id' => $v->position_id,
            'position_name' => $v->position?->name,
            'work_schedule_id' => $v->work_schedule_id,
            'work_schedule_name' => $v->workSchedule?->name,
            'request_type' => $v->request_type?->value,
            'request_type_label' => $v->request_type?->label(),
            'request_type_color' => $v->request_type?->color(),
            'replaced_employee_id' => $v->replaced_employee_id,
            'replaced_employee_name' => $v->replacedEmployee?->name,
            'origin_movement_id' => $v->origin_movement_id,
            'status' => $v->status?->value,
            'status_label' => $v->status?->label(),
            'status_color' => $v->status?->color(),
            'recruiter_id' => $v->recruiter_id,
            'recruiter_name' => $v->recruiter?->name,
            'predicted_sla_days' => $v->predicted_sla_days,
            'effective_sla_days' => $v->effective_sla_days,
            'delivery_forecast' => $v->delivery_forecast?->toDateString(),
            'closing_date' => $v->closing_date?->toDateString(),
            'hired_employee_id' => $v->hired_employee_id,
            'hired_employee_name' => $v->hiredEmployee?->name,
            'date_admission' => $v->date_admission?->toDateString(),
            'comments' => $v->comments,
            'is_overdue' => $v->isOverdue(),
            'is_terminal' => $v->isTerminal(),
            'created_at' => $v->created_at?->toDateTimeString(),
        ];
    }

    protected function formatVacancyDetailed(Vacancy $v): array
    {
        return array_merge($this->formatVacancy($v), [
            'interview_hr' => $v->interview_hr,
            'evaluators_hr' => $v->evaluators_hr,
            'interview_leader' => $v->interview_leader,
            'evaluators_leader' => $v->evaluators_leader,
            'created_by_name' => $v->createdBy?->name,
            'updated_by_name' => $v->updatedBy?->name,
            'origin_movement' => $v->originMovement ? [
                'id' => $v->originMovement->id,
                'type' => $v->originMovement->type,
                'effective_date' => $v->originMovement->effective_date?->toDateString(),
            ] : null,
            'replaced_employee' => $v->replacedEmployee ? [
                'id' => $v->replacedEmployee->id,
                'name' => $v->replacedEmployee->name,
                'cpf' => $v->replacedEmployee->cpf,
            ] : null,
            'hired_employee' => $v->hiredEmployee ? [
                'id' => $v->hiredEmployee->id,
                'name' => $v->hiredEmployee->name,
                'cpf' => $v->hiredEmployee->cpf,
                'status_id' => $v->hiredEmployee->status_id,
            ] : null,
            'status_history' => $v->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
