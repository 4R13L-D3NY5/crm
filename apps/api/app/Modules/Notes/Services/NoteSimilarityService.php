<?php

namespace App\Modules\Notes\Services;

use App\Models\User;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Notes\Http\Resources\NoteResource;
use App\Modules\Notes\Models\Note;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NoteSimilarityService
{
    protected const STOP_WORDS = [
        'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no',
        'una', 'su', 'al', 'lo', 'como', 'más', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'sí', 'porque', 'esta',
        'son', 'entre', 'está', 'cuando', 'muy', 'sin', 'sobre', 'ser', 'tiene', 'también', 'me', 'hasta', 'hay',
        'donde', 'quien', 'desde', 'todo', 'nos', 'durante', 'todos', 'uno', 'les', 'ni', 'contra', 'otros', 'ese',
        'eso', 'ante', 'ellos', 'esto', 'mí', 'antes', 'algunos', 'qué', 'unos', 'yo', 'otro', 'otras', 'otra',
        'él', 'tanto', 'esa', 'estos', 'mucho', 'quienes', 'nada', 'muchos', 'cual', 'sea', 'poco', 'ella', 'estar',
        'haber', 'estas', 'estaba', 'estamos', 'algunas', 'algo', 'nosotros', 'mi', 'mis', 'tú', 'te', 'ti', 'tu',
        'tus', 'las', 'los', 'les', 'por', 'favor', 'nota', 'notas', 'info', 'crm',
    ];

    public function __construct(
        protected HentleAiCopilotService $aiCopilotService,
    ) {}

    /**
     * Encuentra notas similares a una nota existente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findSimilarForNote(Note $note, User $user, int $limit = 5, string $mode = 'auto'): array
    {
        $organizationId = $user->current_organization_id;

        $keywords = $this->extractKeywords("{$note->title} {$note->content}");

        $candidatesQuery = Note::query()
            ->where('notes.organization_id', $organizationId)
            ->where('notes.id', '!=', $note->id)
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags']);

        if (!empty($keywords)) {
            $candidatesQuery->where(function ($q) use ($keywords) {
                foreach (array_slice($keywords, 0, 6) as $kw) {
                    $q->orWhere('title', 'ilike', "%{$kw}%")
                      ->orWhere('content', 'ilike', "%{$kw}%")
                      ->orWhere('shortcut', 'ilike', "%{$kw}%")
                      ->orWhereHas('categories', fn ($catQ) => $catQ->where('name', 'ilike', "%{$kw}%"))
                      ->orWhereHas('tags', fn ($tagQ) => $tagQ->where('name', 'ilike', "%{$kw}%"));
                }
            });
        }

        $candidates = $candidatesQuery->limit(60)->get();

        if ($candidates->count() < 15) {
            $recent = Note::query()
                ->where('notes.organization_id', $organizationId)
                ->where('notes.id', '!=', $note->id)
                ->visibleToUser($user, $organizationId)
                ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags'])
                ->orderByDesc('updated_at')
                ->limit(30)
                ->get();
            $candidates = $candidates->merge($recent)->unique('id');
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        return $this->evaluateSimilarity(
            targetTitle: $note->title ?? '',
            targetContent: $note->content,
            targetCategoryNames: $note->categories->pluck('name')->all(),
            targetTagNames: $note->tags->pluck('name')->all(),
            candidates: $candidates,
            organizationId: $organizationId,
            limit: $limit,
            mode: $mode
        );
    }

    /**
     * Compara las notas de la base de conocimiento entre sí para encontrar pares afines o duplicadas.
     *
     * @return array{
     *     pairs: array<int, array<string, mixed>>,
     *     total_pairs: int,
     *     total_scanned: int,
     *     mode: string
     * }
     */
    public function findSimilarMatrix(
        User $user,
        string $mode = 'auto',
        ?string $search = null,
        ?string $categoryId = null,
        int $limit = 25
    ): array {
        $organizationId = $user->current_organization_id;

        $query = Note::query()
            ->where('notes.organization_id', $organizationId)
            ->where('notes.is_archived', false)
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags'])
            ->orderByDesc('updated_at');

        if (!empty($search)) {
            $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (!empty($words)) {
                $query->where(function ($q) use ($words) {
                    foreach ($words as $w) {
                        $q->where(function ($sub) use ($w) {
                            $sub->where('title', 'ilike', "%{$w}%")
                                ->orWhere('content', 'ilike', "%{$w}%");
                        });
                    }
                });
            }
        }

        if (!empty($categoryId)) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        $notes = $query->limit(80)->get();

        if ($notes->count() < 2) {
            return [
                'pairs' => [],
                'total_pairs' => 0,
                'total_scanned' => $notes->count(),
                'mode' => $mode,
            ];
        }

        // Precomputar palabras clave y datos para comparaciones O(N) rápidas
        $preparedNotes = [];
        foreach ($notes as $note) {
            $preparedNotes[$note->id] = [
                'note' => $note,
                'keywords' => $this->extractKeywords("{$note->title} {$note->content}"),
                'title_keywords' => $this->extractKeywords($note->title ?? ''),
                'category_names' => $note->categories->pluck('name')->all(),
                'tag_names' => $note->tags->pluck('name')->all(),
            ];
        }

        $noteIds = $notes->pluck('id')->values()->all();
        $count = count($noteIds);
        $pairs = [];

        // Comparación algorítmica par a par
        for ($i = 0; $i < $count; $i++) {
            $idA = $noteIds[$i];
            $dataA = $preparedNotes[$idA];

            for ($j = $i + 1; $j < $count; $j++) {
                $idB = $noteIds[$j];
                $dataB = $preparedNotes[$idB];

                // 1. Intersección de palabras clave
                $kwA = $dataA['keywords'];
                $kwB = $dataB['keywords'];
                if (empty($kwA) || empty($kwB)) {
                    continue;
                }

                $intersection = array_intersect($kwA, $kwB);
                $union = array_unique(array_merge($kwA, $kwB));
                $jaccard = count($union) > 0 ? (count($intersection) / count($union)) : 0;
                $overlapMin = min(count($kwA), count($kwB));
                $overlapScore = $overlapMin > 0 ? (count($intersection) / $overlapMin) : 0;

                $textScore = ($jaccard * 0.4 + $overlapScore * 0.6) * 70;

                // 2. Categorías compartidas (+20 pts)
                $sharedCats = array_intersect($dataA['category_names'], $dataB['category_names']);
                $catBonus = !empty($sharedCats) ? 20 : 0;

                // 3. Etiquetas compartidas (+15 pts)
                $sharedTags = array_intersect($dataA['tag_names'], $dataB['tag_names']);
                $tagBonus = !empty($sharedTags) ? 15 : 0;

                // 4. Palabras compartidas en título (+15 pts)
                $sharedTitle = array_intersect($dataA['title_keywords'], $dataB['title_keywords']);
                $titleBonus = !empty($sharedTitle) ? 15 : 0;

                $totalScore = (int) round($textScore + $catBonus + $tagBonus + $titleBonus);

                if ($totalScore >= 25) {
                    $matchedKw = array_slice(array_values($intersection), 0, 3);
                    $reasonParts = [];
                    if (!empty($matchedKw)) {
                        $reasonParts[] = 'términos ("' . implode('", "', $matchedKw) . '")';
                    }
                    if (!empty($sharedCats)) {
                        $reasonParts[] = 'categoría (' . reset($sharedCats) . ')';
                    }
                    if (!empty($sharedTags)) {
                        $reasonParts[] = 'etiqueta (' . reset($sharedTags) . ')';
                    }
                    if (!empty($sharedTitle)) {
                        $reasonParts[] = 'título afín';
                    }

                    $reason = !empty($reasonParts)
                        ? 'Coincidencia en ' . implode(' y ', $reasonParts) . '.'
                        : 'Afinidad temática detectada.';

                    $pairs[] = [
                        'id' => "{$idA}_{$idB}",
                        'note_a' => (new NoteResource($dataA['note']))->resolve(),
                        'note_b' => (new NoteResource($dataB['note']))->resolve(),
                        'similarity_percent' => min(98, max(25, $totalScore)),
                        'similarity_reason' => $reason,
                        'source' => 'algorithmic',
                        'matched_keywords' => $matchedKw,
                    ];
                }
            }
        }

        // Ordenar pares de mayor a menor porcentaje de parecido
        usort($pairs, fn ($a, $b) => $b['similarity_percent'] <=> $a['similarity_percent']);
        $topPairs = array_slice($pairs, 0, $limit);

        // Si se solicita modo IA o auto, y hay configuración de IA activa, refinar los mejores pares
        if (($mode === 'ai' || $mode === 'auto') && !empty($topPairs)) {
            $config = $this->aiCopilotService->getEffectiveConfig($organizationId);
            if (!empty($config['api_key'])) {
                try {
                    $aiPairs = $this->enrichPairsWithAi($topPairs, $config, $organizationId);
                    if (!empty($aiPairs)) {
                        return [
                            'pairs' => $aiPairs,
                            'total_pairs' => count($aiPairs),
                            'total_scanned' => $notes->count(),
                            'mode' => 'ia',
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::info("Similitud Matrix con IA falló ({$e->getMessage()}), empleando evaluación algorítmica.");
                }
            }
        }

        return [
            'pairs' => $topPairs,
            'total_pairs' => count($topPairs),
            'total_scanned' => $notes->count(),
            'mode' => 'algorithmic',
        ];
    }

    /**
     * Enriquece con IA los pares candidatos preseleccionados, refinando el porcentaje y redactando la razón.
     *
     * @param array<int, array<string, mixed>> $candidatePairs
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    protected function enrichPairsWithAi(array $candidatePairs, array $config, string $organizationId): array
    {
        $pairsToEvaluate = array_slice($candidatePairs, 0, 10);
        $payload = array_map(function ($item) {
            return [
                'pair_id' => $item['id'],
                'note_a_title' => $item['note_a']['title'] ?? '',
                'note_a_snippet' => Str::limit($item['note_a']['content'] ?? '', 160),
                'note_b_title' => $item['note_b']['title'] ?? '',
                'note_b_snippet' => Str::limit($item['note_b']['content'] ?? '', 160),
            ];
        }, $pairsToEvaluate);

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
Eres un asistente CRM especialista en análisis de duplicidad y relaciones de base de conocimiento.
Evalúa semánticamente los siguientes pares de notas y determina el porcentaje de parecido temático (30% a 99%) y una breve explicación en español (máx 15 palabras) de por qué se relacionan.

Pares de notas:
{$payloadJson}

Devuelve ÚNICAMENTE un array JSON válido con la siguiente estructura:
[
  {
    "pair_id": "ID_DEL_PAR",
    "similarity_percent": 85,
    "reason": "Ambas notas detallan aranceles de medicina y métodos de pago."
  }
]
PROMPT;

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
        if (!is_array($decoded)) {
            return $candidatePairs;
        }

        $aiByPairId = collect($decoded)->keyBy('pair_id');
        $enriched = [];

        foreach ($candidatePairs as $pair) {
            $aiData = $aiByPairId->get($pair['id']);
            if ($aiData) {
                $pair['similarity_percent'] = max(30, min(99, (int) ($aiData['similarity_percent'] ?? $pair['similarity_percent'])));
                $pair['similarity_reason'] = $aiData['reason'] ?? $pair['similarity_reason'];
                $pair['source'] = 'ia';
            }
            $enriched[] = $pair;
        }

        usort($enriched, fn ($a, $b) => $b['similarity_percent'] <=> $a['similarity_percent']);
        return $enriched;
    }

    /**
     * Encuentra notas similares a partir de un borrador de contenido y título.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findSimilarForDraft(
        string $content,
        ?string $title,
        User $user,
        ?string $excludeId = null,
        int $limit = 5,
        string $mode = 'auto'
    ): array {
        $organizationId = $user->current_organization_id;
        $keywords = $this->extractKeywords("{$title} {$content}");

        $query = Note::query()
            ->where('notes.organization_id', $organizationId)
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags']);

        if (!empty($excludeId)) {
            $query->where('notes.id', '!=', $excludeId);
        }

        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach (array_slice($keywords, 0, 6) as $kw) {
                    $q->orWhere('title', 'ilike', "%{$kw}%")
                      ->orWhere('content', 'ilike', "%{$kw}%")
                      ->orWhere('shortcut', 'ilike', "%{$kw}%")
                      ->orWhereHas('categories', fn ($catQ) => $catQ->where('name', 'ilike', "%{$kw}%"))
                      ->orWhereHas('tags', fn ($tagQ) => $tagQ->where('name', 'ilike', "%{$kw}%"));
                }
            });
        }

        $candidates = $query->limit(60)->get();

        if ($candidates->count() < 15) {
            $fallbackQuery = Note::query()
                ->where('notes.organization_id', $organizationId)
                ->visibleToUser($user, $organizationId)
                ->with(['user', 'categories.parent.parent', 'customStatuses', 'tags'])
                ->orderByDesc('updated_at');

            if (!empty($excludeId)) {
                $fallbackQuery->where('notes.id', '!=', $excludeId);
            }

            $recent = $fallbackQuery->limit(30)->get();
            $candidates = $candidates->merge($recent)->unique('id');
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        return $this->evaluateSimilarity(
            targetTitle: $title ?? '',
            targetContent: $content,
            targetCategoryNames: [],
            targetTagNames: [],
            candidates: $candidates,
            organizationId: $organizationId,
            limit: $limit,
            mode: $mode
        );
    }

    /**
     * Orquesta la evaluación de similitud: soporta modo algorítmico explícito,
     * modo IA explícito y modo automático (intenta IA con fallback algorítmico).
     *
     * @param Collection<int, Note> $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function evaluateSimilarity(
        string $targetTitle,
        string $targetContent,
        array $targetCategoryNames,
        array $targetTagNames,
        Collection $candidates,
        string $organizationId,
        int $limit = 5,
        string $mode = 'auto'
    ): array {
        // 1. Si el usuario solicita específicamente similitud léxica/algorítmica
        if ($mode === 'algorithmic') {
            return $this->evaluateAlgorithmically(
                targetTitle: $targetTitle,
                targetContent: $targetContent,
                targetCategoryNames: $targetCategoryNames,
                targetTagNames: $targetTagNames,
                candidates: $candidates,
                limit: $limit
            );
        }

        // 2. Si es modo 'ai' o 'auto', verificar configuración de IA
        $config = $this->aiCopilotService->getEffectiveConfig($organizationId);

        if (!empty($config['api_key'])) {
            try {
                $aiResults = $this->evaluateWithAi(
                    targetTitle: $targetTitle,
                    targetContent: $targetContent,
                    candidates: $candidates,
                    config: $config,
                    organizationId: $organizationId,
                    limit: $limit
                );

                if (!empty($aiResults)) {
                    return $aiResults;
                }
            } catch (\Throwable $e) {
                Log::info("Similitud con IA no disponible o falló ({$e->getMessage()}). Empleando motor heurístico.");
            }
        }

        // 3. Fallback algorítmico
        return $this->evaluateAlgorithmically(
            targetTitle: $targetTitle,
            targetContent: $targetContent,
            targetCategoryNames: $targetCategoryNames,
            targetTagNames: $targetTagNames,
            candidates: $candidates,
            limit: $limit
        );
    }

    /**
     * Evaluación semántica asistida por IA (MiniMax, Gemini, OpenAI, DeepSeek).
     *
     * @param Collection<int, Note> $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function evaluateWithAi(
        string $targetTitle,
        string $targetContent,
        Collection $candidates,
        array $config,
        string $organizationId,
        int $limit = 5
    ): array {
        $candidatesData = $candidates->map(function (Note $c) {
            return [
                'id' => $c->id,
                'title' => $c->title ?? 'Sin título',
                'snippet' => Str::limit($c->content, 180),
                'categories' => $c->categories->pluck('name')->join(', '),
            ];
        })->values()->toJson(JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Eres un analista semántico de base de conocimiento para un CRM institucional.
Tu tarea es comparar una NOTA DE REFERENCIA contra una lista de NOTAS CANDIDATAS y determinar cuáles abordan el mismo tema, procedimiento, producto, duda o contexto similar.

NOTA DE REFERENCIA:
- Título: {$targetTitle}
- Contenido: {$targetContent}

LISTA DE NOTAS CANDIDATAS:
{$candidatesData}

INSTRUCCIONES:
1. Compara semánticamente la intención, tema y datos clave.
2. Identifica hasta {$limit} notas candidatas con afinidad temática real (porcentaje de 35% a 100%).
3. Para cada nota similar, redacta una breve explicación clara en español (máx 15 palabras) explicando por qué se relacionan.
4. Devuelve ÚNICAMENTE un array JSON válido, ordenado descendentemente por similarity_percent, con esta estructura exacta:
[
  {
    "id": "ID_DE_LA_NOTA",
    "similarity_percent": 88,
    "reason": "Ambas notas detallan requisitos de admisión y plazos de entrega."
  }
]
Si ninguna candidata tiene relación temática, devuelve exactamente: []
PROMPT;

        $rawResponse = $this->aiCopilotService->sendDirectPrompt(
            prompt: $prompt,
            provider: $config['provider'],
            apiKey: $config['api_key'],
            model: $config['model'],
            baseUrl: $config['base_url'],
            systemInstruction: 'Eres un motor de similitud semántica. Responde siempre y exclusivamente en JSON sin markdown ni saludos.',
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
        if (!is_array($decoded)) {
            return [];
        }

        $candidatesById = $candidates->keyBy('id');
        $results = [];

        foreach ($decoded as $item) {
            $id = $item['id'] ?? null;
            if ($id && isset($candidatesById[$id])) {
                $candidateNote = $candidatesById[$id];
                $results[] = [
                    'note' => (new NoteResource($candidateNote))->resolve(),
                    'similarity_percent' => max(30, min(99, (int) ($item['similarity_percent'] ?? 50))),
                    'similarity_reason' => $item['reason'] ?? 'Contenido y conceptos temáticos afines.',
                    'source' => 'ia',
                ];
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Motor de similitud léxica y conceptual basado en tokens clave, intersección Jaccard y metadatos compartidos.
     *
     * @param Collection<int, Note> $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function evaluateAlgorithmically(
        string $targetTitle,
        string $targetContent,
        array $targetCategoryNames,
        array $targetTagNames,
        Collection $candidates,
        int $limit = 5
    ): array {
        $targetWords = $this->extractKeywords("{$targetTitle} {$targetContent}");

        if (empty($targetWords)) {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateWords = $this->extractKeywords("{$candidate->title} {$candidate->content}");
            if (empty($candidateWords)) {
                continue;
            }

            // 1. Intersección de palabras clave
            $intersection = array_intersect($targetWords, $candidateWords);
            $union = array_unique(array_merge($targetWords, $candidateWords));

            $jaccard = count($union) > 0 ? (count($intersection) / count($union)) : 0;
            $overlapScore = count($targetWords) > 0 ? (count($intersection) / count($targetWords)) : 0;

            // Ponderación base de texto (0 a 60 puntos)
            $textScore = ($jaccard * 0.4 + $overlapScore * 0.6) * 70;

            // 2. Bonificación por categorías compartidas (hasta +20 puntos)
            $candCats = $candidate->categories->pluck('name')->all();
            $sharedCats = array_intersect($targetCategoryNames, $candCats);
            $catBonus = !empty($sharedCats) ? 20 : 0;

            // 3. Bonificación por etiquetas compartidas (hasta +15 puntos)
            $candTags = $candidate->tags->pluck('name')->all();
            $sharedTags = array_intersect($targetTagNames, $candTags);
            $tagBonus = !empty($sharedTags) ? 15 : 0;

            // 4. Bonificación si coinciden palabras en el título (+15 puntos)
            $targetTitleWords = $this->extractKeywords($targetTitle);
            $candTitleWords = $this->extractKeywords($candidate->title ?? '');
            $titleOverlap = array_intersect($targetTitleWords, $candTitleWords);
            $titleBonus = !empty($titleOverlap) ? 15 : 0;

            $totalScore = (int) round($textScore + $catBonus + $tagBonus + $titleBonus);

            if ($totalScore >= 20) {
                $matchedKeywords = array_slice(array_values($intersection), 0, 3);
                $reasonParts = [];

                if (!empty($matchedKeywords)) {
                    $reasonParts[] = 'términos clave ("' . implode('", "', $matchedKeywords) . '")';
                }
                if (!empty($sharedCats)) {
                    $reasonParts[] = 'categoría compartida (' . reset($sharedCats) . ')';
                }
                if (!empty($sharedTags)) {
                    $reasonParts[] = 'etiqueta (' . reset($sharedTags) . ')';
                }

                $reason = !empty($reasonParts)
                    ? 'Coincidencia en ' . implode(' y ', $reasonParts) . '.'
                    : 'Afinidad temática y conceptual detectada.';

                $scored[] = [
                    'note' => (new NoteResource($candidate))->resolve(),
                    'similarity_percent' => min(95, max(25, $totalScore)),
                    'similarity_reason' => $reason,
                    'source' => 'semantic_analysis',
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['similarity_percent'] <=> $a['similarity_percent']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Normaliza y extrae palabras clave útiles eliminando signos de puntuación y stop words en español.
     *
     * @return string[]
     */
    protected function extractKeywords(string $text): array
    {
        $normalized = Str::lower($text);
        // Quitar signos de puntuación
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);

        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWordsLookup = array_flip(self::STOP_WORDS);

        $filtered = [];
        foreach ($words as $word) {
            $w = trim($word);
            if (mb_strlen($w) >= 3 && !isset($stopWordsLookup[$w])) {
                $filtered[$w] = $w;
            }
        }

        return array_values($filtered);
    }
}
