<?php

namespace App\Modules\Deals\Policies;

use App\Models\User;
use App\Modules\Deals\Models\Deal;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('deals.view');
    }

    public function view(User $user, Deal $deal): bool
    {
        return $user->hasPermission('deals.view')
            && $user->current_organization_id === $deal->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('deals.manage');
    }

    public function update(User $user, Deal $deal): bool
    {
        return $user->hasPermission('deals.manage')
            && $user->current_organization_id === $deal->organization_id;
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->update($user, $deal);
    }
}
