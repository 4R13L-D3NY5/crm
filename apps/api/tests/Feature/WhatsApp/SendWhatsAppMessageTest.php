<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame('sent', $message->message_status, (string) $message->error_message);

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

    public function test_user_can_send_outbound_media_image_file_to_baileys(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->createMembership();

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => 'baileys_outbound_01',
            'session_type' => 'qr_baileys',
            'access_token' => null,
            'is_active' => true,
            'status' => 'CONNECTED',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59179326793',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Http::fake([
            'http://whatsapp-service:3000/sessions/*/send-media' => Http::response([
                'success' => true,
                'messageId' => 'baileys_outbound_msg_999',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('comprobante.jpg', 120, 'image/jpeg');

        $response = $this->actingAs($user)->post("/api/conversations/{$conversation->getKey()}/messages/whatsapp", [
            'body' => 'Aquí tienes el comprobante de pago',
            'file' => $file,
            'media_type' => 'image',
        ]);

        $response->assertCreated();

        $message = Message::query()->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('image', $message->message_type);
        $this->assertSame('image', $message->media_type);
        $this->assertNotNull($message->media_url);
        $this->assertStringStartsWith('/storage/whatsapp_media/', $message->media_url);
        $this->assertSame('sent', $message->message_status, (string) $message->error_message);
    }

    public function test_user_can_send_outbound_media_audio_voice_note_to_baileys(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->createMembership();

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => 'baileys_outbound_audio',
            'session_type' => 'qr_baileys',
            'access_token' => null,
            'is_active' => true,
            'status' => 'CONNECTED',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59179326793',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Http::fake([
            'http://whatsapp-service:3000/sessions/*/send-media' => Http::response([
                'success' => true,
                'messageId' => 'baileys_outbound_audio_999',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('nota_voz.ogg', 80, 'audio/ogg');

        $response = $this->actingAs($user)->post("/api/conversations/{$conversation->getKey()}/messages/whatsapp", [
            'file' => $file,
            'media_type' => 'audio',
        ]);

        $response->assertCreated();

        $message = Message::query()->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('audio', $message->message_type);
        $this->assertSame('audio', $message->media_type);
        $this->assertNotNull($message->media_url);
        $this->assertSame('[Nota de voz / Audio]', $message->body);
        $this->assertSame('sent', $message->message_status, (string) $message->error_message);
    }

    public function test_user_can_send_outbound_media_sticker_to_baileys(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->createMembership();

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => 'baileys_outbound_stk',
            'session_type' => 'qr_baileys',
            'access_token' => null,
            'is_active' => true,
            'status' => 'CONNECTED',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59179326793',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Http::fake([
            'http://whatsapp-service:3000/sessions/*/send-media' => Http::response([
                'success' => true,
                'messageId' => 'baileys_outbound_stk_888',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('sticker_animado.webp', 80, 'image/webp');

        $response = $this->actingAs($user)->post("/api/conversations/{$conversation->getKey()}/messages/whatsapp", [
            'body' => '',
            'file' => $file,
            'media_type' => 'sticker',
        ]);

        $response->assertCreated();

        $message = Message::query()->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('sticker', $message->message_type);
        $this->assertSame('sticker', $message->media_type);
        $this->assertNotNull($message->media_url);
        $this->assertSame('[Sticker]', $message->body);
        $this->assertSame('sent', $message->message_status, (string) $message->error_message);
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
