<?php

namespace App\Modules\Conversations\Policies;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('conversations.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if (!$user->hasPermission('conversations.view') || $user->current_organization_id !== $conversation->organization_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Si está asignado directamente a este operador
        if ($conversation->assigned_to_user_id === $user->id) {
            return true;
        }

        if ($conversation->assignment && $conversation->assignment->assigned_to_user_id === $user->id) {
            return true;
        }

        // Si la conversación no tiene canal asignado
        if (!$conversation->whatsapp_account_id) {
            return false;
        }

        $account = $conversation->whatsappAccount;
        if (!$account) {
            return false;
        }

        // Solo operadores autorizados en este canal con can_view pueden visualizarlo
        return $account->authorizedUsers()
            ->where('users.id', $user->id)
            ->wherePivot('can_view', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('conversations.manage');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $user->hasPermission('conversations.manage')
            && $this->view($user, $conversation);
    }
}
