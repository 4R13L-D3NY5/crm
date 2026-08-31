<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;

class CreateInternalMessageAction
{
    public function execute(User $user, Conversation $conversation, array $payload): Message
    {
        $message = Message::query()->create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'direction' => 'internal',
            'is_internal' => true,
            'message_type' => 'text',
            'message_status' => 'sent',
            'body' => $payload['body'],
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();

        $freshMessage = $message->load('user');
        \App\Modules\Conversations\Events\TicketMessageCreatedEvent::dispatch($freshMessage);

        return $freshMessage;
    }
}
