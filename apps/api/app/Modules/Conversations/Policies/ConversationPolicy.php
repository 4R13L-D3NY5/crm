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

        // Si la conversación no tiene canal asignado
        if (!$conversation->whatsapp_account_id) {
            return true;
        }

        $account = $conversation->whatsappAccount;
        if (!$account) {
            return true;
        }

        // Si el canal no tiene asignaciones específicas, es visible para todos los operadores
        if (!$account->authorizedUsers()->exists()) {
            return true;
        }

        // Si el canal tiene operadores específicos, verificar permiso de visualización
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
