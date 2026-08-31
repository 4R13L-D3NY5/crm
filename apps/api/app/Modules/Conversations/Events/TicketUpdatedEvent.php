<?php

namespace App\Modules\Conversations\Events;

use App\Modules\Conversations\Http\Resources\ConversationResource;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('organizations.' . $this->conversation->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => (new ConversationResource($this->conversation->load(['queue', 'assignee', 'contact'])))->resolve(),
        ];
    }
}
