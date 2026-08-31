<?php

namespace App\Modules\Tenancy\Policies;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->hasPermission('reports.view')
            && $this->belongsToOrganization($user, $organization);
    }

    public function switch(User $user, Organization $organization): bool
    {
        return $user->hasPermission('organizations.switch')
            && $this->belongsToOrganization($user, $organization);
    }

    private function belongsToOrganization(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->getKey())
            ->exists();
    }
}
