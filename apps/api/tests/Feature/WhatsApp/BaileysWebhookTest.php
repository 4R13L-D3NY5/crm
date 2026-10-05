<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
}
