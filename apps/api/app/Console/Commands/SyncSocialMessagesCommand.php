<?php

namespace App\Console\Commands;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncSocialMessagesCommand extends Command
{
    protected $signature = 'social:sync {--account= : ID de la cuenta}';
    protected $description = 'Sincroniza mensajes recientes de Facebook e Instagram desde Meta Graph API';

    public function handle(): int
    {
        $accounts = WhatsAppAccount::whereIn('session_type', ['facebook', 'instagram'])
            ->whereNotNull('access_token')
            ->when($this->option('account'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $client = new Client(['timeout' => 15]);

        foreach ($accounts as $account) {
            $this->info("Sincronizando cuenta [{$account->session_type}] {$account->name} ({$account->id})...");

            if ($account->session_type === 'facebook') {
                $this->syncFacebook($client, $account);
            } elseif ($account->session_type === 'instagram') {
                $this->syncInstagram($client, $account);
            }
        }

        return self::SUCCESS;
    }

    protected function syncFacebook(Client $client, WhatsAppAccount $account): void
    {
        $pageId = $account->phone_number_id;
        $token = $account->access_token;

        try {
            $url = "https://graph.facebook.com/v21.0/{$pageId}/conversations?fields=id,updated_time,participants{id,name,email},messages{id,message,from,created_time}&access_token={$token}";
            $response = $client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            $this->error("Error al sincronizar Facebook: " . $e->getMessage());
            return;
        }

        $imported = 0;
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

            $contact = Contact::where('organization_id', $account->organization_id)
                ->where(function ($q) use ($customerSocialId, $customerName) {
                    $q->whereJsonContains('custom_fields->facebook_id', $customerSocialId)
                      ->orWhere('first_name', $customerName);
                })
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'organization_id' => $account->organization_id,
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

            $conversation = Conversation::firstOrCreate(
                [
                    'organization_id' => $account->organization_id,
                    'contact_id' => $contact->id,
                    'channel' => 'facebook',
                ],
                [
                    'status' => 'pending',
                    'whatsapp_account_id' => $account->id,
                    'unread_count' => 0,
                    'last_message_at' => now(),
                ]
            );

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
                        'organization_id' => $account->organization_id,
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
                                'organization_id' => $account->organization_id,
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
                    $imported++;
                    $this->line(" + [Facebook " . ($isOutbound ? 'Outbound' : 'Inbound') . "] Mensaje importado: {$text}");
                }
            }
        }

        $this->info("Facebook [{$account->name}]: {$imported} nuevos mensajes importados.");
    }

    protected function syncInstagram(Client $client, WhatsAppAccount $account): void
    {
        $fbAccount = WhatsAppAccount::where('organization_id', $account->organization_id)
            ->where('session_type', 'facebook')
            ->first();

        $pageId = $account->business_account_id
            ?? $fbAccount?->phone_number_id
            ?? $fbAccount?->business_account_id
            ?? '187805724574229';

        $igAccountId = $account->phone_number_id;
        $token = $account->access_token;

        // Intentar resolver Page Token si el token actual es de usuario
        try {
            $tokenRes = $client->get("https://graph.facebook.com/v22.0/{$pageId}?fields=access_token&access_token={$token}");
            $tokenData = json_decode($tokenRes->getBody()->getContents(), true);
            if (!empty($tokenData['access_token'])) {
                $token = $tokenData['access_token'];
                if ($account->access_token !== $token && str_starts_with($token, 'EAA')) {
                    $account->update(['access_token' => $token]);
                }
            }
        } catch (Throwable) {
        }

        $conversationsData = [];
        try {
            $url = "https://graph.facebook.com/v22.0/{$pageId}/conversations?platform=instagram&limit=10&fields=id,updated_time,participants{id,username}&access_token={$token}";
            $response = $client->get($url);
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                $conversationsData = data_get($data, 'data', []);
            }
        } catch (Throwable $e) {
            $this->warn("Aviso Instagram [{$account->name}]: Meta no devolvió hilos históricos (" . $e->getMessage() . "). Los mensajes entrarán en tiempo real por Webhook.");
            return;
        }

        $imported = 0;
        $conversationsData = data_get($data, 'data', []);

        foreach ($conversationsData as $conversationData) {
            $participants = data_get($conversationData, 'participants.data', []);
            $customerParticipant = collect($participants)->first(function ($p) use ($igAccountId) {
                return (string) data_get($p, 'id') !== (string) $igAccountId;
            });

            $customerIgId = (string) data_get($customerParticipant, 'id');
            $customerUsername = (string) data_get($customerParticipant, 'username', 'Usuario Instagram');
            $messagesList = data_get($conversationData, 'messages.data', []);

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

            $contact = Contact::where('organization_id', $account->organization_id)
                ->where(function ($q) use ($customerIgId, $customerUsername) {
                    $q->whereJsonContains('custom_fields->instagram_id', $customerIgId)
                      ->orWhere('first_name', $customerUsername);
                })
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'organization_id' => $account->organization_id,
                    'first_name' => $customerUsername,
                    'status' => 'active',
                    'notes' => "Lead de Instagram Direct @{$customerUsername} (ID #{$customerIgId})",
                    'custom_fields' => [
                        'instagram_id' => $customerIgId,
                        'instagram_username' => $customerUsername,
                    ],
                ]);
            } else {
                $customFields = (array) ($contact->custom_fields ?? []);
                if (empty($customFields['instagram_id'])) {
                    $customFields['instagram_id'] = $customerIgId;
                    $customFields['instagram_username'] = $customerUsername;
                    $contact->update(['custom_fields' => $customFields]);
                }
            }

            $conversation = Conversation::firstOrCreate(
                [
                    'organization_id' => $account->organization_id,
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
                        'organization_id' => $account->organization_id,
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
                                'organization_id' => $account->organization_id,
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
                    $imported++;
                    $this->line(" + [Instagram] Mensaje importado: {$text}");
                }
            }
        }

        $this->info("Instagram [{$account->name}]: {$imported} nuevos mensajes importados.");
    }
}
