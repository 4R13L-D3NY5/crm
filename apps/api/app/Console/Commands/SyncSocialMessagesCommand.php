<?php

namespace App\Console\Commands;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
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
            $url = "https://graph.facebook.com/v21.0/{$pageId}/conversations?fields=id,updated_time,messages{id,message,from,created_time}&access_token={$token}";
            $response = $client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            $this->error("Error al sincronizar Facebook: " . $e->getMessage());
            return;
        }

        $imported = 0;
        foreach (data_get($data, 'data', []) as $conversationData) {
            foreach (data_get($conversationData, 'messages.data', []) as $msgData) {
                $senderId = (string) data_get($msgData, 'from.id');
                $senderName = (string) data_get($msgData, 'from.name', 'Usuario Facebook');
                $text = (string) data_get($msgData, 'message');
                $sentAt = Carbon::parse(data_get($msgData, 'created_time', now()));

                if (!$text || $senderId === (string) $pageId || $senderId === (string) $account->business_account_id) {
                    continue;
                }

                $contact = Contact::firstOrCreate(
                    ['organization_id' => $account->organization_id, 'first_name' => $senderName],
                    [
                        'status' => 'active',
                        'notes' => "Lead de Facebook Messenger ID #{$senderId}",
                        'custom_fields' => ['facebook_id' => $senderId],
                    ]
                );

                if (!data_get($contact->custom_fields, 'facebook_id')) {
                    $fields = (array) ($contact->custom_fields ?? []);
                    $fields['facebook_id'] = $senderId;
                    $contact->update(['custom_fields' => $fields]);
                }

                $conversation = Conversation::firstOrCreate(
                    [
                        'organization_id' => $account->organization_id,
                        'contact_id' => $contact->id,
                        'channel' => 'facebook',
                    ],
                    [
                        'status' => 'open',
                        'whatsapp_account_id' => $account->id,
                        'unread_count' => 0,
                        'last_message_at' => $sentAt,
                    ]
                );

                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('body', $text)
                    ->where('direction', 'inbound')
                    ->exists();

                if (!$exists) {
                    $msg = Message::create([
                        'organization_id' => $account->organization_id,
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'body' => $text,
                        'sent_at' => $sentAt,
                    ]);

                    $conversation->increment('unread_count');
                    $conversation->update(['last_message_at' => $sentAt]);
                    event(new TicketMessageCreatedEvent($msg));
                    $imported++;
                    $this->line(" + Mensaje importado: {$text}");
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

        $pageId = $fbAccount?->phone_number_id ?? '1417856484734200';
        $igAccountId = $account->phone_number_id;
        $token = $account->access_token;

        try {
            $url = "https://graph.facebook.com/v21.0/{$pageId}/conversations?platform=instagram&fields=id,updated_time,participants{id,username},messages{id,message,from,created_time}&access_token={$token}";
            $response = $client->get($url);
            $data = json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            $this->error("Error al sincronizar Instagram: " . $e->getMessage());
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

                $exists = Message::where('conversation_id', $conversation->id)
                    ->where('body', $text)
                    ->where('sent_at', $sentAt)
                    ->exists();

                if (!$exists) {
                    $msg = Message::create([
                        'organization_id' => $account->organization_id,
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
                    $imported++;
                    $this->line(" + [Instagram] Mensaje importado: {$text}");
                }
            }
        }

        $this->info("Instagram [{$account->name}]: {$imported} nuevos mensajes importados.");
    }
}
