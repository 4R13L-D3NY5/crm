<?php

namespace App\Modules\Parameters\Models;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'name',
    'slug',
    'color',
    'icon',
    'stage_type',
    'is_default',
    'sort_order',
    'is_active',
])]
class CustomStatus extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'custom_statuses';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomStatus $status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'custom_status_id');
    }

    /**
     * Estados anteriores permitidos para llegar a este estado
     */
    public function allowedPreviousStatuses(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomStatus::class,
            'custom_status_transitions',
            'to_status_id',
            'from_status_id'
        )->withTimestamps();
    }

    /**
     * Estados siguientes a los que este estado puede avanzar
     */
    public function allowedNextStatuses(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomStatus::class,
            'custom_status_transitions',
            'from_status_id',
            'to_status_id'
        )->withTimestamps();
    }

    /**
     * Valida si se puede realizar la transición hacia el estado objetivo
     */
    public function canTransitionTo(CustomStatus $targetStatus): bool
    {
        // Mismo estado no requiere cambio
        if ($this->id === $targetStatus->id) {
            return true;
        }

        // Si el estado destino no tiene dependencias configuradas, permite transición libre desde cualquiera
        if ($targetStatus->allowedPreviousStatuses()->count() === 0) {
            return true;
        }

        // Verifica si este estado está en la lista de estados previos autorizados del destino
        return $targetStatus->allowedPreviousStatuses()
            ->where('custom_statuses.id', $this->id)
            ->exists();
    }
}
