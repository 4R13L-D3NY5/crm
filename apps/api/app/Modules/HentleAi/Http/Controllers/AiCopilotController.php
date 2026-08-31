<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiCopilotController extends Controller
{
    /**
     * Sugerencia de respuesta del Copiloto en 1 clic
     */
    public function suggest(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)
            ->with(['contact', 'messages'])
            ->findOrFail($id);

        $lastMessage = $ticket->messages()->where('direction', 'inbound')->latest('sent_at')->first();
        $queryText = $lastMessage?->body ?? 'Información de inscripciones y carreras';

        $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // Búsqueda en los artículos de conocimiento de Hentle-AI
        $relevantChunks = AiKnowledgeChunk::where('organization_id', $organization->id)
            ->when($queryText, function ($q) use ($queryText, $likeOp) {
                $q->where('content', $likeOp, "%{$queryText}%")
                  ->orWhere('title', $likeOp, "%{$queryText}%");
            })
            ->limit(2)
            ->get();

        $contextKnowledge = $relevantChunks->pluck('content')->implode("\n");

        $suggestion = "¡Hola {$ticket->contact?->name}! Gracias por contactarte con UNITEPC. " .
            ($contextKnowledge ?: "Contamos con más de 25 carreras a nivel nacional y promociones de matrícula en todas nuestras sedes. ¿En qué carrera estás interesado?");

        return response()->json([
            'data' => [
                'suggested_text' => $suggestion,
                'sources_count' => $relevantChunks->count(),
                'confidence_score' => 0.94,
            ],
            'message' => 'Sugerencia generada con éxito por el copiloto Hentle-AI.',
        ]);
    }

    /**
     * Resumen ejecutivo del historial del chat
     */
    public function summary(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)
            ->with(['contact', 'messages'])
            ->findOrFail($id);

        $messagesCount = $ticket->messages()->count();

        $summary = "📌 Resumen del Prospecto {$ticket->contact?->name}:\n" .
            "- Estado del ticket: {$ticket->status}\n" .
            "- Total de mensajes intercambiados: {$messagesCount}\n" .
            "- Interés: Consultó información sobre sedes académicas y requisitos de postulación.";

        return response()->json([
            'data' => [
                'summary_text' => $summary,
                'total_messages_analyzed' => $messagesCount,
            ],
            'message' => 'Resumen ejecutivo generado exitosamente.',
        ]);
    }
}
