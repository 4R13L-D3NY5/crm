<?php

namespace App\Modules\Queues\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'greeting_message' => $this->greeting_message,
            'out_of_hours_message' => $this->out_of_hours_message,
            'order_index' => $this->order_index,
            'is_active' => $this->is_active,
            'chatbot_options' => $this->chatbot_options,
            'users' => $this->whenLoaded('users', function () {
                return $this->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]);
            }),
            'tickets_count' => $this->whenCounted('tickets'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
