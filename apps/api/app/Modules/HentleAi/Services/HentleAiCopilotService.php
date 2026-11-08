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
        int $maxTokens = 500,
        int $timeoutSeconds = 60,
    ): string {
        if (!$bypassBudget) {
            $this->assertBudgetAvailable($organizationId);
        }

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Se requiere una clave de API válida.');
        }

        if ($provider === 'gemini') {
            return $this->callGemini($prompt, $apiKey, $model, $systemInstruction, $organizationId, $maxTokens, $timeoutSeconds);
        }

        return $this->callOpenAiCompatible($prompt, $provider, $apiKey, $model, $baseUrl, $systemInstruction, $organizationId, $maxTokens, $timeoutSeconds);
    }

    protected function callOpenAiCompatible(
        string $prompt,
        string $provider,
        string $apiKey,
        string $model,
        string $baseUrl,
        ?string $systemInstruction = null,
        ?string $organizationId = null,
        int $maxTokens = 500,
        int $timeoutSeconds = 60,
    ): string {
        $defaultUrls = [
            'bluesminds' => 'https://api.bluesminds.com/v1',
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

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->timeout($timeoutSeconds)
            ->connectTimeout(15)
            ->post($endpoint, [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => $maxTokens,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                throw new \RuntimeException("El proveedor de IA ({$provider}) demoró más de {$timeoutSeconds}s en responder. Por favor intenta de nuevo o prueba con un modelo más veloz como Gemini o MiniMax.");
            }
            throw new \RuntimeException("Error de conexión con el proveedor de IA: {$e->getMessage()}");
        }

        if (!$response->successful()) {
            $errorBody = $response->json();
            $errMessage = $errorBody['error']['message'] ?? $errorBody['message'] ?? $response->body();
            throw new \RuntimeException("Respuesta de API fallida (HTTP {$response->status()}): {$errMessage}");
        }

        $reply = $response->json('choices.0.message.content');
        if (empty($reply)) {
            $reply = $response->json('choices.0.message.reasoning_content');
        }
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
        int $maxTokens = 500,
        int $timeoutSeconds = 60,
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
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->timeout($timeoutSeconds)
            ->connectTimeout(15)
            ->post($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                throw new \RuntimeException("Google Gemini demoró más de {$timeoutSeconds}s en responder. Por favor intenta de nuevo.");
            }
            throw new \RuntimeException("Error de conexión con Google Gemini: {$e->getMessage()}");
        }

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
     * Analiza y extrae artículos/fragmentos de conocimiento atómicos estructurados desde un documento.
     * Soporta modos: 'technical' (Base técnica & FAQ), 'marketing' (Copy publicitario con emojis), 'hybrid' (Ambos).
     */
    public function extractArticlesFromDocument(string $text, ?string $organizationId = null, string $mode = 'technical'): array
    {
        $config = $this->getEffectiveConfig($organizationId);
        if (empty($config['api_key'])) {
            throw new \RuntimeException('No hay un proveedor de IA configurado con API Key. Por favor configura el motor IA primero.');
        }

        if ($mode === 'marketing') {
            $systemInstruction = "Eres un copywriter senior y estratega de marketing digital para WhatsApp y redes sociales (Instagram, TikTok, Facebook).
Tu labor es leer con atención el documento, catálogo o lista de precios y transformarlo en fragmentos de COPY PUBLICITARIO de alto impacto, elegantes y listos para enviar a prospectos o publicar en redes.

ESTRUCTURA DE CADA COPY PUBLICITARIO:
- \"title\": Título llamativo con emoji temático (ej: '🚀 Impulsa tus Ventas con XpertiFlow Pro').
- \"content\": Formato comercial profesional y elegante:
  1. Gancho inicial persuasivo con emoji (ej. 🔥, ✨, 💼).
  2. Propuesta de valor y beneficios destacados con viñetas elegantes (ej. ✅, 💎, 📈).
  3. Precios exactos, plazos o condiciones comerciales de la oferta.
  4. Llamado a la acción (CTA) directo para WhatsApp o redes (ej. 📲 Escríbenos ahora para agendar tu demo).
  Longitud: entre 70 y 250 palabras.
- \"tags\": Array de 2 a 4 etiquetas (ej. ['publicidad', 'whatsapp', 'oferta', 'ventas']).

REGLAS CRÍTICAS:
1. Responde ÚNICAMENTE con un array JSON válido sin bloques markdown ```json.
2. Cada objeto debe tener exactamente 'title', 'content' y 'tags'.
3. Si hay múltiples productos, servicios o planes, genera un copy publicitario individual por cada uno.";
        } elseif ($mode === 'hybrid') {
            $systemInstruction = "Eres un estratega dual de contenido corporativo: dominas tanto la precisión técnica de la Base de Conocimiento (RAG) como el Copywriting persuasivo para ventas por WhatsApp y redes sociales.
Tu labor es extraer fragmentos tanto de especificación técnica/tarifaria como copys publicitarios elegantes con emojis para cada producto o servicio detectado en el documento.
Para los copys publicitarios usa títulos con emojis, viñetas (✅, 💎) y llamado a la acción (CTA 📲).
Responde ÚNICAMENTE con un array JSON válido con campos 'title', 'content' y 'tags'.";
        } else {
            $systemInstruction = "Eres un arquitecto senior de información especializado en RAG (Retrieval-Augmented Generation) y Bases de Conocimiento corporativas.
Tu labor es leer con atención el documento provisto (manuales, directivas, listas de precios, tablas de Excel/CSV o catálogos) y desglosarlo en fragmentos (chunks) atómicos de conocimiento altamente informativos, estructurados y auto-contenidos.
Cada fragmento debe abarcar un tema o categoría de productos concreto (ej. Precios y Tarifas, Planes de Suscripción, Requisitos Técnicos, Políticas de Pago y Cancelación, Catálogo de Productos por Familia, etc.).
Si el documento es una tabla de catálogo o lista de precios (Excel/CSV), agrupa lógicamente los productos por categoría o crea artículos claros con los nombres, especificaciones y precios exactos para que los agentes de WhatsApp puedan consultar precios y detalles inmediatamente.

REGLAS CRÍTICAS:
1. Responde ÚNICAMENTE con un array JSON válido. NO incluyas explicaciones, saludos ni bloques de markdown con ```json.
2. Cada objeto del array debe tener exactamente estos campos:
   - \"title\": Título claro y conciso del tema o categoría de producto (máximo 90 caracteres).
   - \"content\": El contenido limpio y completo del fragmento (entre 60 y 250 palabras). Mantén datos numéricos exactos, precios, monedas, códigos de producto, plazos, teléfonos y condiciones.
   - \"tags\": Array de 2 a 4 palabras clave relevantes.
3. Si el documento contiene múltiples servicios, productos o políticas, sepáralos en artículos individuales.";
        }

        $prompt = "DOCUMENTO A PROCESAR:\n\n" . mb_substr($text, 0, 15000) . "\n\nAnaliza y divide en fragmentos de conocimiento atómicos en formato JSON según las instrucciones:";

        $rawResponse = $this->sendDirectPrompt(
            $prompt,
            $config['provider'],
            $config['api_key'],
            $config['model'],
            $config['base_url'],
            $systemInstruction,
            $organizationId,
            false,
            2000,
            120
        );

        $cleanJson = trim($rawResponse);
        // Quitar bloques de markdown si vinieron
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $cleanJson, $matches)) {
            $cleanJson = $matches[1];
        } else {
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/```$/', '', $cleanJson);
        }

        $parsed = json_decode(trim($cleanJson), true);

        if (!is_array($parsed)) {
            if (preg_match('/\[\s*\{.*\}\s*\]/s', $cleanJson, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }

        if (!is_array($parsed) || empty($parsed)) {
            // Fallback si el LLM no generó JSON válido
            return [
                [
                    'title' => 'Resumen de Documento',
                    'content' => mb_substr($text, 0, 800),
                    'tags' => ['general', 'documento'],
                ]
            ];
        }

        return array_map(function ($item) {
            return [
                'title' => (string) ($item['title'] ?? 'Sin título'),
                'content' => (string) ($item['content'] ?? ''),
                'tags' => is_array($item['tags'] ?? null) ? $item['tags'] : ['general'],
            ];
        }, $parsed);
    }

    /**
     * Transforma un artículo existente entre estilo técnico y copy publicitario / redes con emojis.
     */
    public function transformArticleStyle(string $title, string $content, string $targetStyle = 'marketing', ?string $organizationId = null): array
    {
        $config = $this->getEffectiveConfig($organizationId);
        if (empty($config['api_key'])) {
            throw new \RuntimeException('No hay un proveedor de IA configurado con API Key.');
        }

        if ($targetStyle === 'marketing') {
            $systemInstruction = "Eres un copywriter senior y estratega de contenido publicitario para WhatsApp y redes sociales.
Tu labor es tomar el artículo o contenido provisto y redactar un COPY PUBLICITARIO de alto impacto, elegante y persuasivo con emojis.
Estructura del copy:
1. Título llamativo con emoji temático.
2. Gancho de apertura atractivo (🔥, ✨, 🚀).
3. Beneficios y características con viñetas elegantes (✅, 💎, 📈).
4. Precios, tarifas o condiciones claras.
5. Llamado a la acción (CTA) directo para WhatsApp o redes (📲).

REGLAS:
- Responde ÚNICAMENTE con un JSON objeto: {\"title\": \"...\", \"content\": \"...\", \"tags\": [\"...\"]}.
- NO uses bloques ```json.";
            $prompt = "CONTENIDO ORIGINAL A TRANSFORMAR EN COPY PUBLICITARIO:\nTítulo: {$title}\nContenido: {$content}\n\nGenera el JSON con el copy publicitario elegante:";
        } else {
            $systemInstruction = "Eres un redactor técnico y documentador de bases de conocimiento corporativas (RAG).
Tu labor es transformar el texto provisto en un artículo analítico, formal, preciso y limpio sin exceso de emojis, ideal para respuestas técnicas de atención al cliente y FAQ.
Responde ÚNICAMENTE con un JSON objeto: {\"title\": \"...\", \"content\": \"...\", \"tags\": [\"...\"]}.";
            $prompt = "CONTENIDO ORIGINAL A TRANSFORMAR EN ARTÍCULO TÉCNICO:\nTítulo: {$title}\nContenido: {$content}\n\nGenera el JSON técnico estructurado:";
        }

        $rawResponse = $this->sendDirectPrompt(
            $prompt,
            $config['provider'],
            $config['api_key'],
            $config['model'],
            $config['base_url'],
            $systemInstruction,
            $organizationId,
            false,
            1200,
            60
        );

        $cleanJson = trim($rawResponse);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $cleanJson, $matches)) {
            $cleanJson = $matches[1];
        } else {
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $cleanJson);
            $cleanJson = preg_replace('/```$/', '', $cleanJson);
        }

        $parsed = json_decode(trim($cleanJson), true);
        if (!is_array($parsed) && preg_match('/\{.*\}/s', $cleanJson, $matches)) {
            $parsed = json_decode($matches[0], true);
        }

        if (!is_array($parsed) || empty($parsed['content'])) {
            return [
                'title' => $title,
                'content' => $content,
                'tags' => ['marketing'],
            ];
        }

        return [
            'title' => (string) ($parsed['title'] ?? $title),
            'content' => (string) ($parsed['content'] ?? $content),
            'tags' => is_array($parsed['tags'] ?? null) ? $parsed['tags'] : ['marketing', 'publicidad'],
        ];
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
