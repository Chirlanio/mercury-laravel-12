<?php

namespace App\Services;

use App\Models\HdAttachment;
use App\Models\HdCategory;
use App\Models\HdInteraction;
use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskService
{
    public function __construct(
        private ?HelpdeskSlaCalculator $slaCalculator = null,
    ) {
        $this->slaCalculator ??= app(HelpdeskSlaCalculator::class);
    }

    /**
     * Get tickets visible to the user (own tickets + department tickets if technician).
     */
    public function getTicketsForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = HdTicket::with(['requester', 'assignedTechnician', 'department', 'category'])
            ->latest();

        // Department technicians/managers see all tickets in their departments
        $userDeptIds = HdPermission::where('user_id', $user->id)->pluck('department_id')->toArray();

        if (! empty($userDeptIds)) {
            $query->where(function ($q) use ($user, $userDeptIds) {
                $q->where('requester_id', $user->id)
                    ->orWhereIn('department_id', $userDeptIds);
            });
        } else {
            $query->where('requester_id', $user->id);
        }

        // Apply filters
        if (! empty($filters['status'])) {
            $query->forStatus($filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->forPriority((int) $filters['priority']);
        }
        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['assigned_to_me'])) {
            $query->where('assigned_technician_id', $user->id);
        }
        if (! empty($filters['search'])) {
            $this->applySearch($query, (string) $filters['search']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate(20);
    }

    /**
     * Apply the search filter to a ticket query.
     *
     * Strategy:
     *   - Numeric input → exact ticket id match OR full-text (for "issue 42 printer" cases)
     *   - MySQL + term ≥ 3 chars → MATCH(title, description) AGAINST (boolean) plus
     *     a subquery MATCH on hd_interactions.comment for body-of-thread hits.
     *   - Otherwise (SQLite tests, 1–2 char terms) → LIKE fallback on title + description.
     *
     * All branches union by OR, so a single search string lights up any matching ticket.
     */
    public function applySearch(\Illuminate\Database\Eloquent\Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $canUseFulltext = $driver === 'mysql' && mb_strlen($search) >= 3;

        $query->where(function ($q) use ($search, $canUseFulltext) {
            // Always allow exact id lookup
            if (ctype_digit($search)) {
                $q->orWhere('id', (int) $search);
            }

            if ($canUseFulltext) {
                // Boolean mode + wildcard to catch prefix matches ("impr" → "impressora").
                $booleanTerm = $this->toBooleanFulltextTerm($search);
                $q->orWhereRaw(
                    'MATCH(title, description) AGAINST (? IN BOOLEAN MODE)',
                    [$booleanTerm]
                );
                $q->orWhereIn('id', function ($sub) use ($booleanTerm) {
                    $sub->select('ticket_id')
                        ->from('hd_interactions')
                        ->whereRaw('MATCH(comment) AGAINST (? IN BOOLEAN MODE)', [$booleanTerm]);
                });
            } else {
                $like = '%'.$search.'%';
                $q->orWhere('title', 'like', $like);
                $q->orWhere('description', 'like', $like);
                $q->orWhereIn('id', function ($sub) use ($like) {
                    $sub->select('ticket_id')
                        ->from('hd_interactions')
                        ->where('comment', 'like', $like);
                });
            }
        });
    }

    /**
     * Convert a user search phrase into a MySQL BOOLEAN MODE query string.
     * Strips operators the user might type and appends a trailing wildcard
     * for prefix matching on the last token.
     */
    private function toBooleanFulltextTerm(string $search): string
    {
        // Remove the reserved operators MySQL interprets specially.
        $cleaned = preg_replace('/[+\-><()~*"@]/u', ' ', $search) ?? $search;
        $tokens = preg_split('/\s+/', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (empty($tokens)) {
            return $search;
        }

        // Require every token, and allow prefix match on the last one.
        $last = array_pop($tokens);
        $parts = array_map(fn ($t) => '+'.$t, $tokens);
        $parts[] = '+'.$last.'*';

        return implode(' ', $parts);
    }

    /**
     * Create a new ticket.
     */
    public function createTicket(array $data, int $userId): HdTicket
    {
        return DB::transaction(function () use ($data, $userId) {
            $priority = $data['priority'] ?? 2;
            $slaHours = HdTicket::SLA_HOURS[$priority] ?? 48;

            $department = \App\Models\HdDepartment::find($data['department_id']);
            $now = now();
            $slaDueAt = $this->slaCalculator->calculateDueDate($now, $slaHours, $department);

            $ticket = HdTicket::create([
                'requester_id' => $userId,
                'employee_id' => $data['employee_id'] ?? null,
                'department_id' => $data['department_id'],
                'category_id' => $data['category_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => HdTicket::STATUS_OPEN,
                'priority' => $priority,
                'source' => $data['source'] ?? 'web',
                'sla_due_at' => $slaDueAt,
                'created_by_user_id' => $userId,
            ]);

            // Attach channel origin (web / whatsapp / email). Optional: older
            // callers that do not know about channels still work — the ticket
            // just won't have an hd_ticket_channels row. The 'source' column
            // is always populated regardless.
            if (! empty($data['channel_id'])) {
                \App\Models\HdTicketChannel::create([
                    'ticket_id' => $ticket->id,
                    'channel_id' => $data['channel_id'],
                    'external_contact' => $data['external_contact'] ?? null,
                    'external_id' => $data['external_id'] ?? null,
                    'metadata' => $data['channel_metadata'] ?? null,
                ]);
            }

            // Persist structured intake data when a template was used.
            if (! empty($data['intake_template_id']) && ! empty($data['intake_data'])) {
                \App\Models\HdTicketIntakeData::create([
                    'ticket_id' => $ticket->id,
                    'template_id' => $data['intake_template_id'],
                    'data' => $data['intake_data'],
                ]);
            }

            // Initial interaction
            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'comment' => 'Chamado criado.',
                'type' => 'status_change',
                'new_value' => HdTicket::STATUS_OPEN,
            ]);

            // Auto-assign (round-robin) if department has auto_assign enabled
            if ($department && $department->auto_assign) {
                $technicianId = $this->autoAssignTechnician($ticket);
                if ($technicianId) {
                    $ticket->update(['assigned_technician_id' => $technicianId]);
                    // Note: we don't run the full assignment service here to avoid
                    // re-entering a transaction; the creation event carries the info.
                }
            }

            return $ticket->load('requester', 'department', 'category', 'assignedTechnician');
        });
    }

    /**
     * Pick a technician for round-robin auto-assignment: the technician in the
     * ticket's department with the fewest active (non-terminal) tickets.
     * Managers are included in the pool. Returns null if no eligible user.
     */
    public function autoAssignTechnician(HdTicket $ticket): ?int
    {
        $technicianIds = HdPermission::where('department_id', $ticket->department_id)
            ->whereIn('level', ['technician', 'manager'])
            ->pluck('user_id')
            ->toArray();

        if (empty($technicianIds)) {
            return null;
        }

        // Count active tickets per technician and pick the one with fewest.
        $loads = HdTicket::whereIn('assigned_technician_id', $technicianIds)
            ->whereNotIn('status', HdTicket::TERMINAL_STATUSES)
            ->selectRaw('assigned_technician_id, COUNT(*) as total')
            ->groupBy('assigned_technician_id')
            ->pluck('total', 'assigned_technician_id')
            ->toArray();

        $best = null;
        $bestLoad = PHP_INT_MAX;
        foreach ($technicianIds as $id) {
            $load = $loads[$id] ?? 0;
            if ($load < $bestLoad) {
                $bestLoad = $load;
                $best = $id;
            }
        }

        return $best;
    }

    /**
     * Get full ticket details with interactions.
     */
    public function getTicketDetails(HdTicket $ticket): array
    {
        $ticket->load([
            'requester', 'assignedTechnician', 'department', 'category', 'store',
            'interactions.user', 'interactions.attachments', 'attachments.uploadedBy',
            'createdBy',
        ]);

        return [
            'ticket' => $ticket,
            'interactions' => $ticket->interactions,
            'attachments' => $ticket->attachments,
        ];
    }

    /**
     * Add an interaction (comment or internal note) to a ticket.
     */
    public function addInteraction(HdTicket $ticket, array $data, int $userId): HdInteraction
    {
        return HdInteraction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'comment' => $data['comment'],
            'type' => 'comment',
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }

    /**
     * Upload attachment to a ticket.
     *
     * Files are stored on the private `local` disk under helpdesk/tickets/{id}/.
     * They are never directly accessible via HTTP — downloads always go through
     * HelpdeskController::downloadAttachment which enforces view permissions.
     */
    public function uploadAttachment(HdTicket $ticket, UploadedFile $file, int $userId, ?int $interactionId = null): HdAttachment
    {
        $storedName = $file->hashName();
        $path = $file->storeAs("helpdesk/tickets/{$ticket->id}", $storedName, 'local');

        return HdAttachment::create([
            'ticket_id' => $ticket->id,
            'interaction_id' => $interactionId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $userId,
        ]);
    }

    /**
     * Get statistics for the helpdesk dashboard.
     */
    public function getStatistics(User $user, array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return ['total' => 0, 'open' => 0, 'in_progress' => 0, 'pending' => 0, 'resolved' => 0, 'overdue' => 0];
        }

        $baseQuery = HdTicket::query();

        $userDeptIds = HdPermission::where('user_id', $user->id)->pluck('department_id')->toArray();
        if (! empty($userDeptIds)) {
            $baseQuery->where(fn ($q) => $q->where('requester_id', $user->id)->orWhereIn('department_id', $userDeptIds));
        } else {
            $baseQuery->where('requester_id', $user->id);
        }

        return [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->forStatus(HdTicket::STATUS_OPEN)->count(),
            'in_progress' => (clone $baseQuery)->forStatus(HdTicket::STATUS_IN_PROGRESS)->count(),
            'pending' => (clone $baseQuery)->forStatus(HdTicket::STATUS_PENDING)->count(),
            'resolved' => (clone $baseQuery)->forStatus(HdTicket::STATUS_RESOLVED)->count(),
            'overdue' => (clone $baseQuery)->overdue()->count(),
        ];
    }

    /**
     * Get departments the user has HD permissions on.
     */
    public function getUserDepartments(int $userId): Collection
    {
        if (! Schema::hasTable('hd_permissions')) {
            return collect();
        }

        return HdPermission::where('user_id', $userId)
            ->with('department')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->department_id,
                'name' => $p->department?->name,
                'level' => $p->level,
            ]);
    }

    /**
     * Get cascading categories for a department.
     */
    public function getCategoriesForDepartment(int $departmentId): Collection
    {
        return HdCategory::active()
            ->forDepartment($departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'default_priority']);
    }

    /**
     * Get technicians for a department.
     */
    public function getTechniciansForDepartment(int $departmentId): Collection
    {
        return HdPermission::where('department_id', $departmentId)
            ->with('user:id,name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->user_id,
                'name' => $p->user?->name,
                'level' => $p->level,
            ]);
    }

    /**
     * Merge two tickets: copy interactions + attachments from $sourceId into $targetId,
     * then close the source ticket with a reference. Only managers should call this
     * (caller is responsible for authorization).
     *
     * @return HdTicket The updated target ticket.
     */
    public function mergeTickets(int $sourceId, int $targetId, int $userId): HdTicket
    {
        abort_if($sourceId === $targetId, 422, 'Não é possível mesclar um chamado com ele mesmo.');

        return DB::transaction(function () use ($sourceId, $targetId, $userId) {
            /** @var HdTicket $source */
            $source = HdTicket::with('interactions', 'attachments')->findOrFail($sourceId);
            /** @var HdTicket $target */
            $target = HdTicket::findOrFail($targetId);

            abort_if($source->isTerminal(), 422, 'Chamado de origem já está fechado/cancelado.');
            abort_if($target->isTerminal(), 422, 'Chamado de destino já está fechado/cancelado.');

            // Move interactions
            foreach ($source->interactions as $interaction) {
                HdInteraction::create([
                    'ticket_id' => $target->id,
                    'user_id' => $interaction->user_id,
                    'comment' => "[Mesclado de #{$source->id}] ".($interaction->comment ?? ''),
                    'type' => $interaction->type,
                    'old_value' => $interaction->old_value,
                    'new_value' => $interaction->new_value,
                    'is_internal' => $interaction->is_internal,
                ]);
            }

            // Move attachments (re-point only — files remain in place on disk)
            foreach ($source->attachments as $attachment) {
                $attachment->update(['ticket_id' => $target->id, 'interaction_id' => null]);
            }

            // Close source as merged
            $source->update([
                'status' => HdTicket::STATUS_CLOSED,
                'closed_at' => now(),
                'merged_into_ticket_id' => $target->id,
                'updated_by_user_id' => $userId,
            ]);

            HdInteraction::create([
                'ticket_id' => $source->id,
                'user_id' => $userId,
                'comment' => "Chamado mesclado em #{$target->id}.",
                'type' => 'status_change',
                'old_value' => HdTicket::STATUS_IN_PROGRESS,
                'new_value' => HdTicket::STATUS_CLOSED,
            ]);

            HdInteraction::create([
                'ticket_id' => $target->id,
                'user_id' => $userId,
                'comment' => "Recebeu dados mesclados de #{$source->id}.",
                'type' => 'comment',
            ]);

            return $target->fresh();
        });
    }

    // ---------------------------------------------------------------------
    // Authorization helpers (ownership + department-level)
    // ---------------------------------------------------------------------

    /**
     * Can the user view the ticket? True if requester, assigned technician,
     * or has any HdPermission row for the ticket's department.
     */
    public function userCanViewTicket(User $user, HdTicket $ticket): bool
    {
        if ($ticket->requester_id === $user->id) {
            return true;
        }

        if ($ticket->assigned_technician_id === $user->id) {
            return true;
        }

        return HdPermission::where('user_id', $user->id)
            ->where('department_id', $ticket->department_id)
            ->exists();
    }

    /**
     * Can the user modify the ticket (transition, assign, change priority)?
     * True for department managers and the assigned technician.
     */
    public function userCanModifyTicket(User $user, HdTicket $ticket): bool
    {
        if ($ticket->assigned_technician_id === $user->id) {
            return true;
        }

        return HdPermission::where('user_id', $user->id)
            ->where('department_id', $ticket->department_id)
            ->whereIn('level', ['technician', 'manager'])
            ->exists();
    }

    /**
     * Can the user delete the ticket? Only department managers.
     */
    public function userCanDeleteTicket(User $user, HdTicket $ticket): bool
    {
        return HdPermission::where('user_id', $user->id)
            ->where('department_id', $ticket->department_id)
            ->where('level', 'manager')
            ->exists();
    }

    /**
     * Can the user add an internal note? Only technicians/managers of the department.
     */
    public function userCanCommentInternally(User $user, HdTicket $ticket): bool
    {
        return HdPermission::where('user_id', $user->id)
            ->where('department_id', $ticket->department_id)
            ->whereIn('level', ['technician', 'manager'])
            ->exists();
    }
}
