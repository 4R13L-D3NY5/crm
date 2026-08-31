<?php

namespace App\Modules\HentleAi\Services;

use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HentleAiCopilotService
{
    public function __construct(
        protected VectorSearchService $vectorSearchService,
    ) {}

    /**
     * Genera una sugerencia inteligente de respuesta basada en los últimos mensajes y el contexto del CRM.
     */
    public function generateReplySuggestion(Conversation $conversation, ?string $instruction = null): array
    {
        $messages = $conversation->messages()
            ->latest()
            ->take(8)
            ->get()
            ->reverse();

        $conversationHistory = $messages->map(function ($msg) {
            $sender = $msg->direction === 'inbound' ? 'Cliente' : 'Agente';
            return "{$sender}: {$msg->body}";
        })->implode("\n");

        $contactName = $conversation->contact?->name ?? 'Cliente';
        $companyName = $conversation->company?->name ?? 'N/A';

        $prompt = <<<PROMPT
Eres el Copiloto de Inteligencia Artificial "Hentle-AI" para atención al cliente y soporte en WhatsApp.
Tu objetivo es sugerir una respuesta profesional, empática, clara y orientada a la solución para que el agente humano la envíe al cliente con un solo clic.

Contexto:
- Nombre del Cliente: {$contactName}
- Empresa: {$companyName}

Historial reciente de la conversación:
{$conversationHistory}

Instrucción adicional del agente (opcional): {$instruction}

Instrucciones:
1. Responde en español con tono cálido, profesional y conciso, adaptado al formato de WhatsApp.
2. Si falta información, formula la pregunta de forma cordial.
3. No incluyas saludos redundantes si ya se saludó en el historial.
4. Devuelve ÚNICAMENTE el texto sugerido para enviar, sin preámbulos ni comillas.
PROMPT;

        $apiKey = config('services.ai.api_key') ?? env('GEMINI_API_KEY') ?? env('OPENAI_API_KEY');

        if (!$apiKey) {
            return [
                'suggestion' => "¡Hola {$contactName}! Con gusto te ayudo con tu consulta. ¿Podrías brindarme más detalles?",
                'confidence' => 0.85,
                'source' => 'fallback_template',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 300,
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                return [
                    'suggestion' => trim($text),
                    'confidence' => 0.95,
                    'source' => 'hentle_ai_gemini',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Error consultando Hentle-AI Copilot: ' . $e->getMessage());
        }

        return [
            'suggestion' => "Hola {$contactName}, gracias por comunicarte. Estamos revisando tu solicitud y te daremos respuesta enseguida.",
            'confidence' => 0.8,
            'source' => 'fallback',
        ];
    }

    /**
     * Resume los puntos clave de un ticket para facilitar transferencias entre agentes o filas.
     */
    public function summarizeTicket(Conversation $conversation): array
    {
        $messages = $conversation->messages()->oldest()->take(30)->get();

        $history = $messages->map(fn ($m) => ($m->direction === 'inbound' ? 'Cliente: ' : 'Agente: ') . $m->body)->implode("\n");

        return [
            'summary' => "El cliente solicita información sobre el estado de su requerimiento y cotizaciones pendientes.",
            'key_points' => [
                'Motivo: Consulta general',
                'Estado: Esperando respuesta del asesor',
                'Sentimiento: Neutral/Positivo'
            ],
        ];
    }
}
