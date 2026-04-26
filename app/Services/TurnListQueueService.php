<?php

namespace App\Services;

use App\Enums\TurnListAttendanceStatus;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use App\Models\TurnListQueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operações sobre a fila de espera (Lista da Vez).
 *
 * Toda mutação de `position` acontece dentro de transação para evitar
 * estados inconsistentes (positions duplicadas ou com gaps).
 *
 * Validações cruzadas (não pode entrar se está atendendo / em pausa)
 * ficam aqui — Models não validam.
 */
class TurnListQueueService
{
    /**
     * Adiciona consultora na próxima posição disponível (fim da fila).
     *
     * @throws ValidationException
     */
    public function enter(int $employeeId, string $storeCode, ?User $actor = null): TurnListQueueEntry
    {
        return DB::transaction(function () use ($employeeId, $storeCode, $actor) {
            $this->ensureNotInQueue($employeeId, $storeCode);
            $this->ensureNotAttending($employeeId);
            $this->ensureNotOnBreak($employeeId);

            $nextPosition = (int) (TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->lockForUpdate()
                ->max('position') ?? 0) + 1;

            return TurnListQueueEntry::create([
                'employee_id' => $employeeId,
                'store_code' => $storeCode,
                'position' => $nextPosition,
                'entered_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Insere consultora numa posição específica (1..N+1) — usado para
     * "voltar na vez" após atendimento/pausa com restore_queue_position.
     *
     * Posições subsequentes são deslocadas em +1.
     *
     * @throws ValidationException
     */
    public function enterAtPosition(int $employeeId, string $storeCode, int $position, ?User $actor = null): TurnListQueueEntry
    {
        return DB::transaction(function () use ($employeeId, $storeCode, $position, $actor) {
            $this->ensureNotInQueue($employeeId, $storeCode);
            $this->ensureNotAttending($employeeId);
            $this->ensureNotOnBreak($employeeId);

            $maxPosition = (int) TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->lockForUpdate()
                ->max('position') ?? 0;

            // Clampa entre 1 e max+1 (final da fila)
            $position = max(1, min($position, $maxPosition + 1));

            // Shift +1 nas posições >= position
            TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->where('position', '>=', $position)
                ->increment('position');

            return TurnListQueueEntry::create([
                'employee_id' => $employeeId,
                'store_code' => $storeCode,
                'position' => $position,
                'entered_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Remove consultora da fila e fecha o "buraco" deslocando posições
     * subsequentes em -1.
     *
     * @return bool true se removeu, false se não estava na fila
     */
    public function leave(int $employeeId, string $storeCode): bool
    {
        return DB::transaction(function () use ($employeeId, $storeCode) {
            $entry = TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return false;
            }

            $position = $entry->position;
            $entry->delete();

            // Shift -1 nas posições > position
            TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->where('position', '>', $position)
                ->decrement('position');

            return true;
        });
    }

    /**
     * Reordena a fila: move uma consultora pra outra posição (drag-drop).
     *
     * Algoritmo: shift atômico — se mover pra cima, posições intermediárias
     * descem em 1; se pra baixo, sobem em 1.
     *
     * @throws ValidationException
     */
    public function reorder(int $employeeId, string $storeCode, int $newPosition): void
    {
        DB::transaction(function () use ($employeeId, $storeCode, $newPosition) {
            $entry = TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->where('employee_id', $employeeId)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                throw ValidationException::withMessages([
                    'queue' => 'Consultora não está na fila.',
                ]);
            }

            $maxPosition = (int) TurnListQueueEntry::query()
                ->forStore($storeCode)
                ->max('position');

            $newPosition = max(1, min($newPosition, $maxPosition));
            $currentPosition = $entry->position;

            if ($newPosition === $currentPosition) {
                return; // sem mudança
            }

            if ($newPosition < $currentPosition) {
                // Mover pra cima — posições entre [newPosition, currentPosition-1] sobem em 1
                TurnListQueueEntry::query()
                    ->forStore($storeCode)
                    ->whereBetween('position', [$newPosition, $currentPosition - 1])
                    ->increment('position');
            } else {
                // Mover pra baixo — posições entre [currentPosition+1, newPosition] caem em 1
                TurnListQueueEntry::query()
                    ->forStore($storeCode)
                    ->whereBetween('position', [$currentPosition + 1, $newPosition])
                    ->decrement('position');
            }

            $entry->update(['position' => $newPosition]);
        });
    }

    /**
     * Recupera a posição atual de uma consultora na fila (null se não está).
     */
    public function getPosition(int $employeeId, string $storeCode): ?int
    {
        return TurnListQueueEntry::query()
            ->forStore($storeCode)
            ->where('employee_id', $employeeId)
            ->value('position');
    }

    // ==================================================================
    // Validações cruzadas
    // ==================================================================

    /**
     * @throws ValidationException
     */
    protected function ensureNotInQueue(int $employeeId, string $storeCode): void
    {
        $exists = TurnListQueueEntry::query()
            ->where('employee_id', $employeeId)
            ->where('store_code', $storeCode)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Consultora já está na fila.',
            ]);
        }
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
                'employee_id' => 'Consultora está em atendimento.',
            ]);
        }
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
                'employee_id' => 'Consultora está em pausa.',
            ]);
        }
    }
}
