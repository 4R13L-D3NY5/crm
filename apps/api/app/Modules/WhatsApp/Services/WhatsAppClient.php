<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppAccount;

interface WhatsAppClient
{
    public function sendTextMessage(WhatsAppAccount $account, string $to, string $body): array;

    public function sendMediaMessage(WhatsAppAccount $account, string $to, string $mediaUrl, string $mediaType, ?string $caption = null): array;
}
