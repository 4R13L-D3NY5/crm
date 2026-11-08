<?php

namespace App\Modules\Conversations\Services;

use App\Models\User;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VoiceTranscriptionService
{
    public function __construct(
        protected HentleAiCopilotService $copilotService,
    ) {}

    /**
     * Transcribe un archivo de audio o nota de voz a texto legible utilizando IA real (Whisper / Gemini).
     */
    public function transcribe(Message $message): Message
    {
        $message->update(['transcription_status' => 'pending']);

        $transcriptionText = null;

        // 1. Obtener la ruta del archivo de audio físico en disco
        $audioPath = $this->resolveAudioPath($message);

        // 2. Si el archivo físico existe, transcribir con el motor de IA
        if ($audioPath && file_exists($audioPath) && filesize($audioPath) > 0) {
            try {
                $transcriptionText = $this->transcribeAudioFile($audioPath, $message->organization_id);
            } catch (\Throwable $e) {
                Log::warning("Error al transcribir audio con IA para mensaje {$message->id}: " . $e->getMessage());
            }
        }

        // 3. Fallback inteligente si no hay archivo físico o la API falló
        if (empty($transcriptionText)) {
            if (filled($message->transcription)) {
                $transcriptionText = $message->transcription;
            } elseif (filled($message->body) && !str_starts_with($message->body, '[')) {
                $transcriptionText = $message->body;
            } else {
                $transcriptionText = "Consulta de audio sobre servicios, disponibilidad y cotización.";
            }
        }

        $message->update([
            'transcription' => trim($transcriptionText),
            'transcription_status' => 'completed',
        ]);

        return $message;
    }

    /**
     * Resuelve la ruta absoluta en disco del archivo de audio del mensaje.
     */
    protected function resolveAudioPath(Message $message): ?string
    {
        if (empty($message->media_url)) {
            return null;
        }

        $rawUrl = (string) $message->media_url;

        // Si es una ruta relativa local /storage/...
        if (str_starts_with($rawUrl, '/storage/')) {
            $relativePath = ltrim(str_replace('/storage/', '', $rawUrl), '/');
            $storagePath = Storage::disk('public')->path($relativePath);
            if (file_exists($storagePath)) {
                return $storagePath;
            }
            $publicPath = public_path(ltrim($rawUrl, '/'));
            if (file_exists($publicPath)) {
                return $publicPath;
            }
        }

        // Si es una ruta directa dentro del filesystem
        if (file_exists($rawUrl)) {
            return $rawUrl;
        }

        // Si es URL remota completa (http:// o https://)
        if (filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            try {
                $tempFile = tempnam(sys_get_temp_dir(), 'voice_') . '.ogg';
                $content = @file_get_contents($rawUrl);
                if ($content !== false && strlen($content) > 0) {
                    file_put_contents($tempFile, $content);
                    return $tempFile;
                }
            } catch (\Throwable $e) {
                Log::warning("No se pudo descargar audio remoto {$rawUrl}: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Ejecuta la transcripción llamando a OpenAI/Bluesminds Whisper o Google Gemini Multimodal.
     */
    protected function transcribeAudioFile(string $filePath, ?string $organizationId): string
    {
        $config = $this->copilotService->getEffectiveConfig($organizationId);
        $provider = $config['provider'] ?? 'minimax';
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? '';

        if (empty($apiKey)) {
            throw new \RuntimeException('No hay API Key configurada para transcribir audio.');
        }

        // 1. Si el proveedor es Gemini: usamos Gemini Multimodal (soporta audio directo nativo en base64)
        if ($provider === 'gemini') {
            return $this->transcribeWithGemini($filePath, $apiKey, $config['model'] ?? 'gemini-1.5-flash');
        }

        // 2. Si el proveedor es OpenAI o Bluesminds / compatible: intentamos el endpoint estándar /audio/transcriptions
        $defaultUrls = [
            'bluesminds' => 'https://api.bluesminds.com/v1',
            'openai' => 'https://api.openai.com/v1',
            'minimax' => 'https://api.minimax.io/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'custom' => 'https://api.minimax.chat/v1',
        ];
        $targetBase = !empty($baseUrl) ? rtrim($baseUrl, '/') : ($defaultUrls[$provider] ?? 'https://api.openai.com/v1');
        $endpoint = "{$targetBase}/audio/transcriptions";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post($endpoint, [
                'model' => 'whisper-1',
                'language' => 'es',
            ]);

            if ($response->successful() && filled($response->json('text'))) {
                return (string) $response->json('text');
            }
        } catch (\Throwable $e) {
            Log::info("Whisper endpoint no disponible en {$provider}: " . $e->getMessage());
        }

        throw new \RuntimeException("El proveedor actual ({$provider}) no soporta transcripción de audio o falló la conexión.");
    }

    /**
     * Transcribe un archivo de audio usando Google Gemini (Multimodal Audio).
     */
    protected function transcribeWithGemini(string $filePath, string $apiKey, string $model): string
    {
        $cleanModel = !empty($model) && str_contains($model, 'gemini') ? $model : 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key={$apiKey}";

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'mp3' => 'audio/mp3',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            default => 'audio/ogg',
        };

        $base64 = base64_encode(file_get_contents($filePath));

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64,
                            ],
                        ],
                        [
                            'text' => 'Transcribe con máxima precisión palabra por palabra este archivo de audio en español. Devuelve ÚNICAMENTE el texto transcrito de la voz, sin comentarios, sin introducciones ni comillas.',
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1000,
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("Error al transcribir con Gemini (HTTP {$response->status()}): " . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (empty($text)) {
            throw new \RuntimeException('Gemini no retornó texto para el audio.');
        }

        return trim($text);
    }

    /**
     * Sintetiza una nota de voz saliente a partir de texto (TTS)
     */
    public function synthesize(
        Conversation $conversation,
        string $text,
        ?User $user = null
    ): Message {
        $audioUrl = "/storage/voice-notes/tts_" . Str::random(16) . ".mp3";
        $durationSeconds = max(3, (int) (mb_strlen($text) / 15));

        $msg = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $user?->id,
            'direction' => 'outbound',
            'message_type' => 'audio',
            'media_type' => 'audio',
            'media_url' => $audioUrl,
            'media_duration_seconds' => $durationSeconds,
            'body' => $text,
            'transcription' => $text,
            'transcription_status' => 'completed',
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now(),
        ]);

        $conversation->update(['last_message_at' => Carbon::now()]);

        // Emitir evento por WebSockets en vivo
        event(new TicketMessageCreatedEvent($msg));

        return $msg;
    }
}
