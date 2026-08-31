<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditSummaryController extends Controller
{
    /**
     * Retorna resumen de métricas de seguridad y eventos de auditoría
     */
    public function summary(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $totalEvents = AuditLog::where('organization_id', $organization->id)->count();

        $supervisorGhostEvents = AuditLog::where('organization_id', $organization->id)
            ->where('event', 'supervisor.ghost_message')
            ->count();

        $ticketTransfers = AuditLog::where('organization_id', $organization->id)
            ->where('event', 'ticket.transferred')
            ->count();

        $securityAlerts = AuditLog::where('organization_id', $organization->id)
            ->whereIn('event', ['auth.failed', 'permission.denied', 'export.triggered'])
            ->count();

        return response()->json([
            'data' => [
                'total_events' => $totalEvents,
                'supervisor_interventions' => $supervisorGhostEvents,
                'ticket_transfers' => $ticketTransfers,
                'security_alerts' => $securityAlerts,
                'breakdown' => [
                    'auth' => AuditLog::where('organization_id', $organization->id)->where('event', 'like', 'auth.%')->count(),
                    'tickets' => AuditLog::where('organization_id', $organization->id)->where('event', 'like', 'ticket.%')->count(),
                    'settings' => AuditLog::where('organization_id', $organization->id)->where('event', 'like', 'settings.%')->count(),
                ],
            ],
            'message' => 'Resumen de auditoría obtenido con éxito.',
        ]);
    }

    /**
     * Exporta registros de auditoría en formato estructurado
     */
    public function export(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $logs = AuditLog::where('organization_id', $organization->id)
            ->with(['user'])
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'user' => $log->user?->name ?? 'Sistema',
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toIso8601String(),
                'metadata' => $log->metadata,
            ]);

        return response()->json([
            'data' => [
                'exported_at' => now()->toIso8601String(),
                'organization' => $organization->name,
                'count' => $logs->count(),
                'records' => $logs,
            ],
            'message' => 'Exportación forense generada correctamente.',
        ]);
    }
}
