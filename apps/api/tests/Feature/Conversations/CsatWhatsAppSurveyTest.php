<?php

namespace Tests\Feature\Conversations;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Services\CsatSurveyService;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CsatWhatsAppSurveyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private WhatsAppAccount $account;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SendWhatsAppMessageJob::class]);

        $this->organization = Organization::create([
            'name' => 'UNITEPC Central',
            'slug' => 'unitepc-central',
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
        ]);
        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);

        $this->account = WhatsAppAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Línea WhatsApp Principal',
            'phone_number' => '+59170011223',
            'phone_number_id' => '123456789',
            'waba_id' => 'waba_9999',
            'verify_token' => 'test_token_123',
            'status' => 'connected',
            'session_type' => 'baileys_qr',
        ]);

        $this->contact = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Carla',
            'last_name' => 'Rios',
            'phone' => '+59178899001',
            'status' => 'active',
        ]);
    }

    public function test_closing_ticket_automatically_dispatches_csat_survey_on_whatsapp(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/conversations/{$conversation->id}/close", [
            'send_survey' => true,
        ]);

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertSame('closed', $conversation->status);
        $this->assertNotNull($conversation->closed_at);
        $this->assertNotNull($conversation->metadata['csat_survey_sent_at'] ?? null);

        // Verifica que se haya creado el mensaje de la encuesta en la conversación
        $surveyMessage = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->whereJsonContains('metadata->is_csat_survey', true)
            ->first();

        $this->assertNotNull($surveyMessage);
        $this->assertStringContainsString('calificarías la atención', $surveyMessage->body);
        $this->assertStringContainsString('1 al 5', $surveyMessage->body);
    }

    public function test_closing_ticket_without_survey_flag_does_not_send_survey(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/conversations/{$conversation->id}/close", [
            'send_survey' => false,
        ]);

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertSame('closed', $conversation->status);
        $this->assertNull($conversation->metadata['csat_survey_sent_at'] ?? null);

        $count = Message::where('conversation_id', $conversation->id)->count();
        $this->assertSame(0, $count);
    }

    public function test_closing_ticket_with_manual_rating_does_not_send_survey(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/conversations/{$conversation->id}/close", [
            'rating' => 5,
            'feedback' => 'Excelente atención personalizada',
        ]);

        $response->assertStatus(200);

        $conversation->refresh();
        $this->assertSame('closed', $conversation->status);
        $this->assertSame(5, $conversation->rating);
        $this->assertSame('Excelente atención personalizada', $conversation->feedback);
        $this->assertNull($conversation->metadata['csat_survey_sent_at'] ?? null);
    }

    public function test_customer_replying_with_rating_updates_conversation_and_keeps_it_closed(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'closed',
            'closed_at' => now()->subMinutes(5),
            'metadata' => [
                'csat_survey_sent_at' => now()->subMinutes(5)->toISOString(),
            ],
        ]);

        $csatService = app(CsatSurveyService::class);
        $this->assertTrue($csatService->isEligibleForSurveyReply($conversation, '5'));

        // Simula la recepción de la calificación vía Baileys Webhook
        $response = $this->postJson('/api/v1/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $this->account->id,
            'from_phone' => '+59178899001',
            'from_name' => 'Carla Rios',
            'body' => '5 estrellas, excelente servicio!',
            'provider_message_id' => 'MSG_CSAT_REPLY_1',
            'from_me' => false,
        ]);

        $response->assertStatus(200);

        $conversation->refresh();
        // Verifica que la conversación sigue cerrada (no se reabrió como pendiente)
        $this->assertSame('closed', $conversation->status);
        $this->assertSame(5, $conversation->rating);
        $this->assertStringContainsString('excelente servicio', (string) $conversation->feedback);

        // Verifica que se haya enviado el mensaje de agradecimiento
        $thankYouMessage = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->whereJsonContains('metadata->is_csat_thank_you', true)
            ->first();

        $this->assertNotNull($thankYouMessage);
        $this->assertStringContainsString('Muchas gracias por tu calificación', $thankYouMessage->body);
    }

    public function test_customer_replying_with_unrelated_message_reopens_the_ticket(): void
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'closed',
            'closed_at' => now()->subMinutes(10),
            'metadata' => [
                'csat_survey_sent_at' => now()->subMinutes(10)->toISOString(),
            ],
        ]);

        $csatService = app(CsatSurveyService::class);
        $this->assertFalse($csatService->isEligibleForSurveyReply($conversation, 'Hola disculpe a qué hora abren mañana?'));

        // Simula la recepción del mensaje
        $response = $this->postJson('/api/v1/whatsapp/baileys/webhook', [
            'event' => 'message',
            'account_id' => $this->account->id,
            'from_phone' => '+59178899001',
            'from_name' => 'Carla Rios',
            'body' => 'Hola disculpe a qué hora abren mañana?',
            'provider_message_id' => 'MSG_NEW_QUERY_1',
            'from_me' => false,
        ]);

        $response->assertStatus(200);

        $conversation->refresh();
        // Se debe reabrir como pendiente para que un asesor atienda la nueva consulta
        $this->assertSame('pending', $conversation->status);
        $this->assertNull($conversation->closed_at);
        $this->assertNull($conversation->rating);
    }

    public function test_csat_report_returns_zeros_when_no_responses_exist(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/reports/csat');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_responses', 0);

        $this->assertEquals(0, $response->json('data.average_score'));
        $this->assertEquals(0, $response->json('data.satisfaction_percentage'));
    }
}
