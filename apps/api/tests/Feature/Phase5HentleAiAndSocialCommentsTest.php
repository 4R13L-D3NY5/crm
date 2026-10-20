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

    public function test_instagram_raw_webhook_creates_instagram_ticket(): void
    {
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $account = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Instagram Oficial',
            'session_type' => 'instagram',
            'phone_number_id' => '17841400998877',
            'verify_token' => 'meta_token_ig',
            'is_active' => true,
        ]);

        $rawMetaPayload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => '17841400998877',
                    'time' => 1712000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'ig_customer_456'],
                            'recipient' => ['id' => '17841400998877'],
                            'message' => [
                                'mid' => 'mid.ig.67890',
                                'text' => 'Hola, ¿cuánto cuesta el diplomado?',
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
            'channel' => 'instagram',
        ]);
        $this->assertDatabaseHas('messages', [
            'body' => 'Hola, ¿cuánto cuesta el diplomado?',
            'direction' => 'inbound',
        ]);
    }

    public function test_instagram_sync_returns_404_when_no_account_configured(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/social/instagram/sync');
        $response->assertStatus(404);
    }

    public function test_instagram_sync_handles_meta_gracefully_and_marks_account_connected(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Instagram UNITEPC',
            'session_type' => 'instagram',
            'phone_number_id' => '17841410149608154',
            'business_account_id' => '187805724574229',
            'access_token' => 'dummy_token_123',
            'verify_token' => 'dummy_verify_token',
            'status' => 'PENDING',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->postJson('/api/social/instagram/sync');
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'imported']);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'organization_id' => $organization->id,
            'session_type' => 'instagram',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);
    }

    public function test_operator_with_assigned_channels_only_sees_their_channels(): void
    {
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $juanPablo = User::factory()->create(['name' => 'Juan Pablo']);
        $juanPablo->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $juanPablo->update(['current_organization_id' => $organization->id]);

        // Canal 1: Fanpage La Paz
        $pageLaPaz = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Fanpage La Paz',
            'session_type' => 'facebook',
            'phone_number_id' => 'page_lp_100',
            'access_token' => 'token_lp',
            'verify_token' => 'token_lp_verify',
            'is_active' => true,
        ]);

        // Canal 2: Escuela De Postgrado (sin operadores asignados)
        $pagePostgrado = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Escuela De Postgrado',
            'session_type' => 'facebook',
            'phone_number_id' => 'page_pg_200',
            'access_token' => 'token_pg',
            'verify_token' => 'token_pg_verify',
            'is_active' => true,
        ]);

        // Asignar Juan Pablo exclusivamente a Fanpage La Paz
        $pageLaPaz->authorizedUsers()->attach($juanPablo->id, [
            'id' => (string) Str::ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        // Contactos y Conversaciones en cada canal
        $contactLp = \App\Modules\Contacts\Models\Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Estudiante La Paz',
            'status' => 'active',
        ]);
        $convLp = \App\Modules\Conversations\Models\Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contactLp->id,
            'whatsapp_account_id' => $pageLaPaz->id,
            'channel' => 'facebook',
            'status' => 'pending',
            'last_message_at' => now(),
        ]);

        $contactPg = \App\Modules\Contacts\Models\Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Pedro Antonio Chura (Postgrado)',
            'status' => 'active',
        ]);
        $convPg = \App\Modules\Conversations\Models\Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contactPg->id,
            'whatsapp_account_id' => $pagePostgrado->id,
            'channel' => 'facebook',
            'status' => 'pending',
            'last_message_at' => now(),
        ]);

        $query = new \App\Modules\Conversations\Queries\ListConversationsQuery();
        $results = $query->execute($juanPablo, []);

        $ids = collect($results->items())->pluck('id')->all();

        // Juan Pablo debe ver la conversación de La Paz
        $this->assertContains($convLp->id, $ids);
        // Juan Pablo NO debe ver la conversación de Postgrado
        $this->assertNotContains($convPg->id, $ids);
    }
}
