<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $setting = WorkspaceSetting::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'default_language' => 'es',
                'timezone' => 'America/La_Paz',
                'sla_timeout_minutes' => 10,
            ]
        );

        $custom = $setting->custom_options ?? [];
        $aiConfig = $custom['ai_config'] ?? [];

        $apiKey = $aiConfig['api_key'] ?? env('AI_API_KEY') ?? env('MINIMAX_API_KEY') ?? env('GEMINI_API_KEY') ?? env('OPENAI_API_KEY') ?? '';
        $hasKey = !empty($apiKey);
        $maskedKey = $hasKey ? (strlen($apiKey) > 8 ? substr($apiKey, 0, 4) . '••••••••' . substr($apiKey, -4) : '••••••••') : '';

        return response()->json([
            'data' => [
                'provider' => $aiConfig['provider'] ?? 'minimax',
                'model' => $aiConfig['model'] ?? 'MiniMax-Text-01',
                'base_url' => $aiConfig['base_url'] ?? 'https://api.minimax.chat/v1',
                'has_api_key' => $hasKey,
                'api_key_masked' => $maskedKey,
                'system_prompt' => $aiConfig['system_prompt'] ?? 'Eres el Copiloto de Inteligencia Artificial "Hentle-AI" para UNITEPC. Tu objetivo es asistir a los agentes sugiriendo respuestas empáticas, profesionales y precisas para WhatsApp.',
                'similarity_threshold' => (float) ($aiConfig['similarity_threshold'] ?? 0.75),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:minimax,gemini,openai,deepseek,custom'],
            'model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'system_prompt' => ['nullable', 'string', 'max:3000'],
            'similarity_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $organization = $request->user()->currentOrganization;
        $setting = WorkspaceSetting::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'default_language' => 'es',
                'timezone' => 'America/La_Paz',
                'sla_timeout_minutes' => 10,
            ]
        );

        $custom = $setting->custom_options ?? [];
        $currentAiConfig = $custom['ai_config'] ?? [];

        $apiKeyToSave = $currentAiConfig['api_key'] ?? null;
        if (!empty($validated['api_key']) && !str_contains($validated['api_key'], '••••')) {
            $apiKeyToSave = trim($validated['api_key']);
        }

        $defaultBaseUrls = [
            'minimax' => 'https://api.minimax.chat/v1',
            'openai' => 'https://api.openai.com/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'custom' => $validated['base_url'] ?? '',
        ];

        $baseUrl = !empty($validated['base_url']) ? rtrim($validated['base_url'], '/') : ($defaultBaseUrls[$validated['provider']] ?? '');

        $newAiConfig = [
            'provider' => $validated['provider'],
            'model' => trim($validated['model']),
            'api_key' => $apiKeyToSave,
            'base_url' => $baseUrl,
            'system_prompt' => $validated['system_prompt'] ?? $currentAiConfig['system_prompt'] ?? '',
            'similarity_threshold' => (float) ($validated['similarity_threshold'] ?? 0.75),
        ];

        $custom['ai_config'] = $newAiConfig;
        $setting->custom_options = $custom;
        $setting->save();

        $hasKey = !empty($apiKeyToSave);
        $maskedKey = $hasKey ? (strlen($apiKeyToSave) > 8 ? substr($apiKeyToSave, 0, 4) . '••••••••' . substr($apiKeyToSave, -4) : '••••••••') : '';

        return response()->json([
            'message' => 'Configuración de Inteligencia Artificial guardada exitosamente.',
            'data' => [
                'provider' => $newAiConfig['provider'],
                'model' => $newAiConfig['model'],
                'base_url' => $newAiConfig['base_url'],
                'has_api_key' => $hasKey,
                'api_key_masked' => $maskedKey,
                'system_prompt' => $newAiConfig['system_prompt'],
                'similarity_threshold' => $newAiConfig['similarity_threshold'],
            ],
        ]);
    }

    public function test(Request $request, HentleAiCopilotService $copilotService): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $setting = WorkspaceSetting::where('organization_id', $organization->id)->first();
        $storedAiConfig = $setting?->custom_options['ai_config'] ?? [];

        $provider = $request->input('provider') ?? $storedAiConfig['provider'] ?? 'minimax';
        $model = $request->input('model') ?? $storedAiConfig['model'] ?? 'MiniMax-Text-01';
        $baseUrl = $request->input('base_url') ?? $storedAiConfig['base_url'] ?? '';

        $inputKey = $request->input('api_key');
        if (!empty($inputKey) && !str_contains($inputKey, '••••')) {
            $apiKey = trim($inputKey);
        } else {
            $apiKey = $storedAiConfig['api_key'] ?? env('AI_API_KEY') ?? env('MINIMAX_API_KEY') ?? env('GEMINI_API_KEY') ?? env('OPENAI_API_KEY') ?? '';
        }

        if (empty($apiKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Por favor introduce una API Key válida para probar la conexión.',
            ], 422);
        }

        $startTime = microtime(true);

        try {
            $reply = $copilotService->sendDirectPrompt(
                prompt: "Por favor responde en una sola frase corta confirmando que la conexión está funcionando y tu nombre de modelo.",
                provider: $provider,
                apiKey: $apiKey,
                model: $model,
                baseUrl: $baseUrl
            );

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status' => 'success',
                'message' => "¡Conexión exitosa con {$provider}!",
                'latency_ms' => $latencyMs,
                'provider' => $provider,
                'model' => $model,
                'reply' => $reply,
            ]);
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::warning("Fallo prueba de conexión IA ({$provider}): " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => "Error al conectar con {$provider}: " . $e->getMessage(),
                'latency_ms' => $latencyMs,
            ], 422);
        }
    }
}
