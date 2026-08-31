<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_outbound_whatsapp_message_from_inbox(): void
    {
        [$user, $organization] = $this->createMembership();

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
            'access_token' => 'meta-token',
            'is_active' => true,
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59170001010',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [
                    ['id' => 'wamid.outbound.123'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->getKey()}/messages/whatsapp", [
            'body' => 'Hola, te escribimos desde CRM.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.direction', 'outbound');

        $message = Message::query()->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('sent', $message->message_status);

        $this->assertDatabaseHas('whatsapp_message_mappings', [
            'message_id' => $message->getKey(),
            'provider_message_id' => 'wamid.outbound.123',
            'status' => 'sent',
        ]);
    }

    public function test_failed_outbound_message_can_be_retried(): void
    {
        [$user, $organization] = $this->createMembership();

        WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
            'access_token' => 'meta-token',
            'is_active' => true,
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59170001010',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $message = Message::query()->create([
            'organization_id' => $organization->getKey(),
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'failed',
            'error_message' => 'HTTP request returned status code 400',
            'body' => 'Primer intento',
            'sent_at' => null,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [
                    ['id' => 'wamid.outbound.retry.456'],
                ],
            ], 200),
        ]);

        $retryResponse = $this->actingAs($user)->postJson(
            "/api/conversations/{$conversation->getKey()}/messages/{$message->getKey()}/whatsapp-retry"
        );

        $retryResponse->assertOk();

        $message->refresh();
        $this->assertNull($message->error_message);
        $this->assertSame('sent', $message->message_status);
    }

    private function createMembership(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);

        $organization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        return [$user, $organization];
    }
}
