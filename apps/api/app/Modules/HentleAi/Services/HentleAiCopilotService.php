<?php

namespace App\Modules\HentleAi\Services;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Notes\Models\Note;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HentleAiCopilotService
{
    public function __construct(
        protected VectorSearchService $vectorSearchService,
    ) {}

    /**
     * Obtiene la configuración de IA efectiva para la organización.
     */
    public function getEffectiveConfig(?string $organizationId = null): array
    {
        $setting = $organizationId ? WorkspaceSetting::where('organization_id', $organizationId)->first() : null;
        if (!$setting) {
            $setting = WorkspaceSetting::first();
        }
        $aiConfig = $setting?->custom_options['ai_config'] ?? [];

        $provider = $aiConfig['provider'] ?? env('AI_PROVIDER', 'minimax');
        $apiKey = $aiConfig['api_key'] ?? env('AI_API_KEY') ?? env('MINIMAX_API_KEY') ?? env('GEMINI_API_KEY') ?? env('OPENAI_API_KEY') ?? '';
        $model = $aiConfig['model'] ?? env('AI_MODEL', 'MiniMax-Text-01');
        $baseUrl = $aiConfig['base_url'] ?? '';
        $systemPrompt = $aiConfig['system_prompt'] ?? '';

        return [
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model,
            'base_url' => $baseUrl,
            'system_prompt' => $systemPrompt,
        ];
    }

    /**
     * Valida si el límite presupuestario configurado (en USD) no ha sido excedido.
     */
    public function assertBudgetAvailable(?string $organizationId = null): void
    {
        $setting = $organizationId ? WorkspaceSetting::where('organization_id', $organizationId)->first() : null;
        if (!$setting) {
            $setting = WorkspaceSetting::first();
        }
        if (!$setting) {
            return;
        }

        $aiConfig = $setting->custom_options['ai_config'] ?? [];
        $budgetEnabled = (bool) ($aiConfig['budget_enabled'] ?? false);
        $spendingLimit = (float) ($aiConfig['spending_limit_usd'] ?? 0.20);
        $currentSpent = (float) ($aiConfig['current_spending_usd'] ?? 0.0);

        if ($budgetEnabled && $currentSpent >= $spendingLimit) {
            $formattedLimit = number_format($spendingLimit, 2);
            $formattedSpent = number_format($currentSpent, 4);
            throw new \RuntimeException("Límite de consumo de IA alcanzado (\${$formattedSpent} / \${$formattedLimit} USD). El motor ha sido pausado automáticamente para evitar costos adicionales. Puedes aumentar el límite o reiniciar el contador en Configuración de IA.");
        }
    }

    /**
     * Registra el consumo de tokens y acumula el costo en USD según las tarifas de cada proveedor.
     */
    public function recordUsage(int $promptTokens, int $completionTokens, string $provider, ?string $organizationId = null): void
    {
        $setting = $organizationId ? WorkspaceSetting::where('organization_id', $organizationId)->first() : null;
        if (!$setting) {
            $setting = WorkspaceSetting::first();
        }
        if (!$setting) {
            return;
        }

        // Tarifas por millón de tokens en USD (MiniMax-Text-01, OpenAI GPT-4o-mini, Gemini, DeepSeek)
        $rates = [
            'minimax' => ['input' => 0.20, 'output' => 1.10],
            'openai' => ['input' => 0.15, 'output' => 0.60],
            'deepseek' => ['input' => 0.14, 'output' => 0.28],
            'gemini' => ['input' => 0.075, 'output' => 0.30],
            'custom' => ['input' => 0.20, 'output' => 1.00],
        ];

        $rate = $rates[$provider] ?? ['input' => 0.20, 'output' => 1.00];
        $costUsd = ($promptTokens * ($rate['input'] / 1_000_000)) + ($completionTokens * ($rate['output'] / 1_000_000));

        $custom = $setting->custom_options ?? [];
        $aiConfig = $custom['ai_config'] ?? [];

        $currentSpent = (float) ($aiConfig['current_spending_usd'] ?? 0.0);
        $totalTokens = (int) ($aiConfig['total_tokens_used'] ?? 0);
        $totalRequests = (int) ($aiConfig['total_requests_count'] ?? 0);

        $aiConfig['current_spending_usd'] = round($currentSpent + $costUsd, 6);
        $aiConfig['total_tokens_used'] = $totalTokens + $promptTokens + $completionTokens;
        $aiConfig['total_requests_count'] = $totalRequests + 1;

        $custom['ai_config'] = $aiConfig;
        $setting->custom_options = $custom;
        $setting->save();
    }

