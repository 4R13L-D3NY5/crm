<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notes\Models\Note;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HentleAiUnifiedRagTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieve_knowledge_context_finds_relevant_notes_and_chunks(): void
    {
        $org = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        // 1. Crear una nota de micro-conocimiento (Google Keep Style)
        Note::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'title' => 'Aranceles Medicina Cochabamba',
            'content' => 'El costo de la mensualidad para Medicina en sede Cochabamba es de Bs. 1.250 y la matrícula inicial es Bs. 450.',
            'color' => '#10b981',
            'shortcut' => 'medicina_cbba',
        ]);

        // 2. Crear un chunk en la Base de Conocimiento oficial
        $kb = AiKnowledgeBase::create([
            'organization_id' => $org->id,
            'name' => 'Admisiones y Requisitos',
            'provider' => 'gemini',
        ]);

        AiKnowledgeChunk::create([
            'organization_id' => $org->id,
            'knowledge_base_id' => $kb->id,
            'title' => 'Requisitos de Inscripción',
            'content' => 'Los requisitos son: fotocopia legalizada de Título de Bachiller, Certificado de Nacimiento y Cédula de Identidad vigente.',
        ]);

        /** @var HentleAiCopilotService $service */
        $service = app(HentleAiCopilotService::class);

        // Búsqueda orientada a medicina
        $result = $service->retrieveKnowledgeContext($org->id, 'Quiero saber cuánto cuesta la mensualidad de Medicina en Cochabamba');

        $this->assertTrue($result['has_knowledge']);
        $this->assertNotEmpty($result['sources']);
        $this->assertStringContainsString('Bs. 1.250', $result['context_text']);
        $this->assertStringContainsString('NOTA: Aranceles Medicina Cochabamba', $result['context_text']);

        // Búsqueda orientada a requisitos
        $resultReq = $service->retrieveKnowledgeContext($org->id, 'Cuáles son los requisitos de inscripción para entrar a la universidad');
        $this->assertTrue($resultReq['has_knowledge']);
        $this->assertStringContainsString('Título de Bachiller', $resultReq['context_text']);
        $this->assertStringContainsString('DOC: Requisitos de Inscripción', $resultReq['context_text']);
    }

    public function test_generate_reply_suggestion_uses_knowledge_and_returns_sources(): void
    {
        $org = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        WorkspaceSetting::create([
            'organization_id' => $org->id,
            'custom_options' => [
                'ai_config' => [
                    'provider' => 'minimax',
                    'api_key' => 'test-api-key-123',
                    'model' => 'MiniMax-Text-01',
                    'base_url' => 'https://api.minimax.chat/v1',
                ],
            ],
        ]);

        Note::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'title' => 'Aranceles Odontología',
            'content' => 'Odontología tiene una mensualidad de Bs. 980 en sede La Paz.',
            'color' => '#06b6d4',
            'shortcut' => 'odonto_lp',
        ]);

        $conversation = Conversation::create([
            'organization_id' => $org->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'subject' => 'Consulta Odontología',
            'last_message_at' => now(),
        ]);

        Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'Hola, cuánto cuesta la mensualidad de Odontología en La Paz?',
            'sent_at' => now(),
        ]);

        Http::fake([
            'https://api.minimax.chat/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '¡Hola! La mensualidad para la carrera de Odontología en la sede La Paz es de Bs. 980. ¿Deseas que te brinde los requisitos de inscripción?',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 150,
                    'completion_tokens' => 45,
                ],
            ], 200),
        ]);

        /** @var HentleAiCopilotService $service */
        $service = app(HentleAiCopilotService::class);
        $result = $service->generateReplySuggestion($conversation);

        $this->assertEquals(0.95, $result['confidence']);
        $this->assertTrue($result['used_knowledge']);
        $this->assertNotEmpty($result['sources']);
        $this->assertStringContainsString('Bs. 980', $result['suggestion']);
        $this->assertEquals('note', $result['sources'][0]['type']);
    }
}
