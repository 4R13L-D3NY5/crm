<?php

namespace App\Modules\WhatsApp\Actions;

use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppAccount;

class UpsertWhatsAppAccountAction
{
    public function execute(User $user, array $payload): WhatsAppAccount
    {
        return WhatsAppAccount::query()->updateOrCreate(
            [
                'organization_id' => $user->current_organization_id,
            ],
            [
                'name' => $payload['name'],
                'phone_number_id' => $payload['phone_number_id'],
                'display_phone_number' => $payload['display_phone_number'] ?? null,
                'business_account_id' => $payload['business_account_id'] ?? null,
                'verify_token' => $payload['verify_token'],
                'access_token' => $payload['access_token'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ],
        );
    }
}
