<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Store;
use App\Models\WorkShift;
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
            'type' => 'required|in:abertura,fechamento,integral',
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
            'type' => 'required|in:abertura,fechamento,integral',
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
}
