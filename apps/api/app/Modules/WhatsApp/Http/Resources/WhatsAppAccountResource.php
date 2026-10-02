<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'session_type' => $this->session_type ?? 'baileys_qr',
            'status' => $this->status ?? ($this->is_active ? 'CONNECTED' : 'DISCONNECTED'),
            'qrcode_raw' => $this->qrcode_raw,
            'phone_number_id' => $this->phone_number_id,
            'display_phone_number' => $this->display_phone_number,
            'business_account_id' => $this->business_account_id,
            'verify_token' => $this->verify_token,
            'has_access_token' => filled($this->access_token),
            'is_active' => (bool) $this->is_active,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

