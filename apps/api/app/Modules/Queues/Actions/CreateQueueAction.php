<?php

namespace App\Modules\Queues\Actions;

use App\Models\User;
use App\Modules\Queues\Models\Queue;
use Illuminate\Support\Facades\DB;

class CreateQueueAction
{
    public function execute(User $user, array $data): Queue
    {
        return DB::transaction(function () use ($user, $data) {
            $queue = Queue::create([
                'organization_id' => $user->current_organization_id,
                'name' => $data['name'],
                'color' => $data['color'] ?? '#25D366',
                'greeting_message' => $data['greeting_message'] ?? null,
                'out_of_hours_message' => $data['out_of_hours_message'] ?? null,
                'order_index' => $data['order_index'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'chatbot_options' => $data['chatbot_options'] ?? null,
            ]);

            if (!empty($data['user_ids'])) {
                $queue->users()->sync($data['user_ids']);
            }

            return $queue->load('users');
        });
    }
}
