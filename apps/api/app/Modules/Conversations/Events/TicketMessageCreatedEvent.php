<?php

namespace App\Modules\Conversations\Events;

use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('tickets.' . $this->message->conversation_id),
            new Channel('organizations.' . $this->message->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message->load(['user', 'quotedMessage'])))->resolve(),
        ];
    }
}
