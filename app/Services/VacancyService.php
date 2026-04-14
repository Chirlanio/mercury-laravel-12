<?php

namespace App\Services;

use App\Enums\VacancyRequestType;
use App\Enums\VacancyStatus;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD + regras de negócio de vagas. Transições de status vivem em
 * VacancyTransitionService (não mutar Vacancy::status direto daqui).
 *
 * Regras principais:
 *  - request_type=substitution exige replaced_employee_id
 *  - SLA NUNCA vem do payload de criação. É resolvido automaticamente do
 *    `level_category_id` da position:
 *      - Gerencial (level_category_id=1) → 40 dias
 *      - Qualquer outra (Operacional, Aprendiz, etc.) → 20 dias
 *    Apenas usuários com EDIT_VACANCIES (recrutadores/RH) podem alterar
 *    o SLA depois via update() — gestores de loja que só têm CREATE
 *    nunca conseguem mudar o SLA.
 *  - delivery_forecast = created_at + SLA resolvido
 *  - Toda criação dispara entrada inicial em vacancy_status_history
 *    (from_status=null, to_status=open)
 *  - delete é soft-delete com motivo obrigatório
 */
class VacancyService
{
    /**
     * SLA padrão em dias para vagas gerenciais (level_category_id=1).
     */
    public const SLA_MANAGERIAL_DAYS = 40;

    /**
     * SLA padrão em dias para vagas operacionais / demais categorias.
     */
    public const SLA_OPERATIONAL_DAYS = 20;

    /**
     * Resolve o SLA padrão em dias a partir do nível da position.
     * Gerencial (category 1) → 40. Qualquer outra → 20.
     */
    public function resolveSlaForPosition(Position $position): int
    {
        return (int) $position->level_category_id === 1
            ? self::SLA_MANAGERIAL_DAYS
            : self::SLA_OPERATIONAL_DAYS;
    }

    /**
     * Cria uma nova vaga e registra a entrada inicial no histórico.
     *
     * O SLA é calculado automaticamente a partir do position — o payload
     * NÃO deve conter predicted_sla_days. Apenas o recrutador, via update(),
     * pode alterar o SLA depois da criação.
     *
     * @throws ValidationException quando replaced_employee_id falta num tipo substitution
     */
    public function create(array $data, User $actor): Vacancy
    {
        $this->validateRequestTypeRules($data);

        return DB::transaction(function () use ($data, $actor) {
            $position = Position::findOrFail($data['position_id']);
            $predictedSla = $this->resolveSlaForPosition($position);
            $deliveryForecast = Carbon::today()->addDays($predictedSla)->toDateString();

            $vacancy = Vacancy::create([
                'store_id' => $data['store_id'],
                'position_id' => $position->id,
                'work_schedule_id' => $data['work_schedule_id'] ?? null,
                'request_type' => $data['request_type'],
                'replaced_employee_id' => $data['replaced_employee_id'] ?? null,
                'origin_movement_id' => $data['origin_movement_id'] ?? null,
                'status' => VacancyStatus::OPEN->value,
                'recruiter_id' => $data['recruiter_id'] ?? null,
                'predicted_sla_days' => $predictedSla,
                'delivery_forecast' => $deliveryForecast,
                'comments' => $data['comments'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            // Entrada inicial no histórico (from_status null = criação)
            VacancyStatusHistory::create([
                'vacancy_id' => $vacancy->id,
                'from_status' => null,
                'to_status' => VacancyStatus::OPEN->value,
                'changed_by_user_id' => $actor->id,
                'note' => $data['creation_note'] ?? null,
                'created_at' => now(),
            ]);

            return $vacancy->fresh(['store', 'position', 'workSchedule']);
        });
    }

    /**
     * Atualiza campos editáveis da vaga. Nunca altera: store_id, position_id,
     * request_type, replaced_employee_id, status. Para status, use
     * VacancyTransitionService.
     */
    public function update(Vacancy $vacancy, array $data, User $actor): Vacancy
    {
        $editable = [
            'work_schedule_id', 'recruiter_id', 'predicted_sla_days',
            'delivery_forecast', 'interview_hr', 'evaluators_hr',
            'interview_leader', 'evaluators_leader', 'comments',
        ];

        $update = array_intersect_key($data, array_flip($editable));
        $update['updated_by_user_id'] = $actor->id;

        $vacancy->update($update);

        return $vacancy->fresh(['store', 'position', 'workSchedule', 'recruiter']);
    }

    /**
     * Soft delete com motivo obrigatório.
     */
    public function delete(Vacancy $vacancy, User $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'Motivo da exclusão é obrigatório.',
            ]);
        }

        $vacancy->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
        ]);
    }

    /**
     * Estatísticas para a grid de KPIs (StatisticsGrid no frontend).
     * Se $storeCode for passado, filtra por loja (usado no scoping de
     * gestores que só veem a própria loja).
     *
     * @return array{total_active:int,open:int,processing:int,in_admission:int,overdue:int,finalized_last_30d:int,avg_effective_sla:?float}
     */
    public function getStatistics(?string $storeCode = null): array
    {
        $base = Vacancy::query()->notDeleted();
        if ($storeCode) {
            $base->forStore($storeCode);
        }

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $overdue = (clone $base)->overdue()->count();

        $finalizedLast30d = (clone $base)
            ->forStatus(VacancyStatus::FINALIZED)
            ->where('closing_date', '>=', now()->subDays(30))
            ->count();

        $avgSla = (clone $base)
            ->forStatus(VacancyStatus::FINALIZED)
            ->whereNotNull('effective_sla_days')
            ->avg('effective_sla_days');

        return [
            'total_active' => (int) (
                ($byStatus[VacancyStatus::OPEN->value] ?? 0)
                + ($byStatus[VacancyStatus::PROCESSING->value] ?? 0)
                + ($byStatus[VacancyStatus::IN_ADMISSION->value] ?? 0)
            ),
            'open' => (int) ($byStatus[VacancyStatus::OPEN->value] ?? 0),
            'processing' => (int) ($byStatus[VacancyStatus::PROCESSING->value] ?? 0),
            'in_admission' => (int) ($byStatus[VacancyStatus::IN_ADMISSION->value] ?? 0),
            'overdue' => $overdue,
            'finalized_last_30d' => $finalizedLast30d,
            'avg_effective_sla' => $avgSla !== null ? (float) round($avgSla, 1) : null,
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function validateRequestTypeRules(array $data): void
    {
        $type = $data['request_type'] ?? null;
        if (! $type) {
            throw ValidationException::withMessages([
                'request_type' => 'Tipo de solicitação é obrigatório.',
            ]);
        }

        $typeEnum = VacancyRequestType::tryFrom($type);
        if (! $typeEnum) {
            throw ValidationException::withMessages([
                'request_type' => 'Tipo de solicitação inválido.',
            ]);
        }

        if ($typeEnum->requiresReplacedEmployee() && empty($data['replaced_employee_id'])) {
            throw ValidationException::withMessages([
                'replaced_employee_id' => 'Substituição exige informar o colaborador a ser substituído.',
            ]);
        }
    }
}
