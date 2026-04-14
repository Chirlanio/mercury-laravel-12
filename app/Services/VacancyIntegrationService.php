<?php

namespace App\Services;

use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\PersonnelMovement;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ponte bidirecional entre Vacancy, PersonnelMovement e Employee.
 *
 * Fluxo "Desligamento → Vaga":
 *  Quando PersonnelMovement de tipo=dismissal com open_vacancy=true é criado,
 *  o CreateSubstitutionVacancyFromDismissal listener chama suggestVacancyForDismissal()
 *  que cria uma vaga rascunho em status=open, pré-preenchida.
 *
 * Fluxo "Vaga Finalizada → Pré-cadastro de Employee":
 *  Quando vaga é finalizada (via VacancyController::transition com to_status=finalized),
 *  o controller chama preRegisterEmployeeFromVacancy() que cria um Employee em status
 *  Pendente (status_id=1), SEM criar EmploymentContract nem ExperienceEvaluation
 *  (diferente do EmployeeController::store padrão). O DP completa os dados e ativa depois.
 *  Em seguida chama VacancyTransitionService para transitar a vaga para finalized.
 */
class VacancyIntegrationService
{
    public function __construct(
        protected VacancyTransitionService $transitionService,
    ) {}

    /**
     * Dispara ao receber um PersonnelMovement de desligamento com open_vacancy=true.
     * Cria uma vaga rascunho em estado open, com origin_movement_id preenchido
     * e replaced_employee_id = employee do desligamento.
     *
     * Retorna a vaga criada ou null se o movimento não deveria gerar vaga.
     */
    public function suggestVacancyForDismissal(PersonnelMovement $movement): ?Vacancy
    {
        if ($movement->type !== PersonnelMovement::TYPE_DISMISSAL) {
            return null;
        }

        if (! $movement->open_vacancy) {
            return null;
        }

        // Evita duplicatas: se já existe uma vaga apontando para este
        // movimento, retorna a existente em vez de criar outra.
        $existing = Vacancy::where('origin_movement_id', $movement->id)
            ->notDeleted()
            ->first();
        if ($existing) {
            return $existing;
        }

        $employee = $movement->employee;
        if (! $employee) {
            return null;
        }

        return DB::transaction(function () use ($movement, $employee) {
            // SLA previsto padrão: 30 dias. DP pode editar depois.
            $predictedSla = 30;

            $vacancy = Vacancy::create([
                'store_id' => $movement->store_id ?? $employee->store_id,
                'position_id' => $employee->position_id,
                'work_schedule_id' => null, // DP preenche ao editar
                'request_type' => VacancyRequestType::SUBSTITUTION->value,
                'replaced_employee_id' => $employee->id,
                'origin_movement_id' => $movement->id,
                'status' => VacancyStatus::OPEN->value,
                'predicted_sla_days' => $predictedSla,
                'delivery_forecast' => now()->addDays($predictedSla)->toDateString(),
                'comments' => sprintf(
                    'Vaga gerada automaticamente a partir do desligamento de %s (ID do movimento: %d).',
                    $employee->name,
                    $movement->id
                ),
                'created_by_user_id' => $movement->created_by_user_id ?? $movement->requester_id,
            ]);

            VacancyStatusHistory::create([
                'vacancy_id' => $vacancy->id,
                'from_status' => null,
                'to_status' => VacancyStatus::OPEN->value,
                'changed_by_user_id' => $movement->created_by_user_id ?? $movement->requester_id,
                'note' => 'Vaga de substituição gerada automaticamente pelo desligamento.',
                'created_at' => now(),
            ]);

            return $vacancy;
        });
    }

    /**
     * Finaliza a vaga criando um Employee em estado Pendente (pré-cadastro).
     * Substitui o que seria um PersonnelMovement de admissão — o pré-cadastro
     * é o próprio ponto de entrada no RH.
     *
     * Retorna o Employee criado. A vaga é atualizada com hired_employee_id e
     * transita para finalized através do VacancyTransitionService.
     *
     * @param  array{name:string,cpf:string,date_admission:string,note?:string}  $candidateData
     *
     * @throws ValidationException
     */
    public function preRegisterEmployeeFromVacancy(
        Vacancy $vacancy,
        array $candidateData,
        User $actor
    ): Employee {
        $this->validateCandidateData($candidateData);

        if ($vacancy->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Vaga em estado terminal não pode ser finalizada novamente.',
            ]);
        }

        if (! $vacancy->status->canTransitionTo(VacancyStatus::FINALIZED)) {
            throw ValidationException::withMessages([
                'status' => "Vaga em {$vacancy->status->label()} não pode ir direto para Finalizada. Passe por Em Admissão primeiro.",
            ]);
        }

        // Unicidade do CPF (validação pré-transação para dar erro amigável)
        if (Employee::where('cpf', $candidateData['cpf'])->exists()) {
            throw ValidationException::withMessages([
                'cpf' => 'Já existe um funcionário cadastrado com este CPF.',
            ]);
        }

        return DB::transaction(function () use ($vacancy, $candidateData, $actor) {
            // Cria Employee em estado Pendente com dados mínimos + defaults
            // para campos "requeridos com default" (short_name, birth_date,
            // education_level_id, gender_id, area_id, level, store_id).
            // NÃO cria EmploymentContract nem ExperienceEvaluation —
            // esses são criados quando o DP completar os dados e ativar.
            $employee = Employee::create([
                'name' => $candidateData['name'],
                'short_name' => strtoupper($candidateData['name']),
                'cpf' => $candidateData['cpf'],
                'admission_date' => $candidateData['date_admission'],
                'birth_date' => '1990-01-01', // placeholder, DP edita depois
                'position_id' => $vacancy->position_id,
                'store_id' => $vacancy->store_id,
                'education_level_id' => 1, // placeholder
                'gender_id' => 1, // placeholder
                'area_id' => 1, // placeholder
                'level' => 'Junior',
                'status_id' => 1, // Pendente — chave do pré-cadastro
            ]);

            // Transita a vaga para finalized via service (registra history,
            // calcula SLA efetivo, grava closing_date).
            $this->transitionService->transition(
                $vacancy,
                VacancyStatus::FINALIZED,
                $actor,
                $candidateData['note'] ?? "Vaga finalizada com pré-cadastro do funcionário {$candidateData['name']}.",
                [
                    'hired_employee_id' => $employee->id,
                    'date_admission' => $candidateData['date_admission'],
                ]
            );

            return $employee;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function validateCandidateData(array $data): void
    {
        $errors = [];

        if (empty($data['name']) || trim($data['name']) === '') {
            $errors['name'] = 'Nome do contratado é obrigatório.';
        }

        if (empty($data['cpf'])) {
            $errors['cpf'] = 'CPF do contratado é obrigatório.';
        } else {
            $cpf = preg_replace('/\D/', '', $data['cpf']);
            if (strlen($cpf) !== 11) {
                $errors['cpf'] = 'CPF deve conter 11 dígitos.';
            }
        }

        if (empty($data['date_admission'])) {
            $errors['date_admission'] = 'Data de admissão é obrigatória.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
