<?php

namespace App\Modules\AiAgent\Models;

use App\Modules\Tenancy\Models\Organization;
use Database\Factories\AiAgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'provider',
    'system_prompt',
    'is_active',
])]
class AiAgent extends Model
{
    /** @use HasFactory<AiAgentFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): AiAgentFactory
    {
        return AiAgentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiAgentRun::class)->latest();
    }
}
