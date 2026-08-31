<?php

namespace App\Modules\Conversations\Models;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'conversation_id',
    'user_id',
    'direction',
    'is_internal',
    'message_type',
    'message_status',
    'delivery_status',
    'error_message',
    'body',
    'media_url',
    'media_type',
    'media_duration_seconds',
    'transcription',
    'transcription_status',
    'quoted_message_id',
    'sent_at',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'sent_at' => 'datetime',
            'media_duration_seconds' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quotedMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'quoted_message_id');
    }
}
