<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\HentleAi\Services\AiConversationAnalyzerService;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HentleAiCopilotController
{
    public function suggestReply(
        Request $request,
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        $instruction = $request->input('instruction');

        try {
            $result = $copilotService->generateReplySuggestion($conversation, $instruction);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function summarize(
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        try {
            $result = $copilotService->summarizeTicket($conversation);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function analysis(
        Conversation $conversation,
        AiConversationAnalyzerService $analyzerService,
    ): JsonResponse {
        try {
            $analysis = $analyzerService->analyze($conversation);

            // Evaluar y despachar alerta de frustración proactiva a supervisores si aplica
            $analyzerService->checkAndAlertFrustration($conversation, $analysis);

            return response()->json([
                'data' => $analysis,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function applyStatus(
        Request $request,
        Conversation $conversation,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,open,closed,no_interested,lead'],
            'custom_status_id' => ['nullable', 'string'],
        ]);

        $customStatusId = $validated['custom_status_id'] ?? null;
        $status = $validated['status'] ?? null;

        if ($status === 'no_interested') {
            $conversation->status = 'closed';
            $conversation->closed_at = now();
        } elseif ($status && in_array($status, ['pending', 'open', 'closed'])) {
            $conversation->status = $status;
        }

        if ($customStatusId) {
            $conversation->custom_status_id = $customStatusId;
            if ($conversation->contact) {
                $conversation->contact->update([
                    'custom_status_id' => $customStatusId,
                ]);
            }
        }

        $conversation->save();

        return response()->json([
            'message' => 'Estado actualizado exitosamente según recomendación de IA.',
            'data' => [
                'conversation_id' => $conversation->id,
                'status' => $conversation->status,
                'custom_status_id' => $conversation->custom_status_id,
            ],
        ]);
    }

    public function applyTags(
        Request $request,
        Conversation $conversation,
    ): JsonResponse {
        $validated = $request->validate([
            'tags' => ['required', 'array'],
            'tags.*' => ['required', 'string', 'max:50'],
        ]);

        $contact = $conversation->contact;
        if (! $contact) {
            return response()->json(['message' => 'La conversación no tiene contacto asociado.'], 422);
        }

        $attached = [];
        foreach ($validated['tags'] as $tagName) {
            $cleanName = trim($tagName);
            $slug = Str::slug($cleanName);

            $tag = Tag::firstOrCreate(
                [
                    'organization_id' => $conversation->organization_id,
                    'slug' => $slug,
                ],
                [
                    'name' => $cleanName,
                    'color_hex' => '#0d9488',
                ]
            );

            $contact->tags()->syncWithoutDetaching([$tag->id]);
            $attached[] = $tag;
        }

        return response()->json([
            'message' => 'Etiquetas aplicadas exitosamente al contacto.',
            'data' => $attached,
        ]);
    }

    public function applyContactData(
        Request $request,
        Conversation $conversation,
    ): JsonResponse {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'ci' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $contact = $conversation->contact;
        if (! $contact) {
            return response()->json(['message' => 'La conversación no tiene contacto asociado.'], 422);
        }

        if (! empty($validated['first_name'])) {
            $contact->first_name = trim($validated['first_name']);
        }
        if (! empty($validated['last_name'])) {
            $contact->last_name = trim($validated['last_name']);
        }
        if (! empty($validated['email'])) {
            $contact->email = trim($validated['email']);
        }

        $customFields = $contact->custom_fields ?? [];
        if (! empty($validated['ci'])) {
            $customFields['ci'] = trim($validated['ci']);
        }
        if (! empty($validated['city'])) {
            $customFields['city'] = trim($validated['city']);
        }
        if (! empty($validated['alternative_phone'])) {
            $customFields['alternative_phone'] = trim($validated['alternative_phone']);
        }

        $contact->custom_fields = $customFields;
        $contact->save();

        return response()->json([
            'message' => 'Ficha del contacto actualizada con los datos extraídos por IA.',
            'data' => $contact->fresh(),
        ]);
    }
}
