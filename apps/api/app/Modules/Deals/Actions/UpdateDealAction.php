<?php

namespace App\Modules\Deals\Actions;

use App\Modules\Deals\Models\Deal;

class UpdateDealAction
{
    public function execute(Deal $deal, array $payload): Deal
    {
        $deal->update([
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

        return $deal->load('stage', 'company', 'contact');
    }
}
