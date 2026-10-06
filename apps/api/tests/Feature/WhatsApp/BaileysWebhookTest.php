<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaileysWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_baileys_qr_event_updates_account_qr_and_status(): void
    {
        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_001',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'DISCONNECTED',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'qr',
            'account_id' => $account->id,
            'qr_raw' => '2@sample-baileys-qr-token',
            'qr_image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);

        $account->refresh();
        $this->assertSame('CONNECTING', $account->status);
        $this->assertStringStartsWith('data:image/png;base64,', $account->qrcode_raw);
    }

    public function test_baileys_connected_event_activates_account_with_phone(): void
    {
        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_002',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTING',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'connected',
            'account_id' => $account->id,
            'phone' => '+59178945612',
            'name' => 'Ariel Denys',
        ]);

        $response->assertOk();

        $account->refresh();
        $this->assertSame('CONNECTED', $account->status);
        $this->assertSame('+59178945612', $account->display_phone_number);
        $this->assertTrue($account->is_active);
        $this->assertNull($account->qrcode_raw);
        $this->assertNotNull($account->last_connected_at);
    }

    public function test_baileys_inbound_message_creates_contact_and_ticket_and_broadcasts(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_003',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_msg_998877',
            'from_phone' => '+59171234567',
            'from_name' => 'Cliente WhatsApp Real',
            'body' => 'Hola, quiero consultar por los paquetes disponibles.',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(1, $conversation->unread_count);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('inbound', $message->direction);
        $this->assertSame('Hola, quiero consultar por los paquetes disponibles.', $message->body);

        $this->assertDatabaseHas('whatsapp_message_mappings', [
            'provider_message_id' => 'baileys_msg_998877',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ]);

        Event::assertDispatched(TicketMessageCreatedEvent::class);
    }

    public function test_baileys_inbound_image_message_stores_media_file_and_sets_image_type(): void
    {
        Storage::fake('public');
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_004',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $dummyImageData = base64_encode('fake-binary-image-data-png');

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_img_001',
            'from_phone' => '+59170011223',
            'from_name' => 'Comprador Imagen',
            'body' => 'Mira este comprobante',
            'media_type' => 'image',
            'media_base64' => $dummyImageData,
            'mime_type' => 'image/png',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)->first();
        $this->assertNotNull($conversation);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('image', $message->message_type);
        $this->assertSame('image', $message->media_type);
        $this->assertSame('Mira este comprobante', $message->body);
        $this->assertNotNull($message->media_url);
        $this->assertStringStartsWith('/storage/whatsapp_media/', $message->media_url);
        $this->assertStringEndsWith('.png', $message->media_url);

        $storedPath = str_replace('/storage/', '', $message->media_url);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_baileys_inbound_audio_voice_note_stores_media_file_and_sets_duration(): void
    {
        Storage::fake('public');
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_005',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $dummyAudioData = base64_encode('fake-opus-voice-note');

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_audio_001',
            'from_phone' => '+59170011223',
            'from_name' => 'Cliente Audio',
            'body' => '[Nota de voz / Audio]',
            'media_type' => 'audio',
            'media_base64' => $dummyAudioData,
            'mime_type' => 'audio/ogg; codecs=opus',
            'media_duration_seconds' => 14,
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)->first();
        $this->assertNotNull($conversation);

        $message = Message::where('conversation_id', $conversation->id)->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('audio', $message->message_type);
        $this->assertSame('audio', $message->media_type);
        $this->assertSame(14, $message->media_duration_seconds);
        $this->assertNotNull($message->media_url);
        $this->assertStringStartsWith('/storage/whatsapp_media/', $message->media_url);
        $this->assertStringEndsWith('.ogg', $message->media_url);

        $storedPath = str_replace('/storage/', '', $message->media_url);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_baileys_inbound_sticker_message_stores_webp_media_file_and_sets_sticker_type(): void
    {
        Storage::fake('public');
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_006',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $dummyStickerData = base64_encode('fake-binary-webp-sticker-data');

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_stk_777',
            'from_phone' => '+59170099881',
            'from_name' => 'Cliente Sticker',
            'body' => '[Sticker]',
            'media_type' => 'sticker',
            'media_base64' => $dummyStickerData,
            'mime_type' => 'image/webp',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)->first();
        $this->assertNotNull($conversation);

        $message = Message::where('conversation_id', $conversation->id)->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame('sticker', $message->message_type);
        $this->assertSame('sticker', $message->media_type);
        $this->assertSame('[Sticker]', $message->body);
        $this->assertNotNull($message->media_url);
        $this->assertStringStartsWith('/storage/whatsapp_media/', $message->media_url);
        $this->assertStringEndsWith('.webp', $message->media_url);

        $storedPath = str_replace('/storage/', '', $message->media_url);
        Storage::disk('public')->assertExists($storedPath);
    }
}
