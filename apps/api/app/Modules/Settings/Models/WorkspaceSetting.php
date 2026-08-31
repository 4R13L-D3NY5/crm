<?php

namespace App\Modules\Settings\Models;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceSetting extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'default_language',
        'timezone',
        'hide_contact_data',
        'enforce_2fa',
        'sla_timeout_minutes',
        'custom_options',
    ];

    protected $casts = [
        'hide_contact_data' => 'boolean',
        'enforce_2fa' => 'boolean',
        'sla_timeout_minutes' => 'integer',
        'custom_options' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
