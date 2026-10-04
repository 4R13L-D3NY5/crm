<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Actions\SendWhatsAppMessageAction;
use App\Modules\WhatsApp\Http\Requests\SendWhatsAppMessageRequest;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;

class ConversationWhatsAppMessageController
{
    public function store(
        SendWhatsAppMessageRequest $request,
        Conversation $conversation,
        SendWhatsAppMessageAction $sendWhatsAppMessageAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        abort_unless($request->user()?->can('update', $conversation), 403);
        abort_unless($request->user()?->hasPermission('whatsapp.manage'), 403);

        if ($conversation->channel === 'facebook') {
            return app(\App\Modules\Social\Http\Controllers\SocialCommentController::class)->sendMessage($request, $conversation);
        }

        if ($conversation->channel === 'instagram') {
            return app(\App\Modules\Social\Http\Controllers\InstagramController::class)->sendMessage($request, $conversation);
        }

        if ($conversation->channel === 'tiktok') {
            return app(\App\Modules\Social\Http\Controllers\SocialCommentController::class)->sendTikTokMessage($request, $conversation);
        }

        $message = $sendWhatsAppMessageAction->execute(
            $request->user(),
            $conversation->load('contact'),
            $request->validated(),
        );
        $writeAuditLogAction->execute(
            event: 'whatsapp.outbound_queued',
            user: $request->user(),
            request: $request,
            auditable: $message,
            metadata: [
                'conversation_id' => $conversation->getKey(),
                'message_status' => $message->message_status,
            ],
        );

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Mensaje enviado a la cola correctamente.',
        ], 201);
    }

    public function retry(
        Conversation $conversation,
        Message $message,
        SendWhatsAppMessageAction $sendWhatsAppMessageAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        abort_unless($conversation->getKey() === $message->conversation_id, 404);
        abort_unless(request()->user()?->can('update', $conversation), 403);
        abort_unless(request()->user()?->hasPermission('whatsapp.manage'), 403);

        $message = $sendWhatsAppMessageAction->retry($message);
        $writeAuditLogAction->execute(
            event: 'whatsapp.outbound_retry_queued',
            user: request()->user(),
            request: request(),
            auditable: $message,
            metadata: [
                'conversation_id' => $conversation->getKey(),
                'message_status' => $message->message_status,
            ],
        );

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Mensaje reenviado a la cola correctamente.',
        ]);
    }
}
