<?php

namespace Database\Factories;

use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Automations\Models\AutomationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        $rule = AutomationRule::factory()->create();

        return [
            'organization_id' => $rule->organization_id,
            'automation_rule_id' => $rule->getKey(),
            'trigger_type' => $rule->trigger_type,
            'status' => 'completed',
            'context' => ['channel' => 'whatsapp'],
            'result' => ['applied_actions' => ['set_status']],
            'error_message' => null,
        ];
    }
}
