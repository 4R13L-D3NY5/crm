<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'app_notifications';

    protected $fillable = [
        'organization_id',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForRecipient($query, string $organizationId, ?string $userId = null)
    {
        return $query->where('organization_id', $organizationId)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id');
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            });
    }

    public function markAsRead(): bool
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
            return true;
        }

        return false;
    }
}
