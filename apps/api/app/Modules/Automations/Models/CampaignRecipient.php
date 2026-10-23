<?php

namespace App\Modules\Automations\Models;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'phone',
        'name',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $appends = ['conversation_id'];

    public function getConversationIdAttribute(): ?string
    {
        if ($this->relationLoaded('contact') && $this->contact && $this->contact->relationLoaded('conversations')) {
            return $this->contact->conversations->first()?->id;
        }

        return null;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
