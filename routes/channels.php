<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channel authorization callbacks run within the tenant database context
| thanks to the broadcasting auth route being inside the tenant middleware.
|
*/

// Private channel for a specific user (DMs, notifications, unread counts)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for a conversation (messages, typing, read receipts)
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    if (! \Illuminate\Support\Facades\Schema::hasTable('conversation_participants')) {
        return false;
    }

    return \App\Models\ConversationParticipant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});

// Private channel for a chat group
Broadcast::channel('chat-group.{groupId}', function ($user, $groupId) {
    if (! \Illuminate\Support\Facades\Schema::hasTable('chat_group_members')) {
        return false;
    }

    return \App\Models\ChatGroupMember::where('group_id', $groupId)
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

// Private channel for a helpdesk ticket
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    if (! \Illuminate\Support\Facades\Schema::hasTable('hd_tickets')) {
        return false;
    }

    $ticket = \App\Models\HdTicket::find($ticketId);
    if (! $ticket) {
        return false;
    }

    // Requester, assigned technician, or department manager can listen
    return $ticket->requester_id === $user->id
        || $ticket->assigned_technician_id === $user->id
        || \App\Models\HdPermission::where('user_id', $user->id)
            ->where('department_id', $ticket->department_id)
            ->exists();
});

// Private channel for helpdesk department (new tickets)
Broadcast::channel('hd-department.{departmentId}', function ($user, $departmentId) {
    if (! \Illuminate\Support\Facades\Schema::hasTable('hd_permissions')) {
        return false;
    }

    return \App\Models\HdPermission::where('user_id', $user->id)
        ->where('department_id', $departmentId)
        ->exists();
});

// Private channel per-store for damaged-products module (match found/accepted/rejected).
// Authorization: usuário com MANAGE_DAMAGED_PRODUCTS bypassa scoping (ouve qualquer
// loja). Demais só ouvem a própria loja — users.store_id é code (Z441), o canal
// usa stores.id (numérico), então fazemos lookup code→id.
Broadcast::channel('damaged-products.store.{storeId}', function ($user, $storeId) {
    if ($user->hasPermissionTo(\App\Enums\Permission::MANAGE_DAMAGED_PRODUCTS->value)) {
        return true;
    }

    if (! $user->store_id) {
        return false;
    }

    $userStoreId = \App\Models\Store::where('code', $user->store_id)->value('id');

    return $userStoreId !== null && (int) $userStoreId === (int) $storeId;
});

// Private channel per-store for relocations module (status updates em tempo real
// pra origem e destino). Mesmo padrão do damaged-products: MANAGE_RELOCATIONS
// bypassa, demais só ouvem própria loja. APPROVE_RELOCATIONS (planejamento)
// também bypassa pra ter visão global do que está em trânsito.
Broadcast::channel('relocations.store.{storeId}', function ($user, $storeId) {
    if ($user->hasPermissionTo(\App\Enums\Permission::MANAGE_RELOCATIONS->value)
        || $user->hasPermissionTo(\App\Enums\Permission::APPROVE_RELOCATIONS->value)) {
        return true;
    }

    if (! $user->store_id) {
        return false;
    }

    $userStoreId = \App\Models\Store::where('code', $user->store_id)->value('id');

    return $userStoreId !== null && (int) $userStoreId === (int) $storeId;
});
