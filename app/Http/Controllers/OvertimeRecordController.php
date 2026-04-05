<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRecord;
use App\Models\Employee;
use App\Models\Absence;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OvertimeRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = OvertimeRecord::with(['employee:id,name,short_name', 'approvedBy:id,name', 'createdBy:id,name'])
            ->active()
            ->latest('date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $records = $query->paginate(20)->through(fn ($r) => [
            'id' => $r->id,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->short_name ?? $r->employee?->name,
            'date' => $r->date->format('d/m/Y'),
            'date_raw' => $r->date->format('Y-m-d'),
            'start_time' => substr($r->start_time, 0, 5),
            'end_time' => substr($r->end_time, 0, 5),
            'hours' => $r->hours,
            'type' => $r->type,
            'type_label' => self::typeLabel($r->type),
            'status' => $r->status,
            'status_label' => self::statusLabel($r->status),
            'reason' => $r->reason,
            'notes' => $r->notes,
            'approved_by' => $r->approvedBy?->name,
            'approved_at' => $r->approved_at?->format('d/m/Y H:i'),
            'created_by' => $r->createdBy?->name,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
        ]);

        $employees = Employee::active()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->short_name ?? $e->name]);

        return Inertia::render('OvertimeRecords/Index', [
            'records' => $records,
            'employees' => $employees,
            'filters' => $request->only(['search', 'employee_id', 'status', 'type']),
            'typeOptions' => [
                ['value' => 'regular', 'label' => 'Regular'],
                ['value' => 'holiday', 'label' => 'Feriado'],
                ['value' => 'sunday', 'label' => 'Domingo'],
                ['value' => 'night', 'label' => 'Noturna'],
            ],
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Pendente'],
                ['value' => 'approved', 'label' => 'Aprovada'],
                ['value' => 'rejected', 'label' => 'Rejeitada'],
                ['value' => 'closed', 'label' => 'Fechada'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'hours' => 'required|numeric|min:0.25|max:24',
            'type' => 'required|in:regular,holiday,sunday,night',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cross-validation: no absence on the same day (INT-23)
        $hasAbsence = Absence::where('employee_id', $validated['employee_id'])
            ->where('absence_date', $validated['date'])
            ->where('is_archived', false)
            ->exists();

        if ($hasAbsence) {
            return redirect()->back()->withErrors([
                'date' => 'Ja existe registro de falta para este funcionario nesta data.',
            ]);
        }

        // Check duplicate
        $exists = OvertimeRecord::where('employee_id', $validated['employee_id'])
            ->where('date', $validated['date'])
            ->where('is_archived', false)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors([
                'date' => 'Ja existe registro de hora extra para este funcionario nesta data.',
            ]);
        }

        $validated['status'] = 'pending';
        $validated['created_by_user_id'] = auth()->id();

        OvertimeRecord::create($validated);

        return redirect()->route('overtime-records.index')
            ->with('success', 'Hora extra registrada com sucesso.');
    }

    public function show(OvertimeRecord $overtimeRecord)
    {
        $overtimeRecord->load(['employee:id,name,short_name', 'approvedBy:id,name', 'createdBy:id,name']);

        return response()->json([
            'id' => $overtimeRecord->id,
            'employee_id' => $overtimeRecord->employee_id,
            'employee_name' => $overtimeRecord->employee?->name,
            'date' => $overtimeRecord->date->format('Y-m-d'),
            'date_formatted' => $overtimeRecord->date->format('d/m/Y'),
            'start_time' => substr($overtimeRecord->start_time, 0, 5),
            'end_time' => substr($overtimeRecord->end_time, 0, 5),
            'hours' => $overtimeRecord->hours,
            'type' => $overtimeRecord->type,
            'type_label' => self::typeLabel($overtimeRecord->type),
            'status' => $overtimeRecord->status,
            'status_label' => self::statusLabel($overtimeRecord->status),
            'reason' => $overtimeRecord->reason,
            'notes' => $overtimeRecord->notes,
            'approved_by' => $overtimeRecord->approvedBy?->name,
            'approved_at' => $overtimeRecord->approved_at?->format('d/m/Y H:i'),
            'created_by' => $overtimeRecord->createdBy?->name,
            'created_at' => $overtimeRecord->created_at->format('d/m/Y H:i'),
            'updated_at' => $overtimeRecord->updated_at->format('d/m/Y H:i'),
        ]);
    }

    public function update(Request $request, OvertimeRecord $overtimeRecord)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'hours' => 'required|numeric|min:0.25|max:24',
            'type' => 'required|in:regular,holiday,sunday,night',
            'status' => 'required|in:pending,approved,rejected,closed',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Track approval
        if ($validated['status'] === 'approved' && $overtimeRecord->status !== 'approved') {
            $validated['approved_by_user_id'] = auth()->id();
            $validated['approved_at'] = now();
        }

        $overtimeRecord->update($validated);

        return redirect()->route('overtime-records.index')
            ->with('success', 'Hora extra atualizada com sucesso.');
    }

    public function destroy(OvertimeRecord $overtimeRecord)
    {
        $overtimeRecord->delete();

        return redirect()->route('overtime-records.index')
            ->with('success', 'Hora extra excluida com sucesso.');
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'regular' => 'Regular',
            'holiday' => 'Feriado',
            'sunday' => 'Domingo',
            'night' => 'Noturna',
            default => $type,
        };
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovada',
            'rejected' => 'Rejeitada',
            'closed' => 'Fechada',
            default => $status,
        };
    }
}
