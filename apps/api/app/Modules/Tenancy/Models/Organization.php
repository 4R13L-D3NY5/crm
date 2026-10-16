<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'monthly_price_usd' => 'float',
        'max_whatsapp_lines' => 'integer',
        'extra_whatsapp_lines' => 'integer',
        'max_social_accounts' => 'integer',
        'extra_social_accounts' => 'integer',
        'max_agents' => 'integer',
        'extra_agents' => 'integer',
        'features' => 'array',
    ];

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class);
    }
}
