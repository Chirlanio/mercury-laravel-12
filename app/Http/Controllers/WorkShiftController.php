<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Store;
use App\Models\WorkShift;
use App\Exports\WorkShiftsExport;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class WorkShiftController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $sortField = $request->get('sort', 'date');
        $sortDirection = $request->get('direction', 'desc');
        $storeFilter = $request->get('store');
        $typeFilter = $request->get('type');

        $query = WorkShift::query()
            ->with('employee:id,name,short_name,store_id')
            ->with('employee.store:code,name');

        if ($search) {
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%");
            });
        }

        // Aplicar filtro de loja
        if ($storeFilter) {
            $query->whereHas('employee', function ($q) use ($storeFilter) {
                $q->where('store_id', $storeFilter);
            });
        }

        // Aplicar filtro de tipo
        if ($typeFilter) {
            $query->where('type', $typeFilter);
        }

        $allowedSortFields = ['date', 'start_time', 'end_time', 'employee_name'];
        if (in_array($sortField, $allowedSortFields)) {
            if ($sortField === 'employee_name') {
                // Ordenar pelo nome do funcionário através do relacionamento
                $query->join('employees', 'work_shifts.employee_id', '=', 'employees.id')
                      ->orderBy('employees.name', $sortDirection)
                      ->select('work_shifts.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        }

        $workShifts = $query->paginate($perPage);

        $employees = Employee::where('status_id', 2)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        // Buscar dados para filtros
        $stores = Store::active()->orderBy('name')->get(['code', 'name']);

        $types = [
            ['value' => 'abertura', 'label' => 'Abertura'],
            ['value' => 'fechamento', 'label' => 'Fechamento'],
            ['value' => 'integral', 'label' => 'Integral'],
            ['value' => 'compensar', 'label' => 'Compensar'],
        ];

        $transformedData = $workShifts->through(function ($workShift) {
            return [
                'id' => (int) $workShift->id,
                'employee_name' => (string) ($workShift->employee?->name ?? 'N/A'),
                'employee_short_name' => (string) ($workShift->employee?->short_name ?? 'N/A'),
                'date' => $workShift->date ? (string) $workShift->date->format('d/m/Y') : '',
                'start_time' => (string) ($workShift->start_time ?? ''),
                'end_time' => (string) ($workShift->end_time ?? ''),
                'type' => (string) ($workShift->type ?? ''),
                'type_label' => (string) ucfirst($workShift->type ?? ''),
            ];
        });

        return Inertia::render('WorkShifts/Index', [
            'workShifts' => $transformedData,
            'employees' => $employees,
            'stores' => $stores,
            'types' => $types,
            'filters' => [
                'search' => $search,
                'sort' => $sortField,
                'direction' => $sortDirection,
                'per_page' => $perPage,
                'store' => $storeFilter,
                'type' => $typeFilter,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'type' => 'required|in:abertura,fechamento,integral,compensar',
        ], [
            'employee_id.required' => 'O funcionário é obrigatório.',
            'employee_id.exists' => 'Funcionário inválido.',
            'date.required' => 'A data é obrigatória.',
            'date.date' => 'Data inválida.',
            'start_time.required' => 'A hora de início é obrigatória.',
            'start_time.date_format' => 'Formato de hora inválido.',
            'end_time.required' => 'A hora de término é obrigatória.',
            'end_time.date_format' => 'Formato de hora inválido.',
            'end_time.after' => 'A hora de término deve ser posterior à hora de início.',
            'type.required' => 'O tipo de jornada é obrigatório.',
            'type.in' => 'Tipo de jornada inválido.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        WorkShift::create($request->all());

        return redirect()->route('work-shifts.index')->with('success', 'Jornada cadastrada com sucesso!');
    }

    public function show($id)
    {
        $workShift = WorkShift::with('employee:id,name,short_name')
            ->findOrFail($id);

        return response()->json([
            'workShift' => [
                'id' => $workShift->id,
                'employee_id' => $workShift->employee_id,
                'employee_name' => $workShift->employee?->name ?? 'N/A',
                'date' => $workShift->date->format('d/m/Y'),
                'start_time' => $workShift->start_time,
                'end_time' => $workShift->end_time,
                'type' => $workShift->type,
                'type_label' => ucfirst($workShift->type),
            ]
        ]);
    }

    public function edit($id)
    {
        $workShift = WorkShift::with('employee:id,name,short_name')
            ->findOrFail($id);

        return response()->json([
            'workShift' => [
                'id' => $workShift->id,
                'employee_id' => $workShift->employee_id,
                'date' => $workShift->date->format('Y-m-d'),
                'start_time' => substr($workShift->start_time, 0, 5), // Remove os segundos HH:mm:ss -> HH:mm
                'end_time' => substr($workShift->end_time, 0, 5), // Remove os segundos HH:mm:ss -> HH:mm
                'type' => $workShift->type,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $workShift = WorkShift::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'type' => 'required|in:abertura,fechamento,integral,compensar',
        ], [
            'employee_id.required' => 'O funcionário é obrigatório.',
            'employee_id.exists' => 'Funcionário inválido.',
            'date.required' => 'A data é obrigatória.',
            'date.date' => 'Data inválida.',
            'start_time.required' => 'A hora de início é obrigatória.',
            'start_time.date_format' => 'Formato de hora inválido.',
            'end_time.required' => 'A hora de término é obrigatória.',
            'end_time.date_format' => 'Formato de hora inválido.',
            'end_time.after' => 'A hora de término deve ser posterior à hora de início.',
            'type.required' => 'O tipo de jornada é obrigatório.',
            'type.in' => 'Tipo de jornada inválido.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $workShift->update($request->all());

        return redirect()->route('work-shifts.index')->with('success', 'Jornada atualizada com sucesso!');
    }

    public function destroy($id)
    {
        $workShift = WorkShift::findOrFail($id);
        $workShift->delete();

        return redirect()->route('work-shifts.index')->with('success', 'Jornada excluída com sucesso!');
    }

    /**
     * Export work shifts to Excel
     */
    public function export(Request $request)
    {
        $filters = [
            'employee_ids' => $request->get('employee_ids', []),
            'store_ids' => $request->get('store_ids', []),
            'type_ids' => $request->get('type_ids', []),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];

        $fileName = 'jornadas_trabalho_' . date('Y-m-d_His') . '.xlsx';

        return (new WorkShiftsExport($filters))->download($fileName);
    }

    /**
     * Print summary report of work shifts by store and employee
     */
    public function printSummary(Request $request)
    {
        $query = WorkShift::query()
            ->with(['employee.store'])
            ->select(['id', 'employee_id', 'date', 'start_time', 'end_time', 'type']);

        // Aplicar filtro de lojas
        if ($request->has('store_ids') && is_array($request->store_ids)) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->whereIn('store_id', $request->store_ids);
            });
        }

        // Aplicar filtro de funcionários
        if ($request->has('employee_ids') && is_array($request->employee_ids)) {
            $query->whereIn('employee_id', $request->employee_ids);
        }

        // Aplicar filtro de tipos
        if ($request->has('type_ids') && is_array($request->type_ids)) {
            $query->whereIn('type', $request->type_ids);
        }

        // Aplicar filtro de período
        if ($request->has('start_date') && $request->start_date) {
            $query->where('date', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->where('date', '<=', $request->end_date);
        }

        $workShifts = $query->orderBy('date', 'desc')->get();

        // Agrupar por loja e depois por funcionário
        $storeData = [];
        $totalHours = 0;

        foreach ($workShifts as $shift) {
            $storeId = $shift->employee->store_id ?? 'Sem Loja';
            $storeName = $shift->employee->store->name ?? 'Sem Loja';
            $employeeId = $shift->employee_id;
            $employeeName = $shift->employee->name ?? 'N/A';

            // Calcular duração em minutos
            $start = Carbon::parse($shift->start_time);
            $end = Carbon::parse($shift->end_time);
            $durationMinutes = $start->diffInMinutes($end);

            // Se for compensação, o valor é negativo (abate das horas)
            $isCompensation = $shift->type === 'compensar';
            $adjustedMinutes = $isCompensation ? -$durationMinutes : $durationMinutes;

            // Inicializar loja se não existir
            if (!isset($storeData[$storeId])) {
                $storeData[$storeId] = [
                    'name' => $storeName,
                    'code' => $storeId,
                    'total_minutes' => 0,
                    'total_shifts' => 0,
                    'employees' => []
                ];
            }

            // Inicializar funcionário se não existir
            if (!isset($storeData[$storeId]['employees'][$employeeId])) {
                $storeData[$storeId]['employees'][$employeeId] = [
                    'name' => $employeeName,
                    'total_minutes' => 0,
                    'total_shifts' => 0,
                    'shifts' => []
                ];
            }

            // Adicionar shift
            $storeData[$storeId]['employees'][$employeeId]['shifts'][] = [
                'date' => $shift->date->format('d/m/Y'),
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'type' => ucfirst($shift->type),
                'duration_minutes' => $durationMinutes,
                'is_compensation' => $isCompensation,
            ];

            // Atualizar totais
            $storeData[$storeId]['employees'][$employeeId]['total_minutes'] += $adjustedMinutes;
            $storeData[$storeId]['employees'][$employeeId]['total_shifts']++;
            $storeData[$storeId]['total_minutes'] += $adjustedMinutes;
            $storeData[$storeId]['total_shifts']++;
            $totalHours += $adjustedMinutes;
        }

        // Converter minutos para horas formatadas
        foreach ($storeData as &$store) {
            $store['total_hours'] = $this->formatMinutesToHours($store['total_minutes']);
            foreach ($store['employees'] as &$employee) {
                $employee['total_hours'] = $this->formatMinutesToHours($employee['total_minutes']);
            }
        }

        $data = [
            'stores' => $storeData,
            'filters' => [
                'stores' => $request->has('store_ids') && is_array($request->store_ids)
                    ? Store::whereIn('code', $request->store_ids)->pluck('name')->toArray()
                    : null,
                'employees' => $request->has('employee_ids') && is_array($request->employee_ids)
                    ? Employee::whereIn('id', $request->employee_ids)->pluck('name')->toArray()
                    : null,
                'types' => $request->has('type_ids') && is_array($request->type_ids)
                    ? array_map('ucfirst', $request->type_ids)
                    : null,
                'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('d/m/Y') : null,
                'end_date' => $request->end_date ? Carbon::parse($request->end_date)->format('d/m/Y') : null,
            ],
            'summary' => [
                'total_stores' => count($storeData),
                'total_employees' => array_sum(array_map(fn($store) => count($store['employees']), $storeData)),
                'total_shifts' => $workShifts->count(),
                'total_hours' => $this->formatMinutesToHours($totalHours),
            ],
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = PDF::loadView('pdf.work-shifts-summary', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('resumo_jornadas_trabalho_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Helper function to format minutes to HH:MM format
     */
    private function formatMinutesToHours($minutes)
    {
        $isNegative = $minutes < 0;
        $absMinutes = abs($minutes);
        $hours = floor($absMinutes / 60);
        $mins = $absMinutes % 60;
        $formatted = sprintf('%02d:%02d', $hours, $mins);
        return $isNegative ? '-' . $formatted : $formatted;
    }
}
