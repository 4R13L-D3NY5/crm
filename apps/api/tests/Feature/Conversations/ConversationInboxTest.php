<?php

namespace Tests\Feature\Conversations;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_show_assign_update_status_and_add_internal_message(): void
    {
        [$user, $organization] = $this->createMembership();
        $agent = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);
        $organization->users()->attach($agent->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'agent',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Laura',
            'last_name' => 'Mendez',
        ]);

        $company = Company::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Centro Medico Demo',
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/conversations', [
            'subject' => 'Consulta por demo comercial',
            'status' => 'open',
            'channel' => 'manual',
            'contact_id' => $contact->getKey(),
            'company_id' => $company->getKey(),
            'message' => 'Cliente solicita una llamada para el viernes.',
            'assigned_to_user_id' => $agent->getKey(),
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Consulta por demo comercial')
            ->assertJsonPath('data.assignment.id', $agent->getKey());

        $conversationId = $createResponse->json('data.id');

        $listResponse = $this->actingAs($user)->getJson('/api/conversations?search=Laura&status=open');
        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $showResponse = $this->actingAs($user)->getJson("/api/conversations/{$conversationId}");
        $showResponse
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Cliente solicita una llamada para el viernes.');

        $assignResponse = $this->actingAs($user)->putJson("/api/conversations/{$conversationId}/assignment", [
            'assigned_to_user_id' => $user->getKey(),
        ]);

        $assignResponse
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $user->getKey());

        $statusResponse = $this->actingAs($user)->putJson("/api/conversations/{$conversationId}/status", [
            'status' => 'pending',
        ]);

        $statusResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $messageResponse = $this->actingAs($user)->postJson("/api/conversations/{$conversationId}/messages/internal", [
            'body' => 'Se agenda seguimiento para maniana.',
        ]);

        $messageResponse
            ->assertCreated()
            ->assertJsonPath('data.direction', 'internal');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'body' => 'Se agenda seguimiento para maniana.',
        ]);
    }

    public function test_conversations_are_scoped_to_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();
        $otherOrganization = Organization::factory()->create();

        $conversation = Conversation::factory()->create([
            'organization_id' => $otherOrganization->getKey(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/conversations/{$conversation->getKey()}");
        $response->assertForbidden();

        $visibleConversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);

        $visibleResponse = $this->actingAs($user)->getJson("/api/conversations/{$visibleConversation->getKey()}");
        $visibleResponse->assertOk();
    }

    public function test_agent_can_access_companies_and_reports_and_is_scoped_by_channel(): void
    {
        $organization = Organization::factory()->create();
        
        $admin = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($admin->id, ['id' => (string) str()->ulid(), 'role' => 'admin']);

        $agent = User::factory()->create(['current_organization_id' => $organization->id]);
        $organization->users()->attach($agent->id, ['id' => (string) str()->ulid(), 'role' => 'agent']);

        // 1. Agent can access companies and reports without 403
        $this->actingAs($agent)->getJson('/api/companies')->assertOk();
        $this->actingAs($agent)->getJson('/api/reports/dashboard')->assertOk();

        // 2. Channel isolation
        $channelCochabamba = \App\Modules\WhatsApp\Models\WhatsAppAccount::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Canal Cochabamba',
        ]);
        $channelLaPaz = \App\Modules\WhatsApp\Models\WhatsAppAccount::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Canal La Paz',
        ]);

        // Restrict channel La Paz to admin only
        $channelLaPaz->authorizedUsers()->attach($admin->id, [
            'id' => (string) str()->ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        // Restrict channel Cochabamba to agent
        $channelCochabamba->authorizedUsers()->attach($agent->id, [
            'id' => (string) str()->ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        $convCbba = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $channelCochabamba->id,
            'status' => 'open',
        ]);

        $convLP = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $channelLaPaz->id,
            'status' => 'open',
        ]);

        // Agent querying inbox should see convCbba, but NOT convLP
        $listResponse = $this->actingAs($agent)->getJson('/api/conversations');
        $listResponse->assertOk();
        $ids = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertContains($convCbba->id, $ids);
        $this->assertNotContains($convLP->id, $ids);

        // Agent viewing convLP directly gets 403
        $this->actingAs($agent)->getJson("/api/conversations/{$convLP->id}")->assertForbidden();

        // Agent viewing convCbba succeeds
        $this->actingAs($agent)->getJson("/api/conversations/{$convCbba->id}")->assertOk();

        // Admin sees both
        $adminList = $this->actingAs($admin)->getJson('/api/conversations');
        $adminIds = collect($adminList->json('data'))->pluck('id')->all();
        $this->assertContains($convCbba->id, $adminIds);
        $this->assertContains($convLP->id, $adminIds);
    }

    public function test_viewing_conversation_or_sending_outbound_resets_unread_count(): void
    {
        [$user, $organization] = $this->createMembership();

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '+59172227760',
        ]);

        $conv = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 3,
        ]);

        // Al consultar /api/conversations/{id}, unread_count se reinicia a 0
        $showRes = $this->actingAs($user)->getJson("/api/conversations/{$conv->id}");
        $showRes->assertOk();

        $this->assertDatabaseHas('conversations', [
            'id' => $conv->id,
            'unread_count' => 0,
        ]);

        // Simular que llegan mensajes nuevos (unread_count = 2)
        $conv->update(['unread_count' => 2]);

        // Al llamar a /read se reinicia a 0
        $readRes = $this->actingAs($user)->postJson("/api/conversations/{$conv->id}/read");
        $readRes->assertOk();
        $readRes->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('conversations', [
            'id' => $conv->id,
            'unread_count' => 0,
        ]);
    }

    public function test_conversation_and_message_resource_exposes_origin_referral_and_metadata(): void
    {
        [$user, $organization] = $this->createMembership();

        $referral = [
            'source_type' => 'ad',
            'source_id' => 'ad_meta_123',
            'source_url' => 'https://fb.me/campana_demo',
            'headline' => 'Campaña Meta Ads Test',
            'body' => 'Texto del anuncio publicitario',
            'media_type' => 'image',
            'image_url' => 'https://cdn.example.com/ad.jpg',
        ];

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'channel' => 'whatsapp',
            'origin_referral' => $referral,
            'metadata' => ['origin_referral' => $referral],
        ]);

        $message = \App\Modules\Conversations\Models\Message::create([
            'organization_id' => $organization->getKey(),
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'Hola, vengo del anuncio',
            'metadata' => ['referral' => $referral],
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/conversations/{$conversation->id}");
        $response->assertOk();
        $response->assertJsonPath('data.origin_referral.headline', 'Campaña Meta Ads Test');
        $response->assertJsonPath('data.origin_referral.source_type', 'ad');
        $response->assertJsonPath('data.messages.0.metadata.referral.source_id', 'ad_meta_123');

        $listResponse = $this->actingAs($user)->getJson('/api/conversations');
        $listResponse->assertOk();
        $listResponse->assertJsonPath('data.0.origin_referral.headline', 'Campaña Meta Ads Test');
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
