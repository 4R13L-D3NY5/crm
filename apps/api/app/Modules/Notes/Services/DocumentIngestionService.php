<?php

namespace App\Modules\Notes\Services;

use App\Models\User;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Notes\Models\Note;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentIngestionService
{
    /**
     * Ingerir y procesar un archivo (.pdf, .docx, .xlsx, .txt) extrayendo su texto
     * y creando la Nota + Fragmento de Base de Conocimiento de IA.
     */
    public function ingest(User $user, UploadedFile $file, array $options = []): Note
    {
        $orgId = $user->current_organization_id;
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        // 1. Guardar archivo físico en el almacenamiento público
        $storedPath = $file->store('knowledge_documents', 'public');
        $storageUrl = Storage::disk('public')->url($storedPath);

        // 2. Extraer texto según extensión
        $extractedText = $this->extractText($file->getRealPath(), $extension);

        if (empty(trim($extractedText))) {
            $extractedText = "Documento: {$originalName} (Sin contenido de texto extraíble automáticamente o archivo escaneado sin OCR).";
        }

        // 3. Resolver Título y Atajo
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $title = !empty($options['title']) ? trim($options['title']) : Str::headline($baseName);
        $shortcut = !empty($options['shortcut']) ? KeepImportService::cleanShortcut($options['shortcut']) : KeepImportService::generateShortcut($title);
        $color = !empty($options['color']) ? $options['color'] : $this->getColorByExtension($extension);

        // 4. Crear Nota en la base de datos
        $note = Note::create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'title' => $title,
            'content' => $extractedText,
            'color' => $color,
            'shortcut' => $shortcut,
            'is_pinned' => (bool) ($options['is_pinned'] ?? false),
            'is_archived' => false,
            'visibility' => 'all',
            'metadata' => [
                'source' => 'document_upload',
                'original_filename' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'storage_path' => $storedPath,
                'storage_url' => $storageUrl,
                'uploaded_at' => now()->toIso8601String(),
            ],
        ]);

        // 5. Indexar en Base de Conocimiento de IA (HentleAi RAG)
        $indexAi = $options['index_ai'] ?? true;
        if ($indexAi) {
            $knowledgeBase = AiKnowledgeBase::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'name' => 'Base Institucional UNITEPC',
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Repositorio institucional con notas y documentos para el Copiloto IA.',
                    'provider' => 'gemini',
                    'is_active' => true,
                ]
            );

            AiKnowledgeChunk::create([
                'organization_id' => $orgId,
                'knowledge_base_id' => $knowledgeBase->id,
                'user_id' => $user->id,
                'title' => $title,
                'content' => $extractedText,
                'metadata' => [
                    'note_id' => $note->id,
                    'shortcut' => $shortcut,
                    'source' => 'document_upload',
                    'file_name' => $originalName,
                    'storage_url' => $storageUrl,
                ],
            ]);
        }

        return $note;
    }

    /**
     * Motor de extracción de texto agnóstico por extensión.
     */
    protected function extractText(string $filePath, string $extension): string
    {
        return match ($extension) {
            'txt', 'md', 'csv', 'json' => $this->extractPlain($filePath),
            'docx' => $this->extractDocx($filePath),
            'xlsx', 'xls' => $this->extractXlsx($filePath),
            'pdf' => $this->extractPdf($filePath),
            default => $this->extractPlain($filePath),
        };
    }

    protected function extractPlain(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        return $content !== false ? trim($content) : '';
    }

    /**
     * Extraer texto de un archivo Word (.docx) desempaquetando word/document.xml
     */
    protected function extractDocx(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xmlIndex = $zip->locateName('word/document.xml');
        if ($xmlIndex === false) {
            $zip->close();
            return '';
        }

        $xmlContent = $zip->getFromIndex($xmlIndex);
        $zip->close();

        if (empty($xmlContent)) {
            return '';
        }

        // Reemplazar saltos de párrafo en el XML para legibilidad
        $text = str_replace('</w:p>', "\n", $xmlContent);
        $text = str_replace('</w:tr>', "\n", $text);
        $text = str_replace('</w:tc>', "\t", $text);

        // Eliminar tags XML
        $clean = strip_tags($text);
        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode($clean, ENT_QUOTES | ENT_XML1, 'UTF-8')));
    }

    /**
     * Extraer texto y celdas de un archivo Excel (.xlsx) desempaquetando sharedStrings y hojas
     */
    protected function extractXlsx(string $filePath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        // 1. Leer strings compartidos (sharedStrings.xml)
        $sharedStrings = [];
        $ssIndex = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIndex !== false) {
            $ssXml = $zip->getFromIndex($ssIndex);
            if (!empty($ssXml)) {
                $xml = @simplexml_load_string($ssXml);
                if ($xml && isset($xml->si)) {
                    foreach ($xml->si as $val) {
                        $sharedStrings[] = (string) ($val->t ?? ($val->r->t ?? ''));
                    }
                }
            }
        }

        // 2. Leer hojas de cálculo
        $outputRows = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $name)) {
                $sheetXml = $zip->getFromIndex($i);
                $xml = @simplexml_load_string($sheetXml);
                if ($xml && isset($xml->sheetData->row)) {
                    foreach ($xml->sheetData->row as $row) {
                        $cells = [];
                        foreach ($row->c as $c) {
                            $type = (string) ($c['t'] ?? '');
                            $val = (string) ($c->v ?? '');
                            if ($type === 's' && isset($sharedStrings[(int) $val])) {
                                $val = $sharedStrings[(int) $val];
                            }
                            $cells[] = trim($val);
                        }
                        $rowText = trim(implode(' | ', array_filter($cells, fn($c) => $c !== '')));
                        if (!empty($rowText)) {
                            $outputRows[] = $rowText;
                        }
                    }
                }
            }
        }

        $zip->close();
        return implode("\n", $outputRows);
    }

    /**
     * Extraer texto de un archivo PDF
     */
    protected function extractPdf(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return '';
        }

        $text = '';
        // Buscar streams descomprimidos de texto en PDF (bloques BT ... ET)
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $uncompressed = @gzuncompress($stream);
                $dataToScan = $uncompressed !== false ? $uncompressed : $stream;

                if (preg_match_all('/\((.*?)\)\s*Tj/s', $dataToScan, $tjMatches)) {
                    $text .= implode(' ', $tjMatches[1]) . "\n";
                } elseif (preg_match_all('/\[(.*?)\]\s*TJ/s', $dataToScan, $tJMatches)) {
                    foreach ($tJMatches[1] as $block) {
                        if (preg_match_all('/\((.*?)\)/', $block, $partMatches)) {
                            $text .= implode('', $partMatches[1]) . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        if (empty(trim($text))) {
            // Fallback de rescate para PDFs planos
            $clean = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ.,;:()\-_$%#\/@]/', '', $content);
            $text = preg_replace('/\s+/', ' ', $clean);
        }

        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    }

    protected function getColorByExtension(string $extension): string
    {
        return match ($extension) {
            'pdf' => '#ef4444',       // Rojo característico de PDF
            'xlsx', 'xls' => '#10b981', // Verde de Excel
            'docx', 'doc' => '#2563eb', // Azul de Word
            default => '#14b8a6',       // Teal de notas
        };
    }
}
