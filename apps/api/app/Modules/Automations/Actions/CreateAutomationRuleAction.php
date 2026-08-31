<?php

namespace App\Modules\Automations\Actions;

use App\Models\User;
use App\Modules\Automations\Models\AutomationRule;

class CreateAutomationRuleAction
{
    public function execute(User $user, array $payload): AutomationRule
    {
        return AutomationRule::query()->create([
            'organization_id' => $user->current_organization_id,
            'name' => $payload['name'],
            'trigger_type' => $payload['trigger_type'],
            'conditions' => $payload['conditions'] ?? [],
            'actions' => $payload['actions'],
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);
    }
}
