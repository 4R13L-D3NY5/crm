<?php

namespace Database\Factories;

use App\Modules\AiAgent\Models\AiAgent;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAgent>
 */
class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'CRM Reply Assistant',
            'provider' => 'fake',
            'system_prompt' => 'Sugiere una respuesta breve, amable y orientada a negocio.',
            'is_active' => true,
        ];
    }
}
