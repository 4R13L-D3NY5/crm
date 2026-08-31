<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Facades\DB;

class CloseTicketAction
{
    public function execute(User $user, Conversation $conversation, array $payload = []): Conversation
    {
        return DB::transaction(function () use ($conversation, $payload) {
            $conversation->update([
                'status' => 'closed',
                'closed_at' => now(),
                'rating' => $payload['rating'] ?? null,
                'feedback' => $payload['feedback'] ?? null,
            ]);

            $fresh = $conversation->fresh(['queue', 'assignee', 'contact', 'company']);
            \App\Modules\Conversations\Events\TicketUpdatedEvent::dispatch($fresh);

            return $fresh;
        });
    }
}
