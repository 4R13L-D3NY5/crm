<?php

namespace App\Modules\HentleAi\Models;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiKnowledgeBase extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'ai_knowledge_bases';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'provider',
        'embedding_model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class, 'knowledge_base_id');
    }
}
