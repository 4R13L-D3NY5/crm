<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'session_type',
    'status',
    'qrcode_raw',
    'default_queue_id',
    'phone_number_id',
    'display_phone_number',
    'business_account_id',
    'verify_token',
    'access_token',
    'is_active',
    'is_default',
    'last_connected_at',
])]
class WhatsAppAccount extends Model
{
    /** @use HasFactory<WhatsAppAccountFactory> */
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_accounts';

    protected static function newFactory(): WhatsAppAccountFactory
    {
        return WhatsAppAccountFactory::new();
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function defaultQueue(): BelongsTo
    {
        return $this->belongsTo(Queue::class, 'default_queue_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WhatsAppWebhookEvent::class);
    }

    public function authorizedUsers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'whatsapp_account_users', 'whatsapp_account_id', 'user_id')
            ->withPivot(['can_view', 'can_reply'])
            ->withTimestamps();
    }
}
