<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasAccessToken = false;
        try {
            $hasAccessToken = filled($this->access_token);
        } catch (\Throwable) {
            $hasAccessToken = filled($this->getRawOriginal('access_token'));
        }

        $sessionType = $this->session_type ?? 'baileys_qr';
        $isQr = in_array($sessionType, ['baileys_qr', 'qr_baileys', 'qr']);
        $dailyLimit = $this->daily_limit ?? ($isQr ? 100 : 1000);

        // Chats atendidos o activos hoy en esta línea
        $chatsToday = Conversation::query()
            ->where('whatsapp_account_id', $this->id)
            ->whereDate('last_message_at', today())
            ->count();

        // Mensajes salientes hoy
        $outboundToday = WhatsAppMessageMapping::query()
            ->where('whatsapp_account_id', $this->id)
            ->where('direction', 'outbound')
            ->whereDate('created_at', today())
            ->count();

        $usagePercent = $dailyLimit > 0 ? min(100, (int) round(($chatsToday / $dailyLimit) * 100)) : 0;
        $riskLevel = match (true) {
            $usagePercent >= 95 => 'danger',
            $usagePercent >= 70 => 'warning',
            default => 'safe',
        };

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'session_type' => $sessionType,
            'status' => $this->status ?? ($this->is_active ? 'CONNECTED' : 'DISCONNECTED'),
            'qrcode_raw' => $this->qrcode_raw,
            'phone_number_id' => $this->phone_number_id,
            'display_phone_number' => $this->display_phone_number,
            'business_account_id' => $this->business_account_id,
            'verify_token' => $this->verify_token,
            'has_access_token' => $hasAccessToken,
            'is_active' => (bool) $this->is_active,
            'daily_limit' => $dailyLimit,
            'anti_ban' => [
                'daily_limit' => $dailyLimit,
                'chats_today' => $chatsToday,
                'outbound_today' => $outboundToday,
                'usage_percent' => $usagePercent,
                'risk_level' => $riskLevel,
                'is_qr' => $isQr,
            ],
            'authorized_users' => $this->relationLoaded('authorizedUsers')
                ? $this->authorizedUsers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'can_view' => (bool) ($u->pivot->can_view ?? true),
                    'can_reply' => (bool) ($u->pivot->can_reply ?? true),
                ])->values()
                : [],
            'webhook_url' => url('/api/whatsapp/webhook'),
            'social_webhook_url' => url('/api/social/comments/webhook'),
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
