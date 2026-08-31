<?php

namespace Database\Factories;

use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        $pipeline = Pipeline::factory()->create();
        $stage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->getKey(),
            'position' => 1,
            'probability' => 10,
        ]);

        return [
            'organization_id' => $pipeline->organization_id,
            'pipeline_id' => $pipeline->getKey(),
            'pipeline_stage_id' => $stage->getKey(),
            'name' => fake()->company().' - Oportunidad',
            'status' => 'open',
            'amount' => fake()->randomFloat(2, 500, 5000),
            'probability' => $stage->probability,
            'expected_close_date' => fake()->dateTimeBetween('+7 days', '+45 days'),
            'notes' => fake()->sentence(),
        ];
    }
}
