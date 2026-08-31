<?php

namespace Database\Factories;

use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Auto asignar inbox',
            'trigger_type' => 'message.inbound.received',
            'conditions' => [
                'channel' => 'whatsapp',
            ],
            'actions' => [
                [
                    'type' => 'set_status',
                    'value' => 'pending',
                ],
            ],
            'is_active' => true,
        ];
    }
}
