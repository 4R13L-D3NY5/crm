<?php

namespace App\Modules\Deals\Models;

use App\Models\User;
use App\Modules\Pipelines\Models\PipelineStage;
use Database\Factories\DealStageHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deal_id', 'from_stage_id', 'to_stage_id', 'changed_by_user_id'])]
class DealStageHistory extends Model
{
    /** @use HasFactory<DealStageHistoryFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): DealStageHistoryFactory
    {
        return DealStageHistoryFactory::new();
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
