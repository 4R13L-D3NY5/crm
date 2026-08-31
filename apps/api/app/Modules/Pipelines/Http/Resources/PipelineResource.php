<?php

namespace App\Modules\Pipelines\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'is_default' => (bool) $this->is_default,
            'stages' => PipelineStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
