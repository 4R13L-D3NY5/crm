<?php

namespace App\Modules\QuickMessages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shortcut' => $this->shortcut,
            'message' => $this->message,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'is_general' => $this->is_general,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
