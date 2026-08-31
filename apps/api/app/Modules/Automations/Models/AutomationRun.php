<?php

namespace App\Modules\Automations\Models;

use App\Modules\Tenancy\Models\Organization;
use Database\Factories\AutomationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'automation_rule_id',
    'trigger_type',
    'status',
    'context',
    'result',
    'error_message',
])]
class AutomationRun extends Model
{
    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): AutomationRunFactory
    {
        return AutomationRunFactory::new();
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
