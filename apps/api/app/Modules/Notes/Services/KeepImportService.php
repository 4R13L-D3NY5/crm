<?php

namespace App\Modules\Notes\Services;

use App\Models\User;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Notes\Models\Note;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class KeepImportService
{
    /**
     * Mapeo de colores oficiales de Google Keep a Hexadecimal institucional
     */
    protected static array $colorMap = [
        'DEFAULT' => '#1e293b',
        'RED' => '#ef4444',
        'ORANGE' => '#f97316',
        'YELLOW' => '#eab308',
        'GREEN' => '#22c55e',
        'TEAL' => '#14b8a6',
        'CERULEAN' => '#06b6d4',
        'BLUE' => '#3b82f6',
        'PURPLE' => '#a855f7',
        'PINK' => '#ec4899',
        'BROWN' => '#78350f',
        'GRAY' => '#64748b',
    ];

    /**
     * Previsualizar el contenido de un archivo ZIP de Google Takeout sin guardar nada.
     */
    public function previewZip(string|UploadedFile $zipInput): array
    {
        $filePath = $zipInput instanceof UploadedFile ? $zipInput->getRealPath() : $zipInput;

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo ZIP no existe en la ruta proporcionada.");
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($filePath);

        if ($openResult !== true) {
            throw new \RuntimeException("No se pudo abrir el archivo ZIP (Código de error: {$openResult}).");
        }

        $parsedNotes = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Filtrar únicamente los archivos JSON de notas de Google Keep
            if (!str_ends_with(strtolower($filename), '.json')) {
                continue;
            }

            $contentRaw = $zip->getFromIndex($i);
            if (empty($contentRaw)) {
                continue;
            }

            $data = json_decode($contentRaw, true);
            if (!is_array($data)) {
                continue;
            }

            $title = trim($data['title'] ?? '');
            $text = trim($data['textContent'] ?? '');

            // Si es una nota de tipo lista de verificación (checklist)
            if (empty($text) && !empty($data['listContent']) && is_array($data['listContent'])) {
                $checklistItems = [];
                foreach ($data['listContent'] as $item) {
                    $checked = !empty($item['isChecked']) ? '[x]' : '[ ]';
                    $itemText = trim($item['text'] ?? '');
                    if ($itemText !== '') {
                        $checklistItems[] = "{$checked} {$itemText}";
                    }
                }
                $text = implode("\n", $checklistItems);
            }

            // Omitir notas completamente vacías (sin título ni texto)
            if (empty($title) && empty($text)) {
                continue;
            }

            // Si no tiene título, generar uno representativo a partir de los primeros caracteres del texto
            if (empty($title)) {
                $firstLine = strtok($text, "\n");
                $title = Str::limit($firstLine ?: 'Nota Keep sin título', 40, '...');
            }

            $keepColor = strtoupper($data['color'] ?? 'DEFAULT');
            $hexColor = self::$colorMap[$keepColor] ?? '#1e293b';
            $isPinned = (bool) ($data['isPinned'] ?? false);
            $isArchived = (bool) ($data['isArchived'] ?? false);

            // Extraer etiquetas/tags de Keep
            $labels = [];
            if (!empty($data['annotations']) && is_array($data['annotations'])) {
                foreach ($data['annotations'] as $ann) {
                    if (($ann['type'] ?? '') === 'TAG' && !empty($ann['name'])) {
                        $labels[] = trim($ann['name']);
                    }
                }
            }
            if (!empty($data['labels']) && is_array($data['labels'])) {
                foreach ($data['labels'] as $l) {
                    if (!empty($l['name'])) {
                        $labels[] = trim($l['name']);
                    }
                }
            }
            $labels = array_values(array_unique($labels));

            // Auto-generar sugerencia de atajo rápido inteligente
            $suggestedShortcut = self::generateShortcut($title);

            $parsedNotes[] = [
                'temp_id' => (string) Str::uuid(),
                'title' => $title,
                'content' => $text,
                'color' => $hexColor,
                'keep_color' => $keepColor,
                'is_pinned' => $isPinned,
                'is_archived' => $isArchived,
                'shortcut' => $suggestedShortcut,
                'labels' => $labels,
                'source_file' => basename($filename),
                'selected' => true, // Seleccionado por defecto para importar
            ];
        }

        $zip->close();

        return [
            'total_detected' => count($parsedNotes),
            'notes' => $parsedNotes,
        ];
    }

    /**
     * Importar un lote confirmado de notas a la base de datos y a la Base de Conocimiento de IA.
     */
    public function importBatch(User $user, array $notes, bool $indexAi = true): array
    {
        $orgId = $user->current_organization_id;
        $importedCount = 0;
        $aiChunksCreated = 0;

        // Si se va a indexar en IA, buscar o crear la Base de Conocimiento principal de la organización
        $knowledgeBase = null;
        if ($indexAi) {
            $knowledgeBase = AiKnowledgeBase::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'name' => 'Base Institucional UNITEPC',
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Repositorio institucional con notas, aranceles, guías de admisión y becas importadas de Google Keep.',
                    'provider' => 'gemini',
                    'is_active' => true,
                ]
            );
        }

        DB::transaction(function () use ($user, $orgId, $notes, $indexAi, $knowledgeBase, &$importedCount, &$aiChunksCreated) {
            $now = now();

            foreach ($notes as $item) {
                $title = trim($item['title'] ?? '');
                $content = trim($item['content'] ?? '');

                if (empty($title) && empty($content)) {
                    continue;
                }

                $shortcut = !empty($item['shortcut']) ? self::cleanShortcut($item['shortcut']) : null;
                $color = !empty($item['color']) ? $item['color'] : '#1e293b';
                $isPinned = (bool) ($item['is_pinned'] ?? false);
                $isArchived = (bool) ($item['is_archived'] ?? false);
                $labels = is_array($item['labels'] ?? null) ? $item['labels'] : [];

                $note = Note::create([
                    'organization_id' => $orgId,
                    'user_id' => $user->id,
                    'title' => $title,
                    'content' => $content,
                    'color' => $color,
                    'shortcut' => $shortcut,
                    'is_pinned' => $isPinned,
                    'is_archived' => $isArchived,
                    'visibility' => 'all',
                    'metadata' => [
                        'source' => 'google_keep',
                        'labels' => $labels,
                        'imported_at' => $now->toIso8601String(),
                        'source_file' => $item['source_file'] ?? null,
                    ],
                ]);

                $importedCount++;

                // Indexar en AiKnowledgeChunk para RAG del Copiloto IA
                if ($indexAi && $knowledgeBase && !empty($content)) {
                    AiKnowledgeChunk::create([
                        'organization_id' => $orgId,
                        'knowledge_base_id' => $knowledgeBase->id,
                        'user_id' => $user->id,
                        'title' => $title,
                        'content' => $content,
                        'metadata' => [
                            'note_id' => $note->id,
                            'shortcut' => $shortcut,
                            'source' => 'google_keep',
                            'color' => $color,
                            'is_pinned' => $isPinned,
                        ],
                    ]);

                    $aiChunksCreated++;
                }
            }
        });

        return [
            'imported_count' => $importedCount,
            'ai_chunks_created' => $aiChunksCreated,
        ];
    }

    /**
     * Generador inteligente de atajos rápidos compactos basados en el título.
     */
    public static function generateShortcut(string $title): string
    {
        // 1. Quitar acentos y caracteres especiales
        $text = Str::ascii($title);
        $text = strtolower($text);

        // 2. Normalizar abreviaciones y nombres frecuentes de UNITEPC
        $replacements = [
            'administracion de empresas' => 'adm',
            'administracion' => 'adm',
            'ingenieria de sonido' => 'sonido',
            'ing. de sonido' => 'sonido',
            'ing. electronica' => 'electronica',
            'ingenieria' => 'ing',
            'cochabamba' => 'cbba',
            'santa cruz' => 'scz',
            'puerto quijarro' => 'pq',
            'guayaramerin' => 'guay',
            'ivirgarzama' => 'ivir',
            'informacion' => '',
            'extranjeros' => 'ext',
            'extranjero' => 'ext',
            'bolivianos' => 'bol',
            'boliviano' => 'bol',
            'horarios de atencion' => 'horarios',
            'horarios' => 'horarios',
            'descuento' => 'desc',
        ];

        foreach ($replacements as $search => $replace) {
            $text = str_replace($search, $replace, $text);
        }

        // 3. Convertir a slug con guiones bajos
        $slug = Str::slug($text, '_');

        // 4. Limpiar guiones bajos redundantes
        $slug = trim(preg_replace('/_+/', '_', $slug), '_');

        return Str::limit($slug, 22, '');
    }

    /**
     * Limpia un atajo rápido eliminando almohadillas o barras iniciales.
     */
    public static function cleanShortcut(string $shortcut): string
    {
        $clean = trim($shortcut);
        $clean = ltrim($clean, '#/');
        $clean = Str::slug($clean, '_');
        return Str::limit($clean, 25, '');
    }
}
