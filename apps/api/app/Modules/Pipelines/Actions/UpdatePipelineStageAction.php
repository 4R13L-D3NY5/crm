<?php

namespace App\Modules\Pipelines\Actions;

use App\Modules\Pipelines\Models\PipelineStage;

class UpdatePipelineStageAction
{
    public function execute(PipelineStage $stage, array $payload): PipelineStage
    {
        $stage->update([
            'name' => $payload['name'],
            'position' => $payload['position'],
            'probability' => $payload['probability'],
            'color' => $payload['color'] ?? $stage->color,
        ]);

        return $stage->fresh();
    }
}
