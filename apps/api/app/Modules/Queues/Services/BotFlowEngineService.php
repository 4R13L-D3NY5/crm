<?php

namespace App\Modules\Queues\Services;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Queues\Models\BotFlow;
use Illuminate\Support\Carbon;

class BotFlowEngineService
{
    /**
     * Procesa un mensaje entrante dentro del árbol de decisión del BotFlow
     */
    public function process(BotFlow $flow, Conversation $conversation, string $userMessageText): array
    {
        $input = trim($userMessageText);
        $options = $flow->options ?? [];

        // 1. Buscar coincidencia exacta por número de opción o coincidencia en label
        $matchedOption = null;
        foreach ($options as $opt) {
            $optNum = (string) ($opt['option_number'] ?? '');
            $optLabel = mb_strtolower((string) ($opt['label'] ?? ''));

            if ($input === $optNum || mb_strtolower($input) === $optLabel) {
                $matchedOption = $opt;
                break;
            }
        }

        // 2. Si hubo coincidencia con una opción del árbol
        if ($matchedOption) {
            $replyText = $matchedOption['reply_text'] ?? "Has seleccionado la opción: {$matchedOption['label']}. En un momento te atenderemos.";
            $targetQueueId = $matchedOption['queue_id'] ?? null;

            if ($targetQueueId) {
                $conversation->update([
                    'queue_id' => $targetQueueId,
                    'status' => 'pending',
                ]);
            }

            // Registrar mensaje de respuesta del bot
            $botMsg = Message::create([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'body' => $replyText,
                'sent_at' => Carbon::now(),
            ]);

            return [
                'type' => 'option_matched',
                'matched_option' => $matchedOption,
                'reply_message' => $botMsg,
                'transferred_to_queue_id' => $targetQueueId,
            ];
        }

        // 3. Si no coincide y tiene handoff a IA activo
        if ($flow->handoff_to_ai) {
            $aiReplyText = "🤖 *Hentle-AI Asistente:* Comprendo tu consulta: \"{$input}\". En UNITEPC contamos con más de 25 carreras a nivel nacional, preuniversitarios y convenios internacionales. ¿Deseas que te transfiera con un asesor humano o prefieres ver las opciones de sedes?";

            $botMsg = Message::create([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'body' => $aiReplyText,
                'sent_at' => Carbon::now(),
            ]);

            return [
                'type' => 'ai_handoff',
                'reply_message' => $botMsg,
                'transferred_to_queue_id' => $conversation->queue_id,
            ];
        }

        // 4. Fallback estándar del menú
        $fallback = $flow->fallback_message ?: "Opción no válida. Por favor responde con el número de la opción deseada:\n" . $this->renderMenuText($flow);

        $botMsg = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => $fallback,
            'sent_at' => Carbon::now(),
        ]);

        return [
            'type' => 'fallback',
            'reply_message' => $botMsg,
            'transferred_to_queue_id' => null,
        ];
    }

    /**
     * Renderiza el menú textual de opciones
     */
    public function renderMenuText(BotFlow $flow): string
    {
        $lines = [$flow->greeting_message];
        foreach ($flow->options ?? [] as $opt) {
            $lines[] = "{$opt['option_number']}. {$opt['label']}";
        }
        return implode("\n", $lines);
    }
}
