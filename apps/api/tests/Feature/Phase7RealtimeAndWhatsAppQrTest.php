<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7RealtimeAndWhatsAppQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_qr_code_can_be_generated_with_expiration(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Oficial',
            'phone_number_id' => 'phone_id_001',
            'verify_token' => 'token_secret_123',
            'status' => 'DISCONNECTED',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson("/api/whatsapp/accounts/{$account->id}/qr");

        $response->assertStatus(200)
            ->assertJsonPath('data.account_id', $account->id)
            ->assertJsonPath('data.status', 'CONNECTING')
            ->assertJsonStructure([
                'data' => ['account_id', 'status', 'qrcode_raw', 'expires_in_seconds', 'generated_at'],
            ]);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'status' => 'CONNECTING',
        ]);
    }

    public function test_whatsapp_session_scan_transitions_to_connected(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Soporte',
            'phone_number_id' => 'phone_id_002',
            'verify_token' => 'token_secret_123',
            'status' => 'CONNECTING',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/whatsapp/accounts/{$account->id}/simulate-scan", [
            'phone_number' => '+59170011223',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'CONNECTED');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'status' => 'CONNECTED',
        ]);
    }

    public function test_incoming_message_simulation_broadcasts_and_creates_ticket(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Oficial',
            'phone_number_id' => 'phone_id_003',
            'verify_token' => 'token_secret_123',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/whatsapp/accounts/{$account->id}/simulate-incoming", [
            'from_phone' => '+59176543210',
            'from_name' => 'Carla Guzman',
            'message' => 'Buenas tardes, deseo información de la carrera de Odontología.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.contact.phone', '+59176543210')
            ->assertJsonPath('data.message.body', 'Buenas tardes, deseo información de la carrera de Odontología.');

        // Validar que se creó la conversación y el mensaje
        $this->assertDatabaseHas('contacts', ['phone' => '+59176543210']);
        $this->assertDatabaseHas('conversations', ['organization_id' => $organization->id, 'channel' => 'whatsapp']);
        $this->assertDatabaseHas('messages', ['body' => 'Buenas tardes, deseo información de la carrera de Odontología.']);

        // Validar que se disparó el evento de WebSockets en tiempo real
        Event::assertDispatched(TicketMessageCreatedEvent::class);
    }
}
