<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use Illuminate\Support\Facades\DB;

class AcceptTicketAction
{
    public function execute(User $user, Conversation $conversation): Conversation
    {
        return DB::transaction(function () use ($user, $conversation) {
            $conversation->update([
                'status' => 'open',
                'assigned_to_user_id' => $user->id,
            ]);

            ConversationAssignment::query()->updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'assigned_to_user_id' => $user->id,
                    'assigned_by_user_id' => $user->id,
                ]
            );

            $fresh = $conversation->fresh(['queue', 'assignee', 'contact', 'company']);
            \App\Modules\Conversations\Events\TicketUpdatedEvent::dispatch($fresh);

            return $fresh;
        });
    }
}
