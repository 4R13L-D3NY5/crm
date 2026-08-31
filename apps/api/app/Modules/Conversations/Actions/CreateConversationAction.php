<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Automations\Jobs\RunAutomationRulesJob;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Shared\Support\DispatchDomainJob;

class CreateConversationAction
{
    public function execute(User $user, array $payload): Conversation
    {
        $conversation = Conversation::query()->create([
            'organization_id' => $user->current_organization_id,
            'contact_id' => $payload['contact_id'] ?? null,
            'company_id' => $payload['company_id'] ?? null,
            'created_by_user_id' => $user->getKey(),
            'channel' => $payload['channel'] ?? 'manual',
            'status' => $payload['status'],
            'subject' => $payload['subject'] ?? null,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'organization_id' => $user->current_organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'direction' => 'internal',
            'message_type' => 'text',
            'message_status' => 'sent',
            'body' => $payload['message'],
            'sent_at' => now(),
        ]);

        if (array_key_exists('assigned_to_user_id', $payload)) {
            ConversationAssignment::query()->create([
                'conversation_id' => $conversation->getKey(),
                'assigned_to_user_id' => $payload['assigned_to_user_id'],
                'assigned_by_user_id' => $user->getKey(),
            ]);
        }

        DispatchDomainJob::dispatch(new RunAutomationRulesJob(
            $user->current_organization_id,
            'conversation.created',
            $conversation->getKey(),
        ));

        return $conversation->fresh()->load([
            'contact',
            'company',
            'assignment.assignee',
            'latestMessage.user',
            'messages.user',
        ]);
    }
}
