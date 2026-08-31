<?php

namespace App\Modules\Conversations\Models;

use App\Models\User;
use Database\Factories\ConversationAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'assigned_to_user_id',
    'assigned_by_user_id',
])]
class ConversationAssignment extends Model
{
    /** @use HasFactory<ConversationAssignmentFactory> */
    use HasFactory, HasUlids;

    protected static function newFactory(): ConversationAssignmentFactory
    {
        return ConversationAssignmentFactory::new();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
