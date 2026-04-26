<?php

namespace App\Services;

use App\Enums\TurnListAttendanceStatus;
use App\Models\Employee;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use App\Models\TurnListQueueEntry;

/**
 * Snapshot dos 4 painéis da Lista da Vez para uma loja:
 *  1. Available  — consultoras ativas SEM queue/attendance/break
 *  2. Queue      — fila ordenada por position
 *  3. Attending  — atendimentos ativos com timer
 *  4. OnBreak    — pausas ativas com is_exceeded calculado
 *
 * Filtro estrito: apenas employees com `position_id = CONSULTORA_POSITION_ID`
 * e `status_id = ACTIVE_EMPLOYEE_STATUS_ID` (paridade v1).
 *
 * 1 chamada → 4 queries (uma por painel) — performance OK pra polling
 * 30s. Se virar gargalo, substituir por 1 query agregada com CASE.
 */
class TurnListBoardService
{
    /**
     * ID da position "Consultor(a) de Vendas". Em ambos v1 e v2 é 1.
     * Configurável via env caso tenant queira customizar.
     */
    public const CONSULTORA_POSITION_ID = 1;

    /**
     * ID do status "Ativo" em employees (mesma convenção v1).
     */
    public const ACTIVE_EMPLOYEE_STATUS_ID = 2;

    /**
     * Retorna snapshot completo do board para uma loja.
     *
     * @return array{
     *   available: array<int, array>,
     *   queue: array<int, array>,
     *   attending: array<int, array>,
     *   on_break: array<int, array>,
     *   counts: array{available: int, queue: int, attending: int, on_break: int},
     * }
     */
    public function getBoard(string $storeCode): array
    {
        $available = $this->getAvailable($storeCode);
        $queue = $this->getQueue($storeCode);
        $attending = $this->getAttending($storeCode);
        $onBreak = $this->getOnBreak($storeCode);

        return [
            'available' => $available,
            'queue' => $queue,
            'attending' => $attending,
            'on_break' => $onBreak,
            'counts' => [
                'available' => count($available),
                'queue' => count($queue),
                'attending' => count($attending),
                'on_break' => count($onBreak),
            ],
        ];
    }

    /**
     * Consultoras disponíveis: ativas, da loja, NÃO estão em queue, attendance ou break.
     *
     * @return array<int, array>
     */
    public function getAvailable(string $storeCode): array
    {
        $busyEmployeeIds = collect()
            ->merge(TurnListQueueEntry::query()->forStore($storeCode)->pluck('employee_id'))
            ->merge(TurnListAttendance::query()->forStore($storeCode)->active()->pluck('employee_id'))
            ->merge(TurnListBreak::query()->forStore($storeCode)->active()->pluck('employee_id'))
            ->unique()
            ->all();

        $employees = Employee::query()
            ->where('position_id', self::CONSULTORA_POSITION_ID)
            ->where('status_id', self::ACTIVE_EMPLOYEE_STATUS_ID)
            ->where('store_id', $storeCode)
            ->when(! empty($busyEmployeeIds), fn ($q) => $q->whereNotIn('id', $busyEmployeeIds))
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'store_id']);

        return $employees->map(fn (Employee $e) => $this->formatEmployee($e))->all();
    }

    /**
     * Consultoras na fila (ordenadas por position).
     *
     * @return array<int, array>
     */
    public function getQueue(string $storeCode): array
    {
        return TurnListQueueEntry::query()
            ->with('employee:id,name,short_name,store_id')
            ->forStore($storeCode)
            ->orderedByPosition()
            ->get()
            ->map(function (TurnListQueueEntry $entry) {
                return [
                    'queue_id' => $entry->id,
                    'position' => $entry->position,
                    'entered_at' => $entry->entered_at?->toIso8601String(),
                    'waiting_seconds' => $entry->waiting_seconds,
                    ...$this->formatEmployee($entry->employee),
                ];
            })
            ->all();
    }

    /**
     * Atendimentos ativos com elapsed_seconds.
     *
     * @return array<int, array>
     */
    public function getAttending(string $storeCode): array
    {
        return TurnListAttendance::query()
            ->with('employee:id,name,short_name,store_id')
            ->forStore($storeCode)
            ->active()
            ->orderBy('started_at')
            ->get()
            ->map(function (TurnListAttendance $att) {
                return [
                    'attendance_id' => $att->id,
                    'attendance_ulid' => $att->ulid,
                    'started_at' => $att->started_at?->toIso8601String(),
                    'elapsed_seconds' => $att->elapsed_seconds,
                    'original_queue_position' => $att->original_queue_position,
                    ...$this->formatEmployee($att->employee),
                ];
            })
            ->all();
    }

    /**
     * Pausas ativas com is_exceeded e dados do break_type.
     *
     * @return array<int, array>
     */
    public function getOnBreak(string $storeCode): array
    {
        return TurnListBreak::query()
            ->with([
                'employee:id,name,short_name,store_id',
                'breakType:id,name,max_duration_minutes,color,icon',
            ])
            ->forStore($storeCode)
            ->active()
            ->orderBy('started_at')
            ->get()
            ->map(function (TurnListBreak $break) {
                return [
                    'break_id' => $break->id,
                    'break_type' => $break->breakType ? [
                        'id' => $break->breakType->id,
                        'name' => $break->breakType->name,
                        'max_duration_minutes' => $break->breakType->max_duration_minutes,
                        'color' => $break->breakType->color,
                        'icon' => $break->breakType->icon,
                    ] : null,
                    'started_at' => $break->started_at?->toIso8601String(),
                    'elapsed_seconds' => $break->elapsed_seconds,
                    'elapsed_minutes' => $break->elapsed_minutes,
                    'is_exceeded' => $break->is_exceeded,
                    'original_queue_position' => $break->original_queue_position,
                    ...$this->formatEmployee($break->employee),
                ];
            })
            ->all();
    }

    /**
     * Formato compartilhado entre os 4 painéis. Inclui iniciais geradas
     * server-side (mesma fórmula do EmployeeAvatar do projeto).
     */
    protected function formatEmployee(?Employee $emp): array
    {
        if (! $emp) {
            return [];
        }

        return [
            'employee_id' => $emp->id,
            'employee_name' => $emp->name,
            'employee_short_name' => $emp->short_name,
            'employee_initials' => $this->initials($emp->name),
            'store_code' => $emp->store_id,
        ];
    }

    /**
     * Iniciais "Primeira+Última" (Maria José Silva → MS).
     */
    protected function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) === 0) {
            return '??';
        }

        $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) === 1) {
            return $first;
        }

        $last = mb_strtoupper(mb_substr(end($parts), 0, 1));

        return $first.$last;
    }
}
