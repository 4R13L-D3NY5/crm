<?php

namespace App\Modules\Notes\Models;

use App\Models\User;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'user_id',
    'title',
    'content',
    'image_url',
    'color',
    'is_pinned',
    'is_archived',
    'shortcut',
    'visibility',
    'metadata',
])]
class Note extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'notes';

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_archived' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'note_categories', 'note_id', 'category_id')
            ->withTimestamps();
    }

    public function customStatuses(): BelongsToMany
    {
        return $this->belongsToMany(CustomStatus::class, 'note_custom_statuses', 'note_id', 'custom_status_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'note_tags', 'note_id', 'tag_id')
            ->withTimestamps();
    }

    /**
     * Scope para aplicar las reglas de visibilidad configurables por el administrador:
     * - Admin: Acceso total.
     * - Asesor/Agente:
     *     - 'all': Visibilidad total para el equipo.
     *     - 'private': Solo el creador.
     *     - 'categories': Solo si la nota comparte alguna categoría asignada al asesor.
     */
    public function scopeVisibleToUser(Builder $query, User $user, string $organizationId): Builder
    {
        $query->where('notes.organization_id', $organizationId);

        if ($user->isAdmin()) {
            return $query;
        }

        $userCategoryIds = $user->categories()
            ->where('user_categories.organization_id', $organizationId)
            ->pluck('categories.id')
            ->all();

        return $query->where(function ($sub) use ($user, $userCategoryIds) {
            $sub->where('visibility', 'all')
                ->orWhere(function ($q) use ($user) {
                    $q->where('visibility', 'private')
                        ->where('user_id', $user->id);
                })
                ->orWhere(function ($q) use ($userCategoryIds) {
                    $q->where('visibility', 'categories')
                        ->whereHas('categories', function ($catQuery) use ($userCategoryIds) {
                            $catQuery->whereIn('categories.id', $userCategoryIds);
                        });
                });
        });
    }
}
