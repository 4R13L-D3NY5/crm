<?php

namespace App\Modules\Parameters\Actions;

use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DispatchOutOfHoursAutoReplyAction
{
    public function execute(Conversation $conversation): ?Message
    {
        // 1. Obtener la configuración de horario aplicable:
        // a) Si la conversación tiene un operador asignado, verificar si tiene horario diferenciado
        $config = null;
        $operatorId = $conversation->assigned_to_user_id ?? $conversation->user_id;
        if (!empty($operatorId)) {
            $config = BusinessHoursConfig::where('organization_id', $conversation->organization_id)
                ->where('is_active', true)
                ->where('scope_type', 'operator')
                ->whereHas('users', fn($q) => $q->where('users.id', $operatorId))
                ->first();
        }

        // b) Si no tiene horario por operador, buscar si hay una campaña/temporada vigente para hoy
        if (!$config) {
            $nowDate = Carbon::now('America/La_Paz')->toDateString();
            $config = BusinessHoursConfig::where('organization_id', $conversation->organization_id)
                ->where('is_active', true)
                ->where('scope_type', 'general')
                ->where('validity_type', 'date_range')
                ->where('start_date', '<=', $nowDate)
                ->where('end_date', '>=', $nowDate)
                ->first();
        }

        // c) Si no hay campaña, buscar el horario general por defecto
        if (!$config) {
            $config = BusinessHoursConfig::where('organization_id', $conversation->organization_id)
                ->where('is_active', true)
                ->where('scope_type', 'general')
                ->orderByDesc('is_default')
                ->first();
        }

        // d) Respaldo final: cualquier horario activo de la organización
        if (!$config) {
            $config = BusinessHoursConfig::where('organization_id', $conversation->organization_id)
                ->where('is_active', true)
                ->first();
        }

        if (!$config || !$config->is_active || !$config->auto_reply_enabled) {
            return null;
        }

        $messageText = trim((string) $config->auto_reply_message);
        if (empty($messageText)) {
            return null;
        }

        // 2. Verificar si está dentro del horario laboral
        $tz = $config->timezone ?: 'America/La_Paz';
        $now = Carbon::now($tz);

        if ($config->isWithinHours($now)) {
            // Está dentro del horario de atención, no corresponde auto-respuesta
            return null;
        }

        // 3. Control anti-spam: no repetir la misma auto-respuesta en la conversación si ya se envió recientemente
        $antiSpamMinutes = app()->environment('local') ? 1 : 30;
        $alreadySentRecently = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('body', $messageText)
            ->where('created_at', '>=', now()->subMinutes($antiSpamMinutes))
            ->exists();

        if ($alreadySentRecently) {
            Log::info("Auto-respuesta fuera de horario omitida por deduplicación reciente en conversación {$conversation->id}");
            return null;
        }

        // 4. Crear el mensaje de auto-respuesta en la conversación
        $outboundMsg = Message::create([
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => null, // Enviado automáticamente por el sistema
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_status' => 'pending',
            'delivery_status' => 'pending',
            'body' => $messageText,
            'sent_at' => Carbon::now(),
        ]);

        // 5. Despachar envío según el canal
        if ($conversation->channel === 'whatsapp') {
            try {
                if (config('crm.dispatch_mode', 'sync') === 'sync') {
                    SendWhatsAppMessageJob::dispatchSync($outboundMsg->id);
                } else {
                    SendWhatsAppMessageJob::dispatch($outboundMsg->id);
                }
            } catch (\Throwable $e) {
                Log::error("Error al despachar SendWhatsAppMessageJob para auto-respuesta: " . $e->getMessage());
            }
        }

        // 6. Emitir evento por WebSockets para actualizar en tiempo real el chat en la web
        try {
            event(new TicketMessageCreatedEvent($outboundMsg));
        } catch (\Throwable $e) {
            Log::warning("Error al emitir TicketMessageCreatedEvent: " . $e->getMessage());
        }

        Log::info("Auto-respuesta fuera de horario despachada exitosamente para conversación {$conversation->id}");

        return $outboundMsg;
    }
}
