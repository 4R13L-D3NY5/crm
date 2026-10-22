<?php

namespace App\Modules\Notes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notes\Http\Requests\AnalyzeNoteEntitiesRequest;
use App\Modules\Notes\Http\Requests\CreateNoteRequest;
use App\Modules\Notes\Http\Requests\UpdateNoteRequest;
use App\Modules\Notes\Http\Resources\NoteResource;
use App\Modules\Notes\Models\Note;
use App\Modules\Notes\Services\DocumentIngestionService;
use App\Modules\Notes\Services\KeepImportService;
use App\Modules\Notes\Services\NoteEntityAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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

        // Búsqueda de texto o atajo (soporta modo estándar y modo permisivo no correlativo)
        if ($search = $request->query('search')) {
            $isPermissive = filter_var($request->query('permissive', false), FILTER_VALIDATE_BOOLEAN)
                || $request->query('mode') === 'permissive';
            $isPgsql = DB::getDriverName() === 'pgsql';

            if ($isPermissive) {
                // Modo permisivo: extrae palabras individuales y exige que TODAS estén presentes sin importar el orden
                $words = array_filter(preg_split('/\s+/', trim($search)));

                if (!empty($words)) {
                    $query->where(function ($mainQ) use ($words, $isPgsql) {
                        foreach ($words as $word) {
                            $wordClean = ltrim($word, '#');
                            $mainQ->where(function ($subQ) use ($word, $wordClean, $isPgsql) {
                                if ($isPgsql) {
                                    $subQ->whereRaw('unaccent(title) ILIKE unaccent(?)', ["%{$word}%"])
                                        ->orWhereRaw('unaccent(content) ILIKE unaccent(?)', ["%{$word}%"])
                                        ->orWhereRaw('unaccent(shortcut) ILIKE unaccent(?)', ["%{$wordClean}%"]);
                                } else {
                                    $subQ->where('title', 'like', "%{$word}%")
                                        ->orWhere('content', 'like', "%{$word}%")
                                        ->orWhere('shortcut', 'like', "%{$wordClean}%");
                                }
                            });
                        }
                    });
                }
            } else {
                // Modo estándar correlativo
                $query->where(function ($q) use ($search, $isPgsql) {
                    if ($isPgsql) {
                        $q->whereRaw('unaccent(title) ILIKE unaccent(?)', ["%{$search}%"])
                            ->orWhereRaw('unaccent(content) ILIKE unaccent(?)', ["%{$search}%"])
                            ->orWhereRaw('unaccent(shortcut) ILIKE unaccent(?)', ["%{$search}%"]);
                    } else {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('content', 'like', "%{$search}%")
                            ->orWhere('shortcut', 'like', "%{$search}%");
                    }
                });
            }
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
        $user = $request->user();
        $note = Note::where('organization_id', $user->current_organization_id)->findOrFail($id);

        if (! $user->isAdmin() && $note->user_id !== $user->id) {
            return response()->json([
                'message' => 'Solo los administradores o el autor de la nota pueden eliminarla.',
            ], 403);
        }

        $note->delete();

        return response()->json([
            'message' => 'Nota eliminada correctamente.',
        ]);
    }

    public function togglePin(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $note = Note::where('organization_id', $user->current_organization_id)->findOrFail($id);

        if (! $user->isAdmin() && $note->user_id !== $user->id) {
            return response()->json([
                'message' => 'Solo los administradores o el autor de la nota pueden fijar o desfijar notas.',
            ], 403);
        }

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
        $user = $request->user();
        $note = Note::where('organization_id', $user->current_organization_id)->findOrFail($id);

        if (! $user->isAdmin() && $note->user_id !== $user->id) {
            return response()->json([
                'message' => 'Solo los administradores o el autor de la nota pueden archivar o desarchivar notas.',
            ], 403);
        }

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

    public function previewKeep(Request $request, KeepImportService $service): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'zip') {
            return response()->json([
                'message' => 'El archivo debe ser un archivo comprimido .zip',
            ], 422);
        }

        $result = $service->previewZip($file);

        return response()->json([
            'message' => 'Archivo ZIP analizado exitosamente.',
            'data' => $result,
        ]);
    }

    public function importBatch(Request $request, KeepImportService $service): JsonResponse
    {
        $request->validate([
            'notes' => 'required|array|min:1',
            'notes.*.title' => 'nullable|string|max:255',
            'notes.*.content' => 'nullable|string',
            'notes.*.shortcut' => 'nullable|string|max:100',
            'notes.*.color' => 'nullable|string|max:30',
            'notes.*.is_pinned' => 'nullable|boolean',
            'notes.*.is_archived' => 'nullable|boolean',
            'notes.*.labels' => 'nullable|array',
            'index_ai' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $indexAi = $request->boolean('index_ai', true);

        $result = $service->importBatch($user, $request->input('notes'), $indexAi);

        return response()->json([
            'message' => 'Notas importadas con éxito a la base institucional.',
            'data' => $result,
        ]);
    }

    public function uploadDocument(Request $request, DocumentIngestionService $service): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400',
            'title' => 'nullable|string|max:255',
            'shortcut' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:30',
            'is_pinned' => 'nullable|boolean',
            'index_ai' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = ['pdf', 'docx', 'xlsx', 'xls', 'txt', 'csv', 'doc', 'json'];
        if (!in_array($ext, $allowed)) {
            return response()->json([
                'message' => "El formato .{$ext} no está permitido. Formatos admitidos: " . implode(', ', $allowed),
            ], 422);
        }

        $user = $request->user();
        $note = $service->ingest($user, $file, [
            'title' => $request->input('title'),
            'shortcut' => $request->input('shortcut'),
            'color' => $request->input('color'),
            'is_pinned' => $request->boolean('is_pinned'),
            'index_ai' => $request->boolean('index_ai', true),
        ]);

        return response()->json([
            'message' => 'Documento procesado e importado como nota y base de conocimiento.',
            'data' => new NoteResource($note),
        ], 201);
    }
}
