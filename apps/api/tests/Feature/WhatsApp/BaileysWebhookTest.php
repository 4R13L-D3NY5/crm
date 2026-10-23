<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Conversations\Events\TicketCreatedEvent;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Events\TicketUpdatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
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

    public function test_baileys_outbound_mirror_message_creates_outbound_message_and_mapping(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_007',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_out_phone_112233',
            'from_phone' => '+59178899001',
            'from_name' => 'Comprador Móvil',
            'from_me' => true,
            'body' => 'Hola, te escribo desde mi celular Android fuera del CRM.',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame('open', $conversation->status);
        $this->assertSame(0, $conversation->unread_count, 'Outbound messages should not increment unread counter');

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('outbound', $message->direction);
        $this->assertSame('delivered', $message->message_status);
        $this->assertSame('delivered', $message->delivery_status);
        $this->assertSame('Hola, te escribo desde mi celular Android fuera del CRM.', $message->body);

        $this->assertDatabaseHas('whatsapp_message_mappings', [
            'provider_message_id' => 'baileys_out_phone_112233',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'direction' => 'outbound',
            'status' => 'delivered',
        ]);

        Event::assertDispatched(TicketMessageCreatedEvent::class);
    }

    public function test_baileys_outbound_crm_message_with_existing_mapping_is_ignored_without_duplicate(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_008',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $conversation = Conversation::create([
            'organization_id' => $organization->id,
            'channel' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        $originalMessage = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'sent',
            'delivery_status' => 'sent',
            'body' => 'Mensaje original enviado desde CRM web',
            'sent_at' => now(),
        ]);

        WhatsAppMessageMapping::create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $account->id,
            'conversation_id' => $conversation->id,
            'message_id' => $originalMessage->id,
            'provider_message_id' => 'baileys_crm_existing_id_999',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        // Baileys sends webhook for the message that was already mapped
        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_crm_existing_id_999',
            'from_phone' => '+59178899001',
            'from_name' => 'Comprador Móvil',
            'from_me' => true,
            'body' => 'Mensaje original enviado desde CRM web',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        // Must still be only 1 message, no duplicates
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());
        Event::assertNotDispatched(TicketMessageCreatedEvent::class);
    }

    public function test_baileys_outbound_crm_image_without_preexisting_mapping_is_linked_to_recent_outbound_without_duplicate(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_009',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $contact = \App\Modules\Contacts\Models\Contact::create([
            'organization_id' => $organization->id,
            'phone' => '+59178899002',
            'first_name' => 'Comprador',
            'last_name' => 'Multimedia',
        ]);

        $conversation = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        // Simula el mensaje recién creado por SendWhatsAppMessageAction desde la UI del CRM
        // pero que AUN NO tiene un WhatsAppMessageMapping registrado (condición de carrera)
        $outboundImageMessage = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'image',
            'media_type' => 'image',
            'media_url' => '/storage/whatsapp_media/sample.jpg',
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'body' => '[Imagen]',
            'created_at' => now(),
            'sent_at' => null,
        ]);

        // El webhook espejo de Baileys llega de inmediato antes de que el Job termine de guardar el mapeo
        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_out_race_img_8877',
            'from_phone' => '+59178899002',
            'from_me' => true,
            'media_type' => 'image',
            'body' => '[Imagen]',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        // Debe mantenerse exactamente 1 mensaje en la base de datos (NO duplicado)
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());

        // El mensaje existente debe haber sido actualizado a 'delivered'
        $outboundImageMessage->refresh();
        $this->assertSame('delivered', $outboundImageMessage->message_status);
        $this->assertSame('delivered', $outboundImageMessage->delivery_status);

        // Se debe haber creado el mapeo con el ID del proveedor
        $this->assertDatabaseHas('whatsapp_message_mappings', [
            'provider_message_id' => 'baileys_out_race_img_8877',
            'conversation_id' => $conversation->id,
            'message_id' => $outboundImageMessage->id,
            'direction' => 'outbound',
            'status' => 'delivered',
        ]);

        // NO se debe haber emitido evento de creación nuevo
        Event::assertNotDispatched(TicketMessageCreatedEvent::class);
    }

    public function test_baileys_inbound_message_reopens_closed_conversation_without_duplicate(): void
    {
        Event::fake([TicketMessageCreatedEvent::class, TicketUpdatedEvent::class, TicketCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org', 'slug' => 'demo-org']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Baileys Line',
            'phone_number_id' => 'baileys_phone_010',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $contact = \App\Modules\Contacts\Models\Contact::create([
            'organization_id' => $organization->id,
            'phone' => '+59172001140',
            'first_name' => 'Juan P',
        ]);

        // Conversación previa cerrada / finalizada
        $closedConversation = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
            'status' => 'closed',
            'closed_at' => now()->subHour(),
            'unread_count' => 0,
            'last_message_at' => now()->subHour(),
        ]);

        Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $closedConversation->id,
            'direction' => 'inbound',
            'body' => 'Consulta anterior ya resuelta',
            'sent_at' => now()->subHour(),
        ]);

        // El cliente vuelve a escribir después de cerrada la conversación
        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_in_reopen_001',
            'from_phone' => '+59172001140',
            'from_name' => 'Juan P',
            'body' => 'Hola, me surgió una nueva consulta',
            'timestamp' => now()->timestamp,
        ]);

        $response->assertOk();

        // NO se debe crear una conversación duplicada para el mismo contacto
        $this->assertSame(1, Conversation::where('contact_id', $contact->id)->count());

        // La conversación existente debe haberse reactivado a 'pending'
        $closedConversation->refresh();
        $this->assertSame('pending', $closedConversation->status);
        $this->assertNull($closedConversation->closed_at);
        $this->assertSame(1, $closedConversation->unread_count);

        // El nuevo mensaje debe estar añadido al hilo existente
        $this->assertSame(2, Message::where('conversation_id', $closedConversation->id)->count());

        Event::assertDispatched(TicketMessageCreatedEvent::class);
        Event::assertDispatched(TicketUpdatedEvent::class);
        Event::assertNotDispatched(TicketCreatedEvent::class);
    }

    public function test_baileys_inbound_message_with_meta_ad_referral_stores_origin_and_message_metadata(): void
    {
        Event::fake([TicketCreatedEvent::class, TicketMessageCreatedEvent::class]);

        $organization = Organization::create(['name' => 'Demo Org Ads', 'slug' => 'demo-org-ads']);
        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Ads Line',
            'phone_number_id' => 'baileys_phone_ads',
            'verify_token' => 'baileys_token_secret',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $referralPayload = [
            'source_type' => 'ad',
            'source_id' => 'ad_meta_99887766',
            'source_url' => 'https://fb.me/diplomado_ia',
            'headline' => 'Diplomado en Inteligencia Artificial 2026',
            'body' => 'Inscripciones abiertas con 30% de descuento anticipado.',
            'media_type' => 'image',
            'image_url' => 'https://cdn.example.com/banner.jpg',
        ];

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $account->id,
            'provider_message_id' => 'baileys_msg_ad_001',
            'from_phone' => '+59178888999',
            'from_name' => 'Carlos Lead',
            'body' => 'Hola, quiero info sobre el diplomado',
            'timestamp' => now()->timestamp,
            'referral' => $referralPayload,
        ]);

        $response->assertOk();

        $conversation = Conversation::where('organization_id', $organization->id)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('Diplomado en Inteligencia Artificial 2026', $conversation->origin_referral['headline'] ?? null);
        $this->assertSame('ad_meta_99887766', $conversation->origin_referral['source_id'] ?? null);
        $this->assertSame('ad', $conversation->origin_referral['source_type'] ?? null);

        $message = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('ad_meta_99887766', $message->metadata['referral']['source_id'] ?? null);
        $this->assertSame('https://fb.me/diplomado_ia', $message->metadata['referral']['source_url'] ?? null);

        $contact = $conversation->contact;
        $this->assertNotNull($contact);
        $this->assertSame('ad_meta_99887766', $contact->custom_fields['last_ad_referral']['source_id'] ?? null);
    }
}
