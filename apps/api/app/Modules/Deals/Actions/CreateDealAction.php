<?php

namespace App\Modules\Deals\Actions;

use App\Models\User;
use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Models\DealStageHistory;

class CreateDealAction
{
    public function execute(User $user, array $payload): Deal
    {
        $deal = Deal::query()->create([
            'organization_id' => $user->current_organization_id,
            'pipeline_id' => $payload['pipeline_id'],
            'pipeline_stage_id' => $payload['pipeline_stage_id'],
            'contact_id' => $payload['contact_id'] ?? null,
            'company_id' => $payload['company_id'] ?? null,
            'name' => $payload['name'],
            'status' => $payload['status'],
            'amount' => $payload['amount'],
            'probability' => $payload['probability'],
            'expected_close_date' => $payload['expected_close_date'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        DealStageHistory::query()->create([
            'deal_id' => $deal->getKey(),
            'from_stage_id' => null,
            'to_stage_id' => $deal->pipeline_stage_id,
            'changed_by_user_id' => $user->getKey(),
        ]);

        return $deal->load('stage', 'company', 'contact');
    }
}
