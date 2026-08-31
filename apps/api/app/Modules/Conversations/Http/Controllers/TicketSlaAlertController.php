<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TicketSlaAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $settings = WorkspaceSetting::where('organization_id', $organization->id)->first();
        $slaMinutes = $settings?->sla_timeout_minutes ?? 10;

        $thresholdTime = Carbon::now()->subMinutes($slaMinutes);

        // Tickets no cerrados cuyo último mensaje fue hace más de N minutos y tienen mensajes entrantes pendientes
        $unansweredTickets = Conversation::where('organization_id', $organization->id)
            ->whereIn('status', ['open', 'pending'])
            ->where(function ($q) use ($thresholdTime) {
                $q->where('last_message_at', '<=', $thresholdTime)
                  ->orWhereNull('last_message_at');
            })
            ->with(['contact', 'assignedToUser', 'queue'])
            ->latest('last_message_at')
            ->get()
            ->map(function ($ticket) use ($slaMinutes) {
                $minutesWaiting = $ticket->last_message_at
                    ? abs((int) Carbon::parse($ticket->last_message_at)->diffInMinutes(Carbon::now()))
                    : $slaMinutes;

                return [
                    'id' => $ticket->id,
                    'contact_name' => $ticket->contact?->name ?? 'Contacto',
                    'contact_phone' => $ticket->contact?->phone,
                    'status' => $ticket->status,
                    'assigned_to' => $ticket->assignedToUser?->name ?? 'Sin asignar',
                    'queue' => $ticket->queue?->name ?? 'Sin departamento',
                    'minutes_waiting' => $minutesWaiting,
                    'sla_exceeded' => $minutesWaiting >= $slaMinutes,
                    'last_message_at' => $ticket->last_message_at,
                ];
            });

        return response()->json([
            'data' => $unansweredTickets,
            'sla_timeout_minutes' => $slaMinutes,
            'total_alerts' => $unansweredTickets->count(),
        ]);
    }
}
