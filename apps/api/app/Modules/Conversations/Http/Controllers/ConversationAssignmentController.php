<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Modules\Conversations\Actions\AssignConversationAction;
use App\Modules\Conversations\Http\Requests\AssignConversationRequest;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;

class ConversationAssignmentController
{
    public function update(
        AssignConversationRequest $request,
        Conversation $conversation,
        AssignConversationAction $assignConversationAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        abort_unless($request->user()?->can('update', $conversation), 403);
        abort_unless($request->user()?->hasPermission('conversations.assign'), 403);

        $assignment = $assignConversationAction->execute(
            $request->user(),
            $conversation,
            $request->validated(),
        );
        $writeAuditLogAction->execute(
            event: 'conversations.assigned',
            user: $request->user(),
            request: $request,
            auditable: $conversation,
            metadata: [
                'assigned_to_user_id' => $assignment->assigned_to_user_id,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'assigned_to_user_id' => $assignment->assigned_to_user_id,
                'assignee' => $assignment->assignee ? [
                    'id' => $assignment->assignee->id,
                    'name' => $assignment->assignee->name,
                ] : null,
            ],
            'message' => 'Registro guardado correctamente.',
        ]);
    }
}
