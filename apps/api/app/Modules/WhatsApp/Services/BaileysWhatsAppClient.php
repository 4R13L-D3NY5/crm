<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaileysWhatsAppClient implements WhatsAppClient
{
    public function sendTextMessage(WhatsAppAccount $account, string $to, string $body): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.baileys_url', 'http://whatsapp-service:3000'), '/');

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $response = Http::baseUrl($baseUrl)
            ->timeout(10)
            ->acceptJson()
            ->post("/sessions/{$account->id}/send-message", [
                'to' => $cleanPhone,
                'text' => $body,
            ]);

        if ($response->failed()) {
            Log::error('Baileys send message failed', [
                'account_id' => $account->id,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RequestException($response);
        }

        $data = $response->json();

        return [
            'messages' => [
                [
                    'id' => $data['messageId'] ?? ('baileys_' . time()),
                ],
            ],
            'status' => 'sent',
            'raw' => $data,
        ];
    }
}
