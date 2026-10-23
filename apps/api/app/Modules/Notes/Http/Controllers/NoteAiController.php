<?php

namespace App\Modules\Notes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Tag;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notes\Http\Requests\RefineNoteAiRequest;
use App\Modules\Notes\Http\Resources\NoteResource;
use App\Modules\Notes\Models\Note;
use App\Modules\Notes\Services\NoteEntityAnalyzerService;
use App\Modules\Notes\Services\NoteSimilarityService;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteAiController extends Controller
{
    public function __construct(
        protected HentleAiCopilotService $aiCopilotService,
        protected NoteSimilarityService $similarityService,
        protected NoteEntityAnalyzerService $entityAnalyzer,
    ) {}

    public function similar(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $note = Note::query()
            ->where('notes.organization_id', $organizationId)
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags'])
            ->findOrFail($id);

        $limit = max(1, min(10, (int) $request->query('limit', 5)));
        $mode = (string) $request->query('mode', 'auto');
        $similarNotes = $this->similarityService->findSimilarForNote($note, $user, $limit, $mode);

        return response()->json([
            'data' => $similarNotes,
            'target_note' => (new NoteResource($note))->resolve(),
            'total' => count($similarNotes),
        ]);
    }

    public function similarDraft(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:2'],
            'title' => ['nullable', 'string', 'max:255'],
            'exclude_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
            'mode' => ['nullable', 'string', 'in:auto,ai,algorithmic'],
        ]);

        $limit = (int) ($validated['limit'] ?? 5);
        $mode = (string) ($validated['mode'] ?? 'auto');
        $similarNotes = $this->similarityService->findSimilarForDraft(
            content: $validated['content'],
            title: $validated['title'] ?? null,
            user: $user,
            excludeId: $validated['exclude_id'] ?? null,
            limit: $limit,
            mode: $mode
        );

        return response()->json([
            'data' => $similarNotes,
            'total' => count($similarNotes),
        ]);
    }

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
            'tone_formal' => "Eres un redactor profesional. Reescribe el texto con un tono formal, elegante, respetuoso y profesional, ideal para atención corporativa y ejecutiva. Devuelve ÚNICAMENTE el texto reescrito.",
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

    public function similarMatrix(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:auto,ai,algorithmic'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $mode = (string) ($validated['mode'] ?? 'algorithmic');
        $search = $validated['search'] ?? null;
        $categoryId = $validated['category_id'] ?? null;
        $limit = (int) ($validated['limit'] ?? 25);

        $result = $this->similarityService->findSimilarMatrix(
            user: $user,
            mode: $mode,
            search: $search,
            categoryId: $categoryId,
            limit: $limit
        );

        return response()->json([
            'data' => $result['pairs'],
            'total' => $result['total_pairs'],
            'total_scanned' => $result['total_scanned'],
            'mode' => $result['mode'],
        ]);
    }

    public function suggestEntities(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'string', 'in:heuristic,ai'],
        ]);

        $mode = $validated['mode'] ?? 'ai';
        $title = $validated['title'] ?? '';
        $content = $validated['content'];
        $combinedText = trim("{$title} {$content}");

        // 1. Detección heurística de base
        $heuristicResult = $this->entityAnalyzer->analyze($combinedText, $organizationId);

        if ($mode === 'heuristic') {
            return response()->json([
                'categories' => $heuristicResult['detected_categories'],
                'custom_statuses' => $heuristicResult['detected_custom_statuses'],
                'tags' => $heuristicResult['detected_tags'],
                'total' => $heuristicResult['total_detected'],
                'source' => 'heuristic',
                'message' => 'Entidades detectadas por coincidencia léxica directa.',
            ]);
        }

        // 2. Si se solicitó con IA, validar configuración
        $config = $this->aiCopilotService->getEffectiveConfig($organizationId);

        if (empty($config['api_key'])) {
            return response()->json([
                'categories' => $heuristicResult['detected_categories'],
                'custom_statuses' => $heuristicResult['detected_custom_statuses'],
                'tags' => $heuristicResult['detected_tags'],
                'total' => $heuristicResult['total_detected'],
                'source' => 'heuristic_fallback',
                'message' => 'Entidades detectadas por coincidencia en texto (IA no configurada en el espacio de trabajo).',
            ]);
        }

        // Preseleccionar catálogo relevante (máx 35 categorías para proteger ventana de contexto y tokens)
        $heuristicCatIds = array_column($heuristicResult['detected_categories'], 'id');
        $normText = $this->entityAnalyzer->normalize($combinedText);
        $tokens = preg_split('/\s+/', trim($normText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);

        $candidateCatsQuery = Category::where('organization_id', $organizationId);
        $candidateCatsQuery->where(function ($q) use ($tokens, $heuristicCatIds) {
            if (!empty($heuristicCatIds)) {
                $q->whereIn('id', $heuristicCatIds);
            }
            foreach (array_slice($tokens, 0, 8) as $t) {
                $q->orWhere('name', 'ilike', "%{$t}%")
                  ->orWhere('code', 'ilike', "%{$t}%");
            }
        });
        $candidateCats = $candidateCatsQuery->limit(35)->get();

        // Asegurar que las sedes principales siempre estén presentes en el catálogo para selección de sede
        $sedes = Category::where('organization_id', $organizationId)
            ->where(function ($q) {
                $q->where('name', 'ilike', 'Sede %')
                  ->orWhereNull('parent_id');
            })
            ->limit(10)
            ->get();
        $candidateCats = $candidateCats->merge($sedes)->unique('id');

        $statuses = CustomStatus::where('organization_id', $organizationId)->get();
        $tags = Tag::where('organization_id', $organizationId)->get();

        $catsList = $candidateCats->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'display_path' => $c->display_path,
        ])->values()->all();
        $statusesList = $statuses->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()->all();
        $tagsList = $tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all();

        $catsJson = json_encode($catsList, JSON_UNESCAPED_UNICODE);
        $statusesJson = json_encode($statusesList, JSON_UNESCAPED_UNICODE);
        $tagsJson = json_encode($tagsList, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Eres un clasificador inteligente para un CRM institucional.
Analiza la siguiente NOTA y selecciona del CATÁLOGO las categorías, estados de lead o etiquetas que correspondan semánticamente a su temática o propósito.

NOTA:
Título: {$title}
Contenido: {$content}

CATÁLOGO:
Categorías (con rutas de sede y facultad):
{$catsJson}

Estados de Lead:
{$statusesJson}

Etiquetas:
{$tagsJson}

INSTRUCCIONES:
1. Elige únicamente los IDs de las entidades que aplican a la temática de la nota.
2. Si una categoría, estado o etiqueta no aplica, deja su array vacío [].
3. NO inventes ningún ID. Usa únicamente los IDs presentes en el catálogo.
4. Devuelve ÚNICAMENTE un objeto JSON válido con esta estructura:
{
  "category_ids": ["ID_1"],
  "custom_status_ids": ["ID_2"],
  "tag_ids": ["ID_3"],
  "reason": "Explicación breve de la clasificación (máx 15 palabras)"
}
PROMPT;

        try {
            $rawResponse = $this->aiCopilotService->sendDirectPrompt(
                prompt: $prompt,
                provider: $config['provider'],
                apiKey: $config['api_key'],
                model: $config['model'],
                baseUrl: $config['base_url'],
                systemInstruction: 'Responde siempre y exclusivamente en JSON sin markdown ni saludos.',
                organizationId: $organizationId,
                bypassBudget: true
            );

            $clean = trim($rawResponse);
            if (str_starts_with($clean, '```json')) {
                $clean = substr($clean, 7);
            }
            if (str_starts_with($clean, '```')) {
                $clean = substr($clean, 3);
            }
            if (str_ends_with($clean, '```')) {
                $clean = substr($clean, 0, -3);
            }

            $decoded = json_decode(trim($clean), true);

            if (is_array($decoded)) {
                $catIds = (array) ($decoded['category_ids'] ?? []);
                $stIds = (array) ($decoded['custom_status_ids'] ?? []);
                $tagIds = (array) ($decoded['tag_ids'] ?? []);

                // Fusión con las coincidencias del analizador heurístico
                $heuristicCatIds = array_column($heuristicResult['detected_categories'], 'id');
                $heuristicStIds = array_column($heuristicResult['detected_custom_statuses'], 'id');
                $heuristicTagIds = array_column($heuristicResult['detected_tags'], 'id');

                $allCatIds = array_values(array_unique(array_merge($catIds, $heuristicCatIds)));
                $allStIds = array_values(array_unique(array_merge($stIds, $heuristicStIds)));
                $allTagIds = array_values(array_unique(array_merge($tagIds, $heuristicTagIds)));

                $catsById = Category::whereIn('id', $allCatIds)->get()->keyBy('id');
                $stById = $statuses->keyBy('id');
                $tagsById = $tags->keyBy('id');

                $matchedCats = [];
                foreach ($allCatIds as $id) {
                    if (isset($catsById[$id])) {
                        $c = $catsById[$id];
                        $matchedCats[] = [
                            'id' => $c->id,
                            'parent_id' => $c->parent_id,
                            'name' => $c->name,
                            'code' => $c->code,
                            'color' => $c->color,
                            'icon' => $c->icon,
                            'slug' => $c->slug,
                            'display_path' => $c->display_path,
                            'full_path' => $c->full_path,
                        ];
                    }
                }

                $matchedStatuses = [];
                foreach ($allStIds as $id) {
                    if (isset($stById[$id])) {
                        $s = $stById[$id];
                        $matchedStatuses[] = [
                            'id' => $s->id,
                            'name' => $s->name,
                            'color' => $s->color,
                            'icon' => $s->icon,
                            'slug' => $s->slug,
                        ];
                    }
                }

                $matchedTags = [];
                foreach ($allTagIds as $id) {
                    if (isset($tagsById[$id])) {
                        $t = $tagsById[$id];
                        $matchedTags[] = [
                            'id' => $t->id,
                            'name' => $t->name,
                            'color_hex' => $t->color_hex,
                            'slug' => $t->slug,
                        ];
                    }
                }

                return response()->json([
                    'categories' => $matchedCats,
                    'custom_statuses' => $matchedStatuses,
                    'tags' => $matchedTags,
                    'total' => count($matchedCats) + count($matchedStatuses) + count($matchedTags),
                    'reason' => $decoded['reason'] ?? 'Entidades sugeridas con IA según el tema.',
                    'source' => 'ia',
                    'message' => 'Entidades sugeridas con IA exitosamente.',
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Sugerencia de entidades con IA falló: {$e->getMessage()}");
        }

        // Fallback si la IA falla o da formato inesperado
        return response()->json([
            'categories' => $heuristicResult['detected_categories'],
            'custom_statuses' => $heuristicResult['detected_custom_statuses'],
            'tags' => $heuristicResult['detected_tags'],
            'total' => $heuristicResult['total_detected'],
            'source' => 'heuristic_fallback',
            'message' => 'Entidades detectadas por heurística (fallback).',
        ]);
    }
}
