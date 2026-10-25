<?php

namespace App\Modules\WhatsApp\Actions;

use App\Models\User;
use App\Modules\Conversations\Events\TicketMessageUpdatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Services\BaileysWhatsAppClient;

class EditWhatsAppMessageAction
{
    public function execute(Message $message, string $newBody, User $user): Message
    {
        $conversation = $message->conversation;
        abort_unless($conversation, 404, 'Conversación no encontrada.');

        // 1. Validar dirección y tipo de mensaje
        abort_unless($message->direction === 'outbound', 422, 'Solo se pueden editar mensajes salientes.');
        abort_unless($message->message_type === 'text', 422, 'Solo se pueden editar mensajes de texto.');

        // Normalizar saltos de línea Windows CRLF a \n puro para WhatsApp
        $newBody = preg_replace("/\r\n|\r/", "\n", $newBody);

        // 2. Validar ventana de tiempo de 15 minutos
        $diffMinutes = $message->created_at->diffInMinutes(now());
        abort_if($diffMinutes > 15, 422, 'El límite de 15 minutos para editar este mensaje ha expirado.');

        // 3. Si el canal es WhatsApp Baileys, propagar la edición al protocolo de WhatsApp
        if ($conversation->channel === 'whatsapp' && $conversation->whatsapp_account_id) {
            $account = $conversation->whatsappAccount ?: WhatsAppAccount::find($conversation->whatsapp_account_id);

            if ($account) {
                $isMetaCloud = $account->session_type !== 'qr_baileys' && ($account->session_type === 'meta_cloud' || (filled($account->access_token) && filled($account->phone_number_id)));

                if ($isMetaCloud) {
                    abort(422, 'Meta Cloud API no permite la edición de mensajes salientes.');
                }

                $mapping = WhatsAppMessageMapping::where('message_id', $message->id)->first();
                $contactPhone = $mapping?->from_phone ?: $conversation->contact?->phone;

                if ($mapping && $contactPhone) {
                    $baileysClient = app(BaileysWhatsAppClient::class);
                    $baileysClient->editTextMessage($account, $contactPhone, $mapping->provider_message_id, $newBody);
                }
            }
        }

        // 4. Actualizar registro en base de datos
        $message->original_body = $message->original_body ?: $message->body;
        $message->body = $newBody;
        $message->is_edited = true;
        $message->edited_at = now();
        $message->save();

        // 5. Emitir evento Socket en tiempo real
        event(new TicketMessageUpdatedEvent($message));

        return $message;
    }
}
