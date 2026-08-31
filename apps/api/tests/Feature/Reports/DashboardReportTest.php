<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_dashboard_report_for_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();

        $pipeline = Pipeline::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);

        $stage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->getKey(),
            'name' => 'Calificacion',
            'position' => 1,
            'probability' => 20,
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);

        $openConversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'created_by_user_id' => $user->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
            'subject' => 'Lead WhatsApp',
            'last_message_at' => now()->subMinutes(5),
        ]);

        Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'created_by_user_id' => $user->getKey(),
            'channel' => 'manual',
            'status' => 'pending',
            'last_message_at' => now()->subMinutes(30),
        ]);

        Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'created_by_user_id' => $user->getKey(),
            'channel' => 'manual',
            'status' => 'resolved',
            'last_message_at' => now()->subHour(),
        ]);

        Message::factory()->create([
            'organization_id' => $organization->getKey(),
            'conversation_id' => $openConversation->getKey(),
            'user_id' => null,
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_status' => 'received',
            'body' => 'Hola, quiero informacion.',
        ]);

        Deal::factory()->create([
            'organization_id' => $organization->getKey(),
            'pipeline_id' => $pipeline->getKey(),
            'pipeline_stage_id' => $stage->getKey(),
            'name' => 'CRM Empresa Uno',
            'status' => 'open',
            'amount' => 1500,
            'probability' => 20,
        ]);

        Deal::factory()->create([
            'organization_id' => $organization->getKey(),
            'pipeline_id' => $pipeline->getKey(),
            'pipeline_stage_id' => $stage->getKey(),
            'name' => 'CRM Empresa Dos',
            'status' => 'won',
            'amount' => 3000,
            'probability' => 100,
        ]);

        $response = $this->actingAs($user)->getJson('/api/reports/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.open_conversations', 1)
            ->assertJsonPath('data.summary.pending_conversations', 1)
            ->assertJsonPath('data.summary.resolved_conversations', 1)
            ->assertJsonPath('data.summary.active_deals', 1)
            ->assertJsonPath('data.summary.won_deals', 1)
            ->assertJsonPath('data.summary.estimated_revenue', 1500)
            ->assertJsonPath('data.summary.contacts_total', 1)
            ->assertJsonPath('data.summary.inbound_messages_today', 1);
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
