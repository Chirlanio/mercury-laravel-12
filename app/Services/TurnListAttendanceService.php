<?php

namespace App\Services;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListAttendanceOutcome;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operações sobre atendimentos da Lista da Vez.
 *
 * Captura `original_queue_position` no `start()` antes de remover a
 * consultora da fila — usada no `finish()` para o algoritmo de
 * "voltar na vez" (restore_queue_position).
 *
 * No `finish()`, se o outcome tiver `restore_queue_position=true`, a
 * consultora volta na posição original AJUSTADA pelo algoritmo:
 *
 *   adjustedPosition = max(1, original_queue_position - aheadCount)
 *
 *   onde aheadCount = quantas consultoras estavam à FRENTE na fila E
 *   também saíram pra atender DEPOIS desta (e ainda estão atendendo).
 *
 * Isso evita "buracos" quando várias consultoras saem juntas: se a
 * consultora estava em pos=5 e 3 da frente já saíram pra atender depois
 * dela, ela volta em pos=2 (5-3) em vez de pos=5 (que pode não existir
 * mais).
 */
class TurnListAttendanceService
{
    public function __construct(
        protected TurnListQueueService $queueService,
    ) {}

    /**
     * Inicia atendimento. Captura posição original (se estava na fila),
     * remove da fila e cria registro.
     *
     * @throws ValidationException
     */
    public function start(int $employeeId, string $storeCode, ?User $actor = null): TurnListAttendance
    {
        return DB::transaction(function () use ($employeeId, $storeCode, $actor) {
            $this->ensureNotAttending($employeeId);

            // Captura ANTES de sair da fila
            $originalPosition = $this->queueService->getPosition($employeeId, $storeCode);

            // Remove da fila (silencioso — pode não estar na fila ainda,
            // ex: chamada vinda do "Disponível" direto)
            $this->queueService->leave($employeeId, $storeCode);

            return TurnListAttendance::create([
                'employee_id' => $employeeId,
                'store_code' => $storeCode,
                'original_queue_position' => $originalPosition,
                'status' => TurnListAttendanceStatus::ACTIVE->value,
                'started_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Finaliza atendimento — registra outcome, calcula duração, e
     * decide se volta para fila (e em que posição).
     *
     * @throws ValidationException
     */
    public function finish(
        TurnListAttendance $attendance,
        int $outcomeId,
        bool $returnToQueue = true,
        ?string $notes = null,
        ?User $actor = null,
    ): TurnListAttendance {
        if (! $attendance->is_active) {
            throw ValidationException::withMessages([
                'attendance' => 'Atendimento já foi finalizado.',
            ]);
        }

        $outcome = TurnListAttendanceOutcome::find($outcomeId);
        if (! $outcome || ! $outcome->is_active) {
            throw ValidationException::withMessages([
                'outcome_id' => 'Resultado de atendimento inválido.',
            ]);
        }

        return DB::transaction(function () use ($attendance, $outcome, $returnToQueue, $notes, $actor) {
            $duration = (int) $attendance->started_at->diffInSeconds(now());

            $attendance->update([
                'status' => TurnListAttendanceStatus::FINISHED->value,
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'outcome_id' => $outcome->id,
                'return_to_queue' => $returnToQueue,
                'notes' => $notes,
                'updated_by_user_id' => $actor?->id,
            ]);

            if ($returnToQueue) {
                if ($outcome->restore_queue_position && $attendance->original_queue_position) {
                    $adjustedPosition = $this->calculateAdjustedRestorePosition($attendance);
                    $this->queueService->enterAtPosition(
                        $attendance->employee_id,
                        $attendance->store_code,
                        $adjustedPosition,
                        $actor,
                    );
                } else {
                    $this->queueService->enter(
                        $attendance->employee_id,
                        $attendance->store_code,
                        $actor,
                    );
                }
            }

            return $attendance->fresh(['employee', 'outcome']);
        });
    }

    /**
     * Algoritmo de ajuste de posição (paridade fiel da v1):
     *
     *   adjustedPosition = max(1, original_queue_position - aheadCount)
     *
     *   aheadCount = COUNT(turn_list_attendances WHERE
     *       store_code = X
     *       AND status = 'active' (ainda atendendo)
     *       AND id != current_id (excluindo este)
     *       AND original_queue_position IS NOT NULL
     *       AND original_queue_position < origPos
     *       AND started_at >= attendance.started_at)
     *
     * Lê: "consultoras que estavam à FRENTE na fila E também saíram
     * pra atender DEPOIS desta (e ainda não voltaram)".
     */
    public function calculateAdjustedRestorePosition(TurnListAttendance $attendance): int
    {
        $origPos = (int) $attendance->original_queue_position;

        $aheadCount = TurnListAttendance::query()
            ->where('store_code', $attendance->store_code)
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->where('id', '!=', $attendance->id)
            ->whereNotNull('original_queue_position')
            ->where('original_queue_position', '<', $origPos)
            ->where('started_at', '>=', $attendance->started_at)
            ->count();

        return max(1, $origPos - $aheadCount);
    }

    /**
     * Atendimento ativo da consultora (null se não estiver atendendo).
     */
    public function getActiveByEmployee(int $employeeId): ?TurnListAttendance
    {
        return TurnListAttendance::query()
            ->where('employee_id', $employeeId)
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->first();
    }

    /**
     * @throws ValidationException
     */
    protected function ensureNotAttending(int $employeeId): void
    {
        $exists = TurnListAttendance::query()
            ->where('employee_id', $employeeId)
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Consultora já está em atendimento.',
            ]);
        }
    }
}
