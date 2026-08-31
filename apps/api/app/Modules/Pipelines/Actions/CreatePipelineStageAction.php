<?php

namespace App\Modules\Pipelines\Actions;

use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;

class CreatePipelineStageAction
{
    public function execute(Pipeline $pipeline, array $payload): PipelineStage
    {
        return $pipeline->stages()->create([
            'name' => $payload['name'],
            'position' => $payload['position'],
            'probability' => $payload['probability'],
            'color' => $payload['color'] ?? '#C35F24',
        ]);
    }
}
