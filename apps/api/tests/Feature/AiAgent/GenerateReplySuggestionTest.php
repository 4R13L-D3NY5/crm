<?php

namespace Tests\Feature\AiAgent;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateReplySuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_reply_suggestion_and_run_is_audited(): void
    {
        [$user, $organization] = $this->createMembership();

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Message::factory()->create([
            'organization_id' => $organization->getKey(),
            'conversation_id' => $conversation->getKey(),
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_status' => 'received',
            'body' => 'Hola, necesito una demo del CRM.',
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->getKey()}/ai/reply-suggestion");

        $response
            ->assertOk()
            ->assertJsonPath('data.run_type', 'reply_suggestion');

        $this->assertDatabaseHas('ai_agent_runs', [
            'organization_id' => $organization->getKey(),
            'conversation_id' => $conversation->getKey(),
            'run_type' => 'reply_suggestion',
            'status' => 'completed',
        ]);
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
