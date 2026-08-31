<?php

namespace Database\Factories;

use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Models\DealStageHistory;
use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealStageHistory>
 */
class DealStageHistoryFactory extends Factory
{
    protected $model = DealStageHistory::class;

    public function definition(): array
    {
        $deal = Deal::factory()->create();
        $stage = PipelineStage::factory()->create([
            'pipeline_id' => $deal->pipeline_id,
        ]);

        return [
            'deal_id' => $deal->getKey(),
            'from_stage_id' => $deal->pipeline_stage_id,
            'to_stage_id' => $stage->getKey(),
            'changed_by_user_id' => null,
        ];
    }
}
