<?php

namespace App\Modules\Pipelines\Models;

use App\Modules\Deals\Models\Deal;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'is_default'])]
class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): PipelineFactory
    {
        return PipelineFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
