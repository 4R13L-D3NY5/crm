<?php

namespace App\Modules\Conversations\Models;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'contact_id',
    'company_id',
    'queue_id',
    'created_by_user_id',
    'assigned_to_user_id',
    'custom_status_id',
    'channel',
    'whatsapp_account_id',
    'status',
    'unread_count',
    'is_group',
    'subject',
    'last_message_at',
    'closed_at',
    'rating',
    'feedback',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
            'is_group' => 'boolean',
            'rating' => 'integer',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(ConversationAssignment::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Parameters\Models\Category::class, 'conversation_categories')
            ->withPivot('id', 'organization_id', 'assigned_by_user_id')
            ->withTimestamps();
    }

    public function customStatus(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Parameters\Models\CustomStatus::class, 'custom_status_id');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\WhatsApp\Models\WhatsAppAccount::class, 'whatsapp_account_id');
    }

    // Scopes de filtrado Whaticket
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAttending(Builder $query, ?string $userId = null): Builder
    {
        $query->where('status', 'open');
        if ($userId) {
            $query->where('assigned_to_user_id', $userId);
        }
        return $query;
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }
}
