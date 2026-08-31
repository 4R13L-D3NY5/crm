<?php

namespace Database\Factories;

use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => fake()->randomElement(['Nuevo', 'Calificado', 'Propuesta', 'Cierre']),
            'position' => fake()->numberBetween(1, 5),
            'probability' => fake()->numberBetween(0, 100),
            'color' => fake()->randomElement(['#C35F24', '#0E7C66', '#2B6CB0', '#B7791F']),
        ];
    }
}
