<?php

namespace App\Modules\Pipelines\Policies;

use App\Models\User;
use App\Modules\Pipelines\Models\PipelineStage;

class PipelineStagePolicy
{
    public function update(User $user, PipelineStage $stage): bool
    {
        return $user->hasPermission('pipelines.manage')
            && $user->current_organization_id === $stage->pipeline->organization_id;
    }

    public function delete(User $user, PipelineStage $stage): bool
    {
        return $this->update($user, $stage);
    }
}
