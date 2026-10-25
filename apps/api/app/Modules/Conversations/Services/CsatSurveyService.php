<?php

namespace App\Modules\Conversations\Services;

use App\Models\User;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Events\TicketUpdatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CsatSurveyService
{
    /**
     * Envía la encuesta de satisfacción por WhatsApp al cerrar una conversación
     */
    public function dispatchSurvey(Conversation $conversation, ?User $closedByUser = null): ?Message
    {
        // 1. Validaciones previas: canal WhatsApp, no calificada previamente
        if ($conversation->channel !== 'whatsapp') {
            return null;
        }

        if (!is_null($conversation->rating)) {
            return null;
        }

        $metadata = (array) ($conversation->metadata ?? []);

        // Evitar duplicar encuesta si ya se envió en las últimas 24 horas
        if (!empty($metadata['csat_survey_sent_at'])) {
            $sentAt = Carbon::parse($metadata['csat_survey_sent_at']);
            if ($sentAt->gt(now()->subHours(24))) {
                Log::info("CSAT: Encuesta omitida por envío reciente en conversación {$conversation->id}");
                return null;
            }
        }

        $surveyText = "¡Gracias por comunicarte con nosotros! 🎓\n\n"
            . "¿Cómo calificarías la atención recibida hoy?\n\n"
            . "⭐ Por favor califícanos respondiendo con un número del *1 al 5*:\n"
            . "5️⃣ - Excelente\n"
            . "4️⃣ - Muy Bueno\n"
            . "3️⃣ - Regular\n"
            . "2️⃣ - Malo\n"
            . "1️⃣ - Deficiente";

        // 2. Registrar el mensaje saliente de la encuesta
        $outboundMsg = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $closedByUser?->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'body' => $surveyText,
            'metadata' => [
                'is_csat_survey' => true,
            ],
            'sent_at' => Carbon::now(),
        ]);

        // 3. Marcar metadatos en la conversación
        $metadata['csat_survey_sent_at'] = now()->toISOString();
        $conversation->metadata = $metadata;
        $conversation->save();

        // 4. Despachar envío de WhatsApp
        try {
            if (config('crm.dispatch_mode', 'sync') === 'sync') {
                SendWhatsAppMessageJob::dispatchSync($outboundMsg->id);
            } else {
                SendWhatsAppMessageJob::dispatch($outboundMsg->id);
            }
        } catch (\Throwable $e) {
            Log::error("CSAT: Error al despachar SendWhatsAppMessageJob para encuesta: " . $e->getMessage());
        }

        // 5. Emitir evento por WebSockets para actualización en tiempo real en la UI
        try {
            event(new TicketMessageCreatedEvent($outboundMsg));
        } catch (\Throwable $e) {
            Log::warning("CSAT: Error al emitir TicketMessageCreatedEvent: " . $e->getMessage());
        }

        Log::info("CSAT: Encuesta de satisfacción despachada exitosamente para conversación {$conversation->id}");

        return $outboundMsg;
    }

    /**
     * Evalúa si una conversación y el mensaje entrante califican como respuesta de encuesta CSAT
     */
    public function isEligibleForSurveyReply(Conversation $conversation, ?string $body): bool
    {
        if (is_null($body) || trim($body) === '') {
            return false;
        }

        // Si ya fue calificada, no procesar nuevamente como CSAT
        if (!is_null($conversation->rating)) {
            return false;
        }

        $metadata = (array) ($conversation->metadata ?? []);
        $surveySentAt = $metadata['csat_survey_sent_at'] ?? null;
        $isRecentlyClosed = $conversation->status === 'closed'
            && $conversation->closed_at
            && Carbon::parse($conversation->closed_at)->gt(now()->subHours(48));

        // Debe haber sido cerrada recientemente o haber recibido la encuesta
        if (!$surveySentAt && !$isRecentlyClosed) {
            return false;
        }

        // Debe contener una calificación válida del 1 al 5
        return $this->parseRatingFromText($body) !== null;
    }

    /**
     * Parsea e interpreta la calificación del 1 al 5 desde un texto
     */
    public function parseRatingFromText(string $body): ?int
    {
        $clean = trim($body);
        if ($clean === '') {
            return null;
        }

        // 1. Coincidencia exacta de número del 1 al 5
        if (preg_match('/^\s*([1-5])\s*$/', $clean, $matches)) {
            return (int) $matches[1];
        }

        // 2. Coincidencia de número con palabras: "5 estrellas", "4/5", "opción 5", "nota 5", "califico 5"
        if (preg_match('/^(?:califico\s+con\s+|le\s+doy\s+|nota\s+|opcion\s+|calificacion\s*:?\s*)?([1-5])(?:\s*(?:\/5|estrellas?|pts?|puntos?))?(?:\b.*)?$/i', $clean, $matches)) {
            return (int) $matches[1];
        }

        // 3. Conteo de emojis de estrellas (⭐, 🌟)
        $starCount = substr_count($clean, '⭐') + substr_count($clean, '🌟');
        if ($starCount >= 1 && $starCount <= 5) {
            return $starCount;
        }

        // 4. Palabras cualitativas en español
        $lower = mb_strtolower($clean, 'UTF-8');
        $unaccented = iconv('UTF-8', 'ASCII//TRANSLIT', $lower) ?: $lower;

        if (preg_match('/\b(excelente|perfecto|maravilloso|increible|super)\b/i', $unaccented)) {
            return 5;
        }
        if (preg_match('/\b(muy\s+buen[oa]|buenisimo|muy\s+bien)\b/i', $unaccented)) {
            return 4;
        }
        if (preg_match('/^\s*buen[oa]\s*$/i', $unaccented) || preg_match('/\bbuen\s+servicio\b/i', $unaccented)) {
            return 4;
        }
        if (preg_match('/\b(regular|mas\s+o\s+menos|normal|decente)\b/i', $unaccented)) {
            return 3;
        }
        if (preg_match('/\b(mal[oa]|malisimo|descontento)\b/i', $unaccented)) {
            return 2;
        }
        if (preg_match('/\b(pesim[oa]|horrible|deficiente|fatal|terrible)\b/i', $unaccented)) {
            return 1;
        }

        return null;
    }

    /**
     * Registra la respuesta del cliente, actualiza el rating de la conversación y envía mensaje de agradecimiento
     */
    public function recordSurveyReply(
        Conversation $conversation,
        string $body,
        WhatsAppAccount $account,
        ?string $providerMessageId = null,
        array $rawPayload = []
    ): Message {
        $rating = $this->parseRatingFromText($body) ?? 5;

        // 1. Guardar el mensaje entrante del cliente en la conversación
        $inboundMsg = Message::create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_status' => 'delivered',
            'delivery_status' => 'delivered',
            'body' => $body,
            'metadata' => [
                'is_csat_reply' => true,
                'rating' => $rating,
            ],
            'sent_at' => Carbon::now(),
        ]);

        if ($providerMessageId) {
            WhatsAppMessageMapping::query()->updateOrCreate(
                ['provider_message_id' => $providerMessageId],
                [
                    'organization_id' => $account->organization_id,
                    'whatsapp_account_id' => $account->id,
                    'contact_id' => $conversation->contact_id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $inboundMsg->id,
                    'direction' => 'inbound',
                    'status' => 'delivered',
                    'from_phone' => $conversation->contact?->phone,
                    'payload' => $rawPayload,
                ]
            );
        }

        // 2. Actualizar el rating en la conversación, manteniéndola CERRADA
        $metadata = (array) ($conversation->metadata ?? []);
        $metadata['csat_completed_at'] = now()->toISOString();
        $metadata['csat_source'] = 'whatsapp_survey_reply';

        $feedbackText = (strlen(trim($body)) > 3 && !is_numeric(trim($body))) ? trim($body) : null;

        $conversation->forceFill([
            'rating' => $rating,
            'feedback' => $feedbackText,
            'status' => 'closed',
            'closed_at' => $conversation->closed_at ?: Carbon::now(),
            'metadata' => $metadata,
            'last_message_at' => Carbon::now(),
        ])->save();

        // 3. Emitir eventos en tiempo real
        try {
            event(new TicketMessageCreatedEvent($inboundMsg));
            event(new TicketUpdatedEvent($conversation->fresh(['queue', 'assignee', 'contact', 'company'])));
        } catch (\Throwable $e) {
            Log::warning("CSAT: Error al emitir eventos de actualización: " . $e->getMessage());
        }

        // 4. Enviar mensaje automático de agradecimiento al cliente
        $starsVisual = str_repeat('⭐', $rating);
        $thankYouText = "¡Muchas gracias por tu calificación de {$rating}/5 {$starsVisual}! 🙌\n\n"
            . "Tu opinión nos ayuda a seguir mejorando nuestra atención. Si necesitas algo más adelante, estamos a tu total disposición.";

        $thankYouMsg = Message::create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'body' => $thankYouText,
            'metadata' => [
                'is_csat_thank_you' => true,
            ],
            'sent_at' => Carbon::now(),
        ]);

        try {
            if (config('crm.dispatch_mode', 'sync') === 'sync') {
                SendWhatsAppMessageJob::dispatchSync($thankYouMsg->id);
            } else {
                SendWhatsAppMessageJob::dispatch($thankYouMsg->id);
            }
        } catch (\Throwable $e) {
            Log::error("CSAT: Error al enviar mensaje de agradecimiento: " . $e->getMessage());
        }

        try {
            event(new TicketMessageCreatedEvent($thankYouMsg));
        } catch (\Throwable $e) {
            Log::warning("CSAT: Error al emitir TicketMessageCreatedEvent de agradecimiento: " . $e->getMessage());
        }

        Log::info("CSAT: Calificación de {$rating} estrellas registrada con éxito para la conversación {$conversation->id}");

        return $inboundMsg;
    }
}
