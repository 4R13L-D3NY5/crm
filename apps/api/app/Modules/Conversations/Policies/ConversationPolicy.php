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
        return $user->hasPermission('conversations.view')
            && $user->current_organization_id === $conversation->organization_id;
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
