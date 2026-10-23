<?php

namespace App\Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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

        [$pageToken, $pageId] = $this->resolvePageTokenAndId($account, $organization->id);
        $igAccountId = $account->phone_number_id; // Instagram Business Account ID

        $client = new Client(['timeout' => 8, 'http_errors' => false]);
        $conversationsData = [];

        try {
            // Meta Graph API gestiona las conversaciones de Instagram a través del nodo de la Página con ?platform=instagram
            // Se usa limit defensivo para prevenir timeouts internos (HTTP 500 / code 1) de Meta en cuentas con alto volumen
            $url = "https://graph.facebook.com/v22.0/{$pageId}/conversations?platform=instagram&limit=10&fields=id,updated_time,participants{id,username}&access_token={$pageToken}";
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode === 200 && !empty($body['data'])) {
                $conversationsData = $body['data'];
            } else {
                Log::info('Instagram sync: Meta respondió sin conversaciones históricas o pendiente de permisos', [
                    'status' => $statusCode,
                    'error' => $body['error'] ?? null,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Instagram sync caught exception: ' . $e->getMessage());
        }

        // Marcar la cuenta como conectada y activa
        $account->update([
            'status' => 'CONNECTED',
            'is_active' => true,
            'last_connected_at' => now(),
        ]);

        $createdCount = 0;

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
                $externalMsgId = (string) data_get($msgData, 'id');

                $exists = false;
                if ($externalMsgId && WhatsAppMessageMapping::where('provider_message_id', $externalMsgId)->exists()) {
                    $exists = true;
                } else {
                    $exists = Message::where('conversation_id', $conversation->id)
                        ->where('body', $text)
                        ->where(function ($query) use ($sentAt, $isOutbound) {
                            $query->where('sent_at', $sentAt)
                                ->orWhereBetween('sent_at', [
                                    $sentAt->copy()->subMinutes(5),
                                    $sentAt->copy()->addMinutes(5),
                                ]);
                            if ($isOutbound) {
                                $query->orWhereBetween('created_at', [
                                    $sentAt->copy()->subMinutes(5),
                                    $sentAt->copy()->addMinutes(5),
                                ]);
                            }
                        })
                        ->exists();
                }

                if (!$exists) {
                    $msg = Message::create([
                        'organization_id' => $organization->id,
                        'conversation_id' => $conversation->id,
                        'direction' => $isOutbound ? 'outbound' : 'inbound',
                        'body' => $text,
                        'sent_at' => $sentAt,
                        'delivery_status' => $isOutbound ? 'delivered' : 'read',
                    ]);

                    if ($externalMsgId) {
                        WhatsAppMessageMapping::query()->updateOrCreate(
                            ['provider_message_id' => $externalMsgId],
                            [
                                'organization_id' => $organization->id,
                                'whatsapp_account_id' => $account->id,
                                'contact_id' => $contact->id,
                                'conversation_id' => $conversation->id,
                                'message_id' => $msg->id,
                                'direction' => $isOutbound ? 'outbound' : 'inbound',
                                'status' => $isOutbound ? 'delivered' : 'read',
                            ]
                        );
                    }

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
            'message' => $createdCount > 0
                ? "Sincronización completada. Se importaron {$createdCount} mensajes de Instagram Direct."
                : "Canal de Instagram conectado y sincronizado exitosamente. Los mensajes nuevos y DMs se procesan en tiempo real vía Webhook.",
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

        // Fallback: si no hay cuenta de Instagram separada, utilizar la cuenta de Facebook con token activo
        // (ya que el Page Access Token gestiona tanto la Fanpage como su cuenta comercial de Instagram vinculada)
        if (!$account || !$account->access_token) {
            $account = WhatsAppAccount::where('organization_id', $organization->id)
                ->where('session_type', 'facebook')
                ->whereNotNull('access_token')
                ->first();
        }

        if (!$account || !$account->access_token) {
            return response()->json([
                'message' => 'No hay una cuenta de Instagram activa ni cuenta de Facebook vinculada configurada.',
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

        [$pageToken, $pageId] = $this->resolvePageTokenAndId($account, $organization->id);

        $client = new Client(['timeout' => 15]);

        // Detectar si este ticket proviene de un comentario en un post / reel de Instagram
        $commentId = data_get($conversation->origin_referral, 'comment_id')
            ?: data_get($conversation->metadata, 'origin_referral.comment_id');

        $result = null;
        $sendError = null;
        $commentReplied = false;

        // Estrategia 1: Si hay comment_id, responder públicamente en el comentario del post de Instagram
        if ($commentId) {
            try {
                $commentResp = $client->post("https://graph.facebook.com/v21.0/{$commentId}/replies", [
                    'query' => [
                        'message' => $validated['body'],
                        'access_token' => $pageToken,
                    ],
                ]);
                $commentData = json_decode($commentResp->getBody()->getContents(), true);
                if (!empty($commentData['id'])) {
                    $result = $commentData;
                    $commentReplied = true;
                }
            } catch (Throwable $e) {
                $sendError = $e->getMessage();
            }

            // Si falló responder en el post, intentar como respuesta privada al comentario
            if (!$commentReplied) {
                try {
                    $response = $client->post("https://graph.facebook.com/v21.0/me/messages?access_token={$pageToken}", [
                        'json' => [
                            'recipient' => ['comment_id' => $commentId],
                            'message' => ['text' => $validated['body']],
                        ],
                    ]);
                    $result = json_decode($response->getBody()->getContents(), true);
                } catch (Throwable $e) {
                    $sendError = $e->getMessage();
                }
            }
        }

        // Estrategia 2: Mensaje directo estándar (DM) por ID de usuario
        if (!$result && $recipientId) {
            try {
                $response = $client->post("https://graph.facebook.com/v21.0/me/messages?access_token={$pageToken}", [
                    'json' => [
                        'recipient' => ['id' => $recipientId],
                        'message' => ['text' => $validated['body']],
                    ],
                ]);
                $result = json_decode($response->getBody()->getContents(), true);
            } catch (Throwable $e) {
                $sendError = $e->getMessage();
            }
        }

        if (!$result) {
            $userFriendly = 'No se pudo enviar el mensaje a través de Instagram.';
            if (str_contains((string) $sendError, '2018278') || str_contains((string) $sendError, 'período permitido') || str_contains((string) $sendError, 'outside the allowed window') || str_contains((string) $sendError, '24 hour')) {
                $userFriendly = 'Meta no permite enviar mensajes por Instagram Direct porque han pasado más de 24 horas desde el último mensaje del cliente. Para chatear por privado, el cliente debe escribir primero a tu Instagram (política oficial de 24 horas de Meta).';
            } elseif (str_contains((string) $sendError, '10900') || str_contains((string) $sendError, 'one private reply')) {
                $userFriendly = 'Ya se utilizó la respuesta privada inicial permitida por Instagram para este comentario. Para continuar chateando por privado, el cliente debe responder en Instagram Direct.';
            } elseif (str_contains((string) $sendError, 'instagram_manage_comments') || str_contains((string) $sendError, 'Permissions error') || str_contains((string) $sendError, '"code":200')) {
                $userFriendly = 'Para responder comentarios de Instagram, tu Token en Meta necesita el permiso "instagram_manage_comments".';
            } elseif (str_contains((string) $sendError, '230') || str_contains((string) $sendError, 'not allowed to receive messages')) {
                $userFriendly = 'El usuario no permite mensajes directos o debes activar "Permitir acceso a los mensajes" en la app móvil de Instagram (Configuración > Mensajes y respuestas a historias > Controles de mensajes).';
            } else {
                $userFriendly = 'Error de Meta al enviar a Instagram: ' . $sendError;
            }

            return response()->json([
                'message' => $userFriendly,
            ], 422);
        }

        // Si Meta devolvió el ID en recipient_id, persistirlo en el contacto para futuros mensajes
        $returnedIgId = data_get($result, 'recipient_id');
        if ($returnedIgId && $contact) {
            $cf = (array) ($contact->custom_fields ?? []);
            if (empty($cf['instagram_id']) || $cf['instagram_id'] !== $returnedIgId) {
                $cf['instagram_id'] = $returnedIgId;
                $contact->update(['custom_fields' => $cf]);
            }
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

        $externalMsgId = data_get($result, 'message_id');
        if ($externalMsgId) {
            WhatsAppMessageMapping::query()->updateOrCreate(
                ['provider_message_id' => $externalMsgId],
                [
                    'organization_id' => $organization->id,
                    'whatsapp_account_id' => $account->id,
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'direction' => 'outbound',
                    'status' => 'delivered',
                ]
            );
        }

        $convUpdate = [
            'last_message_at' => now(),
            'status' => 'open',
            'unread_count' => 0,
        ];

        if (empty($conversation->assigned_to_user_id)) {
            $convUpdate['assigned_to_user_id'] = $request->user()->id;

            \App\Modules\Conversations\Models\ConversationAssignment::query()->updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'assigned_to_user_id' => $request->user()->id,
                    'assigned_by_user_id' => $request->user()->id,
                ]
            );
        }

        $conversation->update($convUpdate);

        event(new TicketMessageCreatedEvent($message));

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Mensaje directo enviado a Instagram exitosamente.',
            'meta' => $result,
        ], 201);
    }

    /**
     * Resuelve el Page Access Token y el ID de página de Facebook vinculado para llamadas a Graph API.
     */
    private function resolvePageTokenAndId(WhatsAppAccount $account, string $orgId): array
    {
        $token = $account->access_token;
        $fbAccount = WhatsAppAccount::where('organization_id', $orgId)
            ->where('session_type', 'facebook')
            ->first();

        $pageId = $account->business_account_id
            ?? $fbAccount?->phone_number_id
            ?? $fbAccount?->business_account_id
            ?? '187805724574229';

        $client = new Client(['timeout' => 8, 'http_errors' => false]);
        try {
            $res = $client->get("https://graph.facebook.com/v22.0/{$pageId}?fields=access_token&access_token={$token}");
            $data = json_decode($res->getBody()->getContents(), true);
            if (!empty($data['access_token'])) {
                $resolvedPageToken = $data['access_token'];
                if ($account->access_token !== $resolvedPageToken && str_starts_with($resolvedPageToken, 'EAA')) {
                    $account->update(['access_token' => $resolvedPageToken]);
                }
                return [$resolvedPageToken, $pageId];
            }

            // Fallback via /me/accounts
            $resAccounts = $client->get("https://graph.facebook.com/v22.0/me/accounts?access_token={$token}");
            $dataAccounts = json_decode($resAccounts->getBody()->getContents(), true);
            foreach ($dataAccounts['data'] ?? [] as $p) {
                if ((string) ($p['id'] ?? '') === (string) $pageId && !empty($p['access_token'])) {
                    $resolvedPageToken = $p['access_token'];
                    $account->update(['access_token' => $resolvedPageToken]);
                    return [$resolvedPageToken, $pageId];
                }
            }
        } catch (Throwable) {
        }

        return [$token, $pageId];
    }
}
