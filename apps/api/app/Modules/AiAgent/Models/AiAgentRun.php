<?php

namespace App\Modules\AiAgent\Models;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\AiAgentRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'ai_agent_id',
    'conversation_id',
    'triggered_by_user_id',
    'run_type',
    'status',
    'prompt',
    'input_summary',
    'output_text',
    'error_message',
])]
class AiAgentRun extends Model
{
    /** @use HasFactory<AiAgentRunFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): AiAgentRunFactory
    {
        return AiAgentRunFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
