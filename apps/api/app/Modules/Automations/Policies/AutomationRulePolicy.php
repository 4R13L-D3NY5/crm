<?php

namespace App\Modules\Automations\Policies;

use App\Models\User;
use App\Modules\Automations\Models\AutomationRule;

class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('automations.view');
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $user->hasPermission('automations.view')
            && $user->current_organization_id === $rule->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('automations.manage');
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $user->hasPermission('automations.manage')
            && $this->view($user, $rule);
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
