<?php

namespace App\Services;

use App\Enums\VacancyStatus;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de vagas. Ponto único de mutação de Vacancy::status.
 * Outros serviços e controllers NUNCA devem setar o campo direto.
 *
 * Transições válidas:
 *   open → [processing, cancelled]
 *   processing → [open, in_admission, cancelled]
 *   in_admission → [processing, finalized, cancelled]
 *   finalized → [] (terminal)
 *   cancelled → [] (terminal)
 *
 * Regras por transição:
 *  - → processing: exige recruiter_id (do payload ou já no registro)
 *  - → in_admission: exige recruiter_id
 *  - → finalized: exige hired_employee_id + date_admission.
 *                 Normalmente chamado via VacancyIntegrationService que
 *                 cria o Employee em pré-cadastro e só depois transita.
 *  - → cancelled: exige note (motivo do cancelamento)
 *
 * Ao entrar em estado terminal: grava closing_date = today,
 * e se for finalized, calcula effective_sla_days a partir de created_at.
 */
class VacancyTransitionService
{
    /**
     * @param  array  $extras  Campos adicionais vindos do payload:
     *                         - recruiter_id (para open → processing)
     *                         - hired_employee_id, date_admission (para in_admission → finalized)
     *
     * @throws ValidationException
     */
    public function transition(
        Vacancy $vacancy,
        VacancyStatus|string $toStatus,
        User $actor,
        ?string $note = null,
        array $extras = []
    ): Vacancy {
        $target = $toStatus instanceof VacancyStatus ? $toStatus : VacancyStatus::from($toStatus);
        $current = $vacancy->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        // Atribuir recruiter na transição para processing (ou validar que existe)
        if ($target === VacancyStatus::PROCESSING) {
            $recruiterId = $extras['recruiter_id'] ?? $vacancy->recruiter_id;
            if (! $recruiterId) {
                throw ValidationException::withMessages([
                    'recruiter_id' => 'É obrigatório atribuir um recrutador antes de mover para Em Processamento.',
                ]);
            }
        }

        if ($target === VacancyStatus::IN_ADMISSION && ! $vacancy->recruiter_id && empty($extras['recruiter_id'])) {
            throw ValidationException::withMessages([
                'recruiter_id' => 'Vaga sem recrutador atribuído não pode ir para Em Admissão.',
            ]);
        }

        if ($target === VacancyStatus::FINALIZED) {
            $hiredEmployeeId = $extras['hired_employee_id'] ?? $vacancy->hired_employee_id;
            $dateAdmission = $extras['date_admission'] ?? $vacancy->date_admission;

            if (! $hiredEmployeeId || ! $dateAdmission) {
                throw ValidationException::withMessages([
                    'hired_employee_id' => 'Finalização exige funcionário contratado e data de admissão. Use o fluxo de pré-cadastro.',
                ]);
            }

            $employee = Employee::find($hiredEmployeeId);
            if (! $employee) {
                throw ValidationException::withMessages([
                    'hired_employee_id' => 'Funcionário informado não foi encontrado.',
                ]);
            }
        }

        if ($target === VacancyStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        return DB::transaction(function () use ($vacancy, $current, $target, $actor, $note, $extras) {
            $update = [
                'status' => $target->value,
                'updated_by_user_id' => $actor->id,
            ];

            if ($target === VacancyStatus::PROCESSING && isset($extras['recruiter_id'])) {
                $update['recruiter_id'] = $extras['recruiter_id'];
            }

            if ($target === VacancyStatus::IN_ADMISSION && isset($extras['recruiter_id'])) {
                $update['recruiter_id'] = $extras['recruiter_id'];
            }

            if ($target === VacancyStatus::FINALIZED) {
                $update['hired_employee_id'] = $extras['hired_employee_id'] ?? $vacancy->hired_employee_id;
                $update['date_admission'] = $extras['date_admission'] ?? $vacancy->date_admission;
                $update['closing_date'] = now()->toDateString();
                $update['effective_sla_days'] = $this->calculateEffectiveSla($vacancy);
            }

            if ($target === VacancyStatus::CANCELLED) {
                $update['closing_date'] = now()->toDateString();
            }

            $vacancy->update($update);

            VacancyStatusHistory::create([
                'vacancy_id' => $vacancy->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            return $vacancy->fresh(['store', 'position', 'recruiter', 'hiredEmployee', 'statusHistory']);
        });
    }

    /**
     * SLA efetivo = dias entre criação e fechamento.
     */
    protected function calculateEffectiveSla(Vacancy $vacancy): int
    {
        $start = Carbon::parse($vacancy->created_at)->startOfDay();
        $end = now()->startOfDay();

        return max(0, (int) $start->diffInDays($end));
    }
}
