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

        $mediaUrl = $payload['media_url'] ?? null;
        $mediaType = $payload['media_type'] ?? null;

        if (isset($payload['file']) && $payload['file'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $payload['file'];
            $mime = (string) $file->getMimeType();
            if (! $mediaType) {
                $mediaType = match (true) {
                    str_starts_with($mime, 'image/') => 'image',
                    str_starts_with($mime, 'audio/') || str_contains($mime, 'ogg') || str_contains($mime, 'webm') => 'audio',
                    default => 'document',
                };
            }

            $storedPath = $file->store('whatsapp_media', 'public');
            $mediaUrl = '/storage/' . $storedPath;
        }

        $body = $payload['body'] ?? '';
        if (blank($body) && filled($mediaType)) {
            $body = match ($mediaType) {
                'image' => '[Imagen]',
                'audio' => '[Nota de voz / Audio]',
                'document' => '[Documento]',
                'sticker' => '[Sticker]',
                default => '[Multimedia]',
            };
        }

        $message = Message::query()->create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'direction' => 'outbound',
            'message_type' => $mediaType ?: 'text',
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'message_status' => 'pending',
            'body' => $body,
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
