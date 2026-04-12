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
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
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
     * Create a new ticket.
     */
    public function createTicket(array $data, int $userId): HdTicket
    {
        return DB::transaction(function () use ($data, $userId) {
            $priority = $data['priority'] ?? 2;
            $slaHours = HdTicket::SLA_HOURS[$priority] ?? 48;

            $ticket = HdTicket::create([
                'requester_id' => $userId,
                'department_id' => $data['department_id'],
                'category_id' => $data['category_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => HdTicket::STATUS_OPEN,
                'priority' => $priority,
                'sla_due_at' => now()->addHours($slaHours),
                'created_by_user_id' => $userId,
            ]);

            // Initial interaction
            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'comment' => 'Chamado criado.',
                'type' => 'status_change',
                'new_value' => HdTicket::STATUS_OPEN,
            ]);

            return $ticket->load('requester', 'department', 'category');
        });
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
     */
    public function uploadAttachment(HdTicket $ticket, UploadedFile $file, int $userId, ?int $interactionId = null): HdAttachment
    {
        $storedName = $file->hashName();
        $path = $file->storeAs("helpdesk-tickets/{$ticket->id}", $storedName, 'public');

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
}
