<?php

namespace App\Modules\Notes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notes\Http\Requests\RefineNoteAiRequest;
use Illuminate\Http\JsonResponse;

class NoteAiController extends Controller
{
    public function __construct(
        protected HentleAiCopilotService $aiCopilotService,
    ) {}

    public function refine(RefineNoteAiRequest $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;
        $content = $request->validated('content');
        $action = $request->validated('action');
        $customInstruction = $request->validated('instruction');

        $effectiveConfig = $this->aiCopilotService->getEffectiveConfig($organizationId);

        if (empty($effectiveConfig['api_key'])) {
            return response()->json([
                'message' => 'No hay una clave de API de IA configurada para la organización. Configúrala en Ajustes > Inteligencia Artificial.',
                'code' => 'AI_CONFIG_MISSING',
            ], 422);
        }

        $systemPrompt = match ($action) {
            'spellcheck' => "Eres un corrector de estilo y ortografía profesional en español. Revisa y corrige el siguiente texto corrigiendo faltas de ortografía, tildes, signos de puntuación y gramática. Conserva intactos los enlaces, números o datos clave. Devuelve ÚNICAMENTE el texto corregido, sin explicaciones ni saludos.",
            'summarize' => "Eres un redactor profesional de notas ejecutivas. Resume y sintetiza el texto de forma ágil, clara y directa, conservando todos los requisitos o datos indispensables. Devuelve ÚNICAMENTE el texto sintetizado, sin notas adicionales.",
            'tone_formal' => "Eres un redactor institucional. Reescribe el texto con un tono formal, elegante, respetuoso y profesional, ideal para atención corporativa o académica. Devuelve ÚNICAMENTE el texto reescrito.",
            'tone_persuasive' => "Eres un especialista en ventas y conversión. Reescribe el texto con un tono persuasivo, entusiasta y convincente, resaltando el valor y motivando a la acción. Devuelve ÚNICAMENTE el texto persuasivo.",
            'custom' => "Eres un asistente de redacción. Aplica la siguiente instrucción: '{$customInstruction}'. Devuelve ÚNICAMENTE el texto resultante.",
        };

        try {
            $refinedText = $this->aiCopilotService->sendDirectPrompt(
                prompt: $content,
                provider: $effectiveConfig['provider'],
                apiKey: $effectiveConfig['api_key'],
                model: $effectiveConfig['model'],
                baseUrl: $effectiveConfig['base_url'],
                systemInstruction: $systemPrompt,
                organizationId: $organizationId,
            );

            return response()->json([
                'data' => [
                    'original_content' => $content,
                    'refined_content' => $refinedText,
                    'action' => $action,
                    'provider' => $effectiveConfig['provider'],
                ],
                'message' => 'Texto ajustado con IA exitosamente.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al procesar la solicitud con IA: ' . $e->getMessage(),
                'code' => 'AI_GENERATION_FAILED',
            ], 500);
        }
    }
}