    /**
     * Envía un prompt directo a cualquier proveedor compatible (MiniMax, Gemini, OpenAI, DeepSeek, Custom).
     */
    public function sendDirectPrompt(
        string $prompt,
        string $provider = 'minimax',
        string $apiKey = '',
        string $model = 'MiniMax-Text-01',
        string $baseUrl = '',
        ?string $systemInstruction = null,
        ?string $organizationId = null,
        bool $bypassBudget = false,
    ): string {
        if (!$bypassBudget) {
            $this->assertBudgetAvailable($organizationId);
        }

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Se requiere una clave de API válida.');
        }

        if ($provider === 'gemini') {
            return $this->callGemini($prompt, $apiKey, $model, $systemInstruction, $organizationId);
        }

        return $this->callOpenAiCompatible($prompt, $provider, $apiKey, $model, $baseUrl, $systemInstruction, $organizationId);
    }

    protected function callOpenAiCompatible(
        string $prompt,
        string $provider,
        string $apiKey,
        string $model,
        string $baseUrl,
        ?string $systemInstruction = null,
        ?string $organizationId = null,
    ): string {
        $defaultUrls = [
            'minimax' => 'https://api.minimax.io/v1',
            'openai' => 'https://api.openai.com/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'custom' => 'https://api.minimax.chat/v1',
        ];

        $targetBase = !empty($baseUrl) ? rtrim($baseUrl, '/') : ($defaultUrls[$provider] ?? 'https://api.minimax.chat/v1');
        $endpoint = "{$targetBase}/chat/completions";

        $messages = [];
        if (!empty($systemInstruction)) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($endpoint, [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 500,
        ]);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errMessage = $errorBody['error']['message'] ?? $errorBody['message'] ?? $response->body();
            throw new \RuntimeException("Respuesta de API fallida (HTTP {$response->status()}): {$errMessage}");
        }

        $reply = $response->json('choices.0.message.content');
        if (empty($reply)) {
            throw new \RuntimeException('La API respondió sin contenido de texto.');
        }

        $usage = $response->json('usage') ?? [];
        $promptTokens = (int) ($usage['prompt_tokens'] ?? max(1, (int) (strlen($prompt) / 4)));
        $completionTokens = (int) ($usage['completion_tokens'] ?? max(1, (int) (strlen($reply) / 4)));

        $this->recordUsage($promptTokens, $completionTokens, $provider, $organizationId);

        return trim($reply);
    }

    protected function callGemini(
        string $prompt,
        string $apiKey,
        string $model,
        ?string $systemInstruction = null,
        ?string $organizationId = null,
    ): string {
        $cleanModel = !empty($model) ? $model : 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key={$apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => ($systemInstruction ? "{$systemInstruction}\n\n" : '') . $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 500,
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errMessage = $errorBody['error']['message'] ?? $response->body();
            throw new \RuntimeException("Error en Google Gemini (HTTP {$response->status()}): {$errMessage}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (empty($text)) {
            throw new \RuntimeException('Google Gemini no retornó candidatos de texto.');
        }

        $promptTokens = (int) $response->json('usageMetadata.promptTokenCount', max(1, (int) (strlen($prompt) / 4)));
        $completionTokens = (int) $response->json('usageMetadata.candidatesTokenCount', max(1, (int) (strlen($text) / 4)));

        $this->recordUsage($promptTokens, $completionTokens, 'gemini', $organizationId);

        return trim($text);
    }

    /**
     * Búsqueda híbrida unificada en Notas de la empresa y Chunks de Base de Conocimiento (RAG).
     */
    public function retrieveKnowledgeContext(string $organizationId, string $query, int $limit = 4): array
    {
        $sources = [];
        $contextSnippets = [];
        $cleanQuery = trim($query);

        if (empty($cleanQuery)) {
            return [
                'context_text' => '',
                'sources' => [],
                'has_knowledge' => false,
            ];
        }

        $isPgsql = DB::getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        // Extraer palabras clave de más de 3 letras
        $words = preg_split('/[\s,\.\?\!\¿\¡]+/u', mb_strtolower($cleanQuery), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3));

        // 1. Consultar Notas Rápidas / Micro-Conocimiento (Google Keep Style)
        $notes = Note::query()
            ->where('organization_id', $organizationId)
            ->where('is_archived', false)
            ->where(function ($q) use ($cleanQuery, $keywords, $like) {
                $q->where('title', $like, "%{$cleanQuery}%")
                    ->orWhere('content', $like, "%{$cleanQuery}%")
                    ->orWhere('shortcut', $like, "%{$cleanQuery}%");

                foreach ($keywords as $kw) {
                    $q->orWhere('title', $like, "%{$kw}%")
                        ->orWhere('content', $like, "%{$kw}%");
                }
            })
            ->take($limit)
            ->get();

        foreach ($notes as $note) {
            $contextSnippets[] = "--- [NOTA: {$note->title}] ---\n{$note->content}";
            $sources[] = [
                'type' => 'note',
                'id' => $note->id,
                'title' => $note->title,
                'snippet' => mb_substr($note->content, 0, 160),
            ];
        }

        // 2. Consultar Chunks de Base de Conocimiento (AiKnowledgeChunk)
        $chunks = AiKnowledgeChunk::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($cleanQuery, $keywords, $like) {
                $q->where('title', $like, "%{$cleanQuery}%")
                    ->orWhere('content', $like, "%{$cleanQuery}%");

                foreach ($keywords as $kw) {
                    $q->orWhere('title', $like, "%{$kw}%")
                        ->orWhere('content', $like, "%{$kw}%");
                }
            })
            ->take($limit)
            ->get();

        foreach ($chunks as $chunk) {
            $contextSnippets[] = "--- [DOC: {$chunk->title}] ---\n{$chunk->content}";
            $sources[] = [
                'type' => 'knowledge_chunk',
                'id' => $chunk->id,
                'title' => $chunk->title,
                'snippet' => mb_substr($chunk->content, 0, 160),
            ];
        }

        return [
            'context_text' => implode("\n\n", $contextSnippets),
            'sources' => $sources,
            'has_knowledge' => !empty($sources),
        ];
    }

    /**
     * Genera una sugerencia inteligente de respuesta basada en los últimos mensajes y el contexto del CRM (RAG Unificado).
     */
    public function generateReplySuggestion(Conversation $conversation, ?string $instruction = null): array
    {
        $messages = $conversation->messages()
            ->latest('sent_at')
            ->take(8)
            ->get()
            ->reverse();

        $conversationHistory = $messages->map(function ($msg) {
            $sender = $msg->direction === 'inbound' ? 'Cliente' : 'Agente';
            return "{$sender}: {$msg->body}";
        })->implode("\n");

        $contactName = $conversation->contact?->name ?? 'Cliente';
        $companyName = $conversation->company?->name ?? 'N/A';

        // Buscar última consulta entrante del cliente para RAG
        $lastInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('sent_at')
            ->first();
        $queryText = $lastInbound?->body ?? ($instruction ?? '');

        $knowledge = $this->retrieveKnowledgeContext($conversation->organization_id, $queryText);

        $config = $this->getEffectiveConfig($conversation->organization_id);

        $defaultSystemPrompt = 'Eres el Copiloto de Inteligencia Artificial "Hentle-AI" para atención al cliente y soporte en WhatsApp. Tu objetivo es sugerir una respuesta profesional, empática, clara y orientada a la solución para que el agente humano la envíe al cliente con un solo clic.';
        $systemInstruction = !empty($config['system_prompt']) ? $config['system_prompt'] : $defaultSystemPrompt;

        $knowledgeSection = '';
        if ($knowledge['has_knowledge']) {
            $knowledgeSection = "\n\nInformación Oficial Verificada de la Empresa (Notas y Base de Conocimiento):\n" .
                $knowledge['context_text'] .
                "\n\nREGLA CLAVE DE PRECISIÓN: Utiliza los datos exactos indicados arriba (aranceles, sedes, costos, horarios, promociones, requisitos) para responder al cliente sin alucinaciones.";
        }

        $prompt = <<<PROMPT
Contexto del chat:
- Nombre del Cliente: {$contactName}
- Empresa: {$companyName}

Historial reciente de la conversación:
{$conversationHistory}
{$knowledgeSection}

Instrucción adicional del agente (opcional): {$instruction}

Instrucciones:
1. Responde en español con tono cálido, profesional y conciso, adaptado al formato de WhatsApp.
2. Si la información solicitada está en las notas o documentos oficiales, bríndala de manera clara y directa.
3. Si la información NO está disponible en los documentos, no inventes datos numéricos ni requisitos y responde cordialmente indicando que verificarás el detalle.
4. No incluyas saludos redundantes si ya se saludó en el historial.
5. Devuelve ÚNICAMENTE el texto sugerido para enviar, sin preámbulos ni comillas.
PROMPT;

        if (empty($config['api_key'])) {
            throw new \RuntimeException("No hay una API Key configurada para {$config['provider']}. Por favor ingresa a 'Inteligencia Artificial' para configurar tu clave.");
        }

        try {
            $reply = $this->sendDirectPrompt(
                prompt: $prompt,
                provider: $config['provider'],
                apiKey: $config['api_key'],
                model: $config['model'],
                baseUrl: $config['base_url'],
                systemInstruction: $systemInstruction,
                organizationId: $conversation->organization_id
            );

            return [
                'suggestion' => $reply,
                'confidence' => $knowledge['has_knowledge'] ? 0.95 : 0.75,
                'source' => "hentle_ai_{$config['provider']}",
                'sources' => $knowledge['sources'],
                'used_knowledge' => $knowledge['has_knowledge'],
            ];
        } catch (\Throwable $e) {
            Log::warning("Error consultando Hentle-AI Copilot ({$config['provider']}): " . $e->getMessage());
            throw new \RuntimeException("Error al consultar motor IA ({$config['provider']}): " . $e->getMessage());
        }
    }

    /**
     * Resume los puntos clave de un ticket para facilitar transferencias entre agentes o filas.
     */
    public function summarizeTicket(Conversation $conversation): array
    {
        $messages = $conversation->messages()->oldest()->take(30)->get();

        $contactName = $conversation->contact?->name ?? 'Cliente';
        $history = $messages->map(fn ($m) => ($m->direction === 'inbound' ? 'Cliente: ' : 'Agente: ') . $m->body)->implode("\n");

        $config = $this->getEffectiveConfig($conversation->organization_id);

        if (empty($config['api_key'])) {
            throw new \RuntimeException("No hay una API Key configurada para {$config['provider']}. Por favor ingresa a 'Inteligencia Artificial' para configurar tu clave.");
        }

        try {
            $prompt = <<<PROMPT
Resume la siguiente conversación de WhatsApp entre un asesor y el cliente {$contactName}.
Devuelve un resumen ejecutivo breve (máximo 3 líneas) indicando motivo de contacto, estado actual y sentimiento.

Historial:
{$history}
PROMPT;

            $reply = $this->sendDirectPrompt(
                prompt: $prompt,
                provider: $config['provider'],
                apiKey: $config['api_key'],
                model: $config['model'],
                baseUrl: $config['base_url'],
                systemInstruction: 'Eres un analista de CRM que sintetiza tickets de atención al cliente de forma clara y directa.',
                organizationId: $conversation->organization_id
            );

            return [
                'summary' => $reply,
                'key_points' => [
                    "Cliente: {$contactName}",
                    'Proveedor IA: ' . ucfirst($config['provider']) . " ({$config['model']})",
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('Error en summarizeTicket: ' . $e->getMessage());
            throw new \RuntimeException("Error al generar resumen con {$config['provider']}: " . $e->getMessage());
        }
    }
}
