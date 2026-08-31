<?php

namespace App\Modules\QuickMessages\Actions;

use App\Modules\QuickMessages\Models\QuickMessage;

class UpdateQuickMessageAction
{
    public function execute(QuickMessage $quickMessage, array $data): QuickMessage
    {
        $quickMessage->update([
            'shortcut' => isset($data['shortcut']) ? ltrim($data['shortcut'], '/') : $quickMessage->shortcut,
            'message' => $data['message'] ?? $quickMessage->message,
            'media_url' => array_key_exists('media_url', $data) ? $data['media_url'] : $quickMessage->media_url,
            'media_type' => array_key_exists('media_type', $data) ? $data['media_type'] : $quickMessage->media_type,
            'is_general' => $data['is_general'] ?? $quickMessage->is_general,
        ]);

        return $quickMessage->fresh();
    }
}
