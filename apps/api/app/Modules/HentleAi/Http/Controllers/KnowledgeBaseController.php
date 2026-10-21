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
            ->with(['user:id,name,email'])
            ->withCount('chunks')
            ->orderByDesc('created_at')
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
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'provider' => $validated['provider'] ?? 'gemini',
        ]);

        $base->load('user:id,name,email');

        return response()->json([
            'data' => $base,
            'message' => 'Base de conocimiento creada exitosamente.',
        ], 201);
    }

    public function listChunks(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $chunks = AiKnowledgeChunk::query()
            ->where('organization_id', $organizationId)
            ->with(['user:id,name,email', 'knowledgeBase:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $chunks,
        ]);
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
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $chunk->load(['user:id,name,email', 'knowledgeBase:id,name']);

        return response()->json([
            'data' => $chunk,
            'message' => 'Fragmento de conocimiento indexado.',
        ], 201);
    }

    public function createChunkDirect(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'knowledge_base_id' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $kbId = $validated['knowledge_base_id'] ?? null;
        if (! $kbId) {
            $base = AiKnowledgeBase::firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'name' => 'Base General de Conocimiento',
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Base central de conocimiento, FAQ y procedimientos corporativos.',
                    'provider' => 'gemini',
                    'is_active' => true,
                ]
            );
            $kbId = $base->id;
        }

        $chunk = AiKnowledgeChunk::create([
            'organization_id' => $organizationId,
            'knowledge_base_id' => $kbId,
            'user_id' => $user->id,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $chunk->load(['user:id,name,email', 'knowledgeBase:id,name']);

        return response()->json([
            'data' => $chunk,
            'message' => 'Artículo de conocimiento registrado exitosamente.',
        ], 201);
    }

    public function deleteChunk(Request $request, string $id): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;
        $user = $request->user();

        $chunk = AiKnowledgeChunk::where('organization_id', $organizationId)->findOrFail($id);

        if (! $user->isAdmin() && $chunk->user_id !== $user->id) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar este artículo. Solo su autor o un administrador pueden eliminarlo.',
            ], 403);
        }

        $chunk->delete();

        return response()->json([
            'message' => 'Artículo de conocimiento eliminado.',
        ]);
    }
}
