<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;

class AssignConversationAction
{
    public function execute(User $user, Conversation $conversation, array $payload): ConversationAssignment
    {
        $assignment = ConversationAssignment::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->getKey(),
            ],
            [
                'assigned_to_user_id' => $payload['assigned_to_user_id'] ?? null,
                'assigned_by_user_id' => $user->getKey(),
            ],
        );

        return $assignment->load('assignee', 'assignedBy');
    }
}
