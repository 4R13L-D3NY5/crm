<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5HentleAiAndSocialCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_copilot_generates_reply_suggestion_and_summary(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Sofia',
            'phone' => '+59178901234',
            'status' => 'active',
        ]);

        $ticket = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $ticket->id,
            'direction' => 'inbound',
            'body' => '¿Tienen la carrera de Medicina en Cochabamba?',
            'sent_at' => Carbon::now(),
        ]);

        // 1. Sugerencia del copiloto
        $suggestResponse = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/ai/suggest");
        $suggestResponse->assertStatus(200)
            ->assertJsonStructure(['data' => ['suggested_text', 'sources_count', 'confidence_score']]);

        // 2. Resumen ejecutivo del chat
        $summaryResponse = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/ai/summary");
        $summaryResponse->assertStatus(200)
            ->assertJsonStructure(['data' => ['summary_text', 'total_messages_analyzed']]);
    }

    public function test_social_comment_webhook_creates_contact_and_dm_ticket(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $webhookPayload = [
            'organization_id' => $organization->id,
            'platform' => 'facebook',
            'post_id' => 'fb_post_999888',
            'comment_id' => 'fb_comment_111222',
            'author_name' => 'Alejandro Morales',
            'comment_text' => '¿Cuándo empiezan las clases del preuniversitario?',
            'auto_reply_message' => '¡Hola Alejandro! Te enviamos un mensaje privado con todos los detalles.',
            'open_dm_ticket' => true,
        ];

        $response = $this->postJson('/api/social/comments/webhook', $webhookPayload);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'facebook')
            ->assertJsonPath('data.ticket_created', true);

        // Verificar que se creó el contacto
        $this->assertDatabaseHas('contacts', [
            'organization_id' => $organization->id,
            'first_name' => 'Alejandro Morales',
        ]);

        // Verificar que se abrió el ticket DM en la bandeja
        $this->assertDatabaseHas('conversations', [
            'organization_id' => $organization->id,
            'channel' => 'facebook',
        ]);
    }

    public function test_meta_webhook_verification_returns_challenge(): void
    {
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $account = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Fanpage Oficial',
            'session_type' => 'facebook',
            'phone_number_id' => 'fb_page_100200',
            'verify_token' => 'meta_test_token_123',
            'is_active' => true,
        ]);

        $response = $this->get('/api/social/comments/webhook?hub_mode=subscribe&hub_verify_token=meta_test_token_123&hub_challenge=88997766');

        $response->assertStatus(200);
        $this->assertSame('88997766', $response->getContent());
    }

    public function test_meta_messenger_raw_webhook_creates_ticket(): void
    {
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $account = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Fanpage Oficial',
            'session_type' => 'facebook',
            'phone_number_id' => 'fb_page_100200',
            'verify_token' => 'meta_token_fb',
            'is_active' => true,
        ]);

        $rawMetaPayload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'fb_page_100200',
                    'time' => 1712000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'fb_user_998877'],
                            'recipient' => ['id' => 'fb_page_100200'],
                            'message' => [
                                'mid' => 'mid.fb.12345',
                                'text' => 'Hola, quiero consultar por los cursos disponibles.',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/social/comments/webhook', $rawMetaPayload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversations', [
            'organization_id' => $organization->id,
            'channel' => 'facebook',
        ]);
        $this->assertDatabaseHas('messages', [
            'body' => 'Hola, quiero consultar por los cursos disponibles.',
        ]);
    }
}
