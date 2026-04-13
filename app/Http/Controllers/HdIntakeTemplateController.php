<?php

namespace App\Http\Controllers;

use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdIntakeTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Admin CRUD for hd_intake_templates. Templates define structured field
 * schemas that any intake channel (web, whatsapp, email) can render on
 * top of the basic title/description pair.
 *
 * Each template is scoped to a department and optionally to a category
 * (for more specific prompts). The `fields` column is a JSON array of
 * field definitions following the shape:
 *
 *   [
 *     { "name": "start_date", "label": "Data inicial", "type": "date", "required": true },
 *     { "name": "days", "label": "Quantidade de dias", "type": "text", "required": true },
 *     { "name": "type", "label": "Tipo", "type": "select", "required": true, "options": [
 *         {"value": "full", "label": "Integral"},
 *         {"value": "partial", "label": "Parcial"}
 *     ]}
 *   ]
 *
 * Supported field types: text, textarea, date, select, multiselect, boolean, file
 */
class HdIntakeTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = HdIntakeTemplate::query()
            ->with(['department:id,name', 'category:id,name'])
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'department_id' => $t->department_id,
                'department_name' => $t->department?->name,
                'category_id' => $t->category_id,
                'category_name' => $t->category?->name,
                'fields' => $t->fields ?? [],
                'active' => (bool) $t->active,
                'sort_order' => $t->sort_order,
                'fields_count' => count($t->fields ?? []),
            ]);

        return Inertia::render('Helpdesk/IntakeTemplates', [
            'templates' => $templates,
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'categories' => HdCategory::active()->orderBy('name')->get(['id', 'name', 'department_id']),
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        HdIntakeTemplate::create($validated);

        return back()->with('success', 'Template criado.');
    }

    public function update(Request $request, HdIntakeTemplate $template)
    {
        $validated = $this->validated($request);
        $template->update($validated);

        return back()->with('success', 'Template atualizado.');
    }

    public function destroy(HdIntakeTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template removido.');
    }

    /**
     * Validate the incoming template payload. Runs the field-schema
     * validator from the model instance since field rules are dynamic.
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'department_id' => 'required|integer|exists:hd_departments,id',
            'category_id' => 'nullable|integer|exists:hd_categories,id',
            'active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            'fields' => 'array',
            'fields.*.name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => 'required|string|max:120',
            'fields.*.type' => ['required', 'string', Rule::in(array_keys($this->fieldTypes()))],
            'fields.*.required' => 'nullable|boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*.value' => 'required_with:fields.*.options|string|max:120',
            'fields.*.options.*.label' => 'required_with:fields.*.options|string|max:120',
        ], [
            'fields.*.name.regex' => 'Nome do campo deve começar com letra e conter apenas letras minúsculas, números e underscore.',
        ]);

        // If category is provided, guard that it belongs to the department.
        if (! empty($data['category_id'])) {
            $category = HdCategory::find($data['category_id']);
            if (! $category || $category->department_id !== (int) $data['department_id']) {
                abort(422, 'A categoria selecionada não pertence ao departamento informado.');
            }
        }

        // Defaults
        $data['active'] = $data['active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['fields'] = $data['fields'] ?? [];

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected function fieldTypes(): array
    {
        return [
            'text' => 'Texto curto',
            'textarea' => 'Texto longo',
            'date' => 'Data',
            'select' => 'Seleção única',
            'multiselect' => 'Seleção múltipla',
            'boolean' => 'Sim / Não',
            'file' => 'Arquivo',
        ];
    }
}
