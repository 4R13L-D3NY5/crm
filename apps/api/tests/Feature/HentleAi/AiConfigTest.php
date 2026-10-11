<?php

namespace Tests\Feature\HentleAi;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_and_update_ai_config_for_minimax(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        // 1. Obtener configuración por defecto
        $getResponse = $this->actingAs($user)->getJson('/api/ai/config');
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.provider', 'minimax')
            ->assertJsonPath('data.model', 'MiniMax-Text-01');

        // 2. Actualizar a MiniMax con API Key real
        $updatePayload = [
            'provider' => 'minimax',
            'model' => 'MiniMax-Text-01',
            'api_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.testkey',
            'base_url' => 'https://api.minimax.chat/v1',
            'system_prompt' => 'Eres el asistente oficial de UNITEPC.',
            'similarity_threshold' => 0.8,
        ];

        $updateResponse = $this->actingAs($user)->putJson('/api/ai/config', $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.provider', 'minimax')
            ->assertJsonPath('data.has_api_key', true);

        // 3. Volver a leer para asegurar que la key viene enmascarada
        $recheck = $this->actingAs($user)->getJson('/api/ai/config');
        $recheck->assertStatus(200)
            ->assertJsonPath('data.has_api_key', true);
        $this->assertStringContainsString('••••••••', $recheck->json('data.api_key_masked'));
    }

    public function test_can_test_ai_connection_with_mocked_minimax_response(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        Http::fake([
            'https://api.minimax.chat/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-123',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'MiniMax-Text-01',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '¡Hola! La conexión con MiniMax-Text-01 está funcionando correctamente.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $testResponse = $this->actingAs($user)->postJson('/api/ai/test', [
            'provider' => 'minimax',
            'model' => 'MiniMax-Text-01',
            'api_key' => 'test-minimax-key-12345',
            'base_url' => 'https://api.minimax.chat/v1',
        ]);

        $testResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('model', 'MiniMax-Text-01')
            ->assertJsonPath('reply', '¡Hola! La conexión con MiniMax-Text-01 está funcionando correctamente.');
    }

    public function test_copilot_uses_configured_minimax_provider(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $this->actingAs($user)->putJson('/api/ai/config', [
            'provider' => 'minimax',
            'model' => 'MiniMax-Text-01',
            'api_key' => 'test-minimax-key',
            'base_url' => 'https://api.minimax.chat/v1',
        ]);

        $ticket = Conversation::create([
            'organization_id' => $organization->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $ticket->id,
            'direction' => 'inbound',
            'body' => '¿Tienen la carrera de Sistemas?',
            'sent_at' => Carbon::now(),
        ]);

        Http::fake([
            'https://api.minimax.chat/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '¡Hola! Sí, contamos con la carrera de Ingeniería de Sistemas en varias sedes de UNITEPC.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/ai/copilot/suggest");
        $response->assertStatus(200)
            ->assertJsonPath('data.source', 'hentle_ai_minimax')
            ->assertJsonPath('data.suggestion', '¡Hola! Sí, contamos con la carrera de Ingeniería de Sistemas en varias sedes de UNITEPC.');
    }
}
