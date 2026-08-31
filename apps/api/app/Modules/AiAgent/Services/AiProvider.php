<?php

namespace App\Modules\AiAgent\Services;

interface AiProvider
{
    public function generateReplySuggestion(string $prompt, string $inputSummary): string;
}
