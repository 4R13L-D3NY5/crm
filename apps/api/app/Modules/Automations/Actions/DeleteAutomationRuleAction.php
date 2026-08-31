<?php

namespace App\Modules\Automations\Actions;

use App\Modules\Automations\Models\AutomationRule;

class DeleteAutomationRuleAction
{
    public function execute(AutomationRule $rule): void
    {
        $rule->delete();
    }
}
