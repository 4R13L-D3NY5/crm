<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Jobs\ProcessSmartAiReplyJob;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notes\Models\Note;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\Tenancy\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class HentleAiSmartAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Contact $contact;
    protected Conversation $conversation;
    protected Message $inboundMessage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'UNITEPC Smart AI Org',
            'slug' => 'unitepc-smart-ai-' . uniqid(),
        ]);

        // Enable Smart Reply in WorkspaceSetting
        WorkspaceSetting::create([
            'organization_id' => $this->org->id,
            'default_language' => 'es',
            'timezone' => 'America/La_Paz',
            'custom_options' => [
                'ai_config' => [
                    'provider' => 'minimax',
                    'model' => 'MiniMax-Text-01',
                    'api_key' => 'mock-api-key',
                    'smart_reply_enabled' => true,
                    'smart_reply_delay_seconds' => 15,
                    'smart_reply_confidence_threshold' => 0.85,
                ],
            ],
        ]);

        $this->contact = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
            'phone' => '+59178945612',
            'status' => 'lead',
        ]);

        $this->conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'contact_id' => $this->contact->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'last_message_at' => now(),
        ]);

        $this->inboundMessage = Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola, cuánto cuesta la carrera de Medicina?',
            'sent_at' => now(),
        ]);
    }

    public function test_smart_reply_sends_auto_reply_when_all_conditions_met(): void
    {
        // Mock HentleAiCopilotService to return high-confidence answer
        $mockService = Mockery::mock(HentleAiCopilotService::class);
        $mockService->shouldReceive('generateReplySuggestion')
            ->once()
            ->andReturn([
                'suggestion' => 'El arancel de Medicina es de 1.850 Bs mensuales en la Sede Cochabamba.',
                'confidence' => 0.95,
                'source' => 'hentle_ai_minimax',
                'sources' => ['Nota: Aranceles Medicina'],
                'used_knowledge' => true,
            ]);

        $this->app->instance(HentleAiCopilotService::class, $mockService);

        $job = new ProcessSmartAiReplyJob($this->conversation->id, $this->inboundMessage->id);
        $job->handle($mockService);

        // Verify that an outbound message was created
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'body' => 'El arancel de Medicina es de 1.850 Bs mensuales en la Sede Cochabamba.',
        ]);
    }

    public function test_smart_reply_aborts_if_advisor_assigned(): void
    {
        $user = User::factory()->create();
        $this->conversation->update([
            'assigned_to_user_id' => $user->id,
            'status' => 'open',
        ]);

        $mockService = Mockery::mock(HentleAiCopilotService::class);
        $mockService->shouldNotReceive('generateReplySuggestion');

        $job = new ProcessSmartAiReplyJob($this->conversation->id, $this->inboundMessage->id);
        $job->handle($mockService);

        // No outbound message created
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
        ]);
    }

    public function test_smart_reply_aborts_if_human_already_replied(): void
    {
        // Simulate human advisor replying after the inbound message
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => 'Hola Carlos, con gusto te ayudo.',
            'sent_at' => now(),
        ]);

        $mockService = Mockery::mock(HentleAiCopilotService::class);
        $mockService->shouldNotReceive('generateReplySuggestion');

        $job = new ProcessSmartAiReplyJob($this->conversation->id, $this->inboundMessage->id);
        $job->handle($mockService);

        // Only the human message exists
        $this->assertEquals(1, Message::where('conversation_id', $this->conversation->id)->where('direction', 'outbound')->count());
    }

    public function test_smart_reply_aborts_if_confidence_below_threshold(): void
    {
        $mockService = Mockery::mock(HentleAiCopilotService::class);
        $mockService->shouldReceive('generateReplySuggestion')
            ->once()
            ->andReturn([
                'suggestion' => 'Podría ser...',
                'confidence' => 0.60, // Below 0.85 threshold
                'source' => 'hentle_ai_minimax',
                'sources' => [],
                'used_knowledge' => false,
            ]);

        $this->app->instance(HentleAiCopilotService::class, $mockService);

        $job = new ProcessSmartAiReplyJob($this->conversation->id, $this->inboundMessage->id);
        $job->handle($mockService);

        // No outbound auto-reply sent
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
        ]);
    }

    public function test_smart_reply_aborts_if_feature_disabled_for_org(): void
    {
        // Disable smart reply in settings
        $setting = WorkspaceSetting::where('organization_id', $this->org->id)->first();
        $custom = $setting->custom_options;
        $custom['ai_config']['smart_reply_enabled'] = false;
        $setting->update(['custom_options' => $custom]);

        $mockService = Mockery::mock(HentleAiCopilotService::class);
        $mockService->shouldNotReceive('generateReplySuggestion');

        $job = new ProcessSmartAiReplyJob($this->conversation->id, $this->inboundMessage->id);
        $job->handle($mockService);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
        ]);
    }
}
