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

    public function extractDocument(Request $request, \App\Modules\HentleAi\Services\HentleAiCopilotService $copilotService): JsonResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:15360'], // 15MB
            'raw_text' => ['nullable', 'string', 'min:20'],
        ]);

        $text = '';
        $filename = 'Texto pegado';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());

            if ($ext === 'pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file->getRealPath());
                    $text = $pdf->getText();
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al leer el archivo PDF: ' . $e->getMessage(),
                    ], 422);
                }
            } elseif ($ext === 'docx') {
                try {
                    $zip = new \ZipArchive();
                    if ($zip->open($file->getRealPath()) === true) {
                        $xml = $zip->getFromName('word/document.xml');
                        $zip->close();
                        if ($xml) {
                            $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml));
                        }
                    }
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al leer el archivo Word DOCX: ' . $e->getMessage(),
                    ], 422);
                }
            } else {
                // txt, md, json, csv
                $text = file_get_contents($file->getRealPath());
            }
        } elseif ($request->filled('raw_text')) {
            $text = $request->input('raw_text');
        } else {
            return response()->json([
                'message' => 'Debes proporcionar un archivo (.pdf, .docx, .txt, .md) o texto para analizar.',
            ], 422);
        }

        // Limpiar texto UTF-8
        $cleanText = trim(preg_replace('/[^\P{C}\n\r\t]/u', '', $text));
        if (mb_strlen($cleanText) < 20) {
            return response()->json([
                'message' => 'El documento no contiene suficiente texto legible para extraer conocimiento.',
            ], 422);
        }

        try {
            $articles = $copilotService->extractArticlesFromDocument($cleanText, $request->user()->current_organization_id);

            return response()->json([
                'data' => [
                    'filename' => $filename,
                    'total_characters' => mb_strlen($cleanText),
                    'articles' => $articles,
                ],
                'message' => 'Documento analizado con éxito. Se detectaron ' . count($articles) . ' fragmentos de conocimiento.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error durante el análisis del documento: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function batchStoreChunks(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;
        $user = $request->user();

        $validated = $request->validate([
            'articles' => ['required', 'array', 'min:1'],
            'articles.*.title' => ['required', 'string', 'max:200'],
            'articles.*.content' => ['required', 'string'],
            'articles.*.tags' => ['nullable', 'array'],
            'knowledge_base_id' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
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

        $created = [];
        foreach ($validated['articles'] as $art) {
            $metadata = [
                'tags' => $art['tags'] ?? ['documento'],
                'source_file' => $validated['filename'] ?? 'importacion_ia',
                'imported_at' => now()->toIso8601String(),
            ];

            $chunk = AiKnowledgeChunk::create([
                'organization_id' => $organizationId,
                'knowledge_base_id' => $kbId,
                'user_id' => $user->id,
                'title' => $art['title'],
                'content' => $art['content'],
                'metadata' => $metadata,
            ]);

            $chunk->load(['user:id,name,email', 'knowledgeBase:id,name']);
            $created[] = $chunk;
        }

        return response()->json([
            'data' => $created,
            'count' => count($created),
            'message' => count($created) . ' artículos de conocimiento registrados y vectorizados exitosamente.',
        ], 201);
    }
}
