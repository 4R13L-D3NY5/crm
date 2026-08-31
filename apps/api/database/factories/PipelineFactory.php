<?php

namespace Database\Factories;

use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Ventas B2B', 'Renovaciones', 'Inbound']),
            'is_default' => false,
        ];
    }
}
