<?php

namespace App\Http\Controllers;

use App\Models\MedicalCertificate;
use App\Models\Employee;
use App\Models\Absence;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicalCertificateController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicalCertificate::with(['employee:id,name,short_name', 'createdBy:id,name'])
            ->latest('start_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%"))
                  ->orWhere('cid_code', 'like', "%{$search}%")
                  ->orWhere('doctor_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $today = now()->toDateString();
            if ($request->status === 'active') {
                $query->where('end_date', '>=', $today);
            } else {
                $query->where('end_date', '<', $today);
            }
        }

        $certificates = $query->paginate(20)->through(fn ($c) => [
            'id' => $c->id,
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->short_name ?? $c->employee?->name,
            'start_date' => $c->start_date->format('d/m/Y'),
            'end_date' => $c->end_date->format('d/m/Y'),
            'days' => $c->start_date->diffInDays($c->end_date) + 1,
            'cid_code' => $c->cid_code,
            'cid_description' => $c->cid_description,
            'doctor_name' => $c->doctor_name,
            'doctor_crm' => $c->doctor_crm,
            'notes' => $c->notes,
            'is_active' => $c->end_date->gte(now()),
            'created_by' => $c->createdBy?->name,
            'created_at' => $c->created_at->format('d/m/Y H:i'),
        ]);

        $employees = Employee::active()
            ->orderBy('name')
            ->get(['id', 'name', 'short_name'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->short_name ?? $e->name]);

        return Inertia::render('MedicalCertificates/Index', [
            'certificates' => $certificates,
            'employees' => $employees,
            'filters' => $request->only(['search', 'employee_id', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cid_code' => 'nullable|string|max:20',
            'cid_description' => 'nullable|string|max:255',
            'doctor_name' => 'nullable|string|max:255',
            'doctor_crm' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['created_by_user_id'] = auth()->id();

        $certificate = MedicalCertificate::create($validated);

        // Auto-justify absences within the certificate period
        Absence::where('employee_id', $validated['employee_id'])
            ->whereBetween('absence_date', [$validated['start_date'], $validated['end_date']])
            ->where('is_justified', false)
            ->update([
                'is_justified' => true,
                'medical_certificate_id' => $certificate->id,
            ]);

        return redirect()->route('medical-certificates.index')
            ->with('success', 'Atestado medico cadastrado com sucesso.');
    }

    public function show(MedicalCertificate $medicalCertificate)
    {
        $medicalCertificate->load(['employee:id,name,short_name,cpf', 'createdBy:id,name']);

        return response()->json([
            'id' => $medicalCertificate->id,
            'employee_id' => $medicalCertificate->employee_id,
            'employee_name' => $medicalCertificate->employee?->name,
            'start_date' => $medicalCertificate->start_date->format('Y-m-d'),
            'end_date' => $medicalCertificate->end_date->format('Y-m-d'),
            'start_date_formatted' => $medicalCertificate->start_date->format('d/m/Y'),
            'end_date_formatted' => $medicalCertificate->end_date->format('d/m/Y'),
            'days' => $medicalCertificate->start_date->diffInDays($medicalCertificate->end_date) + 1,
            'cid_code' => $medicalCertificate->cid_code,
            'cid_description' => $medicalCertificate->cid_description,
            'doctor_name' => $medicalCertificate->doctor_name,
            'doctor_crm' => $medicalCertificate->doctor_crm,
            'notes' => $medicalCertificate->notes,
            'certificate_file' => $medicalCertificate->certificate_file,
            'is_active' => $medicalCertificate->end_date->gte(now()),
            'created_by' => $medicalCertificate->createdBy?->name,
            'created_at' => $medicalCertificate->created_at->format('d/m/Y H:i'),
            'updated_at' => $medicalCertificate->updated_at->format('d/m/Y H:i'),
        ]);
    }

    public function update(Request $request, MedicalCertificate $medicalCertificate)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cid_code' => 'nullable|string|max:20',
            'cid_description' => 'nullable|string|max:255',
            'doctor_name' => 'nullable|string|max:255',
            'doctor_crm' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $medicalCertificate->update($validated);

        return redirect()->route('medical-certificates.index')
            ->with('success', 'Atestado medico atualizado com sucesso.');
    }

    public function destroy(MedicalCertificate $medicalCertificate)
    {
        // Unlink justified absences
        Absence::where('medical_certificate_id', $medicalCertificate->id)
            ->update(['medical_certificate_id' => null, 'is_justified' => false]);

        $medicalCertificate->delete();

        return redirect()->route('medical-certificates.index')
            ->with('success', 'Atestado medico excluido com sucesso.');
    }
}
