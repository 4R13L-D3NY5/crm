<?php

namespace App\Modules\Queues\Models;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotFlow extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'queue_id',
        'name',
        'trigger_keyword',
        'is_active',
        'greeting_message',
        'options',
        'handoff_to_ai',
        'fallback_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'handoff_to_ai' => 'boolean',
        'options' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }
}
