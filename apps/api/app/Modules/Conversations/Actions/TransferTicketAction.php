<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use Illuminate\Support\Facades\DB;

class TransferTicketAction
{
    public function execute(User $user, Conversation $conversation, array $payload): Conversation
    {
        return DB::transaction(function () use ($user, $conversation, $payload) {
            $newUserId = $payload['assigned_to_user_id'] ?? null;
            $newQueueId = $payload['queue_id'] ?? $conversation->queue_id;

            $conversation->update([
                'assigned_to_user_id' => $newUserId,
                'queue_id' => $newQueueId,
                'status' => $newUserId ? 'open' : 'pending',
            ]);

            ConversationAssignment::query()->updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'assigned_to_user_id' => $newUserId,
                    'assigned_by_user_id' => $user->id,
                ]
            );

            // Si se adjunta una nota interna explicativa de la transferencia:
            if (!empty($payload['transfer_note'])) {
                Message::create([
                    'organization_id' => $conversation->organization_id,
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'direction' => 'internal',
                    'is_internal' => true,
                    'message_type' => 'text',
                    'body' => "🔄 *Transferencia*: " . $payload['transfer_note'],
                    'sent_at' => now(),
                ]);
            }

            $fresh = $conversation->fresh(['queue', 'assignee', 'contact', 'company']);
            \App\Modules\Conversations\Events\TicketUpdatedEvent::dispatch($fresh);

            return $fresh;
        });
    }
}
