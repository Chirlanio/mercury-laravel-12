<?php

namespace App\Services;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatGroupService
{
    public function __construct(private ChatService $chatService) {}

    /**
     * Create a group with a linked conversation.
     */
    public function createGroup(array $data, int $creatorId): ChatGroup
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $conversation = Conversation::create(['type' => 'group', 'title' => $data['name']]);

            $group = ChatGroup::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'avatar_path' => $data['avatar_path'] ?? null,
                'created_by_user_id' => $creatorId,
                'conversation_id' => $conversation->id,
                'max_members' => $data['max_members'] ?? 50,
                'only_admins_can_send' => $data['only_admins_can_send'] ?? false,
            ]);

            // Add creator as admin
            ChatGroupMember::create([
                'group_id' => $group->id,
                'user_id' => $creatorId,
                'role' => 'admin',
            ]);
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $creatorId,
            ]);

            // Add other members
            foreach ($data['member_ids'] ?? [] as $memberId) {
                if ((int) $memberId === $creatorId) {
                    continue;
                }
                $this->addMember($group, (int) $memberId);
            }

            // System message
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $creatorId,
                'content' => 'Grupo criado.',
                'message_type' => 'text',
                'created_at' => now(),
            ]);

            return $group->load('activeMembers.user', 'creator');
        });
    }

    /**
     * Update group settings.
     */
    public function updateGroup(ChatGroup $group, array $data): ChatGroup
    {
        $group->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'max_members' => $data['max_members'] ?? null,
            'only_admins_can_send' => $data['only_admins_can_send'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['name']) && $group->conversation) {
            $group->conversation->update(['title' => $data['name']]);
        }

        return $group->fresh()->load('activeMembers.user');
    }

    /**
     * Add a member to the group.
     */
    public function addMember(ChatGroup $group, int $userId, string $role = 'member'): ChatGroupMember
    {
        $member = ChatGroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $userId],
            ['role' => $role, 'left_at' => null, 'joined_at' => now()],
        );

        if ($group->conversation_id) {
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $group->conversation_id,
                'user_id' => $userId,
            ]);
        }

        return $member;
    }

    /**
     * Remove a member from the group.
     */
    public function removeMember(ChatGroup $group, int $userId): void
    {
        ChatGroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->update(['left_at' => now()]);
    }

    /**
     * Update member role (admin/member).
     */
    public function updateMemberRole(ChatGroup $group, int $userId, string $role): void
    {
        ChatGroupMember::where('group_id', $group->id)
            ->where('user_id', $userId)
            ->update(['role' => $role]);
    }

    /**
     * Get groups for a user.
     */
    public function getGroupsForUser(int $userId): Collection
    {
        if (! Schema::hasTable('chat_groups')) {
            return collect();
        }

        return ChatGroup::active()
            ->forUser($userId)
            ->withCount('activeMembers')
            ->with('latestConversationMessage')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'conversation_id' => $g->conversation_id,
                'members_count' => $g->active_members_count,
                'is_admin' => $g->activeMembers->where('user_id', $userId)->first()?->isAdmin() ?? false,
            ]);
    }

    /**
     * Deactivate a group.
     */
    public function deactivateGroup(ChatGroup $group): void
    {
        $group->update(['is_active' => false]);
    }
}
