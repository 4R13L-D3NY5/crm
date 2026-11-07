<?php

namespace App\Modules\Parameters\Services;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OutOfHoursAiAgentService
{
    public function __construct(
        protected HentleAiCopilotService $copilotService,
        protected NotificationService $notificationService,
    ) {}

    public function execute(Conversation $conversation, BusinessHoursConfig $config, ?Message $inboundMessage = null): ?Message
    {
        // 1. Obtener el último mensaje entrante del cliente
        if (! $inboundMessage) {
            $inboundMessage = $conversation->messages()
                ->where('direction', 'inbound')
                ->latest('sent_at')
                ->first();
        }

        $queryText = trim((string) ($inboundMessage?->body ?? ''));
        if (empty($queryText)) {
            Log::info("Guardia IA omitido: mensaje entrante vacío en conversación {$conversation->id}");
            return null;
        }

        // 2. Control anti-spam: evitar responder repetidamente si ya se envió una respuesta saliente reciente
        $antiSpamMinutes = app()->environment('local') ? 1 : ((int) ($config->auto_reply_cooldown_minutes ?: 1440));
        $alreadySentRecently = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subMinutes($antiSpamMinutes))
            ->exists();

        if ($alreadySentRecently) {
            Log::info("Guardia IA omitido por deduplicación reciente en conversación {$conversation->id}");
            return null;
        }

        // 3. Consultar RAG (Base de Conocimiento y Notas Keep)
        $knowledge = $this->copilotService->retrieveKnowledgeContext($conversation->organization_id, $queryText);
        $threshold = (float) ($config->ai_confidence_threshold ?: 0.80);

        $hasSufficientKnowledge = $knowledge['has_knowledge'] && ! empty($knowledge['sources']);
        $replyText = null;

        if ($hasSufficientKnowledge) {
            try {
                $instruction = 'El cliente está escribiendo fuera del horario de atención de la empresa. Utiliza EXCLUSIVAMENTE los datos del manual o notas recuperadas para responder con precisión. Indica cordialmente al final que un asesor humano retomará el caso en horario laboral si requiere mayor detalle.';
                $result = $this->copilotService->generateReplySuggestion($conversation, $instruction);

                $confidence = (float) ($result['confidence'] ?? 0.0);
                if ($confidence >= $threshold && filled($result['suggestion'] ?? null)) {
                    $replyText = trim((string) $result['suggestion']);
                }
            } catch (\Throwable $e) {
                Log::warning("Error generando respuesta con Guardia IA: " . $e->getMessage());
            }
        }

        // 4. Si se obtuvo una respuesta válida con base de conocimiento, enviarla al cliente
        if (filled($replyText)) {
            $outbound = $this->dispatchOutboundMessage($conversation, $replyText);
            Log::info("Guardia IA respondió con éxito a conversación {$conversation->id} usando RAG.");
            return $outbound;
        }

        // 5. PROTOCOLO DE FALLBACK: La IA no sabe la respuesta con certeza
        // 5.1 Enviar mensaje de cortesía seguro al cliente (sin alucinaciones)
        $fallbackMessage = trim((string) ($config->ai_fallback_message ?: BusinessHoursConfig::defaultAiFallbackMessage()));
        $outbound = $this->dispatchOutboundMessage($conversation, $fallbackMessage);

        // 5.2 Escalación al Administrador / Supervisor
        if ($config->ai_escalation_enabled) {
            $this->escalateMissingInformation($conversation, $config, $queryText);
        }

        Log::info("Guardia IA activó fallback y escalación para conversación {$conversation->id}");

