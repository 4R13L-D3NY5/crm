<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenancy\Models\Organization;
use Database\Factories\WhatsAppWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'whatsapp_account_id',
    'event_type',
    'payload',
    'headers',
    'processing_status',
    'processed_at',
    'error_message',
])]
class WhatsAppWebhookEvent extends Model
{
    /** @use HasFactory<WhatsAppWebhookEventFactory> */
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_webhook_events';

    protected static function newFactory(): WhatsAppWebhookEventFactory
    {
        return WhatsAppWebhookEventFactory::new();
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
