<?php

namespace App\Modules\Queues\Actions;

use App\Modules\Queues\Models\Queue;
use Illuminate\Support\Facades\DB;

class UpdateQueueAction
{
    public function execute(Queue $queue, array $data): Queue
    {
        return DB::transaction(function () use ($queue, $data) {
            $queue->update([
                'name' => $data['name'] ?? $queue->name,
                'color' => $data['color'] ?? $queue->color,
                'greeting_message' => array_key_exists('greeting_message', $data) ? $data['greeting_message'] : $queue->greeting_message,
                'out_of_hours_message' => array_key_exists('out_of_hours_message', $data) ? $data['out_of_hours_message'] : $queue->out_of_hours_message,
                'order_index' => $data['order_index'] ?? $queue->order_index,
                'is_active' => $data['is_active'] ?? $queue->is_active,
                'chatbot_options' => array_key_exists('chatbot_options', $data) ? $data['chatbot_options'] : $queue->chatbot_options,
            ]);

            if (isset($data['user_ids'])) {
                $queue->users()->sync($data['user_ids']);
            }

            return $queue->fresh(['users']);
        });
    }
}
