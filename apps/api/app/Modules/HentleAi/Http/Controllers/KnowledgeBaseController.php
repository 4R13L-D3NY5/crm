<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $bases = AiKnowledgeBase::query()
            ->where('organization_id', $organizationId)
            ->withCount('chunks')
            ->get();

        return response()->json([
            'data' => $bases,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'provider' => ['nullable', 'string', 'max:50'],
        ]);

        $base = AiKnowledgeBase::create([
            'organization_id' => $request->user()->current_organization_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'provider' => $validated['provider'] ?? 'gemini',
        ]);

        return response()->json([
            'data' => $base,
            'message' => 'Base de conocimiento creada exitosamente.',
        ], 201);
    }

    public function addChunk(Request $request, AiKnowledgeBase $knowledgeBase): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $chunk = AiKnowledgeChunk::create([
            'organization_id' => $request->user()->current_organization_id,
            'knowledge_base_id' => $knowledgeBase->id,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'data' => $chunk,
            'message' => 'Fragmento de conocimiento indexado.',
        ], 201);
    }
}
