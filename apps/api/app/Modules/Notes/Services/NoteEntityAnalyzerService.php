<?php

namespace App\Modules\Notes\Services;

use App\Modules\Contacts\Models\Tag;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Support\Str;

class NoteEntityAnalyzerService
{
    /**
     * Normaliza un texto removiendo tildes, signos y pasando a minúsculas.
     */
    protected function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $trans = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'
        ];
        return strtr($text, $trans);
    }

    /**
     * Escanea el contenido de una nota para detectar menciones de:
     * - Categorías / Subcategorías
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

        $detectedCategories = [];
        foreach ($categories as $cat) {
            $normCatName = $this->normalize($cat->name);
            if (mb_strlen($normCatName) >= 3) {
                $pattern = '/\b' . preg_quote($normCatName, '/') . '\b/iu';
                if (preg_match($pattern, $normalizedText)) {
                    $detectedCategories[] = [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'color' => $cat->color,
                        'icon' => $cat->icon,
                        'slug' => $cat->slug,
                        'matched_text' => $cat->name,
                    ];
                }
            }
        }

        $detectedStatuses = [];
        foreach ($statuses as $st) {
            $normStName = $this->normalize($st->name);
            if (mb_strlen($normStName) >= 3) {
                $pattern = '/\b' . preg_quote($normStName, '/') . '\b/iu';
                if (preg_match($pattern, $normalizedText)) {
                    $detectedStatuses[] = [
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
                if (preg_match($pattern, $normalizedText)) {
                    $detectedTags[] = [
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
