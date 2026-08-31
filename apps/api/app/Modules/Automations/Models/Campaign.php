<?php

namespace App\Modules\Automations\Models;

use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'whatsapp_account_id',
        'name',
        'status',
        'total_contacts',
        'sent_count',
        'failed_count',
        'delay_seconds',
        'message_template',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'total_contacts' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'delay_seconds' => 'integer',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
