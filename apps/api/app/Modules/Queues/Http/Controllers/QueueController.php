<?php

namespace App\Modules\Queues\Http\Controllers;

use App\Modules\Queues\Actions\CreateQueueAction;
use App\Modules\Queues\Actions\DeleteQueueAction;
use App\Modules\Queues\Actions\UpdateQueueAction;
use App\Modules\Queues\Http\Requests\CreateQueueRequest;
use App\Modules\Queues\Http\Requests\UpdateQueueRequest;
use App\Modules\Queues\Http\Resources\QueueResource;
use App\Modules\Queues\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QueueController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = $request->user()->current_organization_id;

        $queues = Queue::query()
            ->where('organization_id', $organizationId)
            ->with(['users'])
            ->withCount(['tickets' => function ($query) {
                $query->whereIn('status', ['pending', 'open']);
            }])
            ->orderBy('order_index')
            ->orderBy('name')
            ->get();

        return QueueResource::collection($queues);
    }

    public function store(
        CreateQueueRequest $request,
        CreateQueueAction $createQueueAction,
    ): JsonResponse {
        $queue = $createQueueAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new QueueResource($queue))->resolve(),
            'message' => 'Fila de atención creada exitosamente.',
        ], 201);
    }

    public function show(Queue $queue): JsonResponse
    {
        return response()->json([
            'data' => (new QueueResource($queue->load(['users', 'businessHours'])))->resolve(),
        ]);
    }

    public function update(
        UpdateQueueRequest $request,
        Queue $queue,
        UpdateQueueAction $updateQueueAction,
    ): JsonResponse {
        $updatedQueue = $updateQueueAction->execute(
            $queue,
            $request->validated(),
        );

        return response()->json([
            'data' => (new QueueResource($updatedQueue))->resolve(),
            'message' => 'Fila de atención actualizada correctamente.',
        ]);
    }

    public function destroy(Queue $queue, DeleteQueueAction $deleteQueueAction): JsonResponse
    {
        $deleteQueueAction->execute($queue);

        return response()->json([
            'message' => 'Fila de atención eliminada correctamente.',
        ]);
    }
}
