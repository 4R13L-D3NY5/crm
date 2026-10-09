<?php

namespace App\Modules\Parameters\Models;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'name',
    'description',
    'is_active',
    'user_timeout_minutes',
    'client_timeout_minutes',
    'notify_user_inactivity',
    'notify_client_inactivity',
    'apply_to_all_statuses',
    'custom_status_ids',
    'severity',
    'action_type',
])]
class TimeAlertRule extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'time_alert_rules';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'user_timeout_minutes' => 'integer',
            'client_timeout_minutes' => 'integer',
            'notify_user_inactivity' => 'boolean',
            'notify_client_inactivity' => 'boolean',
            'apply_to_all_statuses' => 'boolean',
            'custom_status_ids' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Determina si la regla aplica a un estado específico.
     */
    public function appliesToStatus(?string $statusId): bool
    {
        if ($this->apply_to_all_statuses) {
            return true;
        }

        if (empty($statusId) || empty($this->custom_status_ids)) {
            return false;
        }

        return in_array($statusId, $this->custom_status_ids, true);
    }
}
