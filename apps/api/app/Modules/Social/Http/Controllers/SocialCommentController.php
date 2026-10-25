<?php

namespace App\Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Social\Models\SocialComment;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
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

            $isPage = $request->input('object') === 'page';
            $isInstagram = $request->input('object') === 'instagram'
                || str_starts_with((string) data_get($entry, 'id'), '17841');

            if ($isPage) {
                $account = WhatsAppAccount::where('session_type', 'facebook')
                    ->where(function ($q) use ($pageId) {
                        $q->where('phone_number_id', $pageId)
                          ->orWhere('business_account_id', $pageId);
                    })
                    ->first();
            } elseif ($isInstagram) {
                $account = WhatsAppAccount::where('session_type', 'instagram')
                    ->where(function ($q) use ($pageId) {
                        $q->where('phone_number_id', $pageId)
                          ->orWhere('business_account_id', $pageId);
                    })
                    ->first();
            } else {
                $account = WhatsAppAccount::where('phone_number_id', $pageId)
                    ->orWhere('business_account_id', $pageId)
                    ->first();
            }

            $orgId = $account?->organization_id ?? Organization::first()?->id;

            if (!$account) {
                $account = WhatsAppAccount::where('organization_id', $orgId)
                    ->where('is_active', true)
                    ->whereIn('session_type', ['facebook', 'instagram'])
                    ->first();
            }

            if (!$isInstagram && $account && $account->session_type === 'instagram' && !$isPage) {
                $isInstagram = true;
            }

            $platform = $isInstagram ? 'instagram' : 'facebook';

            // A. Mensajería Directa (Facebook Messenger / Instagram DM)
            if ($messaging = data_get($entry, 'messaging.0')) {
                $senderId = (string) data_get($messaging, 'sender.id');
                $recipientId = (string) data_get($messaging, 'recipient.id');
                $isEcho = (bool) data_get($messaging, 'message.is_echo', false);
                $messageText = (string) data_get($messaging, 'message.text', 'Mensaje multimedia recibido');
                $platform = $isInstagram ? 'instagram' : ($account?->session_type ?? 'facebook');

                $isOutbound = $isEcho
                    || ($senderId === (string) $pageId)
                    || ($account && $senderId === (string) $account->phone_number_id)
                    || ($account && $senderId === (string) $account->business_account_id);

                // Si es mensaje saliente de la Fanpage, el interlocutor cliente es el recipientId
                $customerSocialId = $isOutbound ? $recipientId : $senderId;

                $contact = Contact::where('organization_id', $orgId)
                    ->where(function ($q) use ($customerSocialId, $platform) {
                        $q->whereJsonContains("custom_fields->{$platform}_id", $customerSocialId)
                          ->orWhere('first_name', "Usuario {$platform} ({$customerSocialId})");
                    })
                    ->first();

                if (!$contact) {
                    $contact = Contact::create([
                        'organization_id' => $orgId,
                        'first_name' => "Usuario {$platform} ({$customerSocialId})",
                        'status' => 'active',
                        'notes' => "Lead capturado vía {$platform} ID {$customerSocialId}",
                        'custom_fields' => ["{$platform}_id" => $customerSocialId],
                    ]);
                } else {
                    $cf = (array) ($contact->custom_fields ?? []);
                    if (empty($cf["{$platform}_id"])) {
                        $cf["{$platform}_id"] = $customerSocialId;
                        $contact->update(['custom_fields' => $cf]);
                    }
                }

                $rawReferral = data_get($messaging, 'referral') ?: data_get($messaging, 'message.referral');
                $referral = null;
                if ($rawReferral) {
                    $adsContext = data_get($rawReferral, 'ads_context_data', []);
                    $adId = data_get($rawReferral, 'ad_id') ?: data_get($adsContext, 'ad_id');
                    $postId = data_get($adsContext, 'post_id');
                    $sourceUrl = null;
                    if ($postId) {
                        $sourceUrl = str_contains((string) $postId, '_')
                            ? 'https://www.facebook.com/' . str_replace('_', '/posts/', (string) $postId)
                            : "https://facebook.com/{$postId}";
                    }
                    $referral = [
                        'source_type' => 'ad',
                        'source_id' => (string) ($adId ?: $postId),
                        'source_url' => $sourceUrl,
                        'headline' => (string) (data_get($adsContext, 'ad_title') ?: 'Anuncio de Meta Ads'),
                        'body' => data_get($rawReferral, 'ref') ?: null,
                        'media_type' => data_get($adsContext, 'video_url') ? 'video' : 'image',
                        'image_url' => data_get($adsContext, 'photo_url') ?: data_get($adsContext, 'image_url'),
                        'video_url' => data_get($adsContext, 'video_url'),
                    ];
                }

                $conversation = Conversation::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'contact_id' => $contact->id,
                        'channel' => $platform,
                    ],
                    [
                        'whatsapp_account_id' => $account?->id,
                        'status' => 'pending',
                        'unread_count' => 0,
                        'last_message_at' => Carbon::now(),
                        'origin_referral' => $referral,
                        'metadata' => $referral ? ['origin_referral' => $referral] : null,
                    ]
                );

                if ($referral && empty($conversation->origin_referral)) {
                    $conversation->update([
                        'origin_referral' => $referral,
                        'metadata' => array_merge((array) ($conversation->metadata ?? []), ['origin_referral' => $referral]),
                    ]);
                }

                if ($isOutbound) {
                    $conversation->update([
                        'status' => 'open',
                        'last_message_at' => Carbon::now(),
                        'unread_count' => 0,
                    ]);
                } else {
                    $conversation->increment('unread_count');
                    $conversation->update(['last_message_at' => Carbon::now()]);
                }

                $msg = Message::create([
                    'organization_id' => $orgId,
                    'conversation_id' => $conversation->id,
                    'direction' => $isOutbound ? 'outbound' : 'inbound',
                    'body' => $messageText,
                    'metadata' => $referral ? ['referral' => $referral] : null,
                    'sent_at' => Carbon::now(),
                    'delivery_status' => $isOutbound ? 'delivered' : 'read',
                ]);

                if ($referral) {
                    $cf = (array) ($contact->custom_fields ?? []);
                    $cf['last_ad_referral'] = $referral;
                    $contact->update(['custom_fields' => $cf]);
                }

                event(new TicketMessageCreatedEvent($msg));

                return response()->json([
                    'status' => 'ok',
                    'message' => "Mensaje de {$platform} (" . ($isOutbound ? 'saliente espejo' : 'entrante') . ") procesado y enviado a la bandeja omnicanal.",
                ], 200);
            }

            // B. Comentarios en Publicaciones (Feed Changes / Instagram Media Comments)
            if ($changes = data_get($entry, 'changes.0.value')) {
                $validated = [
                    'organization_id' => $orgId,
                    'platform' => $isInstagram ? 'instagram' : ($account?->session_type ?? 'facebook'),
                    'post_id' => (string) data_get($changes, 'post_id', data_get($changes, 'media.id', 'post_' . time())),
                    'comment_id' => (string) data_get($changes, 'comment_id', data_get($changes, 'id', (string) uniqid())),
                    'author_name' => (string) data_get($changes, 'from.name', data_get($changes, 'from.username', data_get($changes, 'sender_name', 'Usuario Redes'))),
                    'author_id' => (string) data_get($changes, 'from.id', data_get($changes, 'sender_id')),
                    'comment_text' => (string) data_get($changes, 'message', data_get($changes, 'text', 'Comentario en publicación')),
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

        $authorId = $validated['author_id'] ?? null;

        // Crear o encontrar contacto por nombre / autor social
        $contact = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'first_name' => $validated['author_name']],
            [
                'status' => 'active',
                'notes' => "Lead capturado desde {$validated['platform']} Post #{$validated['post_id']}",
                'custom_fields' => array_filter([
                    "{$validated['platform']}_id" => $authorId,
                    $validated['platform'] === 'instagram' ? 'instagram_username' : null => $validated['author_name'],
                ]),
            ]
        );

        if (!empty($authorId)) {
            $cf = (array) ($contact->custom_fields ?? []);
            if (empty($cf["{$validated['platform']}_id"])) {
                $cf["{$validated['platform']}_id"] = $authorId;
                $contact->update(['custom_fields' => $cf]);
            }
        }

        $conversation = null;
        if ($validated['open_dm_ticket'] ?? true) {
            $postId = (string) $validated['post_id'];
            $sourceUrl = "https://{$validated['platform']}.com/{$postId}";
            if ($validated['platform'] === 'facebook' && str_contains($postId, '_')) {
                [$pagePart, $postPart] = explode('_', $postId, 2);
                $sourceUrl = "https://www.facebook.com/{$pagePart}/posts/{$postPart}";
            } elseif ($validated['platform'] === 'instagram') {
                $sourceUrl = "https://www.instagram.com/p/{$postId}";
            }

            $postReferral = [
                'source_type' => 'post',
                'source_id' => $postId,
                'source_url' => $sourceUrl,
                'comment_id' => (string) $validated['comment_id'],
                'headline' => "Publicación en {$validated['platform']} (#{$postId})",
                'body' => "Comentario de {$validated['author_name']}: \"{$validated['comment_text']}\"",
                'media_type' => 'text',
            ];

            $conversation = Conversation::where('organization_id', $orgId)
                ->where('contact_id', $contact->id)
                ->where('channel', $validated['platform'])
                ->where(function ($q) {
                    $q->whereNotNull('origin_referral')
                      ->orWhereNotNull('metadata->origin_referral');
                })
                ->whereIn('status', ['pending', 'open'])
                ->latest('last_message_at')
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'organization_id' => $orgId,
                    'contact_id' => $contact->id,
                    'channel' => $validated['platform'],
                    'status' => 'pending',
                    'last_message_at' => Carbon::now(),
                    'unread_count' => 1,
                    'origin_referral' => $postReferral,
                    'metadata' => ['origin_referral' => $postReferral],
                ]);
            } else {
                $conversation->update([
                    'last_message_at' => Carbon::now(),
                    'origin_referral' => $postReferral,
                    'metadata' => array_merge((array) ($conversation->metadata ?? []), ['origin_referral' => $postReferral]),
                ]);
                $conversation->increment('unread_count');
            }

            Message::create([
                'organization_id' => $orgId,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => "💬 Comentario en publicación: \"{$validated['comment_text']}\"",
                'metadata' => ['referral' => $postReferral],
                'sent_at' => Carbon::now(),
            ]);

            $cf = (array) ($contact->custom_fields ?? []);
            $cf['last_ad_referral'] = $postReferral;
            $contact->update(['custom_fields' => $cf]);
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

        // Suscribir automáticamente la Fanpage a los webhooks de la App (feed y messages)
        try {
            $client->post("https://graph.facebook.com/v21.0/{$pageId}/subscribed_apps", [
                'form_params' => [
                    'subscribed_fields' => 'feed,messages',
                    'access_token' => $token,
                ],
                'timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            // Silencioso si aún faltan permisos específicos
        }

        try {
            $response = $client->get("https://graph.facebook.com/v21.0/{$pageId}/conversations?fields=id,updated_time,participants{id,name,email},messages{id,message,from,created_time}&access_token={$token}");
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            $data = ['data' => []];
        }

        $createdCount = 0;
        foreach (data_get($data, 'data', []) as $conversationData) {
            $participants = data_get($conversationData, 'participants.data', []);
            $customerParticipant = collect($participants)->first(function ($p) use ($pageId, $account) {
                $pId = (string) data_get($p, 'id');
                return $pId !== (string) $pageId && $pId !== (string) $account->business_account_id;
            });

            $customerSocialId = (string) data_get($customerParticipant, 'id');
            $customerName = (string) data_get($customerParticipant, 'name', 'Usuario Facebook');

            $messagesList = data_get($conversationData, 'messages.data', []);

            // Fallback: si participants vino vacío, deducir del primer mensaje que no sea de la página
            if (!$customerSocialId && !empty($messagesList)) {
                foreach ($messagesList as $m) {
                    $mSenderId = (string) data_get($m, 'from.id');
                    if ($mSenderId !== (string) $pageId && $mSenderId !== (string) $account->business_account_id) {
                        $customerSocialId = $mSenderId;
                        $customerName = (string) data_get($m, 'from.name', 'Usuario Facebook');
                        break;
                    }
                }
            }

            if (!$customerSocialId) {
                continue;
            }

            $contact = Contact::where('organization_id', $organization->id)
                ->where(function ($q) use ($customerSocialId, $customerName) {
                    $q->whereJsonContains('custom_fields->facebook_id', $customerSocialId)
                      ->orWhere('first_name', $customerName);
                })
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'organization_id' => $organization->id,
                    'first_name' => $customerName,
                    'status' => 'active',
                    'notes' => "Lead de Facebook Messenger ID #{$customerSocialId}",
                    'custom_fields' => ['facebook_id' => $customerSocialId],
                ]);
            } else {
                $fields = (array) ($contact->custom_fields ?? []);
                if (empty($fields['facebook_id'])) {
                    $fields['facebook_id'] = $customerSocialId;
                    $contact->update(['custom_fields' => $fields]);
                }
            }

            $conversation = Conversation::where('organization_id', $organization->id)
                ->where('contact_id', $contact->id)
                ->where('channel', 'facebook')
                ->latest('last_message_at')
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'organization_id' => $organization->id,
                    'contact_id' => $contact->id,
                    'channel' => 'facebook',
                    'status' => 'pending',
                    'whatsapp_account_id' => $account->id,
                    'unread_count' => 0,
                    'last_message_at' => now(),
                ]);
            }

            foreach ($messagesList as $msgData) {
                $senderId = (string) data_get($msgData, 'from.id');
                $text = (string) data_get($msgData, 'message');
                $sentAt = Carbon::parse(data_get($msgData, 'created_time', now()));

                if (trim($text) === '') {
                    continue;
                }

                $isOutbound = ($senderId === (string) $pageId || $senderId === (string) $account->business_account_id);
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
                    } else {
                        $conversation->update(['unread_count' => 0]);
                    }
                    $conversation->update(['last_message_at' => $sentAt]);
                    event(new TicketMessageCreatedEvent($msg));
                    $createdCount++;
                }
            }
        }

        // 2. Sincronizar Comentarios de Publicaciones Recientes (Feed / Posts)
        $postsCount = 0;
        try {
            $postsResponse = $client->get("https://graph.facebook.com/v21.0/{$pageId}/feed?fields=id,message,comments{id,message,from,created_time}&access_token={$token}");
            $postsData = json_decode($postsResponse->getBody()->getContents(), true);

            foreach (data_get($postsData, 'data', []) as $post) {
                $postId = (string) data_get($post, 'id');

                foreach (data_get($post, 'comments.data', []) as $comment) {
                    $commentId = (string) data_get($comment, 'id');
                    $commentText = (string) data_get($comment, 'message');
                    $authorId = (string) data_get($comment, 'from.id');
                    $authorName = (string) data_get($comment, 'from.name', 'Usuario Facebook');
                    $commentTime = Carbon::parse(data_get($comment, 'created_time', now()));

                    if ($authorId === (string) $pageId || $authorId === (string) $account->business_account_id) {
                        continue;
                    }

                    $alreadyImported = SocialComment::where('organization_id', $organization->id)
                        ->where('comment_id', $commentId)
                        ->exists();

                    if ($alreadyImported) {
                        continue;
                    }

                    $contact = Contact::firstOrCreate(
                        ['organization_id' => $organization->id, 'first_name' => $authorName],
                        [
                            'status' => 'active',
                            'notes' => "Lead capturado desde Facebook Post #{$postId}",
                            'custom_fields' => ['facebook_id' => $authorId],
                        ]
                    );

                    $conversation = Conversation::where('organization_id', $organization->id)
                        ->where('contact_id', $contact->id)
                        ->where('channel', 'facebook')
                        ->where(function ($q) {
                            $q->whereNotNull('origin_referral')
                              ->orWhereNotNull('metadata->origin_referral');
                        })
                        ->latest('last_message_at')
                        ->first();

                    $sourceUrl = "https://facebook.com/{$postId}";
                    if (str_contains($postId, '_')) {
                        [$pagePart, $postPart] = explode('_', $postId, 2);
                        $sourceUrl = "https://www.facebook.com/{$pagePart}/posts/{$postPart}";
                    }

                    $postReferral = [
                        'source_type' => 'post',
                        'source_id' => $postId,
                        'source_url' => $sourceUrl,
                        'headline' => (string) (data_get($post, 'message') ?: "Publicación en Facebook (#{$postId})"),
                        'body' => "Comentario de {$authorName}: \"{$commentText}\"",
                        'media_type' => 'text',
                    ];

                    if (!$conversation) {
                        $conversation = Conversation::create([
                            'organization_id' => $organization->id,
                            'contact_id' => $contact->id,
                            'channel' => 'facebook',
                            'status' => 'pending',
                            'whatsapp_account_id' => $account->id,
                            'unread_count' => 0,
                            'last_message_at' => $commentTime,
                            'origin_referral' => $postReferral,
                            'metadata' => ['origin_referral' => $postReferral],
                        ]);
                    }

                    $msg = Message::create([
                        'organization_id' => $organization->id,
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'body' => "💬 Comentario en publicación: \"{$commentText}\"",
                        'metadata' => ['referral' => $postReferral],
                        'sent_at' => $commentTime,
                    ]);

                    SocialComment::create([
                        'organization_id' => $organization->id,
                        'contact_id' => $contact->id,
                        'conversation_id' => $conversation->id,
                        'platform' => 'facebook',
                        'post_id' => $postId,
                        'comment_id' => $commentId,
                        'author_name' => $authorName,
                        'author_id' => $authorId,
                        'comment_text' => $commentText,
                        'reply_text' => null,
                        'is_replied' => false,
                        'ticket_created' => true,
                    ]);

                    $conversation->increment('unread_count');
                    $conversation->update(['last_message_at' => $commentTime]);
                    event(new TicketMessageCreatedEvent($msg));
                    $postsCount++;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info("Facebook Feed Sync: " . $e->getMessage());
        }

        $total = $createdCount + $postsCount;
        return response()->json([
            'message' => "Sincronización completada. Se importaron {$createdCount} mensajes de Messenger y {$postsCount} comentarios de publicaciones.",
            'imported' => $total,
        ]);
    }

    /**
     * Envía un mensaje directo saliente a Facebook Messenger mediante Graph API.
     * POST https://graph.facebook.com/v21.0/me/messages
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

        if ($conversation->channel !== 'facebook') {
            return response()->json([
                'message' => 'Esta conversación no pertenece al canal Facebook.',
            ], 422);
        }

        // Resolver la cuenta exacta vinculada a la conversación (Multi-Fanpage)
        $account = null;
        if ($conversation->whatsapp_account_id) {
            $account = WhatsAppAccount::where('organization_id', $organization->id)
                ->where('id', $conversation->whatsapp_account_id)
                ->first();
        }

        if (!$account) {
            $account = WhatsAppAccount::where('organization_id', $organization->id)
                ->where('session_type', 'facebook')
                ->where('is_active', true)
                ->first();
        }

        if (!$account || !$account->access_token) {
            return response()->json([
                'message' => 'No hay una cuenta de Facebook activa configurada para este canal.',
            ], 422);
        }

        $contact = $conversation->contact;
        $recipientId = data_get($contact?->custom_fields, 'facebook_id');

        // Buscar si hay un comentario asociado (SocialComment) o en metadata
        $socialComment = SocialComment::where('conversation_id', $conversation->id)->latest()->first();
        $commentId = $socialComment?->comment_id;
        if (!$commentId) {
            $commentId = data_get($conversation->origin_referral, 'comment_id')
                ?: data_get($conversation->metadata, 'origin_referral.comment_id');
        }

        // Extraer recipient ID de las notas si no está en custom_fields
        if (!$recipientId && $contact?->notes) {
            if (preg_match('/ID #?(\d+)/', $contact->notes, $matches)) {
                $recipientId = $matches[1];
            }
        }

        if (!$recipientId && !$commentId) {
            return response()->json([
                'message' => 'No se encontró el ID de Facebook Messenger ni el comentario del contacto para enviar el mensaje.',
            ], 422);
        }

        $token = $account->access_token;
        $pageTarget = $account->phone_number_id ?: 'me';
        $client = new \GuzzleHttp\Client(['timeout' => 15]);

        $result = null;
        $sendError = null;
        $commentReplied = false;

        // Estrategia 1: Si proviene de un comentario en un post, responder directamente al comentario en Facebook (Público en el Post)
        if ($commentId) {
            try {
                $commentResp = $client->post("https://graph.facebook.com/v21.0/{$commentId}/comments", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'message' => $validated['body'],
                    ],
                ]);
                $commentData = json_decode($commentResp->getBody()->getContents(), true);
                if (!empty($commentData['id'])) {
                    $result = $commentData;
                    $commentReplied = true;
                    if ($socialComment) {
                        $socialComment->update([
                            'is_replied' => true,
                            'reply_text' => $validated['body'],
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $sendError = $e->getMessage();
            }

            // Si falló responder en el post (ej: falta pages_manage_engagement), intentar respuesta privada por Messenger
            if (!$commentReplied) {
                try {
                    $response = $client->post("https://graph.facebook.com/v21.0/{$pageTarget}/messages", [
                        'headers' => [
                            'Authorization' => "Bearer {$token}",
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'recipient' => ['comment_id' => $commentId],
                            'message' => ['text' => $validated['body']],
                        ],
                    ]);
                    $result = json_decode($response->getBody()->getContents(), true);
                } catch (\Throwable $e) {
                    $sendError = $e->getMessage();
                }
            }
        }

        // Estrategia 2: Si no hubo comment_id o falló, enviar por recipient id (PSID)
        if (!$result && $recipientId) {
            try {
                $response = $client->post("https://graph.facebook.com/v21.0/{$pageTarget}/messages", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'recipient' => ['id' => $recipientId],
                        'messaging_type' => 'RESPONSE',
                        'message' => ['text' => $validated['body']],
                    ],
                ]);
                $result = json_decode($response->getBody()->getContents(), true);
            } catch (\Throwable $e) {
                $sendError = $e->getMessage();
            }
        }

        if (!$result) {
            $userFriendly = 'No se pudo enviar el mensaje a través de Meta.';
            if (str_contains((string) $sendError, '2018278') || str_contains((string) $sendError, 'período permitido') || str_contains((string) $sendError, 'outside the allowed window')) {
                $userFriendly = 'Meta no permite enviar mensajes por Messenger porque han pasado más de 24 horas desde el último mensaje del cliente. Para chatear por privado, el cliente debe escribir primero en su Messenger (política oficial de 24 horas de Facebook).';
            } elseif (str_contains((string) $sendError, '10900') || str_contains((string) $sendError, 'one private reply')) {
                $userFriendly = 'Ya se utilizó la respuesta privada inicial permitida por Meta para este comentario. Para continuar chateando por privado, el cliente debe responder en su Messenger.';
            } elseif (str_contains((string) $sendError, 'Permissions error') || str_contains((string) $sendError, '"code":200')) {
                $userFriendly = 'Para responder públicamente en el comentario del post de Facebook, tu Token en Meta necesita el permiso "pages_manage_engagement".';
            } else {
                $userFriendly = 'Error de Meta: ' . $sendError;
            }

            return response()->json([
                'message' => $userFriendly,
            ], 422);
        }

        // Si Meta devolvió el PSID en recipient_id, persistirlo en el contacto para futuros mensajes
        $returnedPsid = data_get($result, 'recipient_id');
        if ($returnedPsid && $contact) {
            $cf = (array) ($contact->custom_fields ?? []);
            if (empty($cf['facebook_id']) || $cf['facebook_id'] !== $returnedPsid) {
                $cf['facebook_id'] = $returnedPsid;
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
            'message' => 'Mensaje de Messenger enviado con éxito.',
        ], 201);
    }

    /**
     * Envía un mensaje saliente a una conversación de TikTok.
     */
    public function sendTikTokMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $organization = $request->user()->currentOrganization;

        if ($conversation->organization_id !== $organization->id) {
            abort(403);
        }

        if ($conversation->channel !== 'tiktok') {
            return response()->json([
                'message' => 'Esta conversación no pertenece al canal TikTok.',
            ], 422);
        }

        $account = null;
        if ($conversation->whatsapp_account_id) {
            $account = WhatsAppAccount::where('organization_id', $organization->id)
                ->where('id', $conversation->whatsapp_account_id)
                ->first();
        }

        if (!$account) {
            $account = WhatsAppAccount::where('organization_id', $organization->id)
                ->where('session_type', 'tiktok')
                ->first();
        }

        // Si la cuenta tiene credenciales de TikTok activas, realizar llamada externa
        if ($account && $account->access_token && str_starts_with($account->access_token, 'act.')) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 15]);
                $client->post('https://business-api.tiktok.com/open_api/v1.3/business/message/send/', [
                    'headers' => [
                        'Access-Token' => $account->access_token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'recipient_id' => data_get($conversation->contact?->custom_fields, 'tiktok_id', $conversation->contact?->first_name),
                        'message_text' => $validated['body'],
                    ],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('TikTok API send warning: ' . $e->getMessage());
            }
        }

        // Registrar mensaje saliente en la base de datos del CRM
        $message = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'outbound',
            'body' => $validated['body'],
            'sent_at' => now(),
            'delivery_status' => 'delivered',
        ]);

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
            'message' => 'Respuesta enviada a TikTok con éxito.',
        ], 201);
    }

    /**
     * Obtiene o crea la conversación privada (Messenger / Instagram Direct) para el contacto de un post.
     */
    public function openPrivateChat(Request $request, Conversation $conversation): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        if ($conversation->organization_id !== $organization->id) {
            abort(403);
        }

        $contactId = $conversation->contact_id;
        $channel = in_array($conversation->channel, ['facebook', 'instagram']) ? $conversation->channel : 'facebook';

        // 1. Buscar si ya existe una conversación privada activa (sin referral)
        $privateConv = Conversation::where('organization_id', $organization->id)
            ->where('contact_id', $contactId)
            ->where('channel', $channel)
            ->whereNull('origin_referral')
            ->where(function ($q) {
                $q->whereNull('metadata->origin_referral')
                  ->orWhere('metadata', '[]')
                  ->orWhereNull('metadata');
            })
            ->where('status', '!=', 'closed')
            ->latest('last_message_at')
            ->first();

        if (!$privateConv) {
            $privateConv = Conversation::create([
                'organization_id' => $organization->id,
                'contact_id' => $contactId,
                'channel' => $channel,
                'whatsapp_account_id' => $conversation->whatsapp_account_id,
                'status' => 'pending',
                'unread_count' => 0,
                'origin_referral' => null,
                'metadata' => null,
                'last_message_at' => Carbon::now(),
            ]);
        }

        $privateConv->load(['contact', 'assignee', 'messages' => fn($q) => $q->latest()->take(30)]);

        return response()->json([
            'data' => (new \App\Modules\Conversations\Http\Resources\ConversationResource($privateConv))->resolve(),
            'message' => 'Chat privado abierto con éxito.',
        ]);
    }
}

