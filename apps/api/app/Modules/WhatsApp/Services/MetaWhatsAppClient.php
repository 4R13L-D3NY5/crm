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
        $cleanBody = preg_replace("/\r\n|\r/", "\n", $body);

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
                    'body' => $cleanBody,
                ],
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    public function sendMediaMessage(WhatsAppAccount $account, string $to, string $mediaUrl, string $mediaType, ?string $caption = null): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v23.0'), '/');

        $typeKey = match ($mediaType) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'document',
            default => 'image',
        };

        $cleanCaption = $caption !== null ? preg_replace("/\r\n|\r/", "\n", $caption) : null;
        $mediaPayload = ['link' => $mediaUrl];
        if ($cleanCaption && in_array($typeKey, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $cleanCaption;
        }

        $response = Http::baseUrl($baseUrl)
            ->withToken((string) $account->access_token)
            ->acceptJson()
            ->post("/{$account->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => $typeKey,
                $typeKey => $mediaPayload,
            ]);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    public function sendTemplateMessage(WhatsAppAccount $account, string $to, string $templateName, string $languageCode = 'en_US', array $parameters = []): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v23.0'), '/');

        $components = [];
        if (! empty($parameters)) {
            $paramsList = [];
            foreach ($parameters as $param) {
                $paramsList[] = [
                    'type' => 'text',
                    'text' => (string) $param,
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $paramsList,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::baseUrl($baseUrl)
            ->withToken((string) $account->access_token)
            ->acceptJson()
            ->post("/{$account->phone_number_id}/messages", $payload);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    public function downloadMedia(WhatsAppAccount $account, string $mediaId, ?string $mimeType = null): ?string
    {
        try {
            $baseUrl = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com/v23.0'), '/');

            // 1. Obtener la URL temporal del archivo multimedia desde Meta
            $metaRes = Http::baseUrl($baseUrl)
                ->withToken((string) $account->access_token)
                ->acceptJson()
                ->get("/{$mediaId}");

            if ($metaRes->failed()) {
                \Illuminate\Support\Facades\Log::warning("Meta Graph API error al consultar media {$mediaId}: " . $metaRes->body());
                return null;
            }

            $downloadUrl = $metaRes->json('url');
            $detectedMime = $metaRes->json('mime_type') ?? $mimeType ?? 'image/jpeg';

            if (! $downloadUrl) {
                return null;
            }

            // 2. Descargar el archivo binario usando el Bearer token (Meta lookaside requiere auth)
            $fileRes = Http::withToken((string) $account->access_token)->get($downloadUrl);

            if ($fileRes->failed() || empty($fileRes->body())) {
                \Illuminate\Support\Facades\Log::warning("Error descargando binario de media {$mediaId} desde {$downloadUrl}");
                return null;
            }

            // 3. Determinar extensión de archivo
            $extension = match ($detectedMime) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'audio/ogg; codecs=opus', 'audio/ogg' => 'ogg',
                'audio/mp4', 'audio/aac', 'audio/m4a' => 'm4a',
                'audio/mpeg', 'audio/mp3' => 'mp3',
                'video/mp4' => 'mp4',
                'application/pdf' => 'pdf',
                default => 'bin',
            };

            $fileName = "whatsapp_media/{$mediaId}.{$extension}";
            \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $fileRes->body());

            return \Illuminate\Support\Facades\Storage::disk('public')->url($fileName);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("No se pudo descargar multimedia {$mediaId} de Meta: {$e->getMessage()}");
            return null;
        }
    }

    public function editTextMessage(WhatsAppAccount $account, string $to, string $messageId, string $newBody): array
    {
        throw new \BadMethodCallException('Meta Cloud API no permite la edición de mensajes salientes.');
    }
}
