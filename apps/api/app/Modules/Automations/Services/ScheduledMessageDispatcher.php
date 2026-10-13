<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduledMessageDispatcher
{
    /**
     * Dispatch a single scheduled message through WhatsApp channel.
     */
    public function dispatch(ScheduledMessage $scheduled): bool
    {
        if ($scheduled->status === 'sent') {
            return true;
        }

        try {
            $contact = $scheduled->contact;

            if (! $contact) {
                $contact = Contact::firstOrCreate(
                    [
                        'organization_id' => $scheduled->organization_id,
                        'phone' => $scheduled->recipient_phone,
                    ],
                    [
                        'first_name' => 'Contacto',
                        'status' => 'active',
                    ]
                );

                $scheduled->update(['contact_id' => $contact->id]);
            }

            // Encontrar o crear la conversación para este contacto
            $conversation = Conversation::firstOrCreate(
                [
                    'organization_id' => $scheduled->organization_id,
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp',
                ],
                [
                    'status' => 'open',
                    'last_message_at' => now(),
                ]
            );

            $mediaType = null;
            if ($scheduled->media_url) {
                $path = parse_url($scheduled->media_url, PHP_URL_PATH);
                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                $mediaType = match ($ext) {
                    'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
                    'mp4', 'mov', 'avi', 'mkv', 'webm' => 'video',
                    'mp3', 'ogg', 'wav', 'm4a', 'aac' => 'audio',
                    'pdf', 'doc', 'docx', 'xls', 'xlsx' => 'document',
                    default => 'image',
                };
            }

            // Crear el mensaje saliente en la bandeja
            $message = Message::create([
                'organization_id' => $scheduled->organization_id,
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'direction' => 'outbound',
                'message_type' => $mediaType ?: 'text',
                'media_type' => $mediaType,
                'message_status' => 'pending',
                'body' => $scheduled->body ?: ($scheduled->media_url ? '[Archivo adjunto]' : ''),
                'media_url' => $scheduled->media_url,
                'sent_at' => null,
            ]);

            // Mapeo a la cuenta de WhatsApp seleccionada
            if ($scheduled->whatsapp_account_id) {
                WhatsAppMessageMapping::create([
                    'organization_id' => $scheduled->organization_id,
                    'conversation_id' => $conversation->id,
                    'whatsapp_account_id' => $scheduled->whatsapp_account_id,
                    'message_id' => $message->id,
                    'direction' => 'outbound',
                    'status' => 'pending',
                    'to_phone_number_id' => $scheduled->whatsappAccount?->phone_number_id,
                ]);
            }

            // Despachar el Job de WhatsApp de forma síncrona inmediata con fallback a cola
            try {
                SendWhatsAppMessageJob::dispatchSync($message->id);
            } catch (Throwable $jobEx) {
                Log::warning("Sync dispatch failed for message {$message->id}, fallback to queue: " . $jobEx->getMessage());
                SendWhatsAppMessageJob::dispatch($message->id);
            }

            // Actualizar conversación
            $conversation->update([
                'last_message_at' => now(),
                'status' => 'open',
            ]);

            // Marcar mensaje programado como enviado
            $scheduled->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            Log::info("ScheduledMessage {$scheduled->id} dispatched successfully to {$scheduled->recipient_phone}");

            return true;
        } catch (Throwable $e) {
            Log::error("Failed to dispatch ScheduledMessage {$scheduled->id}: {$e->getMessage()}");

            $scheduled->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
