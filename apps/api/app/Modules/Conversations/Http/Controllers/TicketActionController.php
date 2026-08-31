<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Modules\Conversations\Actions\AcceptTicketAction;
use App\Modules\Conversations\Actions\CloseTicketAction;
use App\Modules\Conversations\Actions\TransferTicketAction;
use App\Modules\Conversations\Http\Resources\ConversationResource;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketActionController
{
    public function accept(
        Request $request,
        Conversation $conversation,
        AcceptTicketAction $acceptTicketAction,
    ): JsonResponse {
        $ticket = $acceptTicketAction->execute($request->user(), $conversation);

        return response()->json([
            'data' => (new ConversationResource($ticket))->resolve(),
            'message' => 'Ticket aceptado exitosamente.',
        ]);
    }

    public function transfer(
        Request $request,
        Conversation $conversation,
        TransferTicketAction $transferTicketAction,
    ): JsonResponse {
        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'string', 'exists:users,id'],
            'queue_id' => ['nullable', 'string', 'exists:queues,id'],
            'transfer_note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = $transferTicketAction->execute($request->user(), $conversation, $validated);

        return response()->json([
            'data' => (new ConversationResource($ticket))->resolve(),
            'message' => 'Ticket transferido exitosamente.',
        ]);
    }

    public function close(
        Request $request,
        Conversation $conversation,
        CloseTicketAction $closeTicketAction,
    ): JsonResponse {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket = $closeTicketAction->execute($request->user(), $conversation, $validated);

        return response()->json([
            'data' => (new ConversationResource($ticket))->resolve(),
            'message' => 'Ticket finalizado correctamente.',
        ]);
    }
}
