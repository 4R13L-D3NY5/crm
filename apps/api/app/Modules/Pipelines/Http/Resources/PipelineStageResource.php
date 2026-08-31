<?php

namespace App\Modules\Pipelines\Http\Resources;

use App\Modules\Deals\Http\Resources\DealResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'name' => $this->name,
            'position' => $this->position,
            'probability' => $this->probability,
            'color' => $this->color,
            'deals' => DealResource::collection($this->whenLoaded('deals')),
        ];
    }
}
