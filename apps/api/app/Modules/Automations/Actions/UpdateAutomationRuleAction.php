<?php

namespace App\Modules\Automations\Actions;

use App\Modules\Automations\Models\AutomationRule;

class UpdateAutomationRuleAction
{
    public function execute(AutomationRule $rule, array $payload): AutomationRule
    {
        $rule->update([
            'name' => $payload['name'],
            'trigger_type' => $payload['trigger_type'],
            'conditions' => $payload['conditions'] ?? [],
            'actions' => $payload['actions'],
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);

        return $rule->fresh();
    }
}
