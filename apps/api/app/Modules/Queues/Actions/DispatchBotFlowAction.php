<?php

namespace App\Modules\Queues\Actions;

use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Queues\Models\BotFlow;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchBotFlowAction
{
    /**
     * Evalúa y despacha el menú de bienvenida o enrutamiento de opciones si el BotFlow está activo.
     */
    public function execute(Conversation $conversation, ?string $incomingText): bool
    {
        // 1. Si la conversación ya tiene un operador asignado, o ya tiene cola asignada, no interferir
        if (! empty($conversation->assigned_to_user_id) || ! empty($conversation->queue_id)) {
            return false;
        }

        // 2. Buscar si la organización tiene un BotFlow activo
        $flow = BotFlow::where('organization_id', $conversation->organization_id)
            ->where('is_active', true)
            ->first();

        if (! $flow) {
            return false;
        }

        $input = trim((string) $incomingText);
        $options = $flow->options ?? [];

        // 3. Revisar si el cliente está respondiendo con una de las opciones numéricas o por nombre
        $matchedOption = null;
        if (! empty($input) && ! empty($options)) {
            foreach ($options as $opt) {
                $optNum = (string) ($opt['option_number'] ?? '');
                $optLabel = mb_strtolower((string) ($opt['label'] ?? ''));

                if ($input === $optNum || mb_strtolower($input) === $optLabel) {
                    $matchedOption = $opt;
                    break;
                }
            }
        }

        if ($matchedOption) {
            $targetQueueId = $matchedOption['queue_id'] ?? null;
            $replyText = $matchedOption['reply_text'] ?? "Has seleccionado la opción: {$matchedOption['label']}. En un momento te atenderemos.";

            if ($targetQueueId) {
                $conversation->update([
                    'queue_id' => $targetQueueId,
                    'status' => 'pending',
                ]);
            }

            // Registrar y enviar mensaje de confirmación del bot
            $botMsg = Message::create([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $replyText,
                'message_status' => 'pending',
                'delivery_status' => 'pending',
                'sent_at' => Carbon::now(),
            ]);

            event(new TicketMessageCreatedEvent($botMsg));
            SendWhatsAppMessageJob::dispatch($botMsg->id);

            return true;
        }

        // 4. Si aún no ha seleccionado opción, verificar si ya se le envió el menú de bienvenida recientemente
        $alreadySentMenu = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('body', 'like', '%' . mb_substr($flow->greeting_message, 0, 25) . '%')
            ->exists();

        if (! $alreadySentMenu) {
            // Construir menú completo: saludo + opciones de filas
            $menuLines = [$flow->greeting_message, ''];
            foreach ($options as $opt) {
                $num = $opt['option_number'] ?? '';
                $label = $opt['label'] ?? '';
                $menuLines[] = "{$num}. {$label}";
            }
            $fullMenu = implode("\n", $menuLines);

            $botMsg = Message::create([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $fullMenu,
                'message_status' => 'pending',
                'delivery_status' => 'pending',
                'sent_at' => Carbon::now(),
            ]);

            event(new TicketMessageCreatedEvent($botMsg));
            SendWhatsAppMessageJob::dispatch($botMsg->id);

            return true;
        }

        // 5. Si ya se le envió el menú y el cliente escribió una opción no reconocida
        if (! empty($flow->fallback_message)) {
            $botMsg = Message::create([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $flow->fallback_message,
                'message_status' => 'pending',
                'delivery_status' => 'pending',
                'sent_at' => Carbon::now(),
            ]);

            event(new TicketMessageCreatedEvent($botMsg));
            SendWhatsAppMessageJob::dispatch($botMsg->id);

            return true;
        }

        return false;
    }
}
