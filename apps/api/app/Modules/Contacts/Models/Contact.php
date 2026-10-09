<?php

namespace App\Modules\Contacts\Models;

use App\Modules\Companies\Models\Company;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Deals\Models\Deal;
use App\Models\User;
use App\Modules\Contacts\Policies\ContactPolicy;
use App\Modules\Tenancy\Models\Organization;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'status',
    'custom_status_id',
    'notes',
    'avatar_url',
    'custom_fields',
    'last_message_at',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $casts = [
        'custom_fields' => 'array',
        'last_message_at' => 'datetime',
    ];

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name])));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withTimestamps();
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class)->latest('last_message_at');
    }

    public function customStatus(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Parameters\Models\CustomStatus::class, 'custom_status_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\Parameters\Models\Category::class, 'contact_categories')
            ->withPivot('id', 'organization_id', 'assigned_by_user_id')
            ->withTimestamps();
    }
}
