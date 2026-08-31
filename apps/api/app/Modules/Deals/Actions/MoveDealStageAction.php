<?php

namespace App\Modules\Deals\Actions;

use App\Models\User;
use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Models\DealStageHistory;
use App\Modules\Pipelines\Models\PipelineStage;

class MoveDealStageAction
{
    public function execute(User $user, Deal $deal, PipelineStage $targetStage): Deal
    {
        $fromStageId = $deal->pipeline_stage_id;

        $deal->update([
            'pipeline_stage_id' => $targetStage->getKey(),
            'probability' => $targetStage->probability,
        ]);

        DealStageHistory::query()->create([
            'deal_id' => $deal->getKey(),
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $targetStage->getKey(),
            'changed_by_user_id' => $user->getKey(),
        ]);

        return $deal->load('stage', 'company', 'contact');
    }
}
