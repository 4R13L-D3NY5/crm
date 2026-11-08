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
            } elseif (in_array($ext, ['docx', 'doc'])) {
                try {
                    $zip = new \ZipArchive();
                    if ($zip->open($file->getRealPath()) === true) {
                        $xml = $zip->getFromName('word/document.xml');
                        $zip->close();
                        if ($xml) {
                            $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml));
                        }
                    } else {
                        // Respaldo para documentos Word basados en RTF, HTML o texto
                        $raw = file_get_contents($file->getRealPath());
                        if (stripos($raw, '{\rtf') === 0 || stripos($raw, '<html') !== false || stripos($raw, '<body') !== false) {
                            $text = strip_tags($raw);
                        } else {
                            // Extraer secuencias legibles UTF-8 / ASCII de binario .doc
                            preg_match_all('/[\x20-\x7E\xA0-\xFF\r\n]{4,}/u', $raw, $matches);
                            $text = implode("\n", $matches[0] ?? []);
                        }
                    }
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al leer el archivo Word (' . strtoupper($ext) . '): ' . $e->getMessage(),
                    ], 422);
                }
            } elseif (in_array($ext, ['xlsx', 'xls', 'csv'])) {
                try {
                    $text = $this->extractTextFromSpreadsheet($file->getRealPath(), $ext);
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'Error al leer hoja de cálculo (' . strtoupper($ext) . '): ' . $e->getMessage(),
                    ], 422);
                }
            } else {
                // txt, md, json
                $text = file_get_contents($file->getRealPath());
            }
        } elseif ($request->filled('raw_text')) {
            $text = $request->input('raw_text');
        } else {
            return response()->json([
                'message' => 'Debes proporcionar un archivo (.pdf, .docx, .xlsx, .xls, .csv, .txt, .md) o texto para analizar.',
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

    /**
     * Extrae y estructura el contenido tabular de archivos Excel (.xlsx, .xls) o CSV en formato Markdown.
     * Detecta automáticamente el formato interno real (OpenXML ZIP, OLE2 BIFF8, HTML o texto/CSV)
     * independientemente de la extensión que tenga el archivo.
     */
    protected function extractTextFromSpreadsheet(string $filePath, string $ext): string
    {
        $handle = fopen($filePath, 'r');
        $header = fread($handle, 8);
        fclose($handle);

        $isZip = str_starts_with($header, "PK\x03\x04");
        $isOle = str_starts_with($header, "\xD0\xCF\x11\xE0");

        // 1. Si el archivo es un contenedor ZIP (OpenXML moderno .xlsx o .xls con contenido XLSX)
        if ($isZip || $ext === 'xlsx') {
            if ($xlsx = \Shuchkin\SimpleXLSX::parse($filePath)) {
                return $this->formatWorkbookToMarkdown($xlsx);
            }
        }

        // 2. Si es una exportación HTML o XML con extensión .xls
        $raw = file_get_contents($filePath);
        if (stripos($raw, '<table') !== false || stripos($raw, '<tr') !== false) {
            return trim(strip_tags(str_replace(['</tr>', '</td>', '</th>'], ["\n", "\t", "\t"], $raw)));
        }

        // 3. Si es binario OLE2 (Excel 97-2003 BIFF8 .xls clásico)
        require_once app_path('Modules/HentleAi/Support/SimpleXLS.php');
        if ($isOle || $ext === 'xls') {
            if ($xls = \Shuchkin\SimpleXLS::parse($filePath)) {
                return $this->formatWorkbookToMarkdown($xls);
            }
        }

        // 4. Intentar fallback cruzado con SimpleXLSX por si el header varió
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($filePath)) {
            return $this->formatWorkbookToMarkdown($xlsx);
        }

        // 5. Formato CSV / Delimitado por comas, punto y coma o tabulación
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $delimiter = ';';
            } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $delimiter = "\t";
            }

            $lines = [];
            $rowIndex = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $nonEmpty = array_filter($row, fn ($val) => trim((string) $val) !== '');
                if (empty($nonEmpty)) {
                    continue;
                }
                $cleanCells = array_map(
                    fn ($c) => trim(str_replace(["\r", "\n", '|'], [' ', ' ', '/'], (string) $c)),
                    $row
                );
                $lines[] = '| ' . implode(' | ', $cleanCells) . ' |';
                if ($rowIndex === 0) {
                    $lines[] = '| ' . implode(' | ', array_fill(0, count($cleanCells), '---')) . ' |';
                }
                $rowIndex++;
            }
            fclose($handle);
            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }

        return file_get_contents($filePath);
    }

    /**
     * Convierte hojas y filas de SimpleXLSX o SimpleXLS en tablas Markdown legibles.
     */
    protected function formatWorkbookToMarkdown(object $workbook): string
    {
        $lines = [];
        $sheetNames = $workbook->sheetNames();
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            $rows = $workbook->rows($sheetIndex);
            if (empty($rows)) {
                continue;
            }
            $lines[] = "### Hoja: " . $sheetName;
            foreach ($rows as $rowIndex => $row) {
                $nonEmpty = array_filter($row, fn ($val) => trim((string) $val) !== '');
                if (empty($nonEmpty)) {
                    continue;
                }
                $cleanCells = array_map(
                    fn ($c) => trim(str_replace(["\r", "\n", '|'], [' ', ' ', '/'], (string) $c)),
                    $row
                );
                $lines[] = '| ' . implode(' | ', $cleanCells) . ' |';
                if ($rowIndex === 0) {
                    $lines[] = '| ' . implode(' | ', array_fill(0, count($cleanCells), '---')) . ' |';
                }
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }
}
