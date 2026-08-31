<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;

class SwitchCurrentOrganizationAction
{
    public function execute(User $user, Organization $organization): User
    {
        $user->forceFill([
            'current_organization_id' => $organization->getKey(),
        ])->save();

        return $user->fresh(['organizations', 'currentOrganization']);
    }
}
