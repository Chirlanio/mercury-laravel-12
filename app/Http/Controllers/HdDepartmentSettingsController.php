<?php

namespace App\Http\Controllers;

use App\Models\HdBusinessHour;
use App\Models\HdDepartment;
use App\Models\HdHoliday;
use App\Services\HelpdeskSlaCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin page backend for per-department helpdesk settings:
 *
 *   - Business hours schedule (weekday → time ranges)
 *   - Holidays (date + description)
 *   - AI classification toggle + prompt template
 *
 * Lives outside the generic ConfigController pattern because its shape
 * doesn't match a flat CRUD of homogeneous rows — it's an aggregate of
 * three sub-resources keyed to one department. Follows the same auth
 * pattern as HdPermissionController (MANAGE_HD_PERMISSIONS permission).
 *
 * All mutations flush the HelpdeskSlaCalculator cache for the department
 * so the next ticket creation picks up the new schedule without a
 * process restart.
 */
class HdDepartmentSettingsController extends Controller
{
    public function __construct(private HelpdeskSlaCalculator $slaCalculator) {}

    public function index(Request $request)
    {
        $departments = HdDepartment::orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'is_active', 'ai_classification_enabled']);

        $selectedDepartmentId = (int) $request->get('department_id', $departments->first()?->id ?? 0);
        $department = $selectedDepartmentId
            ? HdDepartment::find($selectedDepartmentId)
            : null;

        if (! $department) {
            return Inertia::render('Helpdesk/DepartmentSettings', [
                'departments' => $departments,
                'selectedDepartmentId' => null,
                'department' => null,
                'businessHours' => [],
                'holidays' => [],
                'defaultSchedule' => config('helpdesk.business_hours.default', []),
                'weekdayLabels' => $this->weekdayLabels(),
                'promptPlaceholders' => $this->promptPlaceholders(),
            ]);
        }

        return Inertia::render('Helpdesk/DepartmentSettings', [
            'departments' => $departments,
            'selectedDepartmentId' => $department->id,
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'ai_classification_enabled' => (bool) $department->ai_classification_enabled,
                'ai_classification_prompt' => $department->ai_classification_prompt,
            ],
            'businessHours' => HdBusinessHour::where('department_id', $department->id)
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get(['id', 'weekday', 'start_time', 'end_time'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'weekday' => $row->weekday,
                    'start_time' => substr($row->start_time, 0, 5),
                    'end_time' => substr($row->end_time, 0, 5),
                ])
                ->values(),
            'holidays' => HdHoliday::where('department_id', $department->id)
                ->orderBy('date')
                ->get(['id', 'date', 'description'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'date' => $row->date->format('Y-m-d'),
                    'description' => $row->description,
                ])
                ->values(),
            'defaultSchedule' => config('helpdesk.business_hours.default', []),
            'weekdayLabels' => $this->weekdayLabels(),
            'promptPlaceholders' => $this->promptPlaceholders(),
        ]);
    }

    /**
     * Replace the entire business hours schedule for a department. The UI
     * always sends the full set — partial updates would complicate the
     * state machine for no real benefit.
     */
    public function updateBusinessHours(Request $request, HdDepartment $department)
    {
        $validated = $request->validate([
            'ranges' => 'array',
            'ranges.*.weekday' => 'required|integer|between:1,7',
            'ranges.*.start_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'ranges.*.end_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        // Extra semantic validation: end > start on each range.
        foreach ($validated['ranges'] ?? [] as $idx => $range) {
            if ($range['end_time'] <= $range['start_time']) {
                return back()->withErrors([
                    "ranges.{$idx}.end_time" => 'O horário de fim deve ser maior que o início.',
                ]);
            }
        }

        DB::transaction(function () use ($department, $validated) {
            HdBusinessHour::where('department_id', $department->id)->delete();

            foreach ($validated['ranges'] ?? [] as $range) {
                HdBusinessHour::create([
                    'department_id' => $department->id,
                    'weekday' => $range['weekday'],
                    'start_time' => $range['start_time'].':00',
                    'end_time' => $range['end_time'].':00',
                ]);
            }
        });

        $this->slaCalculator->flushCache($department->id);

        return back()->with('success', 'Expediente atualizado.');
    }

    public function storeHoliday(Request $request, HdDepartment $department)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'nullable|string|max:120',
        ]);

        HdHoliday::create([
            'department_id' => $department->id,
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->slaCalculator->flushCache($department->id);

        return back()->with('success', 'Feriado cadastrado.');
    }

    public function destroyHoliday(HdDepartment $department, HdHoliday $holiday)
    {
        abort_if($holiday->department_id !== $department->id, 404);

        $holiday->delete();
        $this->slaCalculator->flushCache($department->id);

        return back()->with('success', 'Feriado removido.');
    }

    public function updateAi(Request $request, HdDepartment $department)
    {
        $validated = $request->validate([
            'ai_classification_enabled' => 'required|boolean',
            'ai_classification_prompt' => 'nullable|string|max:10000',
        ]);

        $department->update($validated);

        return back()->with('success', 'Configuração de IA atualizada.');
    }

    /**
     * @return array<int, string>
     */
    protected function weekdayLabels(): array
    {
        return [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    /**
     * @return array<int, array{key:string, description:string}>
     */
    protected function promptPlaceholders(): array
    {
        return [
            ['key' => '{{department_name}}', 'description' => 'Nome do departamento'],
            ['key' => '{{categories_list}}', 'description' => 'Lista de categorias ativas com id + nome'],
            ['key' => '{{employee_block}}', 'description' => 'Bloco com primeiro nome + loja (ou "não identificado")'],
            ['key' => '{{title}}', 'description' => 'Título do chamado (já sanitizado)'],
            ['key' => '{{description}}', 'description' => 'Descrição do chamado (já sanitizada)'],
        ];
    }
}
