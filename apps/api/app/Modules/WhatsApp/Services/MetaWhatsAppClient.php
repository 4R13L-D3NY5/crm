<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppClient implements WhatsAppClient
{
    public function sendTextMessage(WhatsAppAccount $account, string $to, string $body): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v23.0'), '/');

        $response = Http::baseUrl($baseUrl)
            ->withToken((string) $account->access_token)
            ->acceptJson()
            ->post("/{$account->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }
}
