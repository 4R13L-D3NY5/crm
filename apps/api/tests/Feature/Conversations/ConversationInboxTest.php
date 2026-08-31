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
