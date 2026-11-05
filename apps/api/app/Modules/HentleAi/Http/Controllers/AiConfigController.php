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

        $spendingLimit = (float) ($aiConfig['spending_limit_usd'] ?? 0.20);
        $currentSpending = (float) ($aiConfig['current_spending_usd'] ?? 0.0);
        $budgetEnabled = (bool) ($aiConfig['budget_enabled'] ?? false);

        return response()->json([
            'data' => [
                'provider' => $aiConfig['provider'] ?? 'minimax',
                'model' => $aiConfig['model'] ?? 'MiniMax-Text-01',
                'base_url' => $aiConfig['base_url'] ?? 'https://api.minimax.io/v1',
                'has_api_key' => $hasKey,
                'api_key_masked' => $maskedKey,
                'system_prompt' => $aiConfig['system_prompt'] ?? 'Eres el Copiloto de Inteligencia Artificial "Hentle-AI" para UNITEPC. Tu objetivo es asistir a los agentes sugiriendo respuestas empáticas, profesionales y precisas para WhatsApp.',
                'similarity_threshold' => (float) ($aiConfig['similarity_threshold'] ?? 0.75),
                'budget_enabled' => $budgetEnabled,
                'spending_limit_usd' => $spendingLimit,
                'current_spending_usd' => $currentSpending,
                'total_tokens_used' => (int) ($aiConfig['total_tokens_used'] ?? 0),
                'total_requests_count' => (int) ($aiConfig['total_requests_count'] ?? 0),
                'is_budget_exceeded' => $budgetEnabled && ($currentSpending >= $spendingLimit),
                'smart_reply_enabled' => (bool) ($aiConfig['smart_reply_enabled'] ?? false),
                'smart_reply_delay_seconds' => (int) ($aiConfig['smart_reply_delay_seconds'] ?? 15),
                'smart_reply_confidence_threshold' => (float) ($aiConfig['smart_reply_confidence_threshold'] ?? 0.85),
                'auto_tag_recommendation_enabled' => (bool) ($aiConfig['auto_tag_recommendation_enabled'] ?? true),
                'entity_extraction_enabled' => (bool) ($aiConfig['entity_extraction_enabled'] ?? true),
                'sentiment_radar_enabled' => (bool) ($aiConfig['sentiment_radar_enabled'] ?? true),
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
            'budget_enabled' => ['nullable', 'boolean'],
            'spending_limit_usd' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'smart_reply_enabled' => ['nullable', 'boolean'],
            'smart_reply_delay_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'smart_reply_confidence_threshold' => ['nullable', 'numeric', 'min:0.5', 'max:1'],
            'auto_tag_recommendation_enabled' => ['nullable', 'boolean'],
            'entity_extraction_enabled' => ['nullable', 'boolean'],
            'sentiment_radar_enabled' => ['nullable', 'boolean'],
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
            'minimax' => 'https://api.minimax.io/v1',
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
            'budget_enabled' => isset($validated['budget_enabled']) ? (bool) $validated['budget_enabled'] : (bool) ($currentAiConfig['budget_enabled'] ?? false),
            'spending_limit_usd' => isset($validated['spending_limit_usd']) ? (float) $validated['spending_limit_usd'] : (float) ($currentAiConfig['spending_limit_usd'] ?? 0.20),
            'current_spending_usd' => (float) ($currentAiConfig['current_spending_usd'] ?? 0.0),
            'total_tokens_used' => (int) ($currentAiConfig['total_tokens_used'] ?? 0),
            'total_requests_count' => (int) ($currentAiConfig['total_requests_count'] ?? 0),
            'smart_reply_enabled' => isset($validated['smart_reply_enabled']) ? (bool) $validated['smart_reply_enabled'] : (bool) ($currentAiConfig['smart_reply_enabled'] ?? false),
            'smart_reply_delay_seconds' => isset($validated['smart_reply_delay_seconds']) ? (int) $validated['smart_reply_delay_seconds'] : (int) ($currentAiConfig['smart_reply_delay_seconds'] ?? 15),
            'smart_reply_confidence_threshold' => isset($validated['smart_reply_confidence_threshold']) ? (float) $validated['smart_reply_confidence_threshold'] : (float) ($currentAiConfig['smart_reply_confidence_threshold'] ?? 0.85),
            'auto_tag_recommendation_enabled' => isset($validated['auto_tag_recommendation_enabled']) ? (bool) $validated['auto_tag_recommendation_enabled'] : (bool) ($currentAiConfig['auto_tag_recommendation_enabled'] ?? true),
            'entity_extraction_enabled' => isset($validated['entity_extraction_enabled']) ? (bool) $validated['entity_extraction_enabled'] : (bool) ($currentAiConfig['entity_extraction_enabled'] ?? true),
            'sentiment_radar_enabled' => isset($validated['sentiment_radar_enabled']) ? (bool) $validated['sentiment_radar_enabled'] : (bool) ($currentAiConfig['sentiment_radar_enabled'] ?? true),
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
                'budget_enabled' => $newAiConfig['budget_enabled'],
                'spending_limit_usd' => $newAiConfig['spending_limit_usd'],
                'current_spending_usd' => $newAiConfig['current_spending_usd'],
                'total_tokens_used' => $newAiConfig['total_tokens_used'],
                'total_requests_count' => $newAiConfig['total_requests_count'],
                'is_budget_exceeded' => $newAiConfig['budget_enabled'] && ($newAiConfig['current_spending_usd'] >= $newAiConfig['spending_limit_usd']),
                'smart_reply_enabled' => $newAiConfig['smart_reply_enabled'],
                'smart_reply_delay_seconds' => $newAiConfig['smart_reply_delay_seconds'],
                'smart_reply_confidence_threshold' => $newAiConfig['smart_reply_confidence_threshold'],
                'auto_tag_recommendation_enabled' => $newAiConfig['auto_tag_recommendation_enabled'],
                'entity_extraction_enabled' => $newAiConfig['entity_extraction_enabled'],
                'sentiment_radar_enabled' => $newAiConfig['sentiment_radar_enabled'],
            ],
        ]);
    }

    public function resetBudget(Request $request): JsonResponse
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

        $aiConfig['current_spending_usd'] = 0.0;
        $aiConfig['total_tokens_used'] = 0;
        $aiConfig['total_requests_count'] = 0;

        $custom['ai_config'] = $aiConfig;
        $setting->custom_options = $custom;
        $setting->save();

        return response()->json([
            'message' => 'Contador de consumo de IA reiniciado exitosamente a $0.00 USD.',
            'data' => [
                'current_spending_usd' => 0.0,
                'total_tokens_used' => 0,
                'total_requests_count' => 0,
            ],
        ]);
    }

    public function test(Request $request, HentleAiCopilotService $copilotService): JsonResponse
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

            // Auto-guardar la configuración verificada si el test fue exitoso
            $custom = $setting->custom_options ?? [];
            $custom['ai_config'] = [
                'provider' => $provider,
                'model' => $model,
                'api_key' => $apiKey,
                'base_url' => $baseUrl,
                'system_prompt' => $storedAiConfig['system_prompt'] ?? 'Eres el Copiloto de Inteligencia Artificial para UNITEPC. Tu objetivo es asistir a los agentes sugiriendo respuestas empáticas, profesionales y precisas para WhatsApp.',
                'similarity_threshold' => (float) ($storedAiConfig['similarity_threshold'] ?? 0.75),
            ];
            $setting->custom_options = $custom;
            $setting->save();

            return response()->json([
                'status' => 'success',
                'message' => "¡Conexión exitosa con {$provider}! (Guardada automáticamente)",
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
