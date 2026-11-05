<?php

namespace App\Modules\HentleAi\Jobs;

use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSmartAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $inboundMessageId,
    ) {}

    public function handle(HentleAiCopilotService $copilotService): void
    {
        $conversation = Conversation::with(['contact', 'messages'])->find($this->conversationId);
        $message = Message::find($this->inboundMessageId);

        if (! $conversation || ! $message) {
            return;
        }

        // 1. Verificar si Smart Reply está habilitado en la organización
        $setting = WorkspaceSetting::where('organization_id', $conversation->organization_id)->first();
        $aiConfig = $setting?->custom_options['ai_config'] ?? [];
        $smartReplyEnabled = (bool) ($aiConfig['smart_reply_enabled'] ?? false);

        if (! $smartReplyEnabled) {
            Log::info("Smart AI Reply is disabled for org {$conversation->organization_id}. Aborting.");
            return;
        }

        // 2. Safeguard Humano: Si la conversación ya fue tomada por un asesor humano, NO responder automáticamente
        if (! empty($conversation->assigned_to_user_id)) {
            Log::info("Conversation {$conversation->id} was assigned to an advisor. Smart AI Reply aborted.");
            return;
        }

        // 3. Safeguard Humano: Si ya hubo una respuesta de un asesor posterior al mensaje entrante, abortar
        $humanReplied = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $message->created_at)
            ->exists();

        if ($humanReplied) {
            Log::info("Human advisor already responded to conversation {$conversation->id}. Smart AI Reply aborted.");
            return;
        }

        // 4. Si la conversación ya está cerrada o archivada, abortar
        if ($conversation->status === 'closed') {
            Log::info("Conversation {$conversation->id} is closed. Smart AI Reply aborted.");
            return;
        }

        // 5. Generar la sugerencia RAG con Notas & Base de Conocimiento
        try {
            $result = $copilotService->generateReplySuggestion($conversation);
        } catch (\Throwable $e) {
            Log::warning("Smart AI Reply failed to generate suggestion for conv {$conversation->id}: " . $e->getMessage());
            return;
        }

        $minConfidence = (float) ($aiConfig['smart_reply_confidence_threshold'] ?? 0.85);
        $confidence = (float) ($result['confidence'] ?? 0.0);

        if ($confidence < $minConfidence) {
            Log::info("Smart AI Reply confidence {$confidence} is below required {$minConfidence} for conv {$conversation->id}. Aborting auto-reply.");
            return;
        }

        $replyText = trim((string) ($result['suggestion'] ?? ''));
        if (empty($replyText)) {
            return;
        }

        // 6. Crear el mensaje outbound oficial
        $outbound = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => null, // Enviado por el motor Hentle-AI
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $replyText,
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'sent_at' => Carbon::now(),
        ]);

        $conversation->update([
            'last_message_at' => Carbon::now(),
        ]);

        event(new TicketMessageCreatedEvent($outbound));

        if ($conversation->channel === 'whatsapp') {
            SendWhatsAppMessageJob::dispatch($outbound->id);
        }

        Log::info("Smart AI auto-reply successfully sent for conv {$conversation->id} with confidence {$confidence}.");
    }
}
