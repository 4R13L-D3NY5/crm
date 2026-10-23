<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verification_returns_challenge_for_valid_token(): void
    {
        $account = WhatsAppAccount::factory()->create([
            'verify_token' => 'meta-verify-token',
            'is_active' => true,
        ]);

        $response = $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=meta-verify-token&hub.challenge=123456');

        $response
            ->assertOk()
            ->assertSeeText('123456');
    }

    public function test_incoming_message_creates_contact_conversation_message_and_raw_event(): void
    {
        $organization = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
            'display_phone_number' => '+59170000001',
        ]);

        $payload = $this->payload(
            phoneNumberId: $account->phone_number_id,
            messageId: 'wamid.HBgLMzkxNzAwMDAwMTERAgARGBI1',
            from: '59171122334',
            body: 'Hola, necesito informacion del servicio.',
            profileName: 'Carlos Demo',
        );

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response
            ->assertStatus(202)
            ->assertJsonPath('received', true);

        $contact = Contact::query()->where('organization_id', $organization->getKey())->first();
        $this->assertNotNull($contact);
        $this->assertSame('Carlos Demo', $contact->first_name);
        $this->assertSame('59171122334', $contact->phone);

        $conversation = Conversation::query()->where('organization_id', $organization->getKey())->first();
        $this->assertNotNull($conversation);
        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertSame($contact->getKey(), $conversation->contact_id);

        $message = Message::query()->where('conversation_id', $conversation->getKey())->first();
        $this->assertNotNull($message);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('Hola, necesito informacion del servicio.', $message->body);

        $this->assertDatabaseHas('whatsapp_message_mappings', [
            'organization_id' => $organization->getKey(),
            'provider_message_id' => 'wamid.HBgLMzkxNzAwMDAwMTERAgARGBI1',
        ]);

        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'organization_id' => $organization->getKey(),
            'whatsapp_account_id' => $account->getKey(),
            'processing_status' => 'processed',
        ]);
    }

    public function test_duplicate_webhook_does_not_duplicate_messages(): void
    {
        $organization = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
        ]);

        $payload = $this->payload(
            phoneNumberId: $account->phone_number_id,
            messageId: 'wamid.HBgLMzkxNzAwMDAwMTE2AgARGBI1',
            from: '59170001010',
            body: 'Mensaje duplicado',
            profileName: 'Lead Repetido',
        );

        $this->postJson('/api/whatsapp/webhook', $payload)->assertStatus(202);
        $this->postJson('/api/whatsapp/webhook', $payload)->assertStatus(202);

        $this->assertSame(1, Message::query()->count());
        $this->assertSame(1, WhatsAppMessageMapping::query()->count());
        $this->assertSame(2, WhatsAppWebhookEvent::query()->count());
    }

    public function test_incoming_message_with_meta_cloud_referral_stores_origin_and_metadata(): void
    {
        $organization = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555987654321',
        ]);

        $referralData = [
            'source_type' => 'ad',
            'source_id' => 'ad_meta_cloud_112233',
            'source_url' => 'https://fb.me/meta_cloud_ad',
            'headline' => 'Curso de Marketing Digital 2026',
            'body' => 'Inscripciones con 50% de beca',
            'media_type' => 'image',
            'image_url' => 'https://cdn.example.com/cloud-ad.jpg',
        ];

        $payload = $this->payload(
            phoneNumberId: $account->phone_number_id,
            messageId: 'wamid.HBgLMzkxNzAwMDAwMTE5AgARGBI1',
            from: '59173344556',
            body: 'Quiero información de la beca',
            profileName: 'Maria Becas',
            referral: $referralData,
        );

        $this->postJson('/api/whatsapp/webhook', $payload)->assertStatus(202);

        $conversation = Conversation::where('organization_id', $organization->getKey())->first();
        $this->assertNotNull($conversation);
        $this->assertSame('Curso de Marketing Digital 2026', $conversation->origin_referral['headline'] ?? null);
        $this->assertSame('ad_meta_cloud_112233', $conversation->origin_referral['source_id'] ?? null);

        $message = Message::where('conversation_id', $conversation->getKey())->first();
        $this->assertNotNull($message);
        $this->assertSame('ad_meta_cloud_112233', $message->metadata['referral']['source_id'] ?? null);
        $this->assertSame('https://fb.me/meta_cloud_ad', $message->metadata['referral']['source_url'] ?? null);

        $contact = $conversation->contact;
        $this->assertNotNull($contact);
        $this->assertSame('ad_meta_cloud_112233', $contact->custom_fields['last_ad_referral']['source_id'] ?? null);
    }

    private function payload(
        string $phoneNumberId,
        string $messageId,
        string $from,
        string $body,
        string $profileName,
        ?array $referral = null,
    ): array {
        $msg = [
            'from' => $from,
            'id' => $messageId,
            'timestamp' => '1777171717',
            'type' => 'text',
            'text' => [
                'body' => $body,
            ],
        ];

        if ($referral) {
            $msg['referral'] = $referral;
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '123456789',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+59170000001',
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => $profileName,
                            ],
                            'wa_id' => $from,
                        ]],
                        'messages' => [$msg],
                    ],
                ]],
            ]],
        ];
    }
}
