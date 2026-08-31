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

    private function payload(
        string $phoneNumberId,
        string $messageId,
        string $from,
        string $body,
        string $profileName,
    ): array {
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
                        'messages' => [[
                            'from' => $from,
                            'id' => $messageId,
                            'timestamp' => '1777171717',
                            'type' => 'text',
                            'text' => [
                                'body' => $body,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
