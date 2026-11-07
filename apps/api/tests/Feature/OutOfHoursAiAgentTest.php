<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Parameters\Actions\DispatchOutOfHoursAutoReplyAction;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class OutOfHoursAiAgentTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected WhatsAppAccount $account;
    protected Contact $contact;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC Central',
            'slug' => 'unitepc-central',
        ]);

        $this->account = WhatsAppAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'Línea Admisiones',
            'phone_number_id' => '1234567890',
            'display_phone_number' => '+59144445555',
            'status' => 'connected',
            'session_type' => 'baileys',
            'verify_token' => 'verify_token_test',
        ]);

        $this->contact = Contact::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Mariana',
            'phone' => '+59178945612',
            'status' => 'active',
        ]);

        $this->conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);
    }

    public function test_out_of_hours_ai_agent_responds_when_knowledge_found_with_high_confidence(): void
    {
        // 1. Configurar horario en modo ausencia permanente ('always') y responder_type = 'ai_rag_agent'
        $config = BusinessHoursConfig::create([
            'organization_id' => $this->organization->id,
            'name' => 'Guardia Nocturna',
            'is_default' => true,
            'is_active' => true,
            'auto_reply_enabled' => true,
            'auto_reply_mode' => 'always',
            'responder_type' => 'ai_rag_agent',
            'ai_confidence_threshold' => 0.80,
            'auto_reply_cooldown_minutes' => 60,
        ]);

        // 2. Crear artículo en base de conocimiento
        $kb = \App\Modules\HentleAi\Models\AiKnowledgeBase::create([
            'organization_id' => $this->organization->id,
            'name' => 'Admisiones y Requisitos',
            'provider' => 'gemini',
        ]);

        AiKnowledgeChunk::create([
            'organization_id' => $this->organization->id,
            'knowledge_base_id' => $kb->id,
            'title' => 'Arancel Matrícula',
            'content' => 'El costo de la matrícula para Medicina es de 350 Bs en Cochabamba.',
        ]);

        // 3. Mensaje del cliente
        $inbound = Message::create([
            'organization_id' => $this->organization->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => '¿Cuánto cuesta la matrícula de Medicina?',
            'sent_at' => Carbon::now()->subMinute(),
        ]);

        // 4. Mockear el copiloto para devolver la respuesta RAG con 95% de confianza
        $mockCopilot = Mockery::mock(HentleAiCopilotService::class)->makePartial();
        $mockCopilot->shouldReceive('retrieveKnowledgeContext')
            ->once()
            ->andReturn([
                'context_text' => 'El costo de la matrícula para Medicina es de 350 Bs en Cochabamba.',
                'sources' => [['type' => 'knowledge_chunk', 'id' => '1', 'title' => 'Arancel Matrícula']],
                'has_knowledge' => true,
            ]);

        $mockCopilot->shouldReceive('generateReplySuggestion')
            ->once()
            ->andReturn([
                'suggestion' => '¡Hola Mariana! El costo de la matrícula para la carrera de Medicina en la sede Cochabamba es de 350 Bs. Mañana un asesor humano podrá orientarte con más detalles si lo requieres.',
                'confidence' => 0.95,
                'used_knowledge' => true,
            ]);

        $this->app->instance(HentleAiCopilotService::class, $mockCopilot);

        // 5. Ejecutar la acción
        $action = app(DispatchOutOfHoursAutoReplyAction::class);
        $result = $action->execute($this->conversation);

        $this->assertNotNull($result);
        $this->assertEquals('outbound', $result->direction);
        $this->assertStringContainsString('350 Bs', $result->body);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'body' => $result->body,
        ]);
    }

    public function test_out_of_hours_ai_agent_triggers_fallback_and_escalation_when_no_knowledge(): void
    {
        $supervisorPhone = '+59170011223';

        // 1. Configurar Guardia IA con teléfono de escalación
        $config = BusinessHoursConfig::create([
            'organization_id' => $this->organization->id,
            'name' => 'Guardia Nocturna',
            'is_default' => true,
            'is_active' => true,
            'auto_reply_enabled' => true,
            'auto_reply_mode' => 'always',
            'responder_type' => 'ai_rag_agent',
            'ai_confidence_threshold' => 0.80,
            'ai_escalation_enabled' => true,
            'ai_escalation_phone' => $supervisorPhone,
            'ai_fallback_message' => 'Estimado cliente, no tenemos esa información registrada en este momento. Te llamaremos en la mañana.',
            'auto_reply_cooldown_minutes' => 60,
        ]);

        // 2. Mensaje sobre tema no existente en el manual
        $inbound = Message::create([
            'organization_id' => $this->organization->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => '¿Tienen convenio con la NASA para pasantías espaciales?',
            'sent_at' => Carbon::now()->subMinute(),
        ]);

        // 3. Mockear el copiloto para indicar que NO hay conocimiento
        $mockCopilot = Mockery::mock(HentleAiCopilotService::class)->makePartial();
        $mockCopilot->shouldReceive('retrieveKnowledgeContext')
            ->once()
            ->andReturn([
                'context_text' => '',
                'sources' => [],
                'has_knowledge' => false,
            ]);

        $this->app->instance(HentleAiCopilotService::class, $mockCopilot);

        // 4. Ejecutar la acción
        $action = app(DispatchOutOfHoursAutoReplyAction::class);
        $result = $action->execute($this->conversation);

        // 5. Verificar que el cliente recibió el mensaje de cortesía seguro
        $this->assertNotNull($result);
        $this->assertEquals('Estimado cliente, no tenemos esa información registrada en este momento. Te llamaremos en la mañana.', $result->body);

        // 6. Verificar que se creó la notificación interna en el CRM
        $this->assertDatabaseHas('app_notifications', [
            'organization_id' => $this->organization->id,
            'type' => 'ai_escalation',
            'title' => '⚠️ Guardia IA: Consulta sin respuesta en Base de Conocimiento',
        ]);

        // 7. Verificar que se despachó el mensaje de WhatsApp al teléfono del supervisor
        $adminContact = Contact::where('organization_id', $this->organization->id)
            ->where('phone', $supervisorPhone)
            ->first();

        $this->assertNotNull($adminContact, 'Debe crearse o encontrarse el contacto del supervisor.');

        $this->assertDatabaseHas('messages', [
            'organization_id' => $this->organization->id,
            'direction' => 'outbound',
        ]);

        $escalationMsg = Message::where('direction', 'outbound')
            ->where('body', 'like', '%Alerta Guardia IA%')
            ->first();

        $this->assertNotNull($escalationMsg);
        $this->assertStringContainsString('Mariana', $escalationMsg->body);
        $this->assertStringContainsString('NASA', $escalationMsg->body);
    }
}
