<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Actions\EditWhatsAppMessageAction;
use App\Modules\WhatsApp\Actions\SendWhatsAppMessageAction;
use App\Modules\WhatsApp\Http\Requests\EditWhatsAppMessageRequest;
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

        // Si el canal de la conversación tiene operadores asignados específicamente, verificar que el usuario tenga can_reply
        if ($conversation->whatsapp_account_id && !$request->user()->isAdmin()) {
            $account = $conversation->whatsappAccount;
            if ($account && $account->authorizedUsers()->exists()) {
                $canReply = $account->authorizedUsers()
                    ->where('users.id', $request->user()->id)
                    ->wherePivot('can_reply', true)
                    ->exists();

                abort_unless($canReply, 403, 'No tienes permiso para responder mensajes en esta línea o canal.');
            }
        }

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

        // Si el canal de la conversación tiene operadores asignados específicamente, verificar que el usuario tenga can_reply
        if ($conversation->whatsapp_account_id && !request()->user()->isAdmin()) {
            $account = $conversation->whatsappAccount;
            if ($account && $account->authorizedUsers()->exists()) {
                $canReply = $account->authorizedUsers()
                    ->where('users.id', request()->user()->id)
                    ->wherePivot('can_reply', true)
                    ->exists();

                abort_unless($canReply, 403, 'No tienes permiso para responder mensajes en esta línea o canal.');
            }
        }

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

    public function update(
        EditWhatsAppMessageRequest $request,
        Conversation $conversation,
        Message $message,
        EditWhatsAppMessageAction $editWhatsAppMessageAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        abort_unless($conversation->getKey() === $message->conversation_id, 404);
        abort_unless($request->user()?->can('update', $conversation), 403);

        // Si el usuario no es admin, solo puede editar sus propios mensajes
        if (! $request->user()->isAdmin() && $message->user_id !== $request->user()->id) {
            abort(403, 'Solo el autor del mensaje o un administrador puede editarlo.');
        }

        $updatedMessage = $editWhatsAppMessageAction->execute(
            $message,
            $request->validated('body'),
            $request->user()
        );

        $writeAuditLogAction->execute(
            event: 'whatsapp.message_edited',
            user: $request->user(),
            request: $request,
            auditable: $updatedMessage,
            metadata: [
                'conversation_id' => $conversation->getKey(),
                'message_id' => $message->getKey(),
            ],
        );

        return response()->json([
            'data' => (new MessageResource($updatedMessage))->resolve(),
            'message' => 'Mensaje editado y actualizado en WhatsApp correctamente.',
        ]);
    }
}
