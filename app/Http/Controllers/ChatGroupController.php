<?php

namespace App\Http\Controllers;

use App\Models\ChatGroup;
use App\Services\ChatGroupService;
use Illuminate\Http\Request;

class ChatGroupController extends Controller
{
    public function __construct(private ChatGroupService $groupService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'nullable|integer|min:2|max:200',
            'only_admins_can_send' => 'nullable|boolean',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        $group = $this->groupService->createGroup($validated, auth()->id());

        return redirect()->route('chat.show', $group->conversation_id)
            ->with('success', "Grupo '{$group->name}' criado.");
    }

    public function update(Request $request, ChatGroup $chatGroup)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'nullable|integer|min:2|max:200',
            'only_admins_can_send' => 'nullable|boolean',
        ]);

        $this->groupService->updateGroup($chatGroup, $validated);

        return response()->json(['message' => 'Grupo atualizado.']);
    }

    public function destroy(ChatGroup $chatGroup)
    {
        $this->groupService->deactivateGroup($chatGroup);

        return response()->json(['message' => 'Grupo desativado.']);
    }

    public function addMember(Request $request, ChatGroup $chatGroup)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|in:admin,member',
        ]);

        $this->groupService->addMember($chatGroup, $validated['user_id'], $validated['role'] ?? 'member');

        return response()->json(['message' => 'Membro adicionado.']);
    }

    public function removeMember(Request $request, ChatGroup $chatGroup, int $userId)
    {
        $this->groupService->removeMember($chatGroup, $userId);

        return response()->json(['message' => 'Membro removido.']);
    }

    public function updateMemberRole(Request $request, ChatGroup $chatGroup, int $userId)
    {
        $validated = $request->validate(['role' => 'required|string|in:admin,member']);

        $this->groupService->updateMemberRole($chatGroup, $userId, $validated['role']);

        return response()->json(['message' => 'Função atualizada.']);
    }
}
