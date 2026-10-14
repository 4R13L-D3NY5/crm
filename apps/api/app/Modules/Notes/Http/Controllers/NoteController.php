<?php

namespace App\Modules\Notes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notes\Http\Requests\AnalyzeNoteEntitiesRequest;
use App\Modules\Notes\Http\Requests\CreateNoteRequest;
use App\Modules\Notes\Http\Requests\UpdateNoteRequest;
use App\Modules\Notes\Http\Resources\NoteResource;
use App\Modules\Notes\Models\Note;
use App\Modules\Notes\Services\NoteEntityAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $query = Note::query()
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories', 'customStatuses', 'tags']);

        // Filtro de archivadas: por defecto no mostrar archivadas a menos que se solicite
        if ($request->has('is_archived')) {
            $query->where('is_archived', filter_var($request->query('is_archived'), FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('is_archived', false);
        }

        // Filtro de fijadas
        if ($request->has('is_pinned')) {
            $query->where('is_pinned', filter_var($request->query('is_pinned'), FILTER_VALIDATE_BOOLEAN));
        }

        // Búsqueda de texto o atajo
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%")
                    ->orWhere('shortcut', 'ilike', "%{$search}%");
            });
        }

        // Filtro por categoría
        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        // Filtro por estado de lead / custom status
        if ($statusId = $request->query('custom_status_id')) {
            $query->whereHas('customStatuses', fn ($q) => $q->where('custom_statuses.id', $statusId));
        }

        // Filtro por etiqueta
        if ($tagId = $request->query('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        $notes = $query->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        return NoteResource::collection($notes);
    }

    public function store(CreateNoteRequest $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $validated = $request->validated();

        $note = Note::create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'color' => $validated['color'] ?? '#1e293b',
            'is_pinned' => $validated['is_pinned'] ?? false,
            'is_archived' => $validated['is_archived'] ?? false,
            'shortcut' => !empty($validated['shortcut']) ? ltrim($validated['shortcut'], '#') : null,
            'visibility' => $validated['visibility'] ?? 'all',
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if (!empty($validated['category_ids'])) {
            $note->categories()->sync($validated['category_ids']);
        }

        if (!empty($validated['custom_status_ids'])) {
            $note->customStatuses()->sync($validated['custom_status_ids']);
        }

        if (!empty($validated['tag_ids'])) {
            $note->tags()->sync($validated['tag_ids']);
        }

        $note->load(['user', 'categories', 'customStatuses', 'tags']);

        return response()->json([
            'data' => (new NoteResource($note))->resolve(),
            'message' => 'Nota guardada exitosamente en el tablero de notas.',
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $note = Note::query()
            ->visibleToUser($user, $organizationId)
            ->with(['user', 'categories', 'customStatuses', 'tags'])
            ->findOrFail($id);

        return response()->json([
            'data' => (new NoteResource($note))->resolve(),
        ]);
    }

    public function update(UpdateNoteRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->current_organization_id;

        $note = Note::where('organization_id', $organizationId)->findOrFail($id);
        $validated = $request->validated();

        $fillData = [];
        if (array_key_exists('title', $validated)) $fillData['title'] = $validated['title'];
        if (array_key_exists('content', $validated)) $fillData['content'] = $validated['content'];
        if (array_key_exists('color', $validated)) $fillData['color'] = $validated['color'];
        if (array_key_exists('is_pinned', $validated)) $fillData['is_pinned'] = $validated['is_pinned'];
        if (array_key_exists('is_archived', $validated)) $fillData['is_archived'] = $validated['is_archived'];
        if (array_key_exists('shortcut', $validated)) {
            $fillData['shortcut'] = !empty($validated['shortcut']) ? ltrim($validated['shortcut'], '#') : null;
        }
        if (array_key_exists('visibility', $validated)) $fillData['visibility'] = $validated['visibility'];
        if (array_key_exists('metadata', $validated)) $fillData['metadata'] = $validated['metadata'];

        $note->update($fillData);

        if (array_key_exists('category_ids', $validated)) {
            $note->categories()->sync($validated['category_ids'] ?? []);
        }

        if (array_key_exists('custom_status_ids', $validated)) {
            $note->customStatuses()->sync($validated['custom_status_ids'] ?? []);
        }

        if (array_key_exists('tag_ids', $validated)) {
            $note->tags()->sync($validated['tag_ids'] ?? []);
        }

        $note->load(['user', 'categories', 'customStatuses', 'tags']);

        return response()->json([
            'data' => (new NoteResource($note))->resolve(),
            'message' => 'Nota actualizada correctamente.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'Solo los administradores pueden eliminar notas del sistema.',
            ], 403);
        }

        $note = Note::where('organization_id', $request->user()->current_organization_id)->findOrFail($id);
        $note->delete();

        return response()->json([
            'message' => 'Nota eliminada correctamente.',
        ]);
    }

    public function togglePin(Request $request, string $id): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'Solo los administradores pueden fijar o desfijar notas.',
            ], 403);
        }

        $note = Note::where('organization_id', $request->user()->current_organization_id)->findOrFail($id);
        $note->is_pinned = !$note->is_pinned;
        $note->save();

        return response()->json([
            'data' => [
                'id' => $note->id,
                'is_pinned' => $note->is_pinned,
            ],
            'message' => $note->is_pinned ? 'Nota fijada en la parte superior.' : 'Nota desfijada.',
        ]);
    }

    public function toggleArchive(Request $request, string $id): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'Solo los administradores pueden archivar o desarchivar notas.',
            ], 403);
        }

        $note = Note::where('organization_id', $request->user()->current_organization_id)->findOrFail($id);
        $note->is_archived = !$note->is_archived;
        $note->save();

        return response()->json([
            'data' => [
                'id' => $note->id,
                'is_archived' => $note->is_archived,
            ],
            'message' => $note->is_archived ? 'Nota archivada.' : 'Nota desarchivada.',
        ]);
    }

    public function analyze(AnalyzeNoteEntitiesRequest $request, NoteEntityAnalyzerService $analyzer): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;
        $content = $request->validated('content');

        $result = $analyzer->analyze($content, $organizationId);

        return response()->json([
            'data' => $result,
        ]);
    }
}
