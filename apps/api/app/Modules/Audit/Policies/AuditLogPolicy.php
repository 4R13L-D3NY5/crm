<?php

namespace App\Modules\Audit\Policies;

use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('audit.view');
    }
}
