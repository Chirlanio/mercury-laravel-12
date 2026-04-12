<?php

namespace App\Http\Controllers;

use App\Events\Helpdesk\TicketCommentEvent;
use App\Events\Helpdesk\TicketCreatedEvent;
use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use App\Models\Store;
use App\Services\HelpdeskService;
use App\Services\HelpdeskTransitionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpdeskController extends Controller
{
    public function __construct(
        private HelpdeskService $helpdeskService,
        private HelpdeskTransitionService $transitionService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $tickets = $this->helpdeskService->getTicketsForUser($user, $request->all());

        return Inertia::render('Helpdesk/Index', [
            'tickets' => $tickets->through(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'requester_name' => $t->requester?->name,
                'technician_name' => $t->assignedTechnician?->name,
                'department_name' => $t->department?->name,
                'category_name' => $t->category?->name,
                'status' => $t->status,
                'status_label' => $t->status_label,
                'status_color' => $t->status_color,
                'priority' => $t->priority,
                'priority_label' => $t->priority_label,
                'priority_color' => $t->priority_color,
                'is_overdue' => $t->is_overdue,
                'sla_remaining_hours' => $t->sla_remaining_hours,
                'created_at' => $t->created_at->format('d/m/Y H:i'),
            ]),
            'filters' => $request->only(['search', 'status', 'priority', 'department_id', 'assigned_to_me', 'date_from', 'date_to']),
            'statusOptions' => HdTicket::STATUS_LABELS,
            'priorityOptions' => HdTicket::PRIORITY_LABELS,
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'stores' => Store::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function show(HdTicket $ticket)
    {
        $details = $this->helpdeskService->getTicketDetails($ticket);
        $t = $details['ticket'];

        return response()->json([
            'ticket' => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'requester_name' => $t->requester?->name,
                'requester_id' => $t->requester_id,
                'technician_name' => $t->assignedTechnician?->name,
                'technician_id' => $t->assigned_technician_id,
                'department_id' => $t->department_id,
                'department_name' => $t->department?->name,
                'category_id' => $t->category_id,
                'category_name' => $t->category?->name,
                'store_name' => $t->store?->name,
                'status' => $t->status,
                'status_label' => $t->status_label,
                'status_color' => $t->status_color,
                'priority' => $t->priority,
                'priority_label' => $t->priority_label,
                'priority_color' => $t->priority_color,
                'is_overdue' => $t->is_overdue,
                'sla_remaining_hours' => $t->sla_remaining_hours,
                'sla_due_at' => $t->sla_due_at?->format('d/m/Y H:i'),
                'resolved_at' => $t->resolved_at?->format('d/m/Y H:i'),
                'closed_at' => $t->closed_at?->format('d/m/Y H:i'),
                'valid_transitions' => HdTicket::VALID_TRANSITIONS[$t->status] ?? [],
                'transition_labels' => collect(HdTicket::VALID_TRANSITIONS[$t->status] ?? [])
                    ->mapWithKeys(fn ($s) => [$s => HdTicket::STATUS_LABELS[$s]])->toArray(),
                'created_by' => $t->createdBy?->name,
                'created_at' => $t->created_at->format('d/m/Y H:i'),
            ],
            'interactions' => $details['interactions']->map(fn ($i) => [
                'id' => $i->id,
                'user_name' => $i->user?->name,
                'comment' => $i->comment,
                'type' => $i->type,
                'old_value' => $i->old_value,
                'new_value' => $i->new_value,
                'is_internal' => $i->is_internal,
                'attachments' => $i->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->original_filename,
                    'url' => $a->file_url,
                    'size' => $a->formatted_size,
                ]),
                'created_at' => $i->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:hd_departments,id',
            'category_id' => 'nullable|exists:hd_categories,id',
            'store_id' => 'nullable|string|exists:stores,code',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => 'nullable|integer|in:1,2,3,4',
        ]);

        $ticket = $this->helpdeskService->createTicket($validated, auth()->id());

        TicketCreatedEvent::dispatch(
            $ticket->id,
            $ticket->department_id,
            $ticket->title,
            $ticket->requester?->name ?? '',
            HdTicket::PRIORITY_LABELS[$ticket->priority] ?? 'Média',
        );

        return redirect()->route('helpdesk.index')
            ->with('success', "Chamado #{$ticket->id} criado com sucesso.");
    }

    public function transition(Request $request, HdTicket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(HdTicket::STATUS_LABELS)),
            'notes' => 'nullable|string|max:2000',
        ]);

        $validation = $this->transitionService->validateTransition($ticket, $validated['status']);
        if (! $validation['valid']) {
            return response()->json(['error' => $validation['errors'][0]], 422);
        }

        $oldStatus = $ticket->status;
        $this->transitionService->executeTransition($ticket, $validated['status'], auth()->id(), $validated['notes'] ?? null);

        TicketStatusChangedEvent::dispatch($ticket->id, $ticket->department_id, $oldStatus, $validated['status']);

        return response()->json(['message' => 'Status atualizado para '.HdTicket::STATUS_LABELS[$validated['status']].'.']);
    }

    public function assign(Request $request, HdTicket $ticket)
    {
        $validated = $request->validate([
            'technician_id' => 'required|exists:users,id',
        ]);

        $this->transitionService->assignTechnician($ticket, $validated['technician_id'], auth()->id());

        return response()->json(['message' => 'Técnico atribuído com sucesso.']);
    }

    public function changePriority(Request $request, HdTicket $ticket)
    {
        $validated = $request->validate([
            'priority' => 'required|integer|in:1,2,3,4',
        ]);

        $this->transitionService->changePriority($ticket, $validated['priority'], auth()->id());

        return response()->json(['message' => 'Prioridade atualizada.']);
    }

    public function addComment(Request $request, HdTicket $ticket)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
        ]);

        $interaction = $this->helpdeskService->addInteraction($ticket, $validated, auth()->id());

        TicketCommentEvent::dispatch(
            $ticket->id,
            auth()->id(),
            auth()->user()->name,
            $validated['is_internal'] ?? false,
        );

        return response()->json(['message' => 'Comentário adicionado.', 'interaction_id' => $interaction->id]);
    }

    public function uploadAttachment(Request $request, HdTicket $ticket)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $attachment = $this->helpdeskService->uploadAttachment(
            $ticket,
            $request->file('file'),
            auth()->id(),
            $request->get('interaction_id'),
        );

        return response()->json([
            'id' => $attachment->id,
            'name' => $attachment->original_filename,
            'url' => $attachment->file_url,
            'size' => $attachment->formatted_size,
        ]);
    }

    public function statistics(Request $request)
    {
        return response()->json(
            $this->helpdeskService->getStatistics(auth()->user(), $request->all())
        );
    }

    public function categories(int $departmentId)
    {
        return response()->json($this->helpdeskService->getCategoriesForDepartment($departmentId));
    }

    public function technicians(int $departmentId)
    {
        return response()->json($this->helpdeskService->getTechniciansForDepartment($departmentId));
    }

    public function destroy(HdTicket $ticket)
    {
        $ticket->delete();

        return redirect()->route('helpdesk.index')
            ->with('success', "Chamado #{$ticket->id} removido.");
    }
}
