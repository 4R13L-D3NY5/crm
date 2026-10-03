<?php

namespace App\Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class InstagramController extends Controller
{
    /**
     * Sincroniza conversaciones y mensajes directos recientes desde la Graph API de Instagram.
     * GET https://graph.facebook.com/v21.0/{ig_business_id}/conversations?platform=instagram
     */
    public function sync(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $query = WhatsAppAccount::where('organization_id', $organization->id)
            ->where('session_type', 'instagram');

        if ($request->filled('account_id')) {
            $query->where('id', $request->input('account_id'));
        }

        $account = $query->first();

        if (!$account || !$account->access_token) {
            return response()->json([
                'message' => 'No hay una cuenta de Instagram conectada con token de acceso activo.',
            ], 404);
        }

        $token = $account->access_token;
        $igAccountId = $account->phone_number_id; // Instagram Business Account ID

        $client = new Client(['timeout' => 15]);

        try {
            $url = "https://graph.facebook.com/v21.0/{$igAccountId}/conversations?platform=instagram&fields=id,updated_time,participants{id,username},messages{id,message,from,created_time}&access_token={$token}";
            $response = $client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al conectar con la API de Instagram Graph: ' . $e->getMessage(),
            ], 502);
        }

        $createdCount = 0;
        $conversationsData = data_get($data, 'data', []);

        foreach ($conversationsData as $conversationData) {
            // Identificar al cliente en la conversación (el participante que NO es la cuenta de Instagram comercial)
            $participants = data_get($conversationData, 'participants.data', []);
            $customerParticipant = collect($participants)->first(function ($p) use ($igAccountId) {
                return (string) data_get($p, 'id') !== (string) $igAccountId;
            });

            $customerIgId = (string) data_get($customerParticipant, 'id');
            $customerUsername = (string) data_get($customerParticipant, 'username', 'Usuario Instagram');

            $messagesList = data_get($conversationData, 'messages.data', []);

            // Si no obtuvimos participantes explícitos, deducir del primer mensaje que no sea propio
            if (!$customerIgId && !empty($messagesList)) {
                foreach ($messagesList as $m) {
                    $mSenderId = (string) data_get($m, 'from.id');
                    if ($mSenderId !== (string) $igAccountId) {
                        $customerIgId = $mSenderId;
                        $customerUsername = (string) data_get($m, 'from.username', data_get($m, 'from.name', 'Usuario Instagram'));
                        break;
                    }
                }
            }

            if (!$customerIgId) {
                continue;
            }

            // Crear o ubicar el Contacto
            $contact = Contact::where('organization_id', $organization->id)
                ->where(function ($q) use ($customerIgId, $customerUsername) {
                    $q->whereJsonContains('custom_fields->instagram_id', $customerIgId)
                      ->orWhere('first_name', $customerUsername);
                })
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'organization_id' => $organization->id,
                    'first_name' => $customerUsername,
                    'status' => 'active',
                    'notes' => "Lead de Instagram Direct @{$customerUsername} (ID #{$customerIgId})",
                    'custom_fields' => [
                        'instagram_id' => $customerIgId,
                        'instagram_username' => $customerUsername,
                    ],
                ]);
            } else {
                $customFields = $contact->custom_fields ?? [];
                if (empty($customFields['instagram_id'])) {
                    $customFields['instagram_id'] = $customerIgId;
                    $customFields['instagram_username'] = $customerUsername;
                    $contact->update(['custom_fields' => $customFields]);
                }
            }

            // Crear o ubicar la Conversación
            $conversation = Conversation::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'channel' => 'instagram',
                ],
                [
                    'whatsapp_account_id' => $account->id,
                    'status' => 'pending',
                    'unread_count' => 0,
                    'last_message_at' => now(),
                ]
            );

            // Importar los mensajes del hilo
            foreach ($messagesList as $msgData) {
                $msgSenderId = (string) data_get($msgData, 'from.id');
                $text = (string) data_get($msgData, 'message');
                $sentAt = Carbon::parse(data_get($msgData, 'created_time', now()));

                if (trim($text) === '') {
                    continue;
                }

                $isOutbound = ($msgSenderId === (string) $igAccountId);

                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('body', $text)
                    ->where('sent_at', $sentAt)
                    ->exists();

                if (!$exists) {
                    $msg = Message::create([
                        'organization_id' => $organization->id,
                        'conversation_id' => $conversation->id,
                        'direction' => $isOutbound ? 'outbound' : 'inbound',
                        'body' => $text,
                        'sent_at' => $sentAt,
                        'delivery_status' => $isOutbound ? 'delivered' : 'read',
                    ]);

                    if (!$isOutbound) {
                        $conversation->increment('unread_count');
                    }
                    $conversation->update(['last_message_at' => $sentAt]);
                    event(new TicketMessageCreatedEvent($msg));
                    $createdCount++;
                }
            }
        }

        return response()->json([
            'message' => "Sincronización completada. Se importaron {$createdCount} mensajes de Instagram Direct.",
            'imported' => $createdCount,
        ]);
    }

    /**
     * Envía un mensaje directo saliente (DM) a un usuario de Instagram mediante Graph API.
     * POST https://graph.facebook.com/v21.0/{ig_business_id}/messages
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $organization = $request->user()->currentOrganization;

        if ($conversation->organization_id !== $organization->id) {
            abort(403);
        }

        if ($conversation->channel !== 'instagram') {
            return response()->json([
                'message' => 'Esta conversación no pertenece al canal Instagram.',
            ], 422);
        }

        $account = WhatsAppAccount::where('organization_id', $organization->id)
            ->where('session_type', 'instagram')
            ->first();

        if (!$account || !$account->access_token) {
            return response()->json([
                'message' => 'No hay una cuenta de Instagram activa configurada.',
            ], 422);
        }

        $contact = $conversation->contact;
        $recipientId = data_get($contact?->custom_fields, 'instagram_id');

        // Extraer recipient ID de las notas si no está en custom_fields
        if (!$recipientId && $contact?->notes) {
            if (preg_match('/ID #(\d+)/', $contact->notes, $matches)) {
                $recipientId = $matches[1];
            }
        }

        if (!$recipientId) {
            return response()->json([
                'message' => 'No se encontró el ID de Instagram del contacto para enviar el DM.',
            ], 422);
        }

        $token = $account->access_token;
        $igAccountId = $account->phone_number_id;

        $client = new Client(['timeout' => 15]);

        try {
            $response = $client->post("https://graph.facebook.com/v21.0/{$igAccountId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $validated['body']],
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error de Meta al enviar DM: ' . $e->getMessage(),
            ], 422);
        }

        // Registrar mensaje saliente en la base de datos
        $message = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'outbound',
            'body' => $validated['body'],
            'sent_at' => now(),
            'delivery_status' => 'delivered',
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'status' => 'open',
        ]);

        event(new TicketMessageCreatedEvent($message));

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Mensaje directo enviado a Instagram exitosamente.',
            'meta' => $result,
        ], 201);
    }
}