        return $outbound;
    }

    protected function dispatchOutboundMessage(Conversation $conversation, string $text): Message
    {
        $outbound = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => null, // Enviado automáticamente por el Guardia IA
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'body' => $text,
            'sent_at' => Carbon::now(),
        ]);

        $conversation->update([
            'last_message_at' => Carbon::now(),
        ]);

        if ($conversation->channel === 'whatsapp') {
            try {
                if (config('crm.dispatch_mode', 'sync') === 'sync' || app()->environment('testing')) {
                    SendWhatsAppMessageJob::dispatchSync($outbound->id);
                } else {
                    SendWhatsAppMessageJob::dispatch($outbound->id);
                }
            } catch (\Throwable $e) {
                Log::error("Error despachando SendWhatsAppMessageJob en Guardia IA: " . $e->getMessage());
            }
        }

        try {
            event(new TicketMessageCreatedEvent($outbound));
        } catch (\Throwable $e) {
            Log::warning("Error emitiendo TicketMessageCreatedEvent en Guardia IA: " . $e->getMessage());
        }

        return $outbound;
    }

    protected function escalateMissingInformation(Conversation $conversation, BusinessHoursConfig $config, string $queryText): void
    {
        $contactName = $conversation->contact?->name ?? 'Cliente';
        $contactPhone = $conversation->contact?->phone ?? 'Sin número';

        // 1. Notificación interna en el CRM (Campana de Notificaciones)
        try {
            $this->notificationService->createNotification(
                organizationId: $conversation->organization_id,
                title: '⚠️ Guardia IA: Consulta sin respuesta en Base de Conocimiento',
                message: "El contacto {$contactName} ({$contactPhone}) consultó fuera de turno: \"{$queryText}\". La IA no encontró datos en el manual.",
                type: 'ai_escalation',
                userId: null,
                actionUrl: "/app/conversations?id={$conversation->id}",
                data: [
                    'conversation_id' => $conversation->id,
                    'contact_name' => $contactName,
                    'contact_phone' => $contactPhone,
                    'query' => $queryText,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("Error creando notificación interna de escalación: " . $e->getMessage());
        }

        // 2. Alerta vía WhatsApp al número del supervisor/administrador si está configurado
        $adminPhone = preg_replace('/[^\d+]/', '', (string) $config->ai_escalation_phone);
        if (filled($adminPhone) && $conversation->channel === 'whatsapp' && $conversation->whatsapp_account_id) {
            try {
                $adminContact = Contact::firstOrCreate(
                    [
                        'organization_id' => $conversation->organization_id,
                        'phone' => $adminPhone,
                    ],
                    [
                        'first_name' => 'Supervisor/Admin',
                        'status' => 'active',
                    ]
                );

                $adminConv = Conversation::firstOrCreate(
                    [
                        'organization_id' => $conversation->organization_id,
                        'contact_id' => $adminContact->id,
                        'whatsapp_account_id' => $conversation->whatsapp_account_id,
                    ],
                    [
                        'channel' => 'whatsapp',
                        'status' => 'closed',
                    ]
                );

                $alertBody = "⚠️ *Alerta Guardia IA*\n\n"
                    . "El cliente *{$contactName}* ({$contactPhone}) consultó fuera de horario:\n"
                    . "\"_{$queryText}_\"\n\n"
                    . "ℹ️ *No se encontró información suficiente en la Base de Conocimiento ni Notas.* Se requiere que cargues la información oficial o contactes al cliente.";

                $adminMsg = Message::create([
                    'organization_id' => $conversation->organization_id,
                    'conversation_id' => $adminConv->id,
                    'direction' => 'outbound',
                    'message_type' => 'text',
                    'body' => $alertBody,
                    'message_status' => 'pending',
                    'delivery_status' => 'pending',
                    'sent_at' => Carbon::now(),
                ]);

                if (config('crm.dispatch_mode', 'sync') === 'sync' || app()->environment('testing')) {
                    SendWhatsAppMessageJob::dispatchSync($adminMsg->id);
                } else {
                    SendWhatsAppMessageJob::dispatch($adminMsg->id);
                }
            } catch (\Throwable $e) {
                Log::warning("Error despachando alerta WhatsApp de escalación al admin: " . $e->getMessage());
            }
        }
    }
}
