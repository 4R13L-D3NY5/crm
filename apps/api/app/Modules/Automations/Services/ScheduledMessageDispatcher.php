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

            // Crear el mensaje saliente en la bandeja
            $message = Message::create([
                'organization_id' => $scheduled->organization_id,
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'direction' => 'outbound',
                'message_type' => 'text',
                'message_status' => 'pending',
                'body' => $scheduled->body,
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

            // Despachar el Job de WhatsApp (evalúa Baileys QR vs Meta Cloud)
            SendWhatsAppMessageJob::dispatch($message->id);

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
