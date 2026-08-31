<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SupervisorMessageController extends Controller
{
    public function store(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        // Guardamos el mensaje saliente de WhatsApp manteniendo assigned_to_user_id original
        $message = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $ticket->id,
            'direction' => 'outbound',
            'is_internal' => false,
            'body' => $validated['body'],
            'sent_at' => Carbon::now(),
            'delivery_status' => 'sent',
        ]);

        // Actualizamos last_message_at de la conversación sin cambiar el usuario asignado
        $ticket->update([
            'last_message_at' => Carbon::now(),
            'unread_count' => 0,
        ]);

        return response()->json([
            'data' => $message,
            'assigned_to_user_id' => $ticket->assigned_to_user_id,
            'message' => 'Mensaje enviado en Modo Supervisor (sin desasignar al agente).',
        ], 201);
    }
}
