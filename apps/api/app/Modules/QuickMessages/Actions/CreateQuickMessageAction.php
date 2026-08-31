<?php

namespace App\Modules\QuickMessages\Actions;

use App\Models\User;
use App\Modules\QuickMessages\Models\QuickMessage;

class CreateQuickMessageAction
{
    public function execute(User $user, array $data): QuickMessage
    {
        return QuickMessage::create([
            'organization_id' => $user->current_organization_id,
            'user_id' => ($data['is_general'] ?? true) ? null : $user->id,
            'shortcut' => ltrim($data['shortcut'], '/'),
            'message' => $data['message'],
            'media_url' => $data['media_url'] ?? null,
            'media_type' => $data['media_type'] ?? null,
            'is_general' => $data['is_general'] ?? true,
        ]);
    }
}
