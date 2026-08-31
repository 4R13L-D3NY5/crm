<?php

namespace App\Modules\Deals\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'conversation_id' => $this->conversation_id,
            'name' => $this->name,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'notes' => $this->notes,
            'contact' => $this->contact ? [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'phone' => $this->contact->phone,
            ] : null,
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ] : null,
        ];
    }
}
