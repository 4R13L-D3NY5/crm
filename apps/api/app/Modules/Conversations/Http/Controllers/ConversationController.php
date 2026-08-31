<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Modules\Conversations\Actions\CreateConversationAction;
use App\Modules\Conversations\Http\Requests\CreateConversationRequest;
use App\Modules\Conversations\Http\Requests\ListConversationsRequest;
use App\Modules\Conversations\Http\Resources\ConversationResource;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Queries\ListConversationsQuery;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController
{
    public function index(
        ListConversationsRequest $request,
        ListConversationsQuery $listConversationsQuery,
    ): AnonymousResourceCollection {
        $this->authorize($request, 'viewAny', Conversation::class);

        $conversations = $listConversationsQuery->execute(
            $request->user(),
            $request->validated(),
        );

        return ConversationResource::collection($conversations);
    }

    public function store(
        CreateConversationRequest $request,
        CreateConversationAction $createConversationAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        $this->authorize($request, 'create', Conversation::class);

        $conversation = $createConversationAction->execute(
            $request->user(),
            $request->validated(),
        );
        $writeAuditLogAction->execute(
            event: 'conversations.created',
            user: $request->user(),
            request: $request,
            auditable: $conversation,
            metadata: [
                'channel' => $conversation->channel,
                'status' => $conversation->status,
            ],
        );

        return response()->json([
            'data' => (new ConversationResource($conversation))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $request = request();
        $this->authorize($request, 'view', $conversation);

        return response()->json([
            'data' => (new ConversationResource($conversation->load([
                'contact',
                'company',
                'assignment.assignee',
                'messages.user',
                'latestMessage.user',
            ])))->resolve(),
        ]);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
