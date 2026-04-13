<?php

namespace App\Http\Controllers;

use App\Events\Helpdesk\TicketCommentEvent;
use App\Events\Helpdesk\TicketCreatedEvent;
use App\Events\Helpdesk\TicketStatusChangedEvent;
use App\Exports\HelpdeskTicketsExport;
use App\Http\Requests\Helpdesk\AddCommentRequest;
use App\Http\Requests\Helpdesk\AssignTicketRequest;
use App\Http\Requests\Helpdesk\ChangePriorityRequest;
use App\Http\Requests\Helpdesk\StoreTicketRequest;
use App\Http\Requests\Helpdesk\TransitionTicketRequest;
use App\Http\Requests\Helpdesk\UploadAttachmentRequest;
use App\Models\HdDepartment;
use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\Store;
use App\Enums\Permission;
use App\Services\HelpdeskIntakeService;
use App\Services\HelpdeskReportService;
use App\Services\HelpdeskService;
use App\Services\HelpdeskTransitionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class HelpdeskController extends Controller
{
    public function __construct(
        private HelpdeskService $helpdeskService,
        private HelpdeskTransitionService $transitionService,
        private HelpdeskReportService $reportService,
        private HelpdeskIntakeService $intakeService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        $activeTab = $request->get('tab') === 'reports' ? 'reports' : 'tickets';
        $canViewReports = $user->hasPermissionTo(Permission::VIEW_HD_REPORTS->value);

        // If user is on reports tab but lacks permission, fall back silently to tickets.
        if ($activeTab === 'reports' && ! $canViewReports) {
            $activeTab = 'tickets';
        }

        $filters = $request->only([
            'search', 'status', 'priority', 'department_id',
            'assigned_to_me', 'date_from', 'date_to',
        ]);

        $tickets = $this->helpdeskService->getTicketsForUser($user, $filters);

        // Reports data is only computed when the reports tab is active.
        $reports = null;
        if ($activeTab === 'reports' && $canViewReports) {
            $reportFilters = [
                'department_id' => $filters['department_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ];

            $reports = [
                'volumeByDay' => $this->reportService->volumeByDay($reportFilters),
                'slaCompliance' => $this->reportService->slaCompliance($reportFilters),
                'distributionByDepartment' => $this->reportService->distributionByDepartment($reportFilters),
                'averageResolutionTime' => $this->reportService->averageResolutionTime($reportFilters),
            ];
        }

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
            'filters' => $filters,
            'activeTab' => $activeTab,
            'canViewReports' => $canViewReports,
            'reports' => $reports,
            'statusOptions' => HdTicket::STATUS_LABELS,
            'priorityOptions' => HdTicket::PRIORITY_LABELS,
            'departments' => HdDepartment::active()->ordered()->get(['id', 'name']),
            'stores' => Store::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function show(HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanViewTicket(auth()->user(), $ticket), 403);

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
                'can_modify' => $this->helpdeskService->userCanModifyTicket(auth()->user(), $t),
                'can_delete' => $this->helpdeskService->userCanDeleteTicket(auth()->user(), $t),
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
                    'url' => route('helpdesk.download-attachment', $a->id),
                    'size' => $a->formatted_size,
                ]),
                'created_at' => $i->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        // Unified intake pipeline — web goes through the same path that
        // whatsapp/email will use. The WebIntakeDriver is a pass-through that
        // produces a ticket on the first call, so no behavior change vs. the
        // pre-refactor direct call to HelpdeskService::createTicket.
        $payload = array_merge($request->validated(), [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $step = $this->intakeService->handle('web', $payload, [
            'user_id' => auth()->id(),
        ]);

        $ticket = HdTicket::with('requester')->findOrFail($step->ticketId);

        try {
            TicketCreatedEvent::dispatch(
                $ticket->id,
                $ticket->department_id,
                $ticket->title,
                $ticket->requester?->name ?? '',
                HdTicket::PRIORITY_LABELS[$ticket->priority] ?? 'Média',
            );
        } catch (\Throwable $e) {
            // Broadcast indisponível — ignora
        }

        return redirect()->route('helpdesk.index')
            ->with('success', "Chamado #{$ticket->id} criado com sucesso.");
    }

    public function transition(TransitionTicketRequest $request, HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanModifyTicket(auth()->user(), $ticket), 403);

        $validated = $request->validated();

        // Reopen (CLOSED → IN_PROGRESS) requires manager + mandatory notes.
        $isReopen = $ticket->status === HdTicket::STATUS_CLOSED
            && $validated['status'] === HdTicket::STATUS_IN_PROGRESS;

        if ($isReopen) {
            abort_unless(
                $this->helpdeskService->userCanDeleteTicket(auth()->user(), $ticket), // managers only
                403,
                'Apenas gerentes podem reabrir chamados fechados.'
            );
            if (empty($validated['notes'])) {
                return response()->json(['error' => 'Um comentário é obrigatório ao reabrir um chamado.'], 422);
            }
        }

        $validation = $this->transitionService->validateTransition($ticket, $validated['status']);
        if (! $validation['valid']) {
            return response()->json(['error' => $validation['errors'][0]], 422);
        }

        $oldStatus = $ticket->status;
        $this->transitionService->executeTransition($ticket, $validated['status'], auth()->id(), $validated['notes'] ?? null);

        try {
            TicketStatusChangedEvent::dispatch($ticket->id, $ticket->department_id, $oldStatus, $validated['status']);
        } catch (\Throwable $e) {
            // Broadcast indisponível — ignora
        }

        return response()->json(['message' => 'Status atualizado para '.HdTicket::STATUS_LABELS[$validated['status']].'.']);
    }

    public function assign(AssignTicketRequest $request, HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanModifyTicket(auth()->user(), $ticket), 403);

        $this->transitionService->assignTechnician($ticket, $request->validated()['technician_id'], auth()->id());

        return response()->json(['message' => 'Técnico atribuído com sucesso.']);
    }

    public function changePriority(ChangePriorityRequest $request, HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanModifyTicket(auth()->user(), $ticket), 403);

        $this->transitionService->changePriority($ticket, $request->validated()['priority'], auth()->id());

        return response()->json(['message' => 'Prioridade atualizada.']);
    }

    public function addComment(AddCommentRequest $request, HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanViewTicket(auth()->user(), $ticket), 403);

        $validated = $request->validated();

        if (! empty($validated['is_internal'])
            && ! $this->helpdeskService->userCanCommentInternally(auth()->user(), $ticket)) {
            abort(403, 'Apenas técnicos do departamento podem adicionar notas internas.');
        }

        $interaction = $this->helpdeskService->addInteraction($ticket, $validated, auth()->id());

        try {
            TicketCommentEvent::dispatch(
                $ticket->id,
                auth()->id(),
                auth()->user()->name,
                $validated['is_internal'] ?? false,
            );
        } catch (\Throwable $e) {
            // Broadcast indisponível — ignora
        }

        return response()->json(['message' => 'Comentário adicionado.', 'interaction_id' => $interaction->id]);
    }

    public function uploadAttachment(UploadAttachmentRequest $request, HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanViewTicket(auth()->user(), $ticket), 403);

        $attachment = $this->helpdeskService->uploadAttachment(
            $ticket,
            $request->file('file'),
            auth()->id(),
            $request->get('interaction_id'),
        );

        return response()->json([
            'id' => $attachment->id,
            'name' => $attachment->original_filename,
            'url' => route('helpdesk.download-attachment', $attachment->id),
            'size' => $attachment->formatted_size,
        ]);
    }

    public function downloadAttachment(int $attachmentId)
    {
        $attachment = \App\Models\HdAttachment::with('ticket')->findOrFail($attachmentId);

        abort_unless(
            $this->helpdeskService->userCanViewTicket(auth()->user(), $attachment->ticket),
            403
        );

        // New uploads land on disk `local` (private) at helpdesk/tickets/…
        // Legacy uploads are on disk `public` at helpdesk-tickets/… — keep serving them
        // until helpdesk:relocate-attachments is run, then drop this fallback.
        $diskName = str_starts_with($attachment->file_path, 'helpdesk/tickets/')
            ? 'local'
            : 'public';

        $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->original_filename);
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

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:assign,status,delete',
            'ticket_ids' => 'required|array|min:1',
            'ticket_ids.*' => 'integer|exists:hd_tickets,id',
            'technician_id' => 'required_if:action,assign|nullable|integer|exists:users,id',
            'status' => 'required_if:action,status|nullable|string|in:'.implode(',', array_keys(HdTicket::STATUS_LABELS)),
        ]);

        $user = auth()->user();
        $tickets = HdTicket::whereIn('id', $validated['ticket_ids'])->get();

        $updated = 0;
        $skipped = 0;
        $errors = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $tickets, $user, &$updated, &$skipped, &$errors) {
            foreach ($tickets as $ticket) {
                // Authorization check per ticket
                if ($validated['action'] === 'delete') {
                    $allowed = $this->helpdeskService->userCanDeleteTicket($user, $ticket);
                } else {
                    $allowed = $this->helpdeskService->userCanModifyTicket($user, $ticket);
                }

                if (! $allowed) {
                    $skipped++;
                    $errors[] = "#{$ticket->id}: sem permissão";

                    continue;
                }

                try {
                    switch ($validated['action']) {
                        case 'assign':
                            $this->transitionService->assignTechnician($ticket, $validated['technician_id'], $user->id);
                            $updated++;
                            break;
                        case 'status':
                            $validation = $this->transitionService->validateTransition($ticket, $validated['status']);
                            if (! $validation['valid']) {
                                $skipped++;
                                $errors[] = "#{$ticket->id}: ".$validation['errors'][0];
                                break;
                            }
                            $this->transitionService->executeTransition($ticket, $validated['status'], $user->id);
                            $updated++;
                            break;
                        case 'delete':
                            $ticket->delete();
                            $updated++;
                            break;
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "#{$ticket->id}: ".$e->getMessage();
                }
            }
        });

        return response()->json([
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
            'message' => "Ação concluída: {$updated} atualizado(s), {$skipped} ignorado(s).",
        ]);
    }

    public function exportCsv(Request $request)
    {
        $filename = 'chamados-'.now()->format('Ymd-Hi').'.csv';

        return Excel::download(
            new HelpdeskTicketsExport(auth()->user(), $request->all()),
            $filename,
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function exportXlsx(Request $request)
    {
        $filename = 'chamados-'.now()->format('Ymd-Hi').'.xlsx';

        return Excel::download(
            new HelpdeskTicketsExport(auth()->user(), $request->all()),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
        );
    }

    public function exportPdf(Request $request)
    {
        $maxRows = 500;
        $filters = $request->all();

        // Mirror visibility scope.
        $query = HdTicket::query()
            ->with(['requester', 'assignedTechnician', 'department', 'category'])
            ->latest();

        $userDeptIds = HdPermission::where('user_id', auth()->id())
            ->pluck('department_id')->toArray();

        if (! empty($userDeptIds)) {
            $query->where(function ($q) use ($userDeptIds) {
                $q->where('requester_id', auth()->id())
                    ->orWhereIn('department_id', $userDeptIds);
            });
        } else {
            $query->where('requester_id', auth()->id());
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', (int) $filters['priority']);
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['assigned_to_me'])) {
            $query->where('assigned_technician_id', auth()->id());
        }
        if (! empty($filters['search'])) {
            $this->helpdeskService->applySearch($query, (string) $filters['search']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $tickets = $query->limit($maxRows)->get();

        $filterLabels = [];
        if (! empty($filters['status'])) {
            $filterLabels[] = 'Status: '.(HdTicket::STATUS_LABELS[$filters['status']] ?? $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $filterLabels[] = 'Prioridade: '.(HdTicket::PRIORITY_LABELS[(int) $filters['priority']] ?? $filters['priority']);
        }
        if (! empty($filters['search'])) {
            $filterLabels[] = 'Busca: '.$filters['search'];
        }

        $pdf = Pdf::loadView('helpdesk.exports.tickets-pdf', [
            'tickets' => $tickets,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'filterLabels' => $filterLabels,
            'maxRows' => $maxRows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('chamados-'.now()->format('Ymd-Hi').'.pdf');
    }

    public function merge(Request $request, HdTicket $ticket)
    {
        // Only managers can merge (reuses the delete check which requires manager level).
        abort_unless($this->helpdeskService->userCanDeleteTicket(auth()->user(), $ticket), 403);

        $validated = $request->validate([
            'target_ticket_id' => 'required|integer|exists:hd_tickets,id|different:ticket',
        ]);

        $target = HdTicket::findOrFail($validated['target_ticket_id']);
        abort_unless($this->helpdeskService->userCanModifyTicket(auth()->user(), $target), 403, 'Sem permissão no chamado de destino.');

        $merged = $this->helpdeskService->mergeTickets($ticket->id, $target->id, auth()->id());

        return response()->json([
            'message' => "Chamado #{$ticket->id} mesclado em #{$target->id}.",
            'target_id' => $merged->id,
        ]);
    }

    public function destroy(HdTicket $ticket)
    {
        abort_unless($this->helpdeskService->userCanDeleteTicket(auth()->user(), $ticket), 403);

        $ticket->delete();

        return redirect()->route('helpdesk.index')
            ->with('success', "Chamado #{$ticket->id} removido.");
    }
}
