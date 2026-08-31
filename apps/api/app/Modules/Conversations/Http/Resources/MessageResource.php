<?php

namespace App\Modules\Conversations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'is_internal' => (bool) $this->is_internal,
            'message_type' => $this->message_type,
            'message_status' => $this->message_status,
            'delivery_status' => $this->delivery_status ?? 'sent',
            'error_message' => $this->error_message,
            'body' => $this->body,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'media_duration_seconds' => $this->media_duration_seconds,
            'transcription' => $this->transcription,
            'transcription_status' => $this->transcription_status ?? 'none',
            'quoted_message' => $this->whenLoaded('quotedMessage', fn () => $this->quotedMessage ? [
                'id' => $this->quotedMessage->id,
                'body' => $this->quotedMessage->body,
                'user_name' => $this->quotedMessage->user?->name,
            ] : null),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
        ];
    }
}
