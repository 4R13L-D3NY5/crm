<?php

namespace App\Modules\Conversations\Actions;

use App\Modules\Conversations\Models\Conversation;

class UpdateConversationStatusAction
{
    public function execute(Conversation $conversation, array $payload): Conversation
    {
        $conversation->update([
            'status' => $payload['status'],
        ]);

        return $conversation->load([
            'contact',
            'company',
            'assignment.assignee',
            'latestMessage.user',
            'messages.user',
        ]);
    }
}
