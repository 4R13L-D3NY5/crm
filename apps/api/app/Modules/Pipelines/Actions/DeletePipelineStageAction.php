<?php

namespace App\Modules\Pipelines\Actions;

use App\Modules\Pipelines\Models\PipelineStage;

class DeletePipelineStageAction
{
    public function execute(PipelineStage $stage): void
    {
        $stage->delete();
    }
}
