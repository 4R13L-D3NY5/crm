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
}
