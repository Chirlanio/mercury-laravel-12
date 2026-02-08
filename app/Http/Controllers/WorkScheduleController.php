<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeScheduleDayOverride;
use App\Models\EmployeeWorkSchedule;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class WorkScheduleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $statusFilter = $request->get('status');

        $query = WorkSchedule::query()->with('days');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('is_active', $statusFilter === 'active');
        }

        $allowedSortFields = ['name', 'weekly_hours', 'is_active', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $schedules = $query->paginate($perPage);

        // Estatísticas
        $stats = [
            'total' => WorkSchedule::count(),
            'active' => WorkSchedule::where('is_active', true)->count(),
            'inactive' => WorkSchedule::where('is_active', false)->count(),
            'assigned_employees' => EmployeeWorkSchedule::whereNull('end_date')->count(),
        ];

        return Inertia::render('WorkSchedules/Index', [
            'schedules' => $schedules->through(function ($schedule) {
                $workDays = $schedule->days->where('is_work_day', true)->count();
                $offDays = 7 - $workDays;

                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'description' => $schedule->description,
                    'weekly_hours' => $schedule->formatted_weekly_hours,
                    'weekly_hours_raw' => (float) $schedule->weekly_hours,
                    'work_days' => $workDays,
                    'off_days' => $offDays,
                    'work_days_label' => "{$workDays}x{$offDays}",
                    'employee_count' => $schedule->employee_count,
                    'is_active' => $schedule->is_active,
                    'is_default' => $schedule->is_default,
                    'created_at' => $schedule->created_at->format('d/m/Y'),
                ];
            }),
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:work_schedules,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'days' => 'required|array|size:7',
            'days.*.day_of_week' => 'required|integer|between:0,6',
            'days.*.is_work_day' => 'required|boolean',
            'days.*.entry_time' => 'nullable|required_if:days.*.is_work_day,true|date_format:H:i',
            'days.*.exit_time' => 'nullable|required_if:days.*.is_work_day,true|date_format:H:i',
            'days.*.break_start' => 'nullable|date_format:H:i',
            'days.*.break_end' => 'nullable|date_format:H:i',
            'days.*.break_duration_minutes' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'O nome da escala é obrigatório.',
            'name.unique' => 'Já existe uma escala com este nome.',
            'name.max' => 'O nome deve ter no máximo 100 caracteres.',
            'days.required' => 'A configuração dos 7 dias é obrigatória.',
            'days.size' => 'Todos os 7 dias devem ser configurados.',
            'days.*.entry_time.required_if' => 'O horário de entrada é obrigatório para dias de trabalho.',
            'days.*.exit_time.required_if' => 'O horário de saída é obrigatório para dias de trabalho.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Calcular weekly_hours a partir dos dias
            $weeklyHours = $this->calculateWeeklyHours($request->days);

            // Se marcou como padrão, desmarcar outros
            if ($request->is_default) {
                WorkSchedule::where('is_default', true)->update(['is_default' => false]);
            }

            $schedule = WorkSchedule::create([
                'name' => mb_strtoupper($request->name),
                'description' => $request->description,
                'weekly_hours' => $weeklyHours,
                'is_active' => $request->is_active ?? true,
                'is_default' => $request->is_default ?? false,
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            foreach ($request->days as $day) {
                $dailyHours = $this->calculateDailyHours($day);

                WorkScheduleDay::create([
                    'work_schedule_id' => $schedule->id,
                    'day_of_week' => $day['day_of_week'],
                    'is_work_day' => $day['is_work_day'],
                    'entry_time' => $day['is_work_day'] ? $day['entry_time'] : null,
                    'exit_time' => $day['is_work_day'] ? $day['exit_time'] : null,
                    'break_start' => $day['is_work_day'] ? ($day['break_start'] ?? null) : null,
                    'break_end' => $day['is_work_day'] ? ($day['break_end'] ?? null) : null,
                    'break_duration_minutes' => $day['is_work_day'] ? ($day['break_duration_minutes'] ?? null) : null,
                    'daily_hours' => $dailyHours,
                    'notes' => $day['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('work-schedules.index')->with('success', 'Escala criada com sucesso!');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating work schedule', ['error' => $e->getMessage()]);

            return redirect()->back()->withErrors([
                'general' => 'Erro ao criar escala: ' . $e->getMessage()
            ])->withInput();
        }
    }

    public function show($id)
    {
        $schedule = WorkSchedule::with(['days', 'createdBy:id,name', 'updatedBy:id,name'])->findOrFail($id);

        $employees = EmployeeWorkSchedule::where('work_schedule_id', $id)
            ->whereNull('end_date')
            ->with(['employee:id,name,short_name,position_id,store_id', 'employee.position:id,name', 'employee.store:id,code,name', 'dayOverrides'])
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'employee_name' => $assignment->employee->name,
                    'employee_short_name' => $assignment->employee->short_name,
                    'position' => $assignment->employee->position?->name ?? 'N/A',
                    'store' => $assignment->employee->store?->name ?? 'N/A',
                    'effective_date' => $assignment->effective_date->format('d/m/Y'),
                    'notes' => $assignment->notes,
                    'overrides_count' => $assignment->dayOverrides->count(),
                    'overrides' => $assignment->dayOverrides->map(function ($override) {
                        return [
                            'id' => $override->id,
                            'day_of_week' => $override->day_of_week,
                            'is_work_day' => $override->is_work_day,
                            'entry_time' => $override->entry_time,
                            'exit_time' => $override->exit_time,
                            'reason' => $override->reason,
                        ];
                    }),
                ];
            });

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'description' => $schedule->description,
                'weekly_hours' => $schedule->formatted_weekly_hours,
                'weekly_hours_raw' => (float) $schedule->weekly_hours,
                'is_active' => $schedule->is_active,
                'is_default' => $schedule->is_default,
                'created_by' => $schedule->createdBy?->name ?? 'Sistema',
                'updated_by' => $schedule->updatedBy?->name ?? null,
                'created_at' => $schedule->created_at->format('d/m/Y H:i'),
                'updated_at' => $schedule->updated_at->format('d/m/Y H:i'),
                'days' => $schedule->days->map(function ($day) {
                    return [
                        'id' => $day->id,
                        'day_of_week' => $day->day_of_week,
                        'day_name' => $day->day_name,
                        'day_short_name' => $day->day_short_name,
                        'is_work_day' => $day->is_work_day,
                        'entry_time' => $day->entry_time,
                        'exit_time' => $day->exit_time,
                        'break_start' => $day->break_start,
                        'break_end' => $day->break_end,
                        'break_duration_minutes' => $day->break_duration_minutes,
                        'daily_hours' => $day->daily_hours,
                        'notes' => $day->notes,
                    ];
                }),
                'employees' => $employees,
                'employee_count' => $employees->count(),
            ],
        ]);
    }

    public function edit($id)
    {
        $schedule = WorkSchedule::with('days')->findOrFail($id);

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'description' => $schedule->description,
                'weekly_hours' => (float) $schedule->weekly_hours,
                'is_active' => $schedule->is_active,
                'is_default' => $schedule->is_default,
                'days' => $schedule->days->map(function ($day) {
                    return [
                        'id' => $day->id,
                        'day_of_week' => $day->day_of_week,
                        'is_work_day' => $day->is_work_day,
                        'entry_time' => $day->entry_time ? substr($day->entry_time, 0, 5) : null,
                        'exit_time' => $day->exit_time ? substr($day->exit_time, 0, 5) : null,
                        'break_start' => $day->break_start ? substr($day->break_start, 0, 5) : null,
                        'break_end' => $day->break_end ? substr($day->break_end, 0, 5) : null,
                        'break_duration_minutes' => $day->break_duration_minutes,
                        'daily_hours' => $day->daily_hours,
                        'notes' => $day->notes,
                    ];
                }),
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = WorkSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:work_schedules,name,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'days' => 'required|array|size:7',
            'days.*.day_of_week' => 'required|integer|between:0,6',
            'days.*.is_work_day' => 'required|boolean',
            'days.*.entry_time' => 'nullable|required_if:days.*.is_work_day,true|date_format:H:i',
            'days.*.exit_time' => 'nullable|required_if:days.*.is_work_day,true|date_format:H:i',
            'days.*.break_start' => 'nullable|date_format:H:i',
            'days.*.break_end' => 'nullable|date_format:H:i',
            'days.*.break_duration_minutes' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'O nome da escala é obrigatório.',
            'name.unique' => 'Já existe uma escala com este nome.',
            'name.max' => 'O nome deve ter no máximo 100 caracteres.',
            'days.required' => 'A configuração dos 7 dias é obrigatória.',
            'days.size' => 'Todos os 7 dias devem ser configurados.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $weeklyHours = $this->calculateWeeklyHours($request->days);

            if ($request->is_default && !$schedule->is_default) {
                WorkSchedule::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
            }

            $schedule->update([
                'name' => mb_strtoupper($request->name),
                'description' => $request->description,
                'weekly_hours' => $weeklyHours,
                'is_active' => $request->is_active ?? true,
                'is_default' => $request->is_default ?? false,
                'updated_by_user_id' => auth()->id(),
            ]);

            // Atualizar dias (delete + recreate)
            WorkScheduleDay::where('work_schedule_id', $schedule->id)->delete();

            foreach ($request->days as $day) {
                $dailyHours = $this->calculateDailyHours($day);

                WorkScheduleDay::create([
                    'work_schedule_id' => $schedule->id,
                    'day_of_week' => $day['day_of_week'],
                    'is_work_day' => $day['is_work_day'],
                    'entry_time' => $day['is_work_day'] ? $day['entry_time'] : null,
                    'exit_time' => $day['is_work_day'] ? $day['exit_time'] : null,
                    'break_start' => $day['is_work_day'] ? ($day['break_start'] ?? null) : null,
                    'break_end' => $day['is_work_day'] ? ($day['break_end'] ?? null) : null,
                    'break_duration_minutes' => $day['is_work_day'] ? ($day['break_duration_minutes'] ?? null) : null,
                    'daily_hours' => $dailyHours,
                    'notes' => $day['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('work-schedules.index')->with('success', 'Escala atualizada com sucesso!');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating work schedule', ['id' => $id, 'error' => $e->getMessage()]);

            return redirect()->back()->withErrors([
                'general' => 'Erro ao atualizar escala: ' . $e->getMessage()
            ])->withInput();
        }
    }

    public function destroy($id)
    {
        $schedule = WorkSchedule::findOrFail($id);

        // Verificar se tem atribuições ativas
        $activeAssignments = EmployeeWorkSchedule::where('work_schedule_id', $id)
            ->whereNull('end_date')
            ->count();

        if ($activeAssignments > 0) {
            return redirect()->back()->withErrors([
                'general' => "Não é possível excluir esta escala. Existem {$activeAssignments} funcionário(s) atribuído(s)."
            ]);
        }

        try {
            $schedule->delete();
            return redirect()->route('work-schedules.index')->with('success', 'Escala excluída com sucesso!');
        } catch (Exception $e) {
            Log::error('Error deleting work schedule', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()->withErrors([
                'general' => 'Erro ao excluir escala: ' . $e->getMessage()
            ]);
        }
    }

    public function duplicate($id)
    {
        $original = WorkSchedule::with('days')->findOrFail($id);

        try {
            DB::beginTransaction();

            $copy = WorkSchedule::create([
                'name' => mb_strtoupper($original->name . ' (Cópia)'),
                'description' => $original->description,
                'weekly_hours' => $original->weekly_hours,
                'is_active' => true,
                'is_default' => false,
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            foreach ($original->days as $day) {
                WorkScheduleDay::create([
                    'work_schedule_id' => $copy->id,
                    'day_of_week' => $day->day_of_week,
                    'is_work_day' => $day->is_work_day,
                    'entry_time' => $day->entry_time,
                    'exit_time' => $day->exit_time,
                    'break_start' => $day->break_start,
                    'break_end' => $day->break_end,
                    'break_duration_minutes' => $day->break_duration_minutes,
                    'daily_hours' => $day->daily_hours,
                    'notes' => $day->notes,
                ]);
            }

            DB::commit();

            return redirect()->route('work-schedules.index')->with('success', 'Escala duplicada com sucesso!');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error duplicating work schedule', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()->withErrors([
                'general' => 'Erro ao duplicar escala: ' . $e->getMessage()
            ]);
        }
    }

    public function assignEmployee(Request $request, $id)
    {
        $schedule = WorkSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'notes' => 'nullable|string|max:500',
        ], [
            'employee_id.required' => 'O funcionário é obrigatório.',
            'employee_id.exists' => 'Funcionário não encontrado.',
            'effective_date.required' => 'A data de vigência é obrigatória.',
            'effective_date.date' => 'Data de vigência inválida.',
            'end_date.after' => 'A data de término deve ser posterior à data de vigência.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Encerrar atribuição anterior ativa do funcionário
            EmployeeWorkSchedule::where('employee_id', $request->employee_id)
                ->whereNull('end_date')
                ->update(['end_date' => $request->effective_date]);

            $assignment = EmployeeWorkSchedule::create([
                'employee_id' => $request->employee_id,
                'work_schedule_id' => $id,
                'effective_date' => $request->effective_date,
                'end_date' => $request->end_date,
                'notes' => $request->notes,
                'created_by_user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Funcionário atribuído à escala com sucesso!',
                'assignment' => $assignment->load('employee:id,name,short_name'),
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error assigning employee to schedule', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao atribuir funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unassignEmployee($scheduleId, $assignmentId)
    {
        $assignment = EmployeeWorkSchedule::where('id', $assignmentId)
            ->where('work_schedule_id', $scheduleId)
            ->firstOrFail();

        try {
            $assignment->update(['end_date' => now()->toDateString()]);

            return response()->json([
                'message' => 'Funcionário removido da escala com sucesso!',
            ]);
        } catch (Exception $e) {
            Log::error('Error unassigning employee', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao remover funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEmployees($id)
    {
        WorkSchedule::findOrFail($id);

        $employees = EmployeeWorkSchedule::where('work_schedule_id', $id)
            ->with(['employee:id,name,short_name,position_id,store_id', 'employee.position:id,name', 'employee.store:id,code,name', 'dayOverrides'])
            ->orderBy('end_date')
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'employee_name' => $assignment->employee->name,
                    'employee_short_name' => $assignment->employee->short_name,
                    'position' => $assignment->employee->position?->name ?? 'N/A',
                    'store' => $assignment->employee->store?->name ?? 'N/A',
                    'effective_date' => $assignment->effective_date->format('d/m/Y'),
                    'end_date' => $assignment->end_date?->format('d/m/Y'),
                    'is_current' => $assignment->is_current,
                    'notes' => $assignment->notes,
                    'overrides_count' => $assignment->dayOverrides->count(),
                ];
            });

        return response()->json(['employees' => $employees]);
    }

    public function storeOverride(Request $request, $assignmentId)
    {
        $assignment = EmployeeWorkSchedule::findOrFail($assignmentId);

        $validator = Validator::make($request->all(), [
            'day_of_week' => 'required|integer|between:0,6',
            'is_work_day' => 'required|boolean',
            'entry_time' => 'nullable|required_if:is_work_day,true|date_format:H:i',
            'exit_time' => 'nullable|required_if:is_work_day,true|date_format:H:i',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ], [
            'day_of_week.required' => 'O dia da semana é obrigatório.',
            'entry_time.required_if' => 'O horário de entrada é obrigatório para dias de trabalho.',
            'exit_time.required_if' => 'O horário de saída é obrigatório para dias de trabalho.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar se já existe override para este dia
            $existing = EmployeeScheduleDayOverride::where('employee_work_schedule_id', $assignmentId)
                ->where('day_of_week', $request->day_of_week)
                ->first();

            if ($existing) {
                $existing->update([
                    'is_work_day' => $request->is_work_day,
                    'entry_time' => $request->is_work_day ? $request->entry_time : null,
                    'exit_time' => $request->is_work_day ? $request->exit_time : null,
                    'break_start' => $request->is_work_day ? $request->break_start : null,
                    'break_end' => $request->is_work_day ? $request->break_end : null,
                    'reason' => $request->reason,
                    'updated_by_user_id' => auth()->id(),
                ]);
                $override = $existing;
            } else {
                $override = EmployeeScheduleDayOverride::create([
                    'employee_work_schedule_id' => $assignmentId,
                    'day_of_week' => $request->day_of_week,
                    'is_work_day' => $request->is_work_day,
                    'entry_time' => $request->is_work_day ? $request->entry_time : null,
                    'exit_time' => $request->is_work_day ? $request->exit_time : null,
                    'break_start' => $request->is_work_day ? $request->break_start : null,
                    'break_end' => $request->is_work_day ? $request->break_end : null,
                    'reason' => $request->reason,
                    'created_by_user_id' => auth()->id(),
                    'updated_by_user_id' => auth()->id(),
                ]);
            }

            return response()->json([
                'message' => 'Exceção criada com sucesso!',
                'override' => $override,
            ], 201);

        } catch (Exception $e) {
            Log::error('Error creating override', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao criar exceção: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyOverride($assignmentId, $overrideId)
    {
        $override = EmployeeScheduleDayOverride::where('id', $overrideId)
            ->where('employee_work_schedule_id', $assignmentId)
            ->firstOrFail();

        try {
            $override->delete();
            return response()->json(['message' => 'Exceção removida com sucesso!']);
        } catch (Exception $e) {
            Log::error('Error deleting override', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erro ao remover exceção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula horas diárias a partir de horários
     */
    private function calculateDailyHours(array $day): float
    {
        if (!($day['is_work_day'] ?? false) || empty($day['entry_time']) || empty($day['exit_time'])) {
            return 0;
        }

        $entry = strtotime($day['entry_time']);
        $exit = strtotime($day['exit_time']);

        if ($exit <= $entry) {
            return 0;
        }

        $totalMinutes = ($exit - $entry) / 60;

        // Subtrair break
        if (!empty($day['break_duration_minutes'])) {
            $totalMinutes -= $day['break_duration_minutes'];
        } elseif (!empty($day['break_start']) && !empty($day['break_end'])) {
            $breakStart = strtotime($day['break_start']);
            $breakEnd = strtotime($day['break_end']);
            if ($breakEnd > $breakStart) {
                $totalMinutes -= ($breakEnd - $breakStart) / 60;
            }
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Calcula horas semanais somando os dias
     */
    private function calculateWeeklyHours(array $days): float
    {
        $total = 0;
        foreach ($days as $day) {
            $total += $this->calculateDailyHours($day);
        }
        return round($total, 2);
    }
}
