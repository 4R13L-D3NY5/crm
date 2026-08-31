<?php

namespace App\Modules\AiAgent\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAgentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ai_agent_id' => $this->ai_agent_id,
            'conversation_id' => $this->conversation_id,
            'run_type' => $this->run_type,
            'status' => $this->status,
            'prompt' => $this->prompt,
            'input_summary' => $this->input_summary,
            'output_text' => $this->output_text,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
