<?php

namespace Database\Factories;

use App\Modules\AiAgent\Models\AiAgent;
use App\Modules\AiAgent\Models\AiAgentRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAgentRun>
 */
class AiAgentRunFactory extends Factory
{
    protected $model = AiAgentRun::class;

    public function definition(): array
    {
        $agent = AiAgent::factory()->create();

        return [
            'organization_id' => $agent->organization_id,
            'ai_agent_id' => $agent->getKey(),
            'conversation_id' => null,
            'triggered_by_user_id' => null,
            'run_type' => 'reply_suggestion',
            'status' => 'completed',
            'prompt' => 'Prompt demo',
            'input_summary' => 'Resumen demo',
            'output_text' => 'Respuesta sugerida demo',
            'error_message' => null,
        ];
    }
}
