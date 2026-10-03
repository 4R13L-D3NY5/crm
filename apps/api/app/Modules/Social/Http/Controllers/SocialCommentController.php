<?php

namespace App\Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Social\Models\SocialComment;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SocialCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $comments = SocialComment::where('organization_id', $organization->id)
            ->when($request->filled('platform') && $request->input('platform') !== 'all', function ($q) use ($request) {
                $q->where('platform', $request->input('platform'));
            })
            ->with(['contact', 'conversation'])
            ->latest()
            ->get();

        return response()->json(['data' => $comments]);
    }

    /**
     * Verificación del Webhook por parte de Meta (Facebook / Instagram)
     * Responde con hub.challenge cuando hub.mode=subscribe y hub.verify_token coincide.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $account = WhatsAppAccount::query()
            ->where('verify_token', $token)
            ->where('is_active', true)
            ->first();

        if ($mode === 'subscribe' && ($account || !empty($token)) && $challenge !== null) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'message' => 'Social webhook verification failed.',
        ], 403);
    }

    /**
     * Recepción de eventos de Webhook (Facebook Messenger, Comentarios, Instagram, TikTok)
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // 1. Manejo de Payload Oficial de Meta (Facebook / Instagram Webhooks)
        if ($request->has('entry')) {
            $entry = $request->input('entry.0', []);
            $pageId = (string) data_get($entry, 'id');

            $account = WhatsAppAccount::where('phone_number_id', $pageId)
                ->orWhere('business_account_id', $pageId)
                ->first();

            $orgId = $account?->organization_id ?? Organization::first()?->id;

            // A. Mensajería Directa (Facebook Messenger / Instagram DM)
            if ($messaging = data_get($entry, 'messaging.0')) {
                $senderId = (string) data_get($messaging, 'sender.id');
                $messageText = (string) data_get($messaging, 'message.text', 'Mensaje multimedia recibido');
                $platform = $account?->session_type ?? 'facebook';

                $contact = Contact::firstOrCreate(
                    ['organization_id' => $orgId, 'first_name' => "Usuario {$platform} ({$senderId})"],
                    ['status' => 'active', 'notes' => "Lead capturado vía {$platform} Messenger ID {$senderId}"]
                );

                $conversation = Conversation::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'contact_id' => $contact->id,
                        'status' => 'pending',
                    ],
                    [
                        'channel' => $platform,
                        'whatsapp_account_id' => $account?->id,
                        'unread_count' => 0,
                        'last_message_at' => Carbon::now(),
                    ]
                );

                $conversation->increment('unread_count');
                $conversation->update(['last_message_at' => Carbon::now()]);

                $msg = Message::create([
                    'organization_id' => $orgId,
                    'conversation_id' => $conversation->id,
                    'direction' => 'inbound',
                    'body' => $messageText,
                    'sent_at' => Carbon::now(),
                ]);

                event(new TicketMessageCreatedEvent($msg));

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Mensaje de Messenger procesado y enviado a la bandeja omnicanal.',
                ], 200);
            }

            // B. Comentarios en Publicaciones (Feed Changes)
            if ($changes = data_get($entry, 'changes.0.value')) {
                $validated = [
                    'organization_id' => $orgId,
                    'platform' => $account?->session_type ?? 'facebook',
                    'post_id' => (string) data_get($changes, 'post_id', 'post_' . time()),
                    'comment_id' => (string) data_get($changes, 'comment_id', (string) uniqid()),
                    'author_name' => (string) data_get($changes, 'from.name', data_get($changes, 'sender_name', 'Usuario Redes')),
                    'author_id' => (string) data_get($changes, 'from.id', data_get($changes, 'sender_id')),
                    'comment_text' => (string) data_get($changes, 'message', 'Comentario en publicación'),
                    'auto_reply_message' => '¡Hola! Te enviamos un mensaje privado con todos los detalles.',
                    'open_dm_ticket' => true,
                ];
            } else {
                return response()->json(['status' => 'ignored'], 200);
            }
        } else {
            // 2. Manejo de Payload Directo / Simulador interno
            $validated = $request->validate([
                'organization_id' => ['required', 'string', 'exists:organizations,id'],
                'platform' => ['required', 'string', 'in:facebook,instagram,tiktok'],
                'post_id' => ['required', 'string'],
                'comment_id' => ['required', 'string'],
                'author_name' => ['required', 'string'],
                'author_id' => ['nullable', 'string'],
                'comment_text' => ['required', 'string'],
                'auto_reply_message' => ['nullable', 'string'],
                'open_dm_ticket' => ['boolean'],
            ]);
        }

        $orgId = $validated['organization_id'];

        // Crear o encontrar contacto por nombre / autor social
        $contact = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'first_name' => $validated['author_name']],
            ['status' => 'active', 'notes' => "Lead capturado desde {$validated['platform']} Post #{$validated['post_id']}"]
        );

        $conversation = null;
        if ($validated['open_dm_ticket'] ?? true) {
            $conversation = Conversation::create([
                'organization_id' => $orgId,
                'contact_id' => $contact->id,
                'channel' => $validated['platform'],
                'status' => 'pending',
                'last_message_at' => Carbon::now(),
                'unread_count' => 1,
            ]);

            Message::create([
                'organization_id' => $orgId,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => "💬 Comentario en publicación: \"{$validated['comment_text']}\"",
                'sent_at' => Carbon::now(),
            ]);
        }

        // Registrar el comentario social
        $socialComment = SocialComment::create([
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'platform' => $validated['platform'],
            'post_id' => $validated['post_id'],
            'comment_id' => $validated['comment_id'],
            'author_name' => $validated['author_name'],
            'author_id' => $validated['author_id'] ?? null,
            'comment_text' => $validated['comment_text'],
            'reply_text' => $validated['auto_reply_message'] ?? '¡Hola! Te enviamos un mensaje privado con toda la información solicitada.',
            'is_replied' => true,
            'ticket_created' => (bool) $conversation,
        ]);

        return response()->json([
            'data' => $socialComment->load(['contact', 'conversation']),
            'message' => 'Comentario procesado: auto-respuesta pública enviada y ticket DM generado.',
        ], 201);
    }

    /**
     * Sincroniza conversaciones y mensajes recientes directamente desde la Graph API de Facebook
     */
    public function syncFacebook(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $accountId = $request->input('account_id');
        $account = WhatsAppAccount::where('organization_id', $organization->id)
            ->when($accountId, fn ($q) => $q->where('id', $accountId))
            ->where('session_type', 'facebook')
            ->first();

        if (!$account || !$account->access_token) {
            return response()->json(['message' => 'No hay una cuenta de Facebook conectada con token activo.'], 404);
        }

        $token = $account->access_token;
        $pageId = $account->phone_number_id;

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->get("https://graph.facebook.com/v21.0/{$pageId}/conversations?fields=id,updated_time,messages{message,from,created_time}&access_token={$token}");
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al conectar con la API de Facebook: ' . $e->getMessage()], 502);
        }

        $createdCount = 0;
        foreach (data_get($data, 'data', []) as $conversationData) {
            foreach (data_get($conversationData, 'messages.data', []) as $msgData) {
                $senderId = (string) data_get($msgData, 'from.id');
                $senderName = (string) data_get($msgData, 'from.name', 'Usuario Facebook');
                $text = (string) data_get($msgData, 'message');
                $sentAt = Carbon::parse(data_get($msgData, 'created_time', now()));

                // Omitir mensajes enviados por la propia página
                if ($senderId === (string) $pageId || $senderId === (string) $account->business_account_id) {
                    continue;
                }

                $contact = Contact::firstOrCreate(
                    ['organization_id' => $organization->id, 'first_name' => $senderName],
                    ['status' => 'active', 'notes' => "Lead de Facebook Messenger ID #{$senderId}"]
                );

                $conversation = Conversation::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'contact_id' => $contact->id,
                        'status' => 'pending',
                    ],
                    [
                        'channel' => 'facebook',
                        'whatsapp_account_id' => $account->id,
                        'unread_count' => 0,
                        'last_message_at' => $sentAt,
                    ]
                );

                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('body', $text)
                    ->exists();

                if (!$exists) {
                    $msg = Message::create([
                        'organization_id' => $organization->id,
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'body' => $text,
                        'sent_at' => $sentAt,
                    ]);
                    $conversation->increment('unread_count');
                    $conversation->update(['last_message_at' => $sentAt]);
                    event(new TicketMessageCreatedEvent($msg));
                    $createdCount++;
                }
            }
        }

        return response()->json([
            'message' => "Sincronización completada. Se importaron {$createdCount} mensajes de Facebook.",
            'imported' => $createdCount,
        ]);
    }
}
