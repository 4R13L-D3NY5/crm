<?php

namespace App\Models;

use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use App\Shared\Support\RolePermissions;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'current_organization_id',
    'presence_status',
    'last_seen_at',
    'phone',
    'avatar_url',
    'preferences',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'preferences' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Preferencias predeterminadas del usuario.
     */
    public static function defaultPreferences(): array
    {
        return [
            'theme_mode' => 'dark',
            'accent_color' => 'emerald',
            'notification_sound' => 'chime',
            'notification_volume' => 80,
            'desktop_notifications' => true,
            'whatsapp_signature_enabled' => false,
            'whatsapp_signature' => '',
            'language' => 'es',
            'presence_break_reason' => null,
            'presence_break_until' => null,
        ];
    }

    public function getPreferencesWithDefaultsAttribute(): array
    {
        return array_merge(static::defaultPreferences(), $this->preferences ?? []);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    public function queues(): BelongsToMany
    {
        return $this->belongsToMany(Queue::class, 'queue_users', 'user_id', 'queue_id')
            ->withTimestamps();
    }

    public function currentOrganizationRole(): ?string
    {
        if ($this->current_organization_id === null) {
            return null;
        }

        $organization = $this->relationLoaded('organizations')
            ? $this->organizations->firstWhere('id', $this->current_organization_id)
            : $this->organizations()
                ->where('organizations.id', $this->current_organization_id)
                ->first();

        return $organization?->pivot?->role;
    }

    public function permissions(): array
    {
        return RolePermissions::forRole($this->currentOrganizationRole());
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->currentOrganizationRole() === 'admin') {
            return true;
        }

        return in_array($permission, $this->permissions(), true);
    }
}
