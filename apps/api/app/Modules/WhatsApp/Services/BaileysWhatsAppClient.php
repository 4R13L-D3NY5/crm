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

    public function sendMediaMessage(WhatsAppAccount $account, string $to, string $mediaUrl, string $mediaType, ?string $caption = null): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.baileys_url', 'http://whatsapp-service:3000'), '/');

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'to' => $cleanPhone,
            'media_type' => $mediaType,
            'caption' => $caption,
        ];

        // Si el archivo reside localmente en el almacenamiento del CRM (storage público),
        // enviamos directamente el base64 a Baileys para evitar bucles de red HTTP internos y bloqueos
        if (str_starts_with($mediaUrl, '/storage/')) {
            $storageRelativePath = substr($mediaUrl, strlen('/storage/'));
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($storageRelativePath)) {
                $fileBytes = \Illuminate\Support\Facades\Storage::disk('public')->get($storageRelativePath);
                $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($storageRelativePath) ?: 'image/jpeg';
                $payload['media_base64'] = base64_encode($fileBytes);
                $payload['mime_type'] = $mimeType;
            } else {
                $payload['media_url'] = 'http://api:8010' . $mediaUrl;
            }
        } else {
            $payload['media_url'] = $mediaUrl;
        }

        $response = Http::baseUrl($baseUrl)
            ->timeout(30)
            ->acceptJson()
            ->post("/sessions/{$account->id}/send-media", $payload);

        if ($response->failed()) {
            Log::error('Baileys send media failed', [
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

    public function editTextMessage(WhatsAppAccount $account, string $to, string $messageId, string $newBody): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.baileys_url', 'http://whatsapp-service:3000'), '/');

        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $response = Http::baseUrl($baseUrl)
            ->timeout(10)
            ->acceptJson()
            ->post("/sessions/{$account->id}/edit-message", [
                'to' => $cleanPhone,
                'messageId' => $messageId,
                'text' => $newBody,
            ]);

        if ($response->failed()) {
            Log::error('Baileys edit message failed', [
                'account_id' => $account->id,
                'to' => $to,
                'message_id' => $messageId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RequestException($response);
        }

        $data = $response->json();

        return [
            'messages' => [
                [
                    'id' => $data['messageId'] ?? $messageId,
                ],
            ],
            'status' => 'edited',
            'raw' => $data,
        ];
    }
}
