<?php

namespace App\Modules\Queues\Models;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Queue extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'queues';

    protected $fillable = [
        'organization_id',
        'name',
        'color',
        'greeting_message',
        'out_of_hours_message',
        'order_index',
        'is_active',
        'chatbot_options',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
        'chatbot_options' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'queue_users', 'queue_id', 'user_id')
            ->withTimestamps();
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class, 'queue_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Conversation::class, 'queue_id');
    }
}
