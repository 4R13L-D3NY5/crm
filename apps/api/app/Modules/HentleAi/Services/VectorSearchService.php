<?php

namespace App\Modules\HentleAi\Services;

use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VectorSearchService
{
    /**
     * Realiza una búsqueda por similitud de coseno en los embeddings de pgvector.
     *
     * @param string $organizationId
     * @param array<float> $queryEmbedding Array de floats del embedding de la consulta
     * @param int $limit Número de fragmentos más relevantes a retornar
     * @param float $minSimilarity Similitud mínima (0 a 1)
     * @return Collection
     */
    public function search(
        string $organizationId,
        array $queryEmbedding,
        int $limit = 4,
        float $minSimilarity = 0.65
    ): Collection {
        if (DB::getDriverName() !== 'pgsql') {
            // Fallback para bases de datos sin pgvector (búsqueda de texto)
            return AiKnowledgeChunk::query()
                ->where('organization_id', $organizationId)
                ->limit($limit)
                ->get();
        }

        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        // En pgvector, el operador <=> calcula la distancia coseno (0 = idéntico, 2 = opuesto).
        // Similitud = 1 - distancia.
        $results = DB::select("
            SELECT 
                id, 
                knowledge_base_id, 
                title, 
                content, 
                metadata,
                1 - (embedding <=> ?::vector) as similarity
            FROM ai_knowledge_chunks
            WHERE organization_id = ?
              AND (1 - (embedding <=> ?::vector)) >= ?
            ORDER BY embedding <=> ?::vector ASC
            LIMIT ?
        ", [
            $vectorString,
            $organizationId,
            $vectorString,
            $minSimilarity,
            $vectorString,
            $limit
        ]);

        return collect($results);
    }
}
