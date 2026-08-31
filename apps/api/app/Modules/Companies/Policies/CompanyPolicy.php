<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.view')
            && $user->current_organization_id === $company->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('companies.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.manage')
            && $user->current_organization_id === $company->organization_id;
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.manage')
            && $user->current_organization_id === $company->organization_id;
    }
}
