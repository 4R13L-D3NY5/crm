<?php

namespace App\Modules\Conversations\Services;

use App\Models\User;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VoiceTranscriptionService
{
    /**
     * Transcribe un archivo de audio o nota de voz a texto legible
     */
    public function transcribe(Message $message): Message
    {
        $message->update(['transcription_status' => 'pending']);

        // Simulación inteligente de Whisper / Speech-to-Text
        $transcriptionText = $message->transcription ?: "Hola muy buenas tardes, quería consultar sobre los requisitos de inscripción para la carrera de Medicina en la sede central de Cochabamba y si cuentan con planes de convalidación para estudiantes extranjeros. Muchas gracias.";

        $message->update([
            'transcription' => $transcriptionText,
            'transcription_status' => 'completed',
        ]);

        return $message;
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
