<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    /**
     * Lista las notificaciones del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 30);
        $type = $request->input('type');
        $unreadOnly = $request->boolean('unread_only', false);

        $data = $this->notificationService->getForUser($request->user(), $limit, $type, $unreadOnly);

        return response()->json($data);
    }

    /**
     * Marca una notificación como leída.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $updated = $this->notificationService->markAsRead($id, $request->user());

        return response()->json([
            'success' => $updated,
            'message' => $updated ? 'Notificación marcada como leída.' : 'No se pudo actualizar.',
        ]);
    }

    /**
     * Marca todas las notificaciones como leídas.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'updated_count' => $count,
            'message' => 'Todas las notificaciones han sido marcadas como leídas.',
        ]);
    }

    /**
     * Elimina una notificación.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->notificationService->delete($id, $request->user());

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Notificación eliminada.' : 'No se encontró la notificación.',
        ]);
    }

    /**
     * Genera una notificación de prueba para verificar campana y alertas emergentes.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();
        $notif = $this->notificationService->createNotification(
            organizationId: $user->current_organization_id,
            title: 'Mensaje no leído: Pedro Antonio Chura (Prueba)',
            message: 'Tiene 1 mensaje(s) nuevo(s) sin abrir. Canal: Escuela De Postgrado Unitepc • 📌 Publicación #10293847. Asignado a: Sin asignar.',
            type: 'unread',
            userId: $user->id,
            actionUrl: '/app/conversations',
            data: [
                'is_test' => true,
                'channel' => 'facebook',
                'channel_name' => 'Escuela De Postgrado Unitepc',
                'channel_number' => 'Fanpage Oficial',
                'is_from_post' => true,
                'post_id' => '10293847',
                'post_url' => 'https://facebook.com/10293847',
                'origin_label' => 'Publicación #10293847',
                'contact_name' => 'Pedro Antonio Chura',
                'assignee_name' => 'Sin asignar',
                'phone' => '+591 79326793',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación de prueba generada exitosamente.',
            'notification' => [
                'id' => $notif->id,
                'type' => $notif->type,
                'title' => $notif->title,
                'message' => $notif->message,
                'action_url' => $notif->action_url,
                'data' => $notif->data,
                'is_read' => false,
                'created_at_human' => 'Ahora mismo',
            ],
        ]);
    }
}
