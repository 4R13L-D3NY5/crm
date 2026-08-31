<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Services\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceTranscriptionController extends Controller
{
    /**
     * Transcribe un mensaje de audio específico a texto
     */
    public function transcribe(
        Request $request,
        string $conversationId,
        string $messageId,
        VoiceTranscriptionService $service
    ): JsonResponse {
        $organization = $request->user()->currentOrganization;

        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);
        $message = Message::where('conversation_id', $conversation->id)->findOrFail($messageId);

        $transcribedMessage = $service->transcribe($message);

        return response()->json([
            'data' => (new MessageResource($transcribedMessage))->resolve(),
            'message' => 'Audio transcrito exitosamente.',
        ]);
    }

    /**
     * Sintetiza y envía una nota de voz saliente a partir de texto
     */
    public function synthesize(
        Request $request,
        string $conversationId,
        VoiceTranscriptionService $service
    ): JsonResponse {
        $organization = $request->user()->currentOrganization;

        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $message = $service->synthesize($conversation, $validated['text'], $request->user());

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Nota de voz sintetizada y enviada con éxito.',
        ], 201);
    }
}
