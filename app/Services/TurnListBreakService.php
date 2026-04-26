<?php

namespace App\Services;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListBreak;
use App\Models\TurnListBreakType;
use App\Models\TurnListStoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operações sobre pausas (Intervalo, Almoço).
 *
 * Diferenças vs Attendance:
 *  - Pausa SÓ pode ser iniciada por consultora que está NA FILA.
 *    Se está disponível ou atendendo, bloqueia. (V1 permitia da
 *    posição "Disponível" mas isso confundia operadoras.)
 *  - `original_queue_position` é NOT NULL — sempre capturada.
 *  - Retorno à posição original é controlado por `store_settings.
 *    return_to_position` (toggle por loja), NÃO pelo break_type.
 */
class TurnListBreakService
{
    public function __construct(
        protected TurnListQueueService $queueService,
    ) {}

    /**
     * Inicia pausa. Captura posição original obrigatoriamente — se não
     * estava na fila, falha.
     *
     * @throws ValidationException
     */
    public function start(int $employeeId, string $storeCode, int $breakTypeId, ?User $actor = null): TurnListBreak
    {
        return DB::transaction(function () use ($employeeId, $storeCode, $breakTypeId, $actor) {
            $this->ensureNotOnBreak($employeeId);

            $breakType = TurnListBreakType::find($breakTypeId);
            if (! $breakType || ! $breakType->is_active) {
                throw ValidationException::withMessages([
                    'break_type_id' => 'Tipo de pausa inválido.',
                ]);
            }

            // Pausa requer estar na fila (regra de negócio v2 — mais clara
            // que v1, que aceitava partir do "Disponível")
            $originalPosition = $this->queueService->getPosition($employeeId, $storeCode);
            if (! $originalPosition) {
                throw ValidationException::withMessages([
                    'queue' => 'Consultora precisa estar na fila para iniciar pausa.',
                ]);
            }

            // Sai da fila (sem retorno automático)
            $this->queueService->leave($employeeId, $storeCode);

            return TurnListBreak::create([
                'employee_id' => $employeeId,
                'store_code' => $storeCode,
                'break_type_id' => $breakTypeId,
                'original_queue_position' => $originalPosition,
                'status' => TurnListAttendanceStatus::ACTIVE->value,
                'started_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Finaliza pausa — calcula duração e decide retorno conforme
     * configuração da loja.
     *
     * @throws ValidationException
     */
    public function finish(TurnListBreak $break, ?User $actor = null): TurnListBreak
    {
        if (! $break->is_active) {
            throw ValidationException::withMessages([
                'break' => 'Pausa já foi finalizada.',
            ]);
        }

        return DB::transaction(function () use ($break, $actor) {
            $duration = (int) $break->started_at->diffInSeconds(now());

            $break->update([
                'status' => TurnListAttendanceStatus::FINISHED->value,
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'updated_by_user_id' => $actor?->id,
            ]);

            // Configuração por loja decide se volta na posição original
            // ou no fim da fila. Default true (volta na vez).
            $shouldRestore = TurnListStoreSetting::returnToPositionFor($break->store_code);

            if ($shouldRestore && $break->original_queue_position) {
                $this->queueService->enterAtPosition(
                    $break->employee_id,
                    $break->store_code,
                    $break->original_queue_position,
                    $actor,
                );
            } else {
                $this->queueService->enter(
                    $break->employee_id,
                    $break->store_code,
                    $actor,
                );
            }

            return $break->fresh(['employee', 'breakType']);
        });
    }

    /**
     * Pausa ativa da consultora (null se não estiver em pausa).
     */
    public function getActiveByEmployee(int $employeeId): ?TurnListBreak
    {
        return TurnListBreak::query()
            ->where('employee_id', $employeeId)
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->first();
    }

    /**
     * @throws ValidationException
     */
    protected function ensureNotOnBreak(int $employeeId): void
    {
        $exists = TurnListBreak::query()
            ->where('employee_id', $employeeId)
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Consultora já está em pausa.',
            ]);
        }
    }
}
