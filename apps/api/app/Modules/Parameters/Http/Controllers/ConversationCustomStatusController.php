<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ConversationCustomStatusController extends Controller
{
    public function update(Request $request, string $conversationId): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);

        $validated = $request->validate([
            'custom_status_id' => ['required', 'string', 'exists:custom_statuses,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $targetStatus = CustomStatus::where('organization_id', $organization->id)
            ->findOrFail($validated['custom_status_id']);

        $currentStatus = $conversation->customStatus;

        // Validar regla de dependencias de la máquina de estados
        if ($currentStatus && !$currentStatus->canTransitionTo($targetStatus)) {
            $requiredNames = $targetStatus->allowedPreviousStatuses->pluck('name')->implode(', ');
            return response()->json([
                'message' => "Transición no permitida: Para avanzar a '{$targetStatus->name}', el lead debe provenir de: [{$requiredNames}].",
            ], 422);
        }

        $oldStatusName = $currentStatus?->name ?? 'Sin estado';

        $conversation->update([
            'custom_status_id' => $targetStatus->id,
        ]);

        if ($conversation->contact_id) {
            $conversation->contact?->update([
                'custom_status_id' => $targetStatus->id,
            ]);
        }

        // Registrar nota interna en el timeline del chat
        $noteText = "📌 *Estado del Lead actualizado*: {$oldStatusName} ➔ *{$targetStatus->name}*";
        if (!empty($validated['note'])) {
            $noteText .= "\n_Nota: {$validated['note']}_";
        }

        Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'direction' => 'internal',
            'is_internal' => true,
            'message_type' => 'text',
            'body' => $noteText,
            'sent_at' => Carbon::now(),
        ]);

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'custom_status' => [
                    'id' => $targetStatus->id,
                    'name' => $targetStatus->name,
                    'color' => $targetStatus->color,
                    'icon' => $targetStatus->icon,
                    'stage_type' => $targetStatus->stage_type,
                ],
            ],
            'message' => "Estado actualizado a '{$targetStatus->name}' exitosamente.",
        ]);
    }
}
