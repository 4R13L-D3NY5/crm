<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TicketLifecycleController extends Controller
{
    /**
     * Aceptar ticket de la cola (asignar al usuario logueado y pasar a 'open')
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $ticket->update([
            'status' => 'open',
            'assigned_to_user_id' => $request->user()->id,
            'unread_count' => 0,
        ]);

        return response()->json([
            'data' => $ticket->load(['assignedToUser', 'contact', 'queue']),
            'message' => 'Ticket aceptado y asignado exitosamente.',
        ]);
    }

    /**
     * Transferir ticket a otro departamento y/o usuario
     */
    public function transfer(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'queue_id' => ['nullable', 'string', 'exists:queues,id'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->update([
            'queue_id' => $validated['queue_id'] ?? $ticket->queue_id,
            'assigned_to_user_id' => $validated['user_id'] ?? null,
            'status' => $validated['user_id'] ? 'open' : 'pending',
        ]);

        // Registrar nota interna automática si se especificó
        if (!empty($validated['note'])) {
            Message::create([
                'organization_id' => $organization->id,
                'conversation_id' => $ticket->id,
                'direction' => 'outbound',
                'is_internal' => true,
                'body' => "🔄 Transferido: {$validated['note']}",
                'sent_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'data' => $ticket->load(['assignedToUser', 'contact', 'queue']),
            'message' => 'Ticket transferido exitosamente.',
        ]);
    }

    /**
     * Finalizar / Cerrar ticket
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'rating' => $validated['rating'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        return response()->json([
            'data' => $ticket,
            'message' => 'Ticket finalizado exitosamente.',
        ]);
    }

    /**
     * Reabrir ticket
     */
    public function reopen(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $ticket->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return response()->json([
            'data' => $ticket,
            'message' => 'Ticket reabierto exitosamente.',
        ]);
    }
}
