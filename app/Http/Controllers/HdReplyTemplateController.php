<?php

namespace App\Http\Controllers;

use App\Models\HdDepartment;
use App\Models\HdReplyTemplate;
use Illuminate\Http\Request;

/**
 * Admin CRUD + lookup endpoints for technician reply templates.
 *
 * Listing (GET /helpdesk/reply-templates) is open to anyone with
 * MANAGE_TICKETS so technicians can fetch their macros from the ticket
 * detail modal. Create/Update/Delete is also gated by MANAGE_TICKETS
 * but enforces author-only permission on personal (non-shared)
 * templates.
 */
class HdReplyTemplateController extends Controller
{
    /**
     * JSON endpoint used by the ticket detail modal to populate the
     * "Inserir template" dropdown. Returns all templates visible to
     * the current user scoped to the ticket's department.
     */
    public function index(Request $request)
    {
        $departmentId = $request->get('department_id') ? (int) $request->get('department_id') : null;

        $templates = HdReplyTemplate::query()
            ->visibleTo(auth()->id(), $departmentId)
            ->with('department:id,name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'body' => $t->body,
                'department_id' => $t->department_id,
                'department_name' => $t->department?->name,
                'is_shared' => $t->is_shared,
                'usage_count' => $t->usage_count,
            ]);

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = auth()->id();

        $template = HdReplyTemplate::create($data);

        return response()->json(['id' => $template->id, 'message' => 'Template criado.']);
    }

    public function update(Request $request, HdReplyTemplate $template)
    {
        // Author-only guard on personal templates.
        if (! $template->is_shared && $template->author_id !== auth()->id()) {
            abort(403, 'Você só pode editar seus próprios templates pessoais.');
        }

        $data = $this->validated($request);
        $template->update($data);

        return response()->json(['message' => 'Template atualizado.']);
    }

    public function destroy(HdReplyTemplate $template)
    {
        if (! $template->is_shared && $template->author_id !== auth()->id()) {
            abort(403, 'Você só pode remover seus próprios templates pessoais.');
        }

        $template->delete();

        return response()->json(['message' => 'Template removido.']);
    }

    /**
     * Increment usage_count when a technician inserts a template into
     * a comment draft. Called from the frontend as a fire-and-forget
     * POST — the response is ignored by the UI.
     */
    public function recordUsage(HdReplyTemplate $template)
    {
        $template->increment('usage_count');

        return response()->json(['usage_count' => $template->usage_count]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'category' => 'nullable|string|max:60',
            'body' => 'required|string|max:10000',
            'department_id' => 'nullable|integer|exists:hd_departments,id',
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);
    }
}
