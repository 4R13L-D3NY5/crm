<?php

namespace App\Modules\QuickMessages\Models;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickMessage extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'quick_messages';

    protected $fillable = [
        'organization_id',
        'user_id',
        'shortcut',
        'message',
        'media_url',
        'media_type',
        'is_general',
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
