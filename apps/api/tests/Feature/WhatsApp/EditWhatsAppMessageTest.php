<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_edit_own_outbound_whatsapp_message_within_15_minutes(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($agent->id, ['id' => (string) str()->ulid(), 'role' => 'agent']);

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->id,
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
        ]);

        $account->authorizedUsers()->attach($agent->id, [
            'id' => (string) str()->ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->id,
            'phone' => '59170001122',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $account->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $message = Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Texto con error tipografico',
            'created_at' => now()->subMinutes(5),
        ]);

        WhatsAppMessageMapping::factory()->create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $account->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'provider_message_id' => 'baileys_msg_to_edit_123',
            'direction' => 'outbound',
        ]);

        Http::fake([
            'http://whatsapp-service:3000/sessions/*/edit-message' => Http::response([
                'success' => true,
                'messageId' => 'baileys_msg_to_edit_123',
            ], 200),
        ]);

        $response = $this->actingAs($agent)->putJson("/api/conversations/{$conversation->id}/messages/{$message->id}/whatsapp", [
            'body' => 'Texto corregido sin errores',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.body', 'Texto corregido sin errores')
            ->assertJsonPath('data.is_edited', true);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'body' => 'Texto corregido sin errores',
            'original_body' => 'Texto con error tipografico',
            'is_edited' => true,
        ]);
    }

    public function test_cannot_edit_message_older_than_15_minutes(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($agent->id, ['id' => (string) str()->ulid(), 'role' => 'agent']);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to_user_id' => $agent->id,
        ]);

        $message = Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Mensaje muy antiguo',
            'created_at' => now()->subMinutes(20),
        ]);

        $response = $this->actingAs($agent)->putJson("/api/conversations/{$conversation->id}/messages/{$message->id}/whatsapp", [
            'body' => 'Intento de edicion tardia',
        ]);

        $response->assertStatus(422);
    }

    public function test_non_author_agent_cannot_edit_someone_elses_message(): void
    {
        $organization = Organization::factory()->create();
        $author = User::factory()->create(['current_organization_id' => $organization->id]);
        $otherAgent = User::factory()->create(['current_organization_id' => $organization->id]);

        $organization->users()->attach($author->id, ['id' => (string) str()->ulid(), 'role' => 'agent']);
        $organization->users()->attach($otherAgent->id, ['id' => (string) str()->ulid(), 'role' => 'agent']);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to_user_id' => $otherAgent->id,
        ]);

        $message = Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $author->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Mensaje original del autor',
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($otherAgent)->putJson("/api/conversations/{$conversation->id}/messages/{$message->id}/whatsapp", [
            'body' => 'Intento de edicion ajena',
        ]);

        $response->assertStatus(403);
    }

    public function test_baileys_incoming_message_edited_webhook_updates_message(): void
    {
        $organization = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->id,
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $account->id,
        ]);

        $message = Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'Mensaje cliente original',
        ]);

        WhatsAppMessageMapping::factory()->create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $account->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'provider_message_id' => 'provider_client_msg_555',
            'direction' => 'inbound',
        ]);

        $response = $this->postJson('/api/whatsapp/baileys/webhook', [
            'event' => 'message_edited',
            'account_id' => $account->id,
            'target_message_id' => 'provider_client_msg_555',
            'body' => 'Mensaje cliente editado desde su telefono',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'body' => 'Mensaje cliente editado desde su telefono',
            'original_body' => 'Mensaje cliente original',
            'is_edited' => true,
        ]);
    }
}
