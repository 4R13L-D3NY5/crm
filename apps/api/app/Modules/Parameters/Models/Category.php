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
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'parent_id',
    'name',
    'code',
    'slug',
    'color',
    'icon',
    'is_selectable',
    'sort_order',
])]
class Category extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'categories';

    protected function casts(): array
    {
        return [
            'is_selectable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relación de compatibilidad con padre único primario
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación de múltiples categorías padre (Grafo / Múltiples dependencias cruzadas)
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_parents', 'category_id', 'parent_id')
            ->withPivot('id', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Relación de subcategorías hijas (a través de la tabla pivote category_parents)
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_parents', 'parent_id', 'category_id')
            ->withPivot('id', 'sort_order')
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_categories')
            ->withPivot('id', 'organization_id', 'assigned_by_user_id')
            ->withTimestamps();
    }

    /**
     * Retorna la ruta jerárquica de la categoría (ej: "Oferta Académica > Ingenierías > Sistemas")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $current = $this->parent;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }
}
