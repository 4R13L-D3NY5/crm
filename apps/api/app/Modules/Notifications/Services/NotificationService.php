<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Parameters\Models\TimeAlertRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Crea y persiste una nueva notificación manual o de sistema.
     */
    public function createNotification(
        string $organizationId,
        string $title,
        string $message,
        string $type = 'system',
        ?string $userId = null,
        ?string $actionUrl = null,
        ?array $data = null
    ): AppNotification {
        return AppNotification::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
            'read_at' => null,
        ]);
    }

    /**
     * Obtiene el listado consolidado de alertas operativas (SLA / Inactividad / No leídos)
     * y notificaciones del sistema para el usuario en su organización actual.
     */
    public function getForUser(User $user, int $limit = 40, ?string $type = null, bool $unreadOnly = false): array
    {
        $orgId = $user->current_organization_id;
        $now = Carbon::now();

        // 1. Obtener todas las reglas activas de SLA de inactividad de la organización
        $rules = TimeAlertRule::where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        $defaultRule = $rules->firstWhere('apply_to_all_statuses', true) ?? $rules->first();
        $globalAdvisorTimeout = $defaultRule?->user_timeout_minutes ?? 20;
        $globalClientTimeout = $defaultRule?->client_timeout_minutes ?? 120;

        // 2. Traer conversaciones abiertas o pendientes para calcular alertas operativas en tiempo real
        $convQuery = Conversation::where('organization_id', $orgId)
            ->whereIn('status', ['open', 'pending'])
            ->with(['contact', 'assignedToUser', 'latestExternalMessage', 'latestMessage']);

        // Si el usuario es operador/agente (no admin), limitar alertas a sus conversaciones o sin asignar
        if (!$user->isAdmin()) {
            $convQuery->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhereNull('assigned_to_user_id');
            });
        }

        $conversations = $convQuery->latest('last_message_at')->get();

        $operationalAlerts = collect();

        foreach ($conversations as $conv) {
            $contactName = $conv->contact?->name ?? 'Contacto';
            $assigneeName = $conv->assignedToUser?->name ?? 'Sin asignar';
            $latestMsg = $conv->latestExternalMessage ?? $conv->latestMessage;

            // Determinar la regla aplicable a la conversación (específica por estado o global)
            $matchedRule = null;
            if ($conv->custom_status_id) {
                $matchedRule = $rules->first(function ($r) use ($conv) {
                    return !$r->apply_to_all_statuses
                        && is_array($r->custom_status_ids)
                        && in_array($conv->custom_status_id, $r->custom_status_ids, true);
                });
            }
            $matchedRule = $matchedRule ?? $defaultRule;

            $advisorTimeoutMinutes = $matchedRule?->user_timeout_minutes ?? $globalAdvisorTimeout;
            $clientTimeoutMinutes = $matchedRule?->client_timeout_minutes ?? $globalClientTimeout;
            $notifyUser = $matchedRule?->notify_user_inactivity ?? true;
            $notifyClient = $matchedRule?->notify_client_inactivity ?? true;

            // A. Alerta de "No Leídos"
            if ($conv->unread_count > 0) {
                $operationalAlerts->push([
                    'id' => "conv_unread_{$conv->id}",
                    'type' => 'unread',
                    'title' => "Mensaje no leído: {$contactName}",
                    'message' => "Tiene {$conv->unread_count} mensaje(s) nuevo(s) sin abrir. Asignado a: {$assigneeName}.",
                    'action_url' => "/app/conversations?id={$conv->id}",
                    'data' => [
                        'conversation_id' => $conv->id,
                        'unread_count' => $conv->unread_count,
                        'contact_phone' => $conv->contact?->phone,
                    ],
                    'is_read' => false,
                    'read_at' => null,
                    'created_at' => $conv->last_message_at?->toIso8601String() ?? $now->toIso8601String(),
                    'created_at_human' => $conv->last_message_at?->diffForHumans() ?? 'Reciente',
                ]);
            }

            // B. Alerta de SLA: "Sin respuesta del Asesor" (el cliente espera respuesta)
            if ($notifyUser && $latestMsg && $latestMsg->direction === 'inbound') {
                $elapsedMinutes = abs((int) $now->diffInMinutes($latestMsg->created_at));
                if ($elapsedMinutes >= $advisorTimeoutMinutes) {
                    $timeFormatted = $this->formatMinutes($elapsedMinutes);
                    $operationalAlerts->push([
                        'id' => "conv_advisor_{$conv->id}",
                        'type' => 'waiting_advisor',
                        'title' => "Sin respuesta del Asesor: {$contactName}",
                        'message' => "Cliente esperando respuesta hace {$timeFormatted} (SLA superado: {$advisorTimeoutMinutes} min). Operador: {$assigneeName}.",
                        'action_url' => "/app/conversations?id={$conv->id}",
                        'data' => [
                            'conversation_id' => $conv->id,
                            'elapsed_minutes' => $elapsedMinutes,
                            'threshold' => $advisorTimeoutMinutes,
                        ],
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => $latestMsg->created_at?->toIso8601String() ?? $now->toIso8601String(),
                        'created_at_human' => "Hace {$timeFormatted}",
                    ]);
                }
            }

            // C. Alerta de Inactividad: "Sin respuesta del Cliente" (el asesor espera respuesta del cliente)
            if ($notifyClient && $latestMsg && $latestMsg->direction === 'outbound') {
                $elapsedMinutes = abs((int) $now->diffInMinutes($latestMsg->created_at));
                if ($elapsedMinutes >= $clientTimeoutMinutes) {
                    $timeFormatted = $this->formatMinutes($elapsedMinutes);
                    $operationalAlerts->push([
                        'id' => "conv_client_{$conv->id}",
                        'type' => 'waiting_client',
                        'title' => "Sin respuesta del Cliente: {$contactName}",
                        'message' => "Cliente inactivo hace {$timeFormatted} (Límite: {$clientTimeoutMinutes} min). Se sugiere recontactar o archivar.",
                        'action_url' => "/app/conversations?id={$conv->id}",
                        'data' => [
                            'conversation_id' => $conv->id,
                            'elapsed_minutes' => $elapsedMinutes,
                            'threshold' => $clientTimeoutMinutes,
                        ],
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => $latestMsg->created_at?->toIso8601String() ?? $now->toIso8601String(),
                        'created_at_human' => "Hace {$timeFormatted}",
                    ]);
                }
            }
        }

        // 3. Notificaciones persistentes de base de datos
        $dbNotifications = AppNotification::query()
            ->forRecipient($orgId, $user->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (AppNotification $n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'action_url' => $n->action_url,
                    'data' => $n->data,
                    'is_read' => !is_null($n->read_at),
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                    'created_at_human' => $n->created_at?->diffForHumans() ?? 'Reciente',
                ];
            });

        // 4. Unir alertas operativas y notificaciones del sistema
        $combined = $operationalAlerts->concat($dbNotifications);

        // Contadores por categoría operativa
        $unreadCount = $combined->where('is_read', false)->count();
        $countUnreadMsgs = $combined->where('type', 'unread')->where('is_read', false)->count();
        $countWaitingAdvisor = $combined->where('type', 'waiting_advisor')->where('is_read', false)->count();
        $countWaitingClient = $combined->where('type', 'waiting_client')->where('is_read', false)->count();

        // Aplicar filtro si se solicita
        if ($type && $type !== 'all') {
            $combined = $combined->where('type', $type);
        }

        if ($unreadOnly) {
            $combined = $combined->where('is_read', false);
        }

        return [
            'unread_count' => $unreadCount,
            'counts' => [
                'all' => $unreadCount,
                'unread' => $countUnreadMsgs,
                'waiting_advisor' => $countWaitingAdvisor,
                'waiting_client' => $countWaitingClient,
            ],
            'advisor_timeout_minutes' => $globalAdvisorTimeout,
            'client_timeout_minutes' => $globalClientTimeout,
            'notifications' => $combined->values()->take($limit),
        ];
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function markAsRead(string $id, User $user): bool
    {
        // Si es una alerta de conversación virtual
        if (str_starts_with($id, 'conv_unread_')) {
            $convId = str_replace('conv_unread_', '', $id);
            Conversation::where('id', $convId)
                ->where('organization_id', $user->current_organization_id)
                ->update(['unread_count' => 0]);
            return true;
        }

        if (str_starts_with($id, 'conv_')) {
            return true;
        }

        $notification = AppNotification::query()
            ->forRecipient($user->current_organization_id, $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Marca todas las notificaciones pendientes como leídas.
     */
    public function markAllAsRead(User $user): int
    {
        // 1. Limpiar contadores no leídos en conversaciones de la organización
        Conversation::where('organization_id', $user->current_organization_id)
            ->where('unread_count', '>', 0)
            ->update(['unread_count' => 0]);

        // 2. Marcar en base de datos
        return AppNotification::query()
            ->forRecipient($user->current_organization_id, $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Elimina una notificación específica.
     */
    public function delete(string $id, User $user): bool
    {
        if (str_starts_with($id, 'conv_')) {
            return true;
        }

        $notification = AppNotification::query()
            ->forRecipient($user->current_organization_id, $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return false;
        }

        return (bool) $notification->delete();
    }

    /**
     * Formatea minutos en formato legible (ej: 25 min, 2h 15m).
     */
    protected function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $hours = floor($minutes / 60);
        $rem = $minutes % 60;
        return $rem > 0 ? "{$hours}h {$rem}m" : "{$hours}h";
    }
}
