<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppWebhookEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'event_type' => $this->event_type,
            'processing_status' => $this->processing_status,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
