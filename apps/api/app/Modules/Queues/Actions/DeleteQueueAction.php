<?php

namespace App\Modules\Queues\Actions;

use App\Modules\Queues\Models\Queue;

class DeleteQueueAction
{
    public function execute(Queue $queue): void
    {
        $queue->delete();
    }
}
