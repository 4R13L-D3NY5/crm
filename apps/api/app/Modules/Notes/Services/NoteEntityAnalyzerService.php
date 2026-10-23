<?php

namespace App\Modules\Notes\Services;

use App\Modules\Contacts\Models\Tag;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Support\Str;

class NoteEntityAnalyzerService
{
    /**
     * Normaliza un texto removiendo tildes, signos, pasando a minúsculas
     * y expandiendo abreviaturas comunes universitarias y geográficas.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $trans = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'
        ];
        $text = strtr($text, $trans);

        // Expandir abreviaturas comunes
        $replacements = [
            '/\bing\b|\bing\./iu' => 'ingenieria',
            '/\blic\b|\blic\./iu' => 'licenciatura',
            '/\btec\b|\btec\./iu' => 'tecnico',
            '/\bcbba\b/iu' => 'cochabamba',
            '/\blpz\b/iu' => 'la paz',
            '/\bscz\b/iu' => 'santa cruz',
            '/\beal\b/iu' => 'el alto',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Extrae el núcleo significativo de una categoría removiendo prefijos y sufijos institucionales.
     */
    public function extractCoreName(string $name): string
    {
        $norm = $this->normalize($name);

        $prefixes = [
            '/^licenciatura en /u',
            '/^licenciatura de /u',
            '/^carrera de /u',
            '/^tecnico superior en /u',
            '/^tecnico medio en /u',
            '/^facultad de /u',
            '/^sede /u',
        ];
        $norm = preg_replace($prefixes, '', $norm);

        $suffixes = [
            '/\s*\(central\)$/u',
            '/\s*\(pregrado\)$/u',
            '/\s*\(posgrado\)$/u',
            '/\s*\(tropico\)$/u',
            '/\s*\(subsede\)$/u',
            '/\s*\(sede central\)$/u',
        ];

        return trim(preg_replace($suffixes, '', $norm));
    }

    /**
     * Escanea el contenido de una nota para detectar menciones de:
     * - Categorías / Subcategorías (con resolución de antecesoras y desambiguación de sedes)
     * - Estados personalizados (Custom Statuses)
     * - Etiquetas (Tags)
     */
    public function analyze(string $text, string $organizationId): array
    {
        $normalizedText = $this->normalize($text);

        // 1. Obtener catálogo del tenant
        $categories = Category::where('organization_id', $organizationId)->get();
        $statuses = CustomStatus::where('organization_id', $organizationId)->get();
        $tags = Tag::where('organization_id', $organizationId)->get();

        // 2. Detectar si el texto menciona alguna Sede específica
        $sedeMap = [
            'cochabamba' => 'CBA',
            'la paz' => 'LPZ',
            'el alto' => 'EAL',
            'santa cruz' => 'STC',
            'ivirgarzama' => 'IVI',
        ];
        $detectedSedeCodes = [];
        foreach ($sedeMap as $sedeName => $sedeCode) {
            if (str_contains($normalizedText, $sedeName)) {
                $detectedSedeCodes[] = $sedeCode;
            }
        }

        $detectedCategories = [];
        foreach ($categories as $cat) {
            $fullName = $this->normalize($cat->name);
            $coreName = $this->extractCoreName($cat->name);
            $displayPath = $this->normalize($cat->display_path ?? $cat->name);
            $code = strtoupper(trim((string) ($cat->code ?? '')));

            $isMatch = false;
            $matchedText = $cat->name;

            // Coincidencia 1: Núcleo temático (ej: "ingenieria de sonido", "cochabamba", "protesis dental")
            if (mb_strlen($coreName) >= 4 && str_contains($normalizedText, $coreName)) {
                $isMatch = true;
                $matchedText = $coreName;
            }
            // Coincidencia 2: Nombre completo exacto
            elseif (mb_strlen($fullName) >= 4 && str_contains($normalizedText, $fullName)) {
                $isMatch = true;
                $matchedText = $cat->name;
            }
            // Coincidencia 3: Término clave distintivo para carreras clave (ej: "sonido" para Ingeniería de Sonido)
            elseif (str_contains($coreName, 'sonido') && str_contains($normalizedText, 'sonido')) {
                $isMatch = true;
                $matchedText = 'sonido';
            }
            // Coincidencia 4: Código institucional si aparece como hashtag o palabra clave
            elseif (mb_strlen($code) >= 3 && preg_match('/\b' . preg_quote(strtolower($code), '/') . '\b/iu', $normalizedText)) {
                $isMatch = true;
                $matchedText = $code;
            }

            if ($isMatch) {
                // Desambiguación de sede: si la nota menciona una Sede puntual (ej: Cochabamba / CBA),
                // no incluir las ramas de otras sedes (ej: La Paz, El Alto, Santa Cruz).
                if (!empty($detectedSedeCodes)) {
                    $catDisplayUpper = strtoupper($cat->display_path ?? '');
                    $belongsToOtherSede = false;
                    $allSedeCodes = ['CBA', 'LPZ', 'EAL', 'STC', 'IVI'];

                    foreach ($allSedeCodes as $sedeCode) {
                        if (!in_array($sedeCode, $detectedSedeCodes, true)) {
                            if (str_starts_with($catDisplayUpper, $sedeCode . ' ->') || str_contains($displayPath, 'sede ' . strtolower($sedeCode))) {
                                $belongsToOtherSede = true;
                                break;
                            }
                        }
                    }

                    if ($belongsToOtherSede) {
                        continue;
                    }
                }

                $detectedCategories[$cat->id] = [
                    'id' => $cat->id,
                    'parent_id' => $cat->parent_id,
                    'name' => $cat->name,
                    'code' => $cat->code,
                    'color' => $cat->color,
                    'icon' => $cat->icon,
                    'slug' => $cat->slug,
                    'display_path' => $cat->display_path,
                    'full_path' => $cat->full_path,
                    'matched_text' => $matchedText,
                ];
            }
        }

        $detectedStatuses = [];
        foreach ($statuses as $st) {
            $normStName = $this->normalize($st->name);
            if (mb_strlen($normStName) >= 3) {
                $pattern = '/\b' . preg_quote($normStName, '/') . '\b/iu';
                if (preg_match($pattern, $normalizedText) || str_contains($normalizedText, $normStName)) {
                    $detectedStatuses[$st->id] = [
                        'id' => $st->id,
                        'name' => $st->name,
                        'color' => $st->color,
                        'icon' => $st->icon,
                        'slug' => $st->slug,
                        'matched_text' => $st->name,
                    ];
                }
            }
        }

        $detectedTags = [];
        foreach ($tags as $tag) {
            $normTagName = $this->normalize($tag->name);
            if (mb_strlen($normTagName) >= 3) {
                $pattern = '/\b' . preg_quote($normTagName, '/') . '\b/iu';
                if (preg_match($pattern, $normalizedText) || str_contains($normalizedText, $normTagName)) {
                    $detectedTags[$tag->id] = [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color_hex' => $tag->color_hex,
                        'slug' => $tag->slug,
                        'matched_text' => $tag->name,
                    ];
                }
            }
        }

        return [
            'detected_categories' => array_values($detectedCategories),
            'detected_custom_statuses' => array_values($detectedStatuses),
            'detected_tags' => array_values($detectedTags),
            'total_detected' => count($detectedCategories) + count($detectedStatuses) + count($detectedTags),
        ];
    }
}
