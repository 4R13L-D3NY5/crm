<?php

namespace App\Modules\AiAgent\Services;

class FakeAiProvider implements AiProvider
{
    public function generateReplySuggestion(string $prompt, string $inputSummary): string
    {
        $lastLine = collect(explode("\n", trim($inputSummary)))
            ->filter()
            ->last() ?? 'Gracias por escribirnos.';

        return trim("Gracias por tu mensaje. Sobre lo que comentas: {$lastLine}\n\nPodemos ayudarte con una demo y resolver tus dudas. Si te parece, te compartimos los siguientes pasos.");
    }
}
