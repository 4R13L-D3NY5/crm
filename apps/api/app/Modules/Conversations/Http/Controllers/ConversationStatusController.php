<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Modules\Conversations\Actions\UpdateConversationStatusAction;
use App\Modules\Conversations\Http\Requests\UpdateConversationStatusRequest;
use App\Modules\Conversations\Http\Resources\ConversationResource;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;

class ConversationStatusController
{
    public function update(
        UpdateConversationStatusRequest $request,
        Conversation $conversation,
        UpdateConversationStatusAction $updateConversationStatusAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        abort_unless($request->user()?->can('update', $conversation), 403);
        abort_unless($request->user()?->hasPermission('conversations.status.manage'), 403);

        $previousStatus = $conversation->status;

        $conversation = $updateConversationStatusAction->execute(
            $conversation,
            $request->validated(),
        );
        $writeAuditLogAction->execute(
            event: 'conversations.status_updated',
            user: $request->user(),
            request: $request,
            auditable: $conversation,
            metadata: [
                'previous_status' => $previousStatus,
                'current_status' => $conversation->status,
            ],
        );

        return response()->json([
            'data' => (new ConversationResource($conversation))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }
}
