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

#[Fillable(['name', 'email', 'password', 'current_organization_id', 'presence_status', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
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
        ];
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
