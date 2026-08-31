<?php

namespace App\Modules\Pipelines\Actions;

use App\Models\User;
use App\Modules\Pipelines\Models\Pipeline;

class CreatePipelineAction
{
    public function execute(User $user, array $payload): Pipeline
    {
        if (($payload['is_default'] ?? false) === true) {
            Pipeline::query()
                ->where('organization_id', $user->current_organization_id)
                ->update(['is_default' => false]);
        }

        return Pipeline::query()->create([
            'organization_id' => $user->current_organization_id,
            'name' => $payload['name'],
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ]);
    }
}
