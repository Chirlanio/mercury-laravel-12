<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\OvertimeRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $query = Absence::with(['employee:id,name,short_name', 'medicalCertificate:id,cid_code', 'createdBy:id,name'])
            ->active()
            ->latest('absence_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('justified')) {
            $query->where('is_justified', $request->justified === 'yes');
        }

        $absences = $query->paginate(20)->through(fn ($a) => [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'employee_name' => $a->employee?->short_name ?? $a->employee?->name,
            'absence_date' => $a->absence_date->format('d/m/Y'),
            'absence_date_raw' => $a->absence_date->format('Y-m-d'),
            'type' => $a->type,
            'type_label' => self::typeLabel($a->type),
            'is_justified' => $a->is_justified,
            'medical_certificate_id' => $a->medical_certificate_id,
            'cid_code' => $a->medicalCertificate?->cid_code,
            'reason' => $a->reason,
            'notes' => $a->notes,
            'created_by' => $a->createdBy?->name,
            'created_at' => $a->created_at->format('d/m/Y H:i'),
        ]);

        $employees = Employee::active()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->short_name ?? $e->name]);

        return Inertia::render('Absences/Index', [
            'absences' => $absences,
            'employees' => $employees,
            'filters' => $request->only(['search', 'employee_id', 'type', 'justified']),
            'typeOptions' => [
                ['value' => 'unjustified', 'label' => 'Falta Injustificada'],
                ['value' => 'justified', 'label' => 'Falta Justificada'],
                ['value' => 'late', 'label' => 'Atraso'],
                ['value' => 'early_leave', 'label' => 'Saida Antecipada'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'absence_date' => 'required|date',
            'type' => 'required|in:unjustified,justified,late,early_leave',
            'is_justified' => 'boolean',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Cross-validation: no overtime on the same day (INT-23)
        $hasOvertime = OvertimeRecord::where('employee_id', $validated['employee_id'])
            ->where('date', $validated['absence_date'])
            ->where('is_archived', false)
            ->exists();

        if ($hasOvertime) {
            return redirect()->back()->withErrors([
                'absence_date' => 'Ja existe registro de hora extra para este funcionario nesta data.',
            ]);
        }

        // Check duplicate
        $exists = Absence::where('employee_id', $validated['employee_id'])
            ->where('absence_date', $validated['absence_date'])
            ->where('is_archived', false)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors([
                'absence_date' => 'Ja existe uma falta registrada para este funcionario nesta data.',
            ]);
        }

        $validated['created_by_user_id'] = auth()->id();

        Absence::create($validated);

        return redirect()->route('absences.index')
            ->with('success', 'Falta registrada com sucesso.');
    }

    public function show(Absence $absence)
    {
        $absence->load(['employee:id,name,short_name', 'medicalCertificate', 'createdBy:id,name']);

        return response()->json([
            'id' => $absence->id,
            'employee_id' => $absence->employee_id,
            'employee_name' => $absence->employee?->name,
            'absence_date' => $absence->absence_date->format('Y-m-d'),
            'absence_date_formatted' => $absence->absence_date->format('d/m/Y'),
            'type' => $absence->type,
            'type_label' => self::typeLabel($absence->type),
            'is_justified' => $absence->is_justified,
            'medical_certificate_id' => $absence->medical_certificate_id,
            'reason' => $absence->reason,
            'notes' => $absence->notes,
            'created_by' => $absence->createdBy?->name,
            'created_at' => $absence->created_at->format('d/m/Y H:i'),
            'updated_at' => $absence->updated_at->format('d/m/Y H:i'),
        ]);
    }

    public function update(Request $request, Absence $absence)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'absence_date' => 'required|date',
            'type' => 'required|in:unjustified,justified,late,early_leave',
            'is_justified' => 'boolean',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $absence->update($validated);

        return redirect()->route('absences.index')
            ->with('success', 'Falta atualizada com sucesso.');
    }

    public function destroy(Absence $absence)
    {
        $absence->delete();

        return redirect()->route('absences.index')
            ->with('success', 'Falta excluida com sucesso.');
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'unjustified' => 'Falta Injustificada',
            'justified' => 'Falta Justificada',
            'late' => 'Atraso',
            'early_leave' => 'Saida Antecipada',
            default => $type,
        };
    }
}
