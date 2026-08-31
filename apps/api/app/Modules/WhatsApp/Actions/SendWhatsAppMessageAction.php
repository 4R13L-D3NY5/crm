<?php

namespace App\Modules\WhatsApp\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Shared\Support\DispatchDomainJob;

class SendWhatsAppMessageAction
{
    public function execute(User $user, Conversation $conversation, array $payload): Message
    {
        abort_unless($conversation->channel === 'whatsapp', 422, 'La conversacion no pertenece al canal WhatsApp.');
        abort_unless(filled($conversation->contact?->phone), 422, 'La conversacion no tiene un telefono valido.');

        $message = Message::query()->create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'pending',
            'body' => $payload['body'],
            'sent_at' => null,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'status' => 'open',
        ])->save();

        DispatchDomainJob::dispatch(new SendWhatsAppMessageJob($message->getKey()));

        return $message->load('user');
    }

    public function retry(Message $message): Message
    {
        abort_unless($message->direction === 'outbound', 422, 'Solo se pueden reenviar mensajes salientes.');

        $message->forceFill([
            'message_status' => 'pending',
            'error_message' => null,
        ])->save();

        DispatchDomainJob::dispatch(new SendWhatsAppMessageJob($message->getKey()));

        return $message->load('user');
    }
}
