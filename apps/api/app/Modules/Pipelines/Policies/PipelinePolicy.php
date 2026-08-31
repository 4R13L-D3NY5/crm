<?php

namespace App\Modules\Pipelines\Policies;

use App\Models\User;
use App\Modules\Pipelines\Models\Pipeline;

class PipelinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('pipelines.view');
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $user->hasPermission('pipelines.view')
            && $user->current_organization_id === $pipeline->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('pipelines.manage');
    }
}
